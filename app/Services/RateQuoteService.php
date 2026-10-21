<?php

namespace App\Services;

use App\Exceptions\QuoteExpiredException;
use App\Exceptions\RateDriftedException;
use App\Models\DailyRate;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Answers the same question a billing system always eventually has to
 * answer: a cashier opens a bill, the rate changes mid-bill, the bill
 * prints later — which rate counts? Each line locks the rate the moment
 * it's quoted, not at print time. Two owner-set limits are checked when
 * the bill is finalized:
 *
 * 1. Quote validity window — a locked rate only holds for N minutes
 *    (default 15). Past that, the line must be re-quoted. This cannot be
 *    bypassed by a confirmation.
 * 2. Rate-change tolerance — if the live rate has moved more than a set %
 *    (default 0.5%) since quoting, the cashier must explicitly confirm
 *    before it can go through.
 *
 * Purely additive and opt-in: existing POS flows that never call quote()
 * are completely unaffected — this only ever runs when a caller actually
 * provides a quote to check.
 */
class RateQuoteService
{
    private const DEFAULT_QUOTE_VALIDITY_MINUTES = 15;

    private const DEFAULT_RATE_CHANGE_TOLERANCE_PERCENT = 0.5;

    public function quoteValidityMinutes(): int
    {
        return (int) Setting::get('quote_validity_minutes', self::DEFAULT_QUOTE_VALIDITY_MINUTES);
    }

    public function rateChangeTolerancePercent(): float
    {
        return (float) Setting::get('rate_change_tolerance_percent', self::DEFAULT_RATE_CHANGE_TOLERANCE_PERCENT);
    }

    /**
     * Lock the current rate for one purity, the moment a line is added to
     * the bill. Refuses to quote rather than guess — same principle as the
     * rest of the rate pipeline — if there is no current rate at all.
     *
     * @return array{purity_id: int, rate_per_gram: float, quoted_at: Carbon}
     */
    public function quote(int $purityId, ?Carbon $at = null): array
    {
        $at ??= now();

        $current = DailyRate::where('purity_id', $purityId)->where('is_current', true)->first();

        if ($current === null) {
            throw new RuntimeException('No current rate for this purity — refuse to quote rather than guess.');
        }

        return [
            'purity_id' => $purityId,
            'rate_per_gram' => (float) $current->rate_per_gram,
            'quoted_at' => $at,
        ];
    }

    /**
     * Checked when a bill is finalized — exactly where the quote-lock is
     * meant to be enforced. Passes silently (returns void) when the quote
     * is still good as-is.
     */
    public function assertQuoteStillValid(array $quote, ?Carbon $finalizeAt = null, bool $confirmed = false): void
    {
        $finalizeAt ??= now();
        $quotedAt = $quote['quoted_at'];

        $minutesElapsed = $quotedAt->diffInRealSeconds($finalizeAt) / 60;
        $validityMinutes = $this->quoteValidityMinutes();

        if ($minutesElapsed > $validityMinutes) {
            throw new QuoteExpiredException($minutesElapsed, $validityMinutes);
        }

        $current = DailyRate::where('purity_id', $quote['purity_id'])->where('is_current', true)->first();
        if ($current === null) {
            // The rate disappeared entirely since quoting — treat like a
            // validity failure; there's nothing to compare drift against.
            throw new QuoteExpiredException($minutesElapsed, $validityMinutes);
        }

        $quotedRate = (float) $quote['rate_per_gram'];
        $currentRate = (float) $current->rate_per_gram;
        $driftPercent = $quotedRate == 0.0 ? 0.0 : abs($currentRate - $quotedRate) / $quotedRate * 100;
        $tolerancePercent = $this->rateChangeTolerancePercent();

        if ($driftPercent > $tolerancePercent && ! $confirmed) {
            throw new RateDriftedException($quotedRate, $currentRate, $driftPercent, $tolerancePercent);
        }
    }
}
