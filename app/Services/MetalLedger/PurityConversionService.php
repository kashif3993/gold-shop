<?php

declare(strict_types=1);

namespace App\Services\MetalLedger;

use InvalidArgumentException;

/**
 * Turns a worn weight into pure-metal weight — Task 1 of the Metal Ledger
 * brief: "a 10 g 22K chain is 9.167 g of pure gold, a 10 g 925 bar is 9.25 g
 * of pure silver." Stock totals, karigar balances and zakat are summed in
 * pure-metal weight; this is the one place that number gets computed, so
 * every caller reaches the same figure.
 */
final class PurityConversionService
{
    private const WEIGHT_DECIMALS = 3;

    /**
     * @param  float  $wornWeightGrams  the item's actual (worn) weight, in grams
     * @param  float  $finenessPercent  the purity's fineness, e.g. 91.67 for 22K
     * @return float pure-metal weight in grams, rounded once to the shop's
     *               agreed weight precision
     */
    public function pureWeight(float $wornWeightGrams, float $finenessPercent): float
    {
        if ($wornWeightGrams < 0) {
            throw new InvalidArgumentException('Worn weight cannot be negative.');
        }

        if ($finenessPercent < 0 || $finenessPercent > 100) {
            throw new InvalidArgumentException('Fineness must be between 0 and 100.');
        }

        return round($wornWeightGrams * $finenessPercent / 100, self::WEIGHT_DECIMALS);
    }
}
