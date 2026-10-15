<?php

namespace Tests\Feature\MetalLedger;

use App\Models\MetalLedger\MetalLedgerBuyBackDeduction;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Services\MetalLedger\BuyBackValuationService;
use App\Services\MetalLedger\LedgerService;
use App\Services\MetalLedger\PurityConversionService;
use App\Services\MetalLedger\RateBookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class BuyBackValuationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BuyBackValuationService $buyBack;

    private RateBookService $rateBook;

    private LedgerService $ledger;

    private MetalType $gold;

    private Purity $gold22k;

    private Party $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateBook = new RateBookService;
        $this->ledger = new LedgerService(new PurityConversionService);
        $this->buyBack = new BuyBackValuationService($this->rateBook, $this->ledger);

        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->gold22k = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.66]);

        $customerType = PartyType::create(['name' => 'Customer']);
        $this->customer = Party::create(['party_type_id' => $customerType->id, 'name' => 'Walk-in Customer', 'phone' => '0300']);
    }

    public function test_valuate_applies_todays_buying_rate_and_the_default_deduction(): void
    {
        $this->rateBook->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);

        $valuation = $this->buyBack->valuate($this->gold->id, $this->gold22k->id, 11.6638038);

        $this->assertSame(19500.00, $valuation['rate_per_gram']);
        $this->assertSame(2.50, $valuation['deduction_percent']);
        $this->assertSame(0.292, $valuation['deduction_grams']);
        $this->assertSame(11.372, $valuation['effective_weight_grams']);
        $this->assertSame(221754.0, $valuation['amount']);
        $this->assertStringContainsString('11.664g', $valuation['explanation']);
    }

    public function test_valuate_refuses_rather_than_guesses_with_no_current_buying_rate(): void
    {
        $this->expectException(RuntimeException::class);

        $this->buyBack->valuate($this->gold->id, $this->gold22k->id, 10.0);
    }

    public function test_deduction_cascades_exact_purity_then_metal_wide_then_shop_default(): void
    {
        $this->rateBook->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);

        // Nothing configured -> hard fallback (2.5%).
        $this->assertSame(2.50, $this->buyBack->resolveDeductionPercent($this->gold->id, $this->gold22k->id));

        // Shop-wide default.
        MetalLedgerBuyBackDeduction::create(['deduction_percent' => 3.00, 'is_shop_default' => true]);
        $this->assertSame(3.00, $this->buyBack->resolveDeductionPercent($this->gold->id, $this->gold22k->id));

        // Metal-wide beats the shop default.
        MetalLedgerBuyBackDeduction::create(['metal_type_id' => $this->gold->id, 'deduction_percent' => 4.00]);
        $this->assertSame(4.00, $this->buyBack->resolveDeductionPercent($this->gold->id, $this->gold22k->id));

        // An exact metal+purity row beats the metal-wide row.
        MetalLedgerBuyBackDeduction::create(['metal_type_id' => $this->gold->id, 'purity_id' => $this->gold22k->id, 'deduction_percent' => 1.75]);
        $this->assertSame(1.75, $this->buyBack->resolveDeductionPercent($this->gold->id, $this->gold22k->id));
    }

    public function test_record_buy_back_goes_on_the_ledger_as_direction_in_for_the_customer(): void
    {
        $this->rateBook->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);

        $movement = $this->buyBack->recordBuyBack(
            partyId: $this->customer->id,
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            reweighedGrams: 11.6638038,
            movementDate: '2026-10-15',
            documentReference: 'BB-0001',
        );

        $this->assertSame('in', $movement->direction);
        $this->assertSame($this->customer->id, $movement->party_id);
        $this->assertSame('221754.00', $movement->amount);
        $this->assertSame('19500.00', $movement->rate_per_gram);
    }

    /**
     * The brief's own "Done when" line: the identical one-tola item brought
     * back the same day values below what the customer paid.
     */
    public function test_the_identical_item_brought_back_same_day_values_below_what_the_customer_paid(): void
    {
        $weight = 11.6638038; // 1 tola

        // The original sale, this afternoon, through the ordinary ledger path.
        $sale = $this->ledger->recordMovement(
            partyId: $this->customer->id,
            direction: 'out',
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            weightGrams: $weight,
            ratePerGram: 20500.00,
            amount: 239107.98,
            movementDate: '2026-10-15',
            documentReference: 'INV-1234',
        );

        // Same afternoon, same physical weight, brought straight back.
        $this->rateBook->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);
        $buyBack = $this->buyBack->recordBuyBack(
            partyId: $this->customer->id,
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            reweighedGrams: $weight,
            movementDate: '2026-10-15',
            provenanceReference: $sale->document_reference,
        );

        $this->assertLessThan((float) $sale->amount, (float) $buyBack->amount);
        $this->assertSame('221754.00', $buyBack->amount);
    }

    /**
     * G-3, enforced structurally, not by convention: valuate() and
     * recordBuyBack() simply have no parameter an original sale's price or
     * weight could be passed through — so there is nothing to disable,
     * override, or forget to check. Reflection proves the signatures
     * themselves carry no such parameter.
     */
    public function test_no_method_on_this_service_accepts_an_original_sale_price_or_weight(): void
    {
        $suspectNames = ['original', 'sale_price', 'salePrice', 'saleWeight', 'sale_weight', 'original_amount', 'originalAmount'];

        foreach (['valuate', 'recordBuyBack'] as $method) {
            $reflection = new \ReflectionMethod(BuyBackValuationService::class, $method);

            foreach ($reflection->getParameters() as $parameter) {
                foreach ($suspectNames as $suspect) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $suspect,
                        $parameter->getName(),
                        "{$method}() must not accept a parameter resembling an original sale figure (found: {$parameter->getName()})."
                    );
                }
            }
        }
    }

    public function test_a_provenance_reference_never_changes_the_amount(): void
    {
        $this->rateBook->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);

        $without = $this->buyBack->recordBuyBack(
            partyId: $this->customer->id,
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            reweighedGrams: 10.0,
            movementDate: '2026-10-15',
        );

        $with = $this->buyBack->recordBuyBack(
            partyId: $this->customer->id,
            metalTypeId: $this->gold->id,
            purityId: $this->gold22k->id,
            reweighedGrams: 10.0,
            movementDate: '2026-10-15',
            provenanceReference: 'INV-9999 (an expensive original sale)',
        );

        $this->assertSame($without->amount, $with->amount);
        $this->assertStringContainsString('provenance only, not used in valuation', $with->notes);
    }

    public function test_reweighed_grams_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buyBack->valuate($this->gold->id, $this->gold22k->id, 0.0);
    }

    public function test_an_out_of_range_deduction_override_is_rejected(): void
    {
        $this->rateBook->recordManualRate($this->gold->id, $this->gold22k->id, 'buy', 19500.00);

        $this->expectException(InvalidArgumentException::class);

        $this->buyBack->valuate($this->gold->id, $this->gold22k->id, 10.0, deductionPercentOverride: 150.0);
    }
}
