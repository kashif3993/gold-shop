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
     * Calculate buy-back valuation. Labour & polish are always zero.
     *
     * Two ways a jeweller expresses the deduction for wear / melting loss:
     *   - a percentage of the re-weighed weight, or
     *   - a fixed weight cut in grams (what "cut 3 ratti / half a masha" means).
     *
     * Pass $deductionWeightGrams for the weight-cut case; it wins over the
     * percentage and the equivalent percentage is derived back for the record.
     *
     * Effective Weight = reweighedWeight - cut   (cut = % of weight, or the fixed grams)
     * Buyback Amount   = Effective Weight * currentRate
     */
    public function calculateValuation(
        float $weightGrams,
        float $ratePerGram,
        float $deductionPercent,
        ?float $deductionWeightGrams = null,
    ): array {
        if ($deductionWeightGrams !== null) {
            $cut = max(0.0, min($weightGrams, $deductionWeightGrams));
            $effectiveWeight = $weightGrams - $cut;
            $deductionPercent = $weightGrams > 0 ? ($cut / $weightGrams) * 100 : 0.0;
        } else {
            $deductionFraction = max(0.0, min(100.0, $deductionPercent)) / 100.0;
            $effectiveWeight = $weightGrams * (1.0 - $deductionFraction);
        }

        $totalAmount = round($effectiveWeight * $ratePerGram, 2);

        return [
            'gross_weight_grams' => $weightGrams,
            'deduction_percent' => round($deductionPercent, 2),
            'deduction_weight_grams' => round($weightGrams - $effectiveWeight, 3),
            'effective_weight_grams' => round($effectiveWeight, 3),
            'rate_per_gram' => $ratePerGram,
            'total_amount' => $totalAmount,
        ];
    }
}
