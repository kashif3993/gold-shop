<?php

namespace App\Services;

use App\Models\DailyRate;
use App\Models\Item;
use App\Models\Purity;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Sales, profit, and stock valuation numbers for the Reports screen.
 *
 * Every total here is computed server-side from transaction/item rows — the
 * frontend never sums financial figures itself, it only displays what this
 * service returns.
 */
class ReportService
{
    public function __construct(private GoldRateService $rates) {}

    /** Sales billed through POS in the given date range, by day and by metal. */
    public function salesSummary(?string $from, ?string $to): array
    {
        $sales = $this->saleTransactions($from, $to);

        $byDay = $sales->groupBy(fn (Transaction $t) => $t->transaction_date->toDateString())
            ->map(fn (Collection $rows, string $day) => [
                'date' => $day,
                'count' => $rows->count(),
                'revenue' => round((float) $rows->sum('total_amount'), 2),
            ])
            ->sortBy('date')
            ->values();

        $byMetal = $this->groupByMetal($sales, fn (Collection $rows) => [
            'count' => $rows->count(),
            'weight_grams' => round((float) $rows->sum('weight_grams'), 3),
            'revenue' => round((float) $rows->sum('total_amount'), 2),
        ]);

        return [
            'count' => $sales->count(),
            'revenue' => round((float) $sales->sum('total_amount'), 2),
            'by_day' => $byDay,
            'by_metal' => $byMetal,
        ];
    }

    /** Profit per sale = the sale price minus the item's own locked purchase cost. */
    public function profitSummary(?string $from, ?string $to): array
    {
        $sales = $this->saleTransactions($from, $to)->loadMissing('item:id,purchase_price');

        $withProfit = $sales->map(function (Transaction $t) {
            $t->setAttribute('_cost', (float) ($t->item->purchase_price ?? 0));
            $t->setAttribute('_profit', round((float) $t->total_amount - (float) ($t->item->purchase_price ?? 0), 2));

            return $t;
        });

        $byMetal = $this->groupByMetal($withProfit, fn (Collection $rows) => [
            'count' => $rows->count(),
            'revenue' => round((float) $rows->sum('total_amount'), 2),
            'cost' => round((float) $rows->sum('_cost'), 2),
            'profit' => round((float) $rows->sum('_profit'), 2),
        ]);

        return [
            'revenue' => round((float) $withProfit->sum('total_amount'), 2),
            'cost' => round((float) $withProfit->sum('_cost'), 2),
            'profit' => round((float) $withProfit->sum('_profit'), 2),
            'by_metal' => $byMetal,
        ];
    }

    /** What's sitting in stock right now, valued at TODAY's live rate — never the purchase rate. */
    public function stockValuation(): array
    {
        $currentRates = $this->rates->currentRates(); // keyed by purity_id

        $items = Item::with('metalType:id,name')
            ->where('status', 'in_stock')
            ->get()
            ->map(function (Item $item) use ($currentRates) {
                $ratePerGram = (float) ($currentRates->get($item->purity_id)->rate_per_gram ?? 0);
                $metalValue = round((float) $item->net_weight_grams * $ratePerGram, 2);

                $item->setAttribute('_value', round($metalValue + (float) $item->labour_cost + (float) $item->polish_cost, 2));

                return $item;
            });

        $byMetal = $this->groupByMetal($items, fn (Collection $rows) => [
            'item_count' => $rows->count(),
            'weight_grams' => round((float) $rows->sum('net_weight_grams'), 3),
            'value' => round((float) $rows->sum('_value'), 2),
        ]);

        return [
            'item_count' => $items->count(),
            'weight_grams' => round((float) $items->sum('net_weight_grams'), 3),
            'value' => round((float) $items->sum('_value'), 2),
            'by_metal' => $byMetal,
        ];
    }

    /**
     * One rate line per metal — the purest active purity is used as the
     * metal's benchmark spot price. Multiple fetches on the same day are
     * collapsed to that day's last rate, so the chart shows one point/day.
     */
    public function rateHistory(?string $from, ?string $to): array
    {
        $benchmarkPurityIds = Purity::with('metalType:id,name')
            ->where('is_active', true)
            ->orderByDesc('fineness_percent')
            ->get()
            ->groupBy('metal_type_id')
            ->map(fn (Collection $purities) => $purities->first());

        return $benchmarkPurityIds->map(function (Purity $purity) use ($from, $to) {
            $points = DailyRate::where('purity_id', $purity->id)
                ->when($from, fn ($q) => $q->whereDate('rate_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('rate_date', '<=', $to))
                ->orderBy('fetched_at')
                ->get()
                ->groupBy(fn (DailyRate $r) => $r->rate_date->toDateString())
                ->map(fn (Collection $rows, string $day) => [
                    'date' => $day,
                    'rate' => (float) $rows->sortBy('fetched_at')->last()->rate_per_gram,
                ])
                ->sortBy('date')
                ->values();

            return [
                'metal' => $purity->metalType->name ?? 'Unknown',
                'purity' => $purity->name,
                'points' => $points,
            ];
        })
            ->sortBy('metal')
            ->values()
            ->all();
    }

    /** @param  callable(Collection):array  $summarize */
    private function groupByMetal(Collection $rows, callable $summarize): Collection
    {
        return $rows->groupBy(fn ($row) => $row->metalType->name ?? 'Unknown')
            ->map(fn (Collection $group, string $metal) => ['metal' => $metal, ...$summarize($group)])
            ->sortBy('metal')
            ->values();
    }

    private function saleTransactions(?string $from, ?string $to): Collection
    {
        return Transaction::with('metalType:id,name')
            ->whereHas('transactionType', fn ($q) => $q->where('name', 'sale'))
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->get();
    }
}
