<?php

namespace Tests\Feature;

use App\Models\BuybackDeductionSetting;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\BuybackDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyBackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MetalType $goldMetal;
    private MetalType $silverMetal;
    private Purity $purity22k;
    private Purity $purity24k;
    private Purity $silverPurity;
    private PartyType $customerPartyType;
    private TransactionType $saleTransType;
    private TransactionType $buybackTransType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->customerPartyType = PartyType::create(['name' => 'Customer']);
        PartyType::create(['name' => 'Karigar']);

        $this->saleTransType = TransactionType::create(['name' => 'sale']);
        $this->buybackTransType = TransactionType::create(['name' => 'buy_back']);

        $this->goldMetal = MetalType::create(['name' => 'Gold']);
        $this->silverMetal = MetalType::create(['name' => 'Silver']);

        $this->purity22k = Purity::create([
            'metal_type_id' => $this->goldMetal->id,
            'name' => '22K',
            'fineness_percent' => 91.6,
        ]);

        $this->purity24k = Purity::create([
            'metal_type_id' => $this->goldMetal->id,
            'name' => '24K',
            'fineness_percent' => 99.9,
        ]);

        $this->silverPurity = Purity::create([
            'metal_type_id' => $this->silverMetal->id,
            'name' => 'Fine Silver',
            'fineness_percent' => 99.9,
        ]);

        // Default shop deduction: 2.50%
        BuybackDeductionSetting::create([
            'metal_type_id' => null,
            'purity_id' => null,
            'deduction_percent' => 2.50,
            'is_shop_default' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_buyback_endpoints(): void
    {
        $this->getJson('/api/v1/buyback/deduction')->assertStatus(401);
        $this->postJson('/api/v1/buyback', [])->assertStatus(401);
    }

    public function test_deduction_resolution_cascade_hierarchy(): void
    {
        $service = app(BuybackDeductionService::class);

        // 1. Initially only shop default exists (2.50%)
        $this->assertEquals(2.50, $service->resolveDeductionPercent($this->purity22k->id, $this->goldMetal->id));

        // 2. Add metal-level override for Gold (3.00%)
        BuybackDeductionSetting::create([
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => null,
            'deduction_percent' => 3.00,
            'is_shop_default' => false,
        ]);

        // Gold purities without specific override should now get 3.00%, silver still gets shop default 2.50%
        $this->assertEquals(3.00, $service->resolveDeductionPercent($this->purity22k->id, $this->goldMetal->id));
        $this->assertEquals(2.50, $service->resolveDeductionPercent($this->silverPurity->id, $this->silverMetal->id));

        // 3. Add purity-specific override for 22K (4.50%)
        BuybackDeductionSetting::create([
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'deduction_percent' => 4.50,
            'is_shop_default' => false,
        ]);

        // 22K should resolve to 4.50%, 24K gets gold metal-level 3.00%, silver gets shop default 2.50%
        $this->assertEquals(4.50, $service->resolveDeductionPercent($this->purity22k->id, $this->goldMetal->id));
        $this->assertEquals(3.00, $service->resolveDeductionPercent($this->purity24k->id, $this->goldMetal->id));
        $this->assertEquals(2.50, $service->resolveDeductionPercent($this->silverPurity->id, $this->silverMetal->id));
    }

    public function test_buyback_endpoint_calculates_valuation_strictly_from_reweighed_weight_and_rate(): void
    {
        // Re-weighed weight: 10.0g
        // Current gold rate: 20000/g
        // Deduction resolved: 2.5%
        // Effective weight: 10.0 * 0.975 = 9.75g
        // Expected total: 9.75 * 20000 = 195,000.00
        // Labour and Polish: MUST BE 0.00

        $payload = [
            'customer_name' => 'Tariq Mehmood',
            'customer_phone' => '03001234567',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'weight_grams' => 10.0,
            'rate_per_gram' => 20000,
            'payment_method' => 'Cash',
            'notes' => 'Old gold bangle buy-back',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/buyback', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'valuation' => [
                'gross_weight_grams' => 10.0,
                'deduction_percent' => 2.5,
                'effective_weight_grams' => 9.75,
                'rate_per_gram' => 20000,
                'total_amount' => 195000.00,
            ],
        ]);

        // Check Transaction created with direction IN and ZERO labour/polish
        $this->assertDatabaseHas('transactions', [
            'transaction_type_id' => $this->buybackTransType->id,
            'direction' => 'IN',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'weight_grams' => 10.0,
            'rate_per_gram' => 20000.00,
            'metal_cost' => 195000.00,
            'labour_cost' => 0.00,
            'polish_cost' => 0.00,
            'tax_amount' => 0.00,
            'discount_amount' => 0.00,
            'deduction_percent_applied' => 2.50,
            'total_amount' => 195000.00,
            'payment_method' => 'cash',
        ]);

        // Check Item created with bought_back status
        $this->assertDatabaseHas('items', [
            'item_type' => 'old_gold',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'gross_weight_grams' => 10.0,
            'net_weight_grams' => 9.75,
            'purchase_rate_per_gram' => 20000.00,
            'purchase_price' => 195000.00,
            'status' => 'bought_back',
        ]);
    }

    /**
     * DAY 17 CRITICAL REGRESSION TEST:
     * Assert buy-back value is fully independent of any linked original sale,
     * and that the original sale record in the database remains completely untouched.
     */
    public function test_buyback_value_is_strictly_independent_of_original_sale_and_original_sale_row_is_untouched(): void
    {
        $customer = Party::create([
            'name' => 'Original Buyer',
            'phone' => '03331112233',
            'party_type_id' => $this->customerPartyType->id,
        ]);

        // 1. Create original item that was sold previously
        $item = Item::create([
            'item_code' => 'RING-999',
            'item_type' => 'ring',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'gross_weight_grams' => 10.0,
            'stone_weight_grams' => 0.0,
            'cutting_loss_grams' => 0.0,
            'net_weight_grams' => 10.0,
            'labour_cost' => 5000.00,
            'polish_cost' => 1000.00,
            'purchase_rate_per_gram' => 18000.00,
            'purchase_price' => 186000.00,
            'status' => 'sold',
            'created_by_user_id' => $this->user->id,
            'date_received' => now()->subMonths(6)->toDateString(),
        ]);

        // Original Invoice: Sold at rate 20,000/g => Metal: 200,000 + Labour: 5,000 + Polish: 1,000 = 206,000
        $originalInvoice = Invoice::create([
            'invoice_number' => 'INV-20260101-0001',
            'party_id' => $customer->id,
            'invoice_date' => now()->subMonths(6)->toDateString(),
            'total_metal_cost' => 200000.00,
            'total_labour_cost' => 5000.00,
            'total_polish_cost' => 1000.00,
            'total_tax' => 0.00,
            'total_discount' => 0.00,
            'total_exchange_deduction' => 0.00,
            'grand_total' => 206000.00,
            'payment_method' => 'cash',
            'created_by_user_id' => $this->user->id,
        ]);

        $originalSaleTxn = Transaction::create([
            'party_id' => $customer->id,
            'transaction_type_id' => $this->saleTransType->id,
            'direction' => 'OUT',
            'item_id' => $item->id,
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'weight_grams' => 10.0,
            'rate_per_gram' => 20000.00,
            'metal_cost' => 200000.00,
            'labour_cost' => 5000.00,
            'polish_cost' => 1000.00,
            'tax_amount' => 0.00,
            'discount_amount' => 0.00,
            'deduction_percent_applied' => 0.00,
            'total_amount' => 206000.00,
            'invoice_id' => $originalInvoice->id,
            'payment_method' => 'cash',
            'transaction_date' => now()->subMonths(6)->toDateString(),
            'created_by_user_id' => $this->user->id,
        ]);

        // 2. Perform Buy-Back today:
        // Re-weighed at: 9.20g (wear/tear after 6 months)
        // Today's Gold rate: 26,000/g
        // Purity: 22K (deduction resolved: 2.50%)
        // Effective Weight: 9.20 * (1 - 0.025) = 8.970g
        // Buy-back Value: 8.970 * 26,000 = 233,220.00
        // Notice: 233,220 is completely different from the original sale price 206,000!

        $buybackPayload = [
            'party_id' => $customer->id,
            'item_id' => $item->id,
            'original_sale_transaction_id' => $originalSaleTxn->id,
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'weight_grams' => 9.20,
            'rate_per_gram' => 26000,
            'payment_method' => 'Bank_Transfer',
            'notes' => 'Customer brought back ring after 6 months',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/buyback', $buybackPayload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'valuation' => [
                'gross_weight_grams' => 9.20,
                'deduction_percent' => 2.5,
                'effective_weight_grams' => 8.97,
                'rate_per_gram' => 26000,
                'total_amount' => 233220.00,
            ],
        ]);

        // 3. ASSERTION: Original sale transaction is completely UNTOUCHED
        $originalSaleTxn->refresh();
        $this->assertEquals(206000.00, $originalSaleTxn->total_amount);
        $this->assertEquals(200000.00, $originalSaleTxn->metal_cost);
        $this->assertEquals(5000.00, $originalSaleTxn->labour_cost);
        $this->assertEquals(1000.00, $originalSaleTxn->polish_cost);
        $this->assertEquals('OUT', $originalSaleTxn->direction);
        $this->assertEquals(10.0, $originalSaleTxn->weight_grams);
        $this->assertEquals(20000.00, $originalSaleTxn->rate_per_gram);

        // 4. ASSERTION: Original invoice is completely UNTOUCHED
        $originalInvoice->refresh();
        $this->assertEquals(206000.00, $originalInvoice->grand_total);

        // 5. ASSERTION: Buy-back transaction has its own independent record linked by reference
        $buybackTxn = Transaction::where('transaction_type_id', $this->buybackTransType->id)->first();
        $this->assertNotNull($buybackTxn);
        $this->assertEquals('IN', $buybackTxn->direction);
        $this->assertEquals(9.20, $buybackTxn->weight_grams);
        $this->assertEquals(26000.00, $buybackTxn->rate_per_gram);
        $this->assertEquals(233220.00, $buybackTxn->total_amount);
        $this->assertEquals(0.00, $buybackTxn->labour_cost);
        $this->assertEquals(0.00, $buybackTxn->polish_cost);
        $this->assertEquals($originalSaleTxn->id, $buybackTxn->original_sale_transaction_id);
        $this->assertEquals('bank_transfer', $buybackTxn->payment_method);

        // Item status updated to bought_back
        $this->assertEquals('bought_back', $item->fresh()->status);
    }

    public function test_lookup_original_sale_returns_reference_only_details(): void
    {
        $item = Item::create([
            'item_code' => 'TEST-LOOKUP-01',
            'item_type' => 'necklace',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'gross_weight_grams' => 15.0,
            'net_weight_grams' => 15.0,
            'purchase_rate_per_gram' => 18000.00,
            'purchase_price' => 270000.00,
            'status' => 'sold',
            'created_by_user_id' => $this->user->id,
            'date_received' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/buyback/lookup-sale?query=TEST-LOOKUP-01');

        $response->assertStatus(200);
        $response->assertJson([
            'reference_only' => true,
        ]);
        $this->assertStringContainsString('reference only', $response->json('disclaimer'));
    }

    public function test_validation_rejects_invalid_weights_and_rates(): void
    {
        $payload = [
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'weight_grams' => 0, // Invalid zero weight
            'rate_per_gram' => -100, // Invalid negative rate
            'payment_method' => 'InvalidMethod',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/buyback', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'weight_grams',
            'rate_per_gram',
            'payment_method',
        ]);
    }
}
