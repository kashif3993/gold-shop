<?php

namespace Tests\Feature\MetalLedger;

use App\Models\MetalLedger\MetalLedgerMovement;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Services\MetalLedger\LedgerService;
use App\Services\MetalLedger\PurityConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private MetalType $gold;

    private Purity $gold22k;

    private Party $customer;

    private Party $otherShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new LedgerService(new PurityConversionService);
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->gold22k = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.66]);

        $customerType = PartyType::create(['name' => 'Customer']);
        $otherShopType = PartyType::create(['name' => 'Other Shop']);
        $this->customer = Party::create(['party_type_id' => $customerType->id, 'name' => 'Walk-in Customer', 'phone' => '0300']);
        $this->otherShop = Party::create(['party_type_id' => $otherShopType->id, 'name' => 'Neighbour Jewellers', 'phone' => '0301']);
    }

    /**
     * The brief's own "Done when" line: buying one tola from another
     * shopkeeper goes down the same path as a customer sale.
     */
    public function test_a_customer_sale_and_a_shop_to_shop_purchase_use_the_identical_method(): void
    {
        // Customer sale: metal leaves the shop.
        $sale = $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 183320.00,
            movementDate: '2026-10-15',
        );

        // Buying a tola off another shop: metal arrives at the shop. Same method, same shape.
        $purchase = $this->ledger->recordMovement(
            partyId: $this->otherShop->id,
            direction: 'in',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 11.664,
            ratePerGram: 19500.00,
            amount: 208456.85,
            movementDate: '2026-10-15',
            documentReference: 'SHOP-PURCHASE-0001',
        );

        $this->assertInstanceOf(MetalLedgerMovement::class, $sale);
        $this->assertInstanceOf(MetalLedgerMovement::class, $purchase);
        $this->assertSame('metal_ledger_movements', $sale->getTable());
        $this->assertSame($sale->getTable(), $purchase->getTable());
    }

    public function test_pure_weight_is_computed_from_the_purity_at_write_time(): void
    {
        $movement = $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 183320.00,
            movementDate: '2026-10-15',
        );

        // 10 * 91.66 / 100 = 9.166, matching Task 1's PurityConversionService.
        $this->assertSame('9.166', $movement->pure_weight_grams);
    }

    public function test_both_parties_balances_come_out_right_with_nothing_reconciled_by_hand(): void
    {
        // Shop sells to the customer (metal + money out).
        $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 183320.00,
            movementDate: '2026-10-15',
        );

        // Shop buys from another shop (metal + money in).
        $this->ledger->recordMovement(
            partyId: $this->otherShop->id,
            direction: 'in',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 11.664,
            ratePerGram: 19500.00,
            amount: 100000.00,
            movementDate: '2026-10-15',
        );

        // Customer received metal from the shop -> customer owes the shop (positive).
        $this->assertEqualsWithDelta(9.166, $this->ledger->metalBalance($this->customer->id, $this->gold->id), 0.001);
        $this->assertEqualsWithDelta(183320.00, $this->ledger->moneyBalance($this->customer->id), 0.01);

        // The shop received metal from the other shop -> the shop owes them (negative).
        $this->assertEqualsWithDelta(-10.691, $this->ledger->metalBalance($this->otherShop->id, $this->gold->id), 0.001);
        $this->assertEqualsWithDelta(-100000.00, $this->ledger->moneyBalance($this->otherShop->id), 0.01);
    }

    public function test_metal_and_money_balances_are_tracked_separately_and_never_netted(): void
    {
        // Metal goes out to the customer, but no money is recorded for it
        // (e.g. still on credit) -- the two must read independently.
        $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 0.0,
            movementDate: '2026-10-15',
        );

        $this->assertEqualsWithDelta(9.166, $this->ledger->metalBalance($this->customer->id, $this->gold->id), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->ledger->moneyBalance($this->customer->id), 0.01);
    }

    public function test_correcting_a_movement_keeps_the_original_and_the_balance_uses_only_the_correction(): void
    {
        $original = $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0, // cashier mis-weighed
            ratePerGram: 20000.00,
            amount: 183320.00,
            movementDate: '2026-10-15',
        );

        $corrected = $this->ledger->correctMovement(
            originalMovementId: $original->id,
            changes: ['weight_grams' => 11.0, 'amount' => 201652.00],
            notes: 'Re-weighed — original entry used the wrong scale reading.',
        );

        // The original is still there, completely unchanged.
        $this->assertDatabaseHas('metal_ledger_movements', [
            'id' => $original->id,
            'weight_grams' => '10.000',
            'amount' => '183320.00',
            'corrects_movement_id' => null,
        ]);

        $this->assertSame($original->id, $corrected->corrects_movement_id);

        // The balance reflects only the corrected figure -- not both added together.
        $this->assertEqualsWithDelta(10.083, $this->ledger->metalBalance($this->customer->id, $this->gold->id), 0.001);
        $this->assertEqualsWithDelta(201652.00, $this->ledger->moneyBalance($this->customer->id), 0.01);
    }

    public function test_correction_chain_returns_every_version_oldest_first(): void
    {
        $original = $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 183320.00,
            movementDate: '2026-10-15',
        );

        $firstFix = $this->ledger->correctMovement($original->id, ['weight_grams' => 11.0]);
        $secondFix = $this->ledger->correctMovement($firstFix->id, ['weight_grams' => 11.5]);

        $chain = $this->ledger->correctionChain($secondFix->id);

        $this->assertCount(3, $chain);
        $this->assertSame($original->id, $chain[0]->id);
        $this->assertSame($firstFix->id, $chain[1]->id);
        $this->assertSame($secondFix->id, $chain[2]->id);
    }

    /**
     * The brief's own "Done when" line: a party balance as at a past date
     * returns the same figure when asked again later, after more
     * movements have happened.
     */
    public function test_balance_as_of_a_past_date_is_stable_against_movements_added_afterward(): void
    {
        $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 183320.00,
            movementDate: '2026-10-01',
        );

        $balanceOnOctFirst = $this->ledger->metalBalance($this->customer->id, $this->gold->id, '2026-10-01');

        // A month and several more movements later...
        for ($i = 0; $i < 5; $i++) {
            $this->ledger->recordMovement(
                partyId: $this->customer->id,
                direction: 'out',
                metalTypeId: $this->gold->id,
                purityId: $this->gold22k->id,
                weightGrams: 2.0,
                ratePerGram: 20000.00,
                amount: 40000.00,
                movementDate: '2026-11-0'.($i + 1),
            );
        }

        $balanceOnOctFirstAgain = $this->ledger->metalBalance($this->customer->id, $this->gold->id, '2026-10-01');

        $this->assertSame($balanceOnOctFirst, $balanceOnOctFirstAgain);
        $this->assertEqualsWithDelta(9.166, $balanceOnOctFirstAgain, 0.001);
    }

    public function test_the_same_method_works_identically_for_a_karigar_as_for_any_other_party_type(): void
    {
        $karigarType = PartyType::create(['name' => 'Karigar']);
        $karigar = Party::create(['party_type_id' => $karigarType->id, 'name' => 'Master Imran', 'phone' => '0302']);

        // Raw gold issued to the karigar -- no special-casing needed, same recordMovement().
        $this->ledger->recordMovement(
            partyId: $karigar->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 50.0,
            ratePerGram: 20000.00,
            amount: 0.0, // no sale value -- this is raw material, not a sale
            movementDate: '2026-10-15',
            itemReference: 'KARIGAR-ISSUE',
        );

        // Holding 45.83g pure gold of the shop's -- shop is owed that metal.
        $this->assertEqualsWithDelta(45.83, $this->ledger->metalBalance($karigar->id, $this->gold->id), 0.001);
    }

    public function test_an_unknown_direction_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'sideways',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: 10.0,
            ratePerGram: 20000.00,
            amount: 0.0,
            movementDate: '2026-10-15',
        );
    }
}
