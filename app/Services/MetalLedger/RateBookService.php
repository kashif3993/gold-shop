<?php

declare(strict_types=1);

namespace App\Services\MetalLedger;

use App\Models\MetalLedger\MetalLedgerRate;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Task 2 of the Metal Ledger brief — the rate book. Every price traces back
 * to one rate, for one metal and one purity, in force at one moment.
 *
 * Nothing in here ever updates or deletes a row: a correction is a new row
 * that takes over as current, and the one it replaces stays in history
 * forever (see {@see recordManualRate()}/{@see recordFetchedRate()}).
 */
final class RateBookService
{
    private const RATE_TYPES = ['buy', 'sell'];

    private const DEFAULT_STALE_AFTER_HOURS = 12;

    /**
     * An Admin/manual entry for one metal + purity + buy-or-sell rate.
     * Always becomes the new current rate — a manual entry is never
     * refused, since it's exactly how an owner corrects an automatic fetch.
     */
    public function recordManualRate(
        int $metalTypeId,
        int $purityId,
        string $rateType,
        float $ratePerGram,
        ?int $enteredByUserId = null,
        ?CarbonInterface $effectiveAt = null,
    ): MetalLedgerRate {
        $this->assertValidRateType($rateType);
        $effectiveAt ??= now();

        $this->retireCurrent($metalTypeId, $purityId, $rateType);

        return MetalLedgerRate::create([
            'metal_type_id' => $metalTypeId,
            'purity_id' => $purityId,
            'rate_type' => $rateType,
            'rate_per_gram' => $ratePerGram,
            'effective_at' => $effectiveAt,
            'source' => 'manual',
            'entered_by_user_id' => $enteredByUserId,
            'is_current' => true,
            'superseded_by_manual' => false,
        ]);
    }

    /**
     * An automatic fetch for one metal + purity + buy-or-sell rate. If the
     * owner has already corrected this exact combination today, the fetch
     * is written to history but never applied — it must not quietly undo
     * the owner's decision. Otherwise it becomes the new current rate, same
     * as any other entry.
     *
     * Check the returned row's `superseded_by_manual` to see whether it
     * actually took effect.
     */
    public function recordFetchedRate(
        int $metalTypeId,
        int $purityId,
        string $rateType,
        float $ratePerGram,
        ?CarbonInterface $effectiveAt = null,
    ): MetalLedgerRate {
        $this->assertValidRateType($rateType);
        $effectiveAt ??= now();

        $supersededByManual = $this->hasManualEntryOn($metalTypeId, $purityId, $rateType, $effectiveAt);

        if (! $supersededByManual) {
            $this->retireCurrent($metalTypeId, $purityId, $rateType);
        }

        return MetalLedgerRate::create([
            'metal_type_id' => $metalTypeId,
            'purity_id' => $purityId,
            'rate_type' => $rateType,
            'rate_per_gram' => $ratePerGram,
            'effective_at' => $effectiveAt,
            'source' => 'fetch',
            'entered_by_user_id' => null,
            'is_current' => ! $supersededByManual,
            'superseded_by_manual' => $supersededByManual,
        ]);
    }

    public function currentRate(int $metalTypeId, int $purityId, string $rateType): ?MetalLedgerRate
    {
        $this->assertValidRateType($rateType);

        return MetalLedgerRate::query()
            ->where('metal_type_id', $metalTypeId)
            ->where('purity_id', $purityId)
            ->where('rate_type', $rateType)
            ->where('is_current', true)
            ->first();
    }

    /**
     * "What was the rate for this metal + purity + buy-or-sell type at this
     * exact moment?" — the latest entry that was actually in force at or
     * before $moment (a fetch the owner's correction superseded was never
     * in force, so it's excluded here regardless of its own timestamp).
     */
    public function rateAt(int $metalTypeId, int $purityId, string $rateType, CarbonInterface $moment): ?MetalLedgerRate
    {
        $this->assertValidRateType($rateType);

        return MetalLedgerRate::query()
            ->where('metal_type_id', $metalTypeId)
            ->where('purity_id', $purityId)
            ->where('rate_type', $rateType)
            ->where('superseded_by_manual', false)
            ->where('effective_at', '<=', $moment)
            ->orderByDesc('effective_at')
            ->first();
    }

    public function isStale(
        int $metalTypeId,
        int $purityId,
        string $rateType,
        int $staleAfterHours = self::DEFAULT_STALE_AFTER_HOURS,
    ): bool {
        $current = $this->currentRate($metalTypeId, $purityId, $rateType);

        if ($current === null) {
            return true;
        }

        return $current->effective_at->lt(now()->subHours($staleAfterHours));
    }

    /**
     * A purity with no rate of its own, derived from a basis purity's
     * current rate by the ratio of fineness — e.g. pricing a 20K item off
     * the 24K rate until the owner sets 20K's own rate directly. Never
     * applied silently: the result always carries `is_derived` so the
     * caller labels it, and this never writes a row to the rate book itself
     * — a derived figure is computed fresh each time, not stored as if it
     * were a real quoted rate.
     *
     * @return array{rate_per_gram: float, is_derived: true, basis: MetalLedgerRate}|null
     *                                                                                    null when the basis purity itself has no current rate —
     *                                                                                    refuse to price rather than guess.
     */
    public function deriveRate(
        int $basisMetalTypeId,
        int $basisPurityId,
        string $rateType,
        float $basisFinenessPercent,
        float $targetFinenessPercent,
    ): ?array {
        $this->assertValidRateType($rateType);

        if ($basisFinenessPercent <= 0) {
            throw new InvalidArgumentException('Basis fineness must be greater than 0.');
        }

        $basis = $this->currentRate($basisMetalTypeId, $basisPurityId, $rateType);

        if ($basis === null) {
            return null;
        }

        $derivedRatePerGram = round((float) $basis->rate_per_gram * $targetFinenessPercent / $basisFinenessPercent, 2);

        return ['rate_per_gram' => $derivedRatePerGram, 'is_derived' => true, 'basis' => $basis];
    }

    private function hasManualEntryOn(int $metalTypeId, int $purityId, string $rateType, CarbonInterface $onDate): bool
    {
        return MetalLedgerRate::query()
            ->where('metal_type_id', $metalTypeId)
            ->where('purity_id', $purityId)
            ->where('rate_type', $rateType)
            ->where('source', 'manual')
            ->whereDate('effective_at', $onDate->toDateString())
            ->exists();
    }

    private function retireCurrent(int $metalTypeId, int $purityId, string $rateType): void
    {
        MetalLedgerRate::query()
            ->where('metal_type_id', $metalTypeId)
            ->where('purity_id', $purityId)
            ->where('rate_type', $rateType)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function assertValidRateType(string $rateType): void
    {
        if (! in_array($rateType, self::RATE_TYPES, true)) {
            throw new InvalidArgumentException(
                "Unknown rate type [{$rateType}]. Expected one of: ".implode(', ', self::RATE_TYPES)
            );
        }
    }
}
