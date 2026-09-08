<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class POSTransactionTest extends TestCase
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
    private TransactionType $exchangeTransType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->customerPartyType = PartyType::create(['name' => 'Customer']);
        PartyType::create(['name' => 'Karigar']);

        $this->saleTransType = TransactionType::create(['name' => 'sale']);
        $this->exchangeTransType = TransactionType::create(['name' => 'old_gold_exchange']);

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
    }

    private function createInStockItem(array $attributes = []): Item
    {
        $gross = $attributes['gross_weight_grams'] ?? 10.0;
        $stone = $attributes['stone_weight_grams'] ?? 0.0;
        $cutting = $attributes['cutting_loss_grams'] ?? 0.0;
        $net = $gross - $stone - $cutting;

        return Item::create(array_merge([
            'item_code' => 'ITM-' . strtoupper(uniqid()),
            'item_type' => 'ring',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'gross_weight_grams' => $gross,
            'stone_weight_grams' => $stone,
            'cutting_loss_grams' => $cutting,
            'net_weight_grams' => $net,
            'labour_cost' => 1500,
            'polish_cost' => 300,
            'purchase_rate_per_gram' => 20000,
            'purchase_price' => ($net * 20000) + 1500 + 300,
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
            'date_received' => now()->toDateString(),
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_create_pos_transaction(): void
    {
        $response = $this->postJson('/api/v1/pos/transaction', []);
        $response->assertStatus(401);
    }

    public function test_successful_multi_item_sale_with_flat_discount_and_walk_in_customer(): void
    {
        // 3 items in stock
        // Item 1: Net = 10g, Labour = 2000, Polish = 500. Rate = 25000/g => Metal = 250,000, Line = 252,500
        $item1 = $this->createInStockItem([
            'gross_weight_grams' => 10,
            'labour_cost' => 2000,
            'polish_cost' => 500,
        ]);

        // Item 2: Net = 5g, Labour = 1000, Polish = 200. Rate = 25000/g => Metal = 125,000, Line = 126,200
        $item2 = $this->createInStockItem([
            'gross_weight_grams' => 5,
            'labour_cost' => 1000,
            'polish_cost' => 200,
        ]);

        // Item 3: Net = 8g, Labour = 1500, Polish = 300. Rate = 25000/g => Metal = 200,000, Line = 201,800
        $item3 = $this->createInStockItem([
            'gross_weight_grams' => 8,
            'labour_cost' => 1500,
            'polish_cost' => 300,
        ]);

        // Totals:
        // Total Metal = 250,000 + 125,000 + 200,000 = 575,000
        // Total Labour = 2,000 + 1,000 + 1,500 = 4,500
        // Total Polish = 500 + 200 + 300 = 1,000
        // Subtotal = 580,500
        // Flat discount = 5,500 => Grand Total = 575,000

        $payload = [
            'goldRate' => 25000,
            'discount' => 5500,
            'discount_reason' => 'VIP Customer discount',
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [
                ['id' => $item1->id],
                ['id' => $item2->id],
                ['id' => $item3->id],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        // Assert Invoice created correctly
        $this->assertDatabaseHas('invoices', [
            'total_metal_cost' => 575000.00,
            'total_labour_cost' => 4500.00,
            'total_polish_cost' => 1000.00,
            'total_discount' => 5500.00,
            'discount_reason' => 'VIP Customer discount',
            'total_exchange_deduction' => 0.00,
            'grand_total' => 575000.00,
            'payment_method' => 'cash',
            'created_by_user_id' => $this->user->id,
        ]);

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);

        // Assert items marked as sold
        $this->assertEquals('sold', $item1->fresh()->status);
        $this->assertEquals('sold', $item2->fresh()->status);
        $this->assertEquals('sold', $item3->fresh()->status);

        // Assert 3 InvoiceLineItems created
        $this->assertCount(3, $invoice->lineItems);
        $this->assertDatabaseHas('invoice_line_items', [
            'invoice_id' => $invoice->id,
            'item_id' => $item1->id,
            'weight_grams' => 10.0,
            'rate_per_gram' => 25000.00,
            'metal_cost' => 250000.00,
            'labour_cost' => 2000.00,
            'polish_cost' => 500.00,
            'line_total' => 252500.00,
        ]);

        // Assert 3 Transactions created
        $this->assertEquals(3, Transaction::where('invoice_id', $invoice->id)->count());
        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'item_id' => $item1->id,
            'transaction_type_id' => $this->saleTransType->id,
            'direction' => 'OUT',
            'total_amount' => 252500.00,
            'payment_method' => 'cash',
        ]);
    }

    public function test_successful_multi_item_sale_with_percentage_discount_and_named_customer(): void
    {
        $item1 = $this->createInStockItem([
            'gross_weight_grams' => 4,
            'labour_cost' => 1000,
            'polish_cost' => 200,
        ]);
        $item2 = $this->createInStockItem([
            'gross_weight_grams' => 6,
            'labour_cost' => 1500,
            'polish_cost' => 300,
        ]);

        // Gold Rate: 20000/g
        // Item 1: 4g * 20000 = 80000 + 1000 + 200 = 81200
        // Item 2: 6g * 20000 = 120000 + 1500 + 300 = 121800
        // Subtotal = 203000
        // 10% Discount = 20300
        // Grand Total = 182700

        $payload = [
            'customer_name' => 'Ahmed Khan',
            'customer_phone' => '+923001234567',
            'goldRate' => 20000,
            'discount' => 10, // 10%
            'discountType' => 'percentage',
            'discount_reason' => 'Eid special promo',
            'paymentMethod' => 'Card',
            'items' => [
                ['id' => $item1->id],
                ['id' => $item2->id],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        $response->assertStatus(201);

        // Assert customer Party was created
        $party = Party::where('phone', '+923001234567')->first();
        $this->assertNotNull($party);
        $this->assertEquals('Ahmed Khan', $party->name);

        $this->assertDatabaseHas('invoices', [
            'party_id' => $party->id,
            'total_metal_cost' => 200000.00,
            'total_labour_cost' => 2500.00,
            'total_polish_cost' => 500.00,
            'total_discount' => 20300.00,
            'grand_total' => 182700.00,
            'payment_method' => 'card',
        ]);
    }

    public function test_sale_with_old_gold_exchange_deduction(): void
    {
        $item = $this->createInStockItem([
            'gross_weight_grams' => 10,
            'labour_cost' => 2000,
            'polish_cost' => 500,
        ]);

        // Item total @ 25000 rate = 250,000 + 2000 + 500 = 252,500
        // Exchange scrap: 5g @ 25000 with 10% deduction => 5g * (1 - 0.10) * 25000 = 4.5g * 25000 = 112,500
        // Grand Total = 252,500 - 112,500 = 140,000

        $payload = [
            'customer_name' => 'Sara Ali',
            'customer_phone' => '03111222333',
            'goldRate' => 25000,
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Transfer',
            'items' => [
                ['id' => $item->id],
            ],
            'exchanges' => [
                [
                    'metal_type_id' => $this->goldMetal->id,
                    'purity_id' => $this->purity22k->id,
                    'weight_grams' => 5.0,
                    'deduction_percent' => 10,
                    'valuation' => 112500,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        $response->assertStatus(201);

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(112500.00, $invoice->total_exchange_deduction);
        $this->assertEquals(140000.00, $invoice->grand_total);

        // Verify exchange scrap item created with bought_back status
        $this->assertDatabaseHas('items', [
            'item_type' => 'old_gold',
            'gross_weight_grams' => 5.0,
            'purchase_price' => 112500.00,
            'status' => 'bought_back',
        ]);

        // Verify exchange transaction created with direction IN
        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'transaction_type_id' => $this->exchangeTransType->id,
            'direction' => 'IN',
            'weight_grams' => 5.0,
            'deduction_percent_applied' => 10.00,
            'total_amount' => 112500.00,
        ]);
    }

    public function test_validation_failure_when_items_and_exchanges_are_both_missing(): void
    {
        $payload = [
            'goldRate' => 25000,
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_validation_failure_for_invalid_payment_method_and_gold_rate(): void
    {
        $item = $this->createInStockItem();

        $payload = [
            'goldRate' => -500, // Invalid negative rate
            'discount' => -10, // Invalid negative discount
            'discountType' => 'invalid_type',
            'paymentMethod' => 'Bitcoin', // Invalid method
            'items' => [
                ['id' => $item->id],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'goldRate',
            'discount',
            'discountType',
            'paymentMethod',
        ]);
    }

    public function test_validation_failure_when_cart_item_does_not_exist(): void
    {
        $payload = [
            'goldRate' => 25000,
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [
                ['id' => 999999],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.id']);
    }

    public function test_rejection_and_rollback_when_item_is_already_sold(): void
    {
        $inStockItem = $this->createInStockItem(['gross_weight_grams' => 5]);
        $soldItem = $this->createInStockItem([
            'gross_weight_grams' => 8,
            'status' => 'sold',
        ]);

        $payload = [
            'goldRate' => 25000,
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [
                ['id' => $inStockItem->id],
                ['id' => $soldItem->id],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);

        // Expect 400 Bad Request due to already sold item
        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);

        // Confirm database atomicity: NO invoice or transactions created, inStockItem remains in_stock
        $this->assertEquals(0, Invoice::count());
        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(0, InvoiceLineItem::count());
        $this->assertEquals('in_stock', $inStockItem->fresh()->status);
    }

    /**
     * DAY 15 CHECKPOINT: invoice totals must match the line items exactly.
     * Uses a fractional rate + fractional weights + percentage discount + an
     * exchange so every rounding path is exercised.
     */
    public function test_invoice_totals_reconcile_exactly_with_line_items(): void
    {
        $rate = 19850.33;

        $item1 = $this->createInStockItem(['gross_weight_grams' => 3.337, 'labour_cost' => 1234.50, 'polish_cost' => 210.75]);
        $item2 = $this->createInStockItem(['gross_weight_grams' => 7.771, 'labour_cost' => 999.00, 'polish_cost' => 0.00]);
        $item3 = $this->createInStockItem(['gross_weight_grams' => 1.119, 'labour_cost' => 480.25, 'polish_cost' => 66.60]);

        $payload = [
            'goldRate' => $rate,
            'discount' => 7.5, // percent
            'discountType' => 'percentage',
            'discount_reason' => 'Loyalty',
            'paymentMethod' => 'Cash',
            'items' => [['id' => $item1->id], ['id' => $item2->id], ['id' => $item3->id]],
            'exchanges' => [[
                'metal_type_id' => $this->goldMetal->id,
                'purity_id' => $this->purity22k->id,
                'weight_grams' => 4.213,
                'deduction_percent' => 8,
                'valuation' => 76543.21,
            ]],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload);
        $response->assertStatus(201);

        $invoice = Invoice::with('lineItems')->first();
        $lines = $invoice->lineItems;
        $this->assertCount(3, $lines);

        // Each line's own total is internally consistent.
        foreach ($lines as $line) {
            $this->assertEqualsWithDelta(
                (float) $line->metal_cost + (float) $line->labour_cost + (float) $line->polish_cost,
                (float) $line->line_total,
                0.001,
            );
        }

        // Invoice component totals == sum of the stored line items, to the cent.
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->metal_cost), (float) $invoice->total_metal_cost, 0.001);
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->labour_cost), (float) $invoice->total_labour_cost, 0.001);
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->polish_cost), (float) $invoice->total_polish_cost, 0.001);

        $subtotal = (float) $invoice->total_metal_cost + (float) $invoice->total_labour_cost + (float) $invoice->total_polish_cost;
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->line_total), $subtotal, 0.001);

        // Grand total == subtotal - discount - exchange deduction.
        $this->assertEqualsWithDelta(
            $subtotal - (float) $invoice->total_discount - (float) $invoice->total_exchange_deduction,
            (float) $invoice->grand_total,
            0.001,
        );

        // Every sale transaction mirrors its line item.
        foreach ($lines as $line) {
            $this->assertDatabaseHas('transactions', [
                'id' => $line->transaction_id,
                'invoice_id' => $invoice->id,
                'total_amount' => $line->line_total,
            ]);
        }
    }
}
