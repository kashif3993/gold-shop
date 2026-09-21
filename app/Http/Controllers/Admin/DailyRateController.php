<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyRate;
use App\Models\MetalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DailyRateController extends Controller
{
    /** Full, filterable history of every rate ever stored — not just the current one. */
    public function index(Request $request)
    {
        $filters = $request->only(['metal_type_id', 'purity_id', 'source', 'date_from', 'date_to']);

        $filtered = fn () => DailyRate::query()
            ->when($filters['metal_type_id'] ?? null, fn (Builder $q, $v) => $q->where('metal_type_id', $v))
            ->when($filters['purity_id'] ?? null, fn (Builder $q, $v) => $q->where('purity_id', $v))
            ->when($filters['source'] ?? null, fn (Builder $q, $v) => $q->where('source', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('rate_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('rate_date', '<=', $v));

        $rates = $filtered()
            ->with(['metalType:id,name', 'purity:id,name', 'enteredBy:id,username'])
            ->orderByDesc('fetched_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/daily-rates/index', [
            'rates' => $rates,
            'filters' => $filters,
            'metals' => MetalType::with('purities')->orderBy('name')->get(),
            'chartSeries' => $this->buildChartSeries($filtered()),
        ]);
    }

    /** One line per purity in view, one point per day (last fetch that day) — same filters as the table, no pagination. */
    private function buildChartSeries(Builder $query): array
    {
        return $query->with(['metalType:id,name', 'purity:id,name'])
            ->orderBy('fetched_at')
            ->get()
            ->groupBy('purity_id')
            ->map(function (Collection $rows) {
                $points = $rows->groupBy(fn (DailyRate $r) => $r->rate_date->toDateString())
                    ->map(fn (Collection $day) => [
                        'date' => $day->first()->rate_date->toDateString(),
                        'rate' => (float) $day->sortBy('fetched_at')->last()->rate_per_gram,
                    ])
                    ->sortBy('date')
                    ->values();

                $first = $rows->first();

                return [
                    'metal' => $first->metalType->name ?? 'Unknown',
                    'purity' => $first->purity->name ?? 'Unknown',
                    'points' => $points,
                ];
            })
            ->sortBy('metal')
            ->values()
            ->all();
    }
}
