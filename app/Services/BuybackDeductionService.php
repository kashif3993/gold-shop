<?php

namespace App\Services;

use App\Models\BuybackDeductionSetting;

class BuybackDeductionService
{
    /**
     * Resolve buy-back deduction percentage based on cascade:
     * 1. Purity-specific setting
     * 2. Metal-level setting (purity_id is null)
     * 3. Shop default setting (is_shop_default is true)
     * 4. Hardcoded fallback (2.50%)
     */
    public function resolveDeductionPercent(?int $purityId = null, ?int $metalTypeId = null): float
    {
        // 1. Purity-specific override
        if ($purityId) {
            $puritySetting = BuybackDeductionSetting::where('purity_id', $purityId)->first();
            if ($puritySetting !== null) {
                return (float) $puritySetting->deduction_percent;
            }
        }

        // 2. Metal-level override (where purity_id is null)
        if ($metalTypeId) {
            $metalSetting = BuybackDeductionSetting::where('metal_type_id', $metalTypeId)
                ->whereNull('purity_id')
                ->first();
            if ($metalSetting !== null) {
                return (float) $metalSetting->deduction_percent;
            }
        }

        // 3. Shop default setting
        $shopDefault = BuybackDeductionSetting::where('is_shop_default', true)->first();
        if ($shopDefault !== null) {
            return (float) $shopDefault->deduction_percent;
        }

        // 4. Fallback default
        return 2.50;
    }

    /**
     * Calculate buy-back valuation.
     * Note: Labour & polish are always zero. Value is strictly derived from:
     * Effective Weight = reweighedWeight * (1 - (deductionPercent / 100))
     * Buyback Amount = Effective Weight * currentRate
     */
    public function calculateValuation(float $weightGrams, float $ratePerGram, float $deductionPercent): array
    {
        $deductionFraction = max(0.0, min(100.0, $deductionPercent)) / 100.0;
        $effectiveWeight = $weightGrams * (1.0 - $deductionFraction);
        $totalAmount = round($effectiveWeight * $ratePerGram, 2);

        return [
            'gross_weight_grams' => $weightGrams,
            'deduction_percent' => $deductionPercent,
            'effective_weight_grams' => round($effectiveWeight, 3),
            'rate_per_gram' => $ratePerGram,
            'total_amount' => $totalAmount,
        ];
    }
}
