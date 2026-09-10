<?php

namespace App\Services;

use App\Models\DailyRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RateFetchLog;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetch → convert → adjust → store pipeline for the daily gold/silver rate.
 *
 *   spot (USD / troy oz)  ──/31.1034768──►  USD/gram  ──×usd_pkr──►  PKR/gram (24K / fine)
 *   per purity: base × fineness%           ──shop adjustment──►      stored rate_per_gram
 *
 * Only the newest row per (metal, purity) is `is_current`. If the feed is
 * unreachable the last current rate is kept (billing never stops) and, once it
 * is older than the stale threshold, flagged `is_stale` for the UI to warn.
 */
class GoldRateService
{
    public const TROY_OUNCE_GRAMS = 31.1034768;

    /** gold-api.com symbols keyed by our metal name. */
    private const SYMBOLS = ['Gold' => 'XAU', 'Silver' => 'XAG'];

    public function __construct(private RateAdjustmentService $adjustments) {}

    /* ─────────────────────────── reads ─────────────────────────── */

    /** Current effective rate per purity_id, with the numbers behind it. */
    public function currentRates(): Collection
    {
        return DailyRate::with(['metalType:id,name', 'purity:id,name,metal_type_id'])
            ->where('is_current', true)
            ->get()
            ->keyBy('purity_id');
    }

    public function isFeedStale(): bool
    {
        $newest = DailyRate::where('is_current', true)->max('fetched_at');
        if (! $newest) {
            return true;
        }

        return Carbon::parse($newest)->lt(now()->subHours($this->staleAfterHours()));
    }

    public function staleAfterHours(): int
    {
        return (int) config('services.gold_api.stale_after_hours', 12);
    }

    /** The USD→PKR rate the conversion uses: the stored setting, else the config default. */
    public function usdPkr(): float
    {
        $stored = (float) Setting::get('usd_pkr', 0);

        return $stored > 0 ? $stored : (float) config('services.gold_api.usd_pkr', 278.0);
    }

    /** USD→PKR state for the Rate Management screen. */
    public function fxState(): array
    {
        return [
            'value' => $this->usdPkr(),
            'auto' => Setting::bool('usd_pkr_auto'),
            'fetched_at' => Setting::get('usd_pkr_fetched_at'),
            'default' => (float) config('services.gold_api.usd_pkr', 278.0),
        ];
    }

    /* ─────────────────────────── writes ─────────────────────────── */

    /**
     * Store one purity's rate: retire the previous current row, insert the new
     * one with the shop adjustment applied. Returns the created row.
     */
    public function storeRate(int $metalTypeId, int $purityId, float $rawPerGram, string $source, ?int $userId = null): DailyRate
    {
        $adj = $this->adjustments->resolveAdjustment($purityId, $metalTypeId);
        $final = $this->adjustments->applyAdjustment($rawPerGram, $purityId, $metalTypeId);

        return DB::transaction(function () use ($metalTypeId, $purityId, $rawPerGram, $source, $userId, $adj, $final) {
            DailyRate::where('metal_type_id', $metalTypeId)
                ->where('purity_id', $purityId)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return DailyRate::create([
                'metal_type_id' => $metalTypeId,
                'purity_id' => $purityId,
                'api_raw_rate_per_gram' => round($rawPerGram, 2),
                'adjustment_type_used' => $adj['value'] != 0.0 ? $adj['type'] : null,
                'adjustment_value_used' => $adj['value'] != 0.0 ? $adj['value'] : null,
                'rate_per_gram' => $final,
                'rate_date' => now()->toDateString(),
                'source' => $source,
                'fetched_at' => now(),
                'is_current' => true,
                'is_stale' => false,
                'entered_by_user_id' => $userId,
            ]);
        });
    }

