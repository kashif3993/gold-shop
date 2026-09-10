<?php

namespace App\Services;

use App\Models\RateAdjustmentSetting;

/**
 * Resolves the shop's rate adjustment (mark-up / mark-down over the official
 * spot rate) using the same cascade as buy-back deductions:
 *
 *   1. Purity-specific setting
 *   2. Metal-level setting  (purity_id is null)
 *   3. Shop default setting (is_shop_default = true)
 *   4. No adjustment        (amount, 0)
 */
class RateAdjustmentService
{
    /** @return array{type: 'amount'|'percent', value: float} */
    public function resolveAdjustment(?int $purityId = null, ?int $metalTypeId = null): array
    {
        if ($purityId) {
            $s = RateAdjustmentSetting::where('purity_id', $purityId)->first();
            if ($s) {
                return ['type' => $s->adjustment_type, 'value' => (float) $s->adjustment_value];
            }
        }

        if ($metalTypeId) {
            $s = RateAdjustmentSetting::where('metal_type_id', $metalTypeId)
                ->whereNull('purity_id')
                ->first();
            if ($s) {
                return ['type' => $s->adjustment_type, 'value' => (float) $s->adjustment_value];
            }
        }

        $default = RateAdjustmentSetting::where('is_shop_default', true)->first();
        if ($default) {
            return ['type' => $default->adjustment_type, 'value' => (float) $default->adjustment_value];
        }

        return ['type' => 'amount', 'value' => 0.0];
    }

    /**
     * Apply the resolved adjustment to an official per-gram rate.
     * `amount` adds a flat PKR/gram; `percent` scales it.
     */
    public function applyAdjustment(float $officialPerGram, ?int $purityId = null, ?int $metalTypeId = null): float
    {
        $adj = $this->resolveAdjustment($purityId, $metalTypeId);

        $result = $adj['type'] === 'percent'
            ? $officialPerGram * (1 + $adj['value'] / 100)
            : $officialPerGram + $adj['value'];

        return round(max(0.0, $result), 2);
    }
}
