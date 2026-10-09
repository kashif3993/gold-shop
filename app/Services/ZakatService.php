<?php

namespace App\Services;

use App\Models\Purity;
use App\Models\Setting;

/**
 * Zakat on gold/silver is due, at the configured rate (default 2.5%), once
 * total holdings reach the Nisab threshold — classically the value of a set
 * weight of gold or silver, whichever is lower (config/Admin → Settings
 * default: 87.48g gold / 612.36g silver). Everything here is computed live
 * from the current rate; nothing is cached or stored, matching the
 * roadmap's "always computed live from current stock + rate" requirement.
 */
class ZakatService
{
    public function __construct(private GoldRateService $rates, private ReportService $reports) {}

    public function ratePercent(): float
    {
        return (float) Setting::get('zakat_rate_percent', 2.5);
    }

    /**
     * Nisab in PKR for gold and silver separately (null for a metal the shop
     * doesn't currently deal in / has no live rate for), plus the threshold
     * actually used — the lower of the two available, the standard basis
     * for trade goods since it makes Zakat obligatory to more people.
     */
    public function nisab(): array
    {
        $goldGrams = (float) Setting::get('zakat_nisab_gold_grams', 87.48);
        $silverGrams = (float) Setting::get('zakat_nisab_silver_grams', 612.36);

        $gold = $this->benchmarkValue('Gold', $goldGrams);
        $silver = $this->benchmarkValue('Silver', $silverGrams);

        $available = array_filter([$gold, $silver]);

        return [
            'gold' => $gold,
            'silver' => $silver,
            'threshold' => $available === [] ? 0.0 : min(array_column($available, 'value')),
        ];
    }

    /** The shop's own current stock, valued exactly like the Reports screen — Zakat due if it clears nisab. */
    public function shopStock(): array
    {
        $stock = $this->reports->stockValuation();
        $nisab = $this->nisab();
        $meetsNisab = $stock['value'] >= $nisab['threshold'] && $nisab['threshold'] > 0;

        return [
            'stock_value' => $stock['value'],
            'by_metal' => $stock['by_metal'],
            'nisab' => $nisab,
            'meets_nisab' => $meetsNisab,
            'zakat_due' => $meetsNisab ? round($stock['value'] * $this->ratePercent() / 100, 2) : 0.0,
        ];
    }

    /** @return array{grams: float, rate_per_gram: float, value: float}|null */
    private function benchmarkValue(string $metalName, float $grams): ?array
    {
        $purity = Purity::whereHas('metalType', fn ($q) => $q->where('name', $metalName)->where('is_active', true))
            ->where('is_active', true)
            ->orderByDesc('fineness_percent')
            ->first();

        if (! $purity) {
            return null;
        }

        $rate = (float) ($this->rates->currentRates()->get($purity->id)->rate_per_gram ?? 0);
        if ($rate <= 0) {
            return null;
        }

        return ['grams' => $grams, 'rate_per_gram' => $rate, 'value' => round($grams * $rate, 2)];
    }
}