    /**
     * Manual entry: shopkeeper types the pure (24K / fine) PKR-per-gram base for
     * a metal; every active purity of that metal is derived from its fineness.
     *
     * @param  array<string,float>  $baseByMetalName  e.g. ['Gold' => 24500, 'Silver' => 312]
     */
    public function applyManual(array $baseByMetalName, ?int $userId = null): int
    {
        $count = 0;

        foreach ($this->metalsWithPurities() as $metal) {
            $base = $baseByMetalName[$metal->name] ?? null;
            if ($base === null || $base <= 0) {
                continue;
            }

            foreach ($metal->purities as $purity) {
                $this->storeRate($metal->id, $purity->id, $this->deriveForPurity((float) $base, $purity), 'manual', $userId);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Fetch the spot rate and store every purity. On any failure the last
     * current rate is left untouched and the attempt is logged as a fallback.
     */
    public function refreshFromApi(?int $userId = null): RateFetchLog
    {
        $host = parse_url((string) config('services.gold_api.base_url'), PHP_URL_HOST) ?: 'gold-api.com';

        try {
            $bases = $this->fetchBasesFromApi();

            $stored = 0;
            $summaryParts = [];
            foreach ($this->metalsWithPurities() as $metal) {
                if (! isset($bases[$metal->name])) {
                    continue;
                }
                $base = $bases[$metal->name];
                $summaryParts[] = "{$metal->name} " . number_format($base, 2) . '/g';
                foreach ($metal->purities as $purity) {
                    $this->storeRate($metal->id, $purity->id, $this->deriveForPurity($base, $purity), 'api', $userId);
                    $stored++;
                }
            }

            return RateFetchLog::create([
                'success' => true,
                'source_api' => $host,
                'response_summary' => "Stored {$stored} rates — " . implode(', ', $summaryParts),
                'fallback_used' => false,
            ]);
        } catch (\Throwable $e) {
            $this->markStale();

            return RateFetchLog::create([
                'success' => false,
                'source_api' => $host,
                'response_summary' => substr('Fetch failed: ' . $e->getMessage(), 0, 255),
                'fallback_used' => true,
            ]);
        }
    }

    /**
     * Recompute the effective rate of every current row from its stored raw
     * rate + the (possibly just-changed) shop adjustment. Called after a
     * settings edit so the counter rate updates without waiting for a fetch.
     */
    public function reapplyAdjustments(): int
    {
        $n = 0;
        foreach (DailyRate::where('is_current', true)->get() as $row) {
            if ($row->api_raw_rate_per_gram === null) {
                continue;
            }
            $adj = $this->adjustments->resolveAdjustment($row->purity_id, $row->metal_type_id);
            $row->update([
                'adjustment_type_used' => $adj['value'] != 0.0 ? $adj['type'] : null,
                'adjustment_value_used' => $adj['value'] != 0.0 ? $adj['value'] : null,
                'rate_per_gram' => $this->adjustments->applyAdjustment(
                    (float) $row->api_raw_rate_per_gram,
                    $row->purity_id,
                    $row->metal_type_id,
                ),
            ]);
            $n++;
        }

        return $n;
    }

    /** Flag current rates that have gone past the stale threshold. */
    public function markStale(): int
    {
        return DailyRate::where('is_current', true)
            ->where('is_stale', false)
            ->where('fetched_at', '<', now()->subHours($this->staleAfterHours()))
            ->update(['is_stale' => true]);
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    /** Pure base × purity fineness = that purity's per-gram metal value. */
    public function deriveForPurity(float $pureBasePerGram, Purity $purity): float
    {
        $fineness = (float) $purity->fineness_percent ?: 100.0;

        return round($pureBasePerGram * $fineness / 100, 4);
    }

    /**
     * Hit gold-api.com for each metal and return the 24K / fine PKR-per-gram base.
     *
     * @return array<string,float>
     */
    private function fetchBasesFromApi(): array
    {
        $base = rtrim((string) config('services.gold_api.base_url'), '/');
        $timeout = (int) config('services.gold_api.timeout', 8);

        // Auto-refresh USD→PKR first if enabled; on failure keep the stored value.
        if (Setting::bool('usd_pkr_auto')) {
            try {
                $fx = $this->fetchFxRate();
                Setting::put('usd_pkr', $fx);
                Setting::put('usd_pkr_fetched_at', now()->toIso8601String());
            } catch (\Throwable) {
                // keep whatever usd_pkr is stored
            }
        }

        $usdPkr = $this->usdPkr();
        if ($usdPkr <= 0) {
            throw new \RuntimeException('USD→PKR rate is not configured.');
        }

        $out = [];
        foreach (self::SYMBOLS as $metalName => $symbol) {
            $resp = Http::timeout($timeout)->acceptJson()->get("{$base}/price/{$symbol}");
            if (! $resp->ok()) {
                throw new \RuntimeException("{$symbol} request returned HTTP {$resp->status()}");
            }
            $usdPerOunce = (float) $resp->json('price');
            if ($usdPerOunce <= 0) {
                throw new \RuntimeException("{$symbol} response had no usable price");
            }
            $out[$metalName] = round($usdPerOunce / self::TROY_OUNCE_GRAMS * $usdPkr, 4);
        }

        return $out;
    }

    /** Fetch the live USD→PKR rate from the configured FX endpoint. */
    public function fetchFxRate(): float
    {
        $url = (string) config('services.gold_api.fx_url');
        $timeout = (int) config('services.gold_api.timeout', 8);

        $resp = Http::timeout($timeout)->acceptJson()->get($url);
        if (! $resp->ok()) {
            throw new \RuntimeException("FX request returned HTTP {$resp->status()}");
        }

        // open.er-api.com shape: { "rates": { "PKR": 283.5, ... } }
        $pkr = (float) ($resp->json('rates.PKR') ?? $resp->json('conversion_rates.PKR') ?? 0);
        if ($pkr <= 0) {
            throw new \RuntimeException('FX response had no usable PKR rate');
        }

        return round($pkr, 4);
    }

    /** @return Collection<int,MetalType> */
    private function metalsWithPurities(): Collection
    {
        return MetalType::with(['purities' => fn ($q) => $q->where('is_active', true)])->get();
    }
}
