<?php

namespace Tests\Feature\MetalLedger;

use App\Models\MetalLedger\MetalLedgerRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\User;
use App\Services\MetalLedger\RateBookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RateBookServiceTest extends TestCase
{
    use RefreshDatabase;

    private RateBookService $rates;

    private MetalType $gold;

    private Purity $gold22k;

    private Purity $gold24k;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rates = new RateBookService;
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->gold22k = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.66]);
        $this->gold24k = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '24K', 'fineness_percent' => 99.90]);
    }

    /**
     * The brief's own "Done when" line, verbatim: a day with a 9 a.m. fetch,
     * an 11 a.m. Admin correction and a 1 p.m. fetch leaves three entries in
     * history and the 11 a.m. figure in force.
     */
    public function test_an_admin_correction_outranks_a_same_day_fetch_and_a_later_fetch_cannot_undo_it(): void
    {
        Carbon::setTestNow('2026-10-14 09:00:00');
        $this->rates->recordFetchedRate($this->gold->id, $this->gold22k->id, 'sell', 20000.00);

        Carbon::setTestNow('2026-10-14 11:00:00');
        $admin = User::factory()->create();
        $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'sell', 20500.00, enteredByUserId: $admin->id);

        Carbon::setTestNow('2026-10-14 13:00:00');
        $this->rates->recordFetchedRate($this->gold->id, $this->gold22k->id, 'sell', 20100.00);

        $this->assertSame(3, MetalLedgerRate::where('metal_type_id', $this->gold->id)
            ->where('purity_id', $this->gold22k->id)
            ->where('rate_type', 'sell')
            ->count());

        $current = $this->rates->currentRate($this->gold->id, $this->gold22k->id, 'sell');
        $this->assertNotNull($current);
        $this->assertSame('20500.00', $current->rate_per_gram);
        $this->assertSame('manual', $current->source);

        // The 1pm fetch is stored for the record, but never took effect.
        $thirdRow = MetalLedgerRate::where('source', 'fetch')->orderByDesc('id')->first();
        $this->assertTrue((bool) $thirdRow->superseded_by_manual);
        $this->assertFalse((bool) $thirdRow->is_current);

        Carbon::setTestNow();
    }

    public function test_a_fetch_becomes_current_when_no_manual_entry_exists_that_day(): void
    {
        $row = $this->rates->recordFetchedRate($this->gold->id, $this->gold22k->id, 'sell', 20000.00);

        $this->assertTrue($row->is_current);
        $this->assertFalse($row->superseded_by_manual);
        $this->assertSame('20000.00', $this->rates->currentRate($this->gold->id, $this->gold22k->id, 'sell')->rate_per_gram);
    }

    public function test_old_rows_are_never_deleted_or_overwritten(): void
    {
        $first = $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'sell', 20000.00);
        $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'sell', 21000.00);

        $this->assertDatabaseHas('metal_ledger_rates', [
            'id' => $first->id,
            'rate_per_gram' => '20000.00',
            'is_current' => false,
        ]);
    }

    public function test_buy_and_sell_are_tracked_as_independent_current_rates(): void
    {
        $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);
        $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'sell', 20500.00);

        $this->assertSame('19500.00', $this->rates->currentRate($this->gold->id, $this->gold22k->id, 'buy')->rate_per_gram);
        $this->assertSame('20500.00', $this->rates->currentRate($this->gold->id, $this->gold22k->id, 'sell')->rate_per_gram);
    }

    public function test_rate_at_a_past_moment_reconstructs_what_was_actually_in_force_then(): void
    {
        Carbon::setTestNow('2026-10-03 09:00:00');
        $this->rates->recordFetchedRate($this->gold->id, $this->gold22k->id, 'sell', 20000.00);

        Carbon::setTestNow('2026-10-03 16:00:00');
        $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'sell', 20750.00);

        // "What was the rate for Gold 22K on 3 October at 4pm?" — exactly the brief's own example question.
        $atFour = $this->rates->rateAt($this->gold->id, $this->gold22k->id, 'sell', Carbon::parse('2026-10-03 16:00:00'));
        $this->assertSame('20750.00', $atFour->rate_per_gram);

        // An hour earlier, only the morning fetch had happened.
        $atNoon = $this->rates->rateAt($this->gold->id, $this->gold22k->id, 'sell', Carbon::parse('2026-10-03 12:00:00'));
        $this->assertSame('20000.00', $atNoon->rate_per_gram);

        Carbon::setTestNow();
    }

    public function test_rate_at_a_moment_before_any_rate_existed_returns_null(): void
    {
        Carbon::setTestNow('2026-10-03 09:00:00');
        $this->rates->recordFetchedRate($this->gold->id, $this->gold22k->id, 'sell', 20000.00);
        Carbon::setTestNow();

        $this->assertNull($this->rates->rateAt($this->gold->id, $this->gold22k->id, 'sell', Carbon::parse('2026-10-02 23:59:00')));
    }

    public function test_no_rate_at_all_counts_as_stale(): void
    {
        $this->assertTrue($this->rates->isStale($this->gold->id, $this->gold22k->id, 'sell'));
    }

    public function test_a_fresh_rate_is_not_stale_but_an_old_one_is(): void
    {
        Carbon::setTestNow(now());
        $this->rates->recordFetchedRate($this->gold->id, $this->gold22k->id, 'sell', 20000.00);
        $this->assertFalse($this->rates->isStale($this->gold->id, $this->gold22k->id, 'sell', staleAfterHours: 12));

        Carbon::setTestNow(now()->addHours(13));
        $this->assertTrue($this->rates->isStale($this->gold->id, $this->gold22k->id, 'sell', staleAfterHours: 12));

        Carbon::setTestNow();
    }

    public function test_a_purity_with_no_rate_derives_one_from_a_basis_purity_and_flags_it(): void
    {
        $this->rates->recordManualRate($this->gold->id, $this->gold24k->id, 'sell', 24000.00);

        $derived = $this->rates->deriveRate(
            basisMetalTypeId: $this->gold->id,
            basisPurityId: $this->gold24k->id,
            rateType: 'sell',
            basisFinenessPercent: 99.90,
            targetFinenessPercent: 91.66,
        );

        $this->assertNotNull($derived);
        $this->assertTrue($derived['is_derived']);
        // 24000 * 91.66 / 99.90 = 22020.42042... -> rounds to 22020.42.
        $this->assertSame(22020.42, $derived['rate_per_gram']);
    }

    public function test_deriving_refuses_rather_than_guesses_when_the_basis_purity_has_no_rate_either(): void
    {
        $derived = $this->rates->deriveRate(
            basisMetalTypeId: $this->gold->id,
            basisPurityId: $this->gold24k->id,
            rateType: 'sell',
            basisFinenessPercent: 99.90,
            targetFinenessPercent: 91.66,
        );

        $this->assertNull($derived);
    }

    public function test_an_unknown_rate_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->rates->recordManualRate($this->gold->id, $this->gold22k->id, 'wholesale', 20000.00);
    }
}
