<?php

declare(strict_types=1);

namespace App\Services\MetalLedger;

use App\Models\MetalLedger\MetalLedgerBuyBackDeduction;
use App\Models\MetalLedger\MetalLedgerMovement;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Task 5 of the Metal Ledger brief — buy-back valuation. Gold coming back
 * over the counter is gold being bought fresh today; it is never a sale
 * being undone.
 *
 * Deliberately, {@see valuate()} and {@see recordBuyBack()} have no
 * parameter anywhere for an original sale's price or weight — not because
 * it's ignored by convention, but because the method signatures simply
 * don't accept one. There is no override, no admin flag, no path through
 * this class that can make an original sale figure reach a buy-back
 * amount — the only inputs are today's re-weighed grams, today's buying
 * rate (pulled fresh from {@see RateBookService}, never client-supplied),
 * and a deduction percentage.
 */
final class BuyBackValuationService
{
    private const DEFAULT_DEDUCTION_PERCENT = 2.50;

    public function __construct(
        private readonly RateBookService $rateBook,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Price a buy-back from today's re-weighed grams alone. Throws rather
     * than guess if there's no current buying rate for this metal+purity —
     * refusing to price is the safe failure, not silently using a stale or
     * substitute figure.
     *
     * @return array{
     *     weight_grams: float,
     *     rate_per_gram: float,
     *     deduction_percent: float,
     *     deduction_grams: float,
     *     effective_weight_grams: float,
     *     amount: float,
     *     explanation: string,
     * }
     */
    public function valuate(
        int $metalTypeId,
        int $purityId,
        float $reweighedGrams,
        ?float $deductionPercentOverride = null,
    ): array {
        if ($reweighedGrams <= 0) {
            throw new InvalidArgumentException('Re-weighed grams must be greater than 0.');
        }

        $rate = $this->rateBook->currentRate($metalTypeId, $purityId, 'buy');

        if ($rate === null) {
            throw new RuntimeException(
                'No current buying rate for this metal and purity — refuse to price rather than guess.'
            );
        }

        $deductionPercent = $deductionPercentOverride ?? $this->resolveDeductionPercent($metalTypeId, $purityId);

        if ($deductionPercent < 0 || $deductionPercent > 100) {
            throw new InvalidArgumentException('Deduction percent must be between 0 and 100.');
        }

        $ratePerGram = (float) $rate->rate_per_gram;
        $deductionGrams = round($reweighedGrams * $deductionPercent / 100, 3);
        $effectiveWeightGrams = round($reweighedGrams - $deductionGrams, 3);
        $amount = round($effectiveWeightGrams * $ratePerGram, 2);

        return [
            'weight_grams' => $reweighedGrams,
            'rate_per_gram' => $ratePerGram,
            'deduction_percent' => $deductionPercent,
            'deduction_grams' => $deductionGrams,
            'effective_weight_grams' => $effectiveWeightGrams,
            'amount' => $amount,
            // The brief's own bar: the cashier has to justify this to a
            // customer who expected to get back what they paid — so the
            // figure explains itself in one line.
            'explanation' => sprintf(
                '%.3fg re-weighed − %.2f%% (%.3fg) deduction = %.3fg × Rs %.2f/g = Rs %.2f',
                $reweighedGrams,
                $deductionPercent,
                $deductionGrams,
                $effectiveWeightGrams,
                $ratePerGram,
                $amount,
            ),
        ];
    }

    /**
     * Price a buy-back and record it on the ledger in one step — always
     * `direction: 'in'`, `party`: the customer, exactly as any other
     * purchase (Task 3). `$provenanceReference` is for display only (e.g.
     * "originally sold as INV-1234, for reference"); it is never read by
     * {@see valuate()} and never touches the amount.
     */
    public function recordBuyBack(
        int $partyId,
        int $metalTypeId,
        int $purityId,
        float $reweighedGrams,
        CarbonInterface|string $movementDate,
        ?float $deductionPercentOverride = null,
        ?int $enteredByUserId = null,
        ?string $documentReference = null,
        ?string $provenanceReference = null,
    ): MetalLedgerMovement {
        $valuation = $this->valuate($metalTypeId, $purityId, $reweighedGrams, $deductionPercentOverride);

        $notes = $valuation['explanation'];
        if ($provenanceReference !== null) {
            $notes .= " (provenance only, not used in valuation: {$provenanceReference})";
        }

        return $this->ledger->recordMovement(
            partyId: $partyId,
            direction: 'in',
            metalTypeId: $metalTypeId,
            purityId: $purityId,
            weightGrams: $valuation['weight_grams'],
            ratePerGram: $valuation['rate_per_gram'],
            amount: $valuation['amount'],
            movementDate: $movementDate,
            enteredByUserId: $enteredByUserId,
            documentReference: $documentReference,
            notes: $notes,
        );
    }

    /**
     * Most-specific first: an exact metal+purity row, then a metal-wide
     * row, then the shop-wide default, then a hard fallback if nothing has
     * ever been configured.
     */
    public function resolveDeductionPercent(int $metalTypeId, int $purityId): float
    {
        $exact = MetalLedgerBuyBackDeduction::query()
            ->where('metal_type_id', $metalTypeId)
            ->where('purity_id', $purityId)
            ->first();
        if ($exact !== null) {
            return (float) $exact->deduction_percent;
        }

        $metalWide = MetalLedgerBuyBackDeduction::query()
            ->where('metal_type_id', $metalTypeId)
            ->whereNull('purity_id')
            ->first();
        if ($metalWide !== null) {
            return (float) $metalWide->deduction_percent;
        }

        $shopDefault = MetalLedgerBuyBackDeduction::query()
            ->where('is_shop_default', true)
            ->first();
        if ($shopDefault !== null) {
            return (float) $shopDefault->deduction_percent;
        }

        return self::DEFAULT_DEDUCTION_PERCENT;
    }
}
