<?php

namespace Tests\Feature;

use App\Exceptions\QuoteExpiredException;
use App\Exceptions\RateDriftedException;
use App\Models\DailyRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Setting;
use App\Services\RateQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real-system port of metal-ledger-engine's rate-lock mechanism — the
 * answer to "a cashier opens a bill, the rate changes mid-bill, which rate
 * counts?" Purely additive: nothing here is wired into the existing POS
 * flow yet, so none of this can regress anything currently working.
 */
class RateQuoteLockTest extends TestCase
{
    use RefreshDatabase;

    private RateQuoteService $quotes;

    private MetalType $gold;

    private Purity $gold22k;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotes = new RateQuoteService;
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->gold22k = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.66]);
    }

    private function setCurrentRate(float $rate): void
    {
        DailyRate::where('purity_id', $this->gold22k->id)->update(['is_current' => false]);
        DailyRate::create([
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->gold22k->id,
            'rate_per_gram' => $rate,
            'rate_date' => now()->toDateString(),
            'source' => 'manual',
            'is_current' => true,
        ]);
    }

    public function test_a_quote_still_within_the_window_and_unchanged_rate_passes_silently(): void
    {
        $this->setCurrentRate(22000.00);
        $quote = $this->quotes->quote($this->gold22k->id);

        $this->quotes->assertQuoteStillValid($quote, now()->addMinutes(5));

        $this->expectNotToPerformAssertions();
    }

    public function test_a_quote_past_the_default_15_minute_window_must_be_re_quoted(): void
    {
        $this->setCurrentRate(22000.00);
        $quote = $this->quotes->quote($this->gold22k->id);

        $this->expectException(QuoteExpiredException::class);
        $this->quotes->assertQuoteStillValid($quote, $quote['quoted_at']->copy()->addMinutes(16));
    }

    public function test_confirmed_does_not_rescue_an_expired_quote(): void
    {
        $this->setCurrentRate(22000.00);
        $quote = $this->quotes->quote($this->gold22k->id);

        $this->expectException(QuoteExpiredException::class);
        $this->quotes->assertQuoteStillValid($quote, $quote['quoted_at']->copy()->addMinutes(20), confirmed: true);
    }

    public function test_a_rate_drifted_beyond_tolerance_forces_confirmation(): void
    {
        $this->setCurrentRate(22000.00);
        $quote = $this->quotes->quote($this->gold22k->id);

        // 22400 is +1.818% over 22000 — past the 0.5% default tolerance.
        $this->setCurrentRate(22400.00);

        try {
            $this->quotes->assertQuoteStillValid($quote, $quote['quoted_at']->copy()->addMinutes(5));
            $this->fail('Expected RateDriftedException.');
        } catch (RateDriftedException $e) {
            $this->assertSame(22000.0, $e->quotedRatePerGram);
            $this->assertSame(22400.0, $e->currentRatePerGram);
        }

        $this->quotes->assertQuoteStillValid($quote, $quote['quoted_at']->copy()->addMinutes(5), confirmed: true);
    }

    public function test_a_drift_within_tolerance_does_not_require_confirmation(): void
    {
        $this->setCurrentRate(22000.00);
        $quote = $this->quotes->quote($this->gold22k->id);

        // 22050 is +0.227% — within the 0.5% default tolerance.
        $this->setCurrentRate(22050.00);

        $this->quotes->assertQuoteStillValid($quote, $quote['quoted_at']->copy()->addMinutes(5));
        $this->expectNotToPerformAssertions();
    }

    public function test_the_owner_can_configure_both_limits(): void
    {
        Setting::create(['key' => 'quote_validity_minutes', 'value' => '5']);
        Setting::create(['key' => 'rate_change_tolerance_percent', 'value' => '2']);

        $this->setCurrentRate(22000.00);
        $quote = $this->quotes->quote($this->gold22k->id);

        // Past the shop's own tighter 5-minute window (would have passed the 15-minute default).
        $this->expectException(QuoteExpiredException::class);
        $this->quotes->assertQuoteStillValid($quote, $quote['quoted_at']->copy()->addMinutes(6));
    }

    public function test_refuses_to_quote_rather_than_guess_when_no_rate_is_in_force(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->quotes->quote($this->gold22k->id);
    }
}
