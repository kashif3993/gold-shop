<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MetalType $gold;
    private Purity $purity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->purity = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6, 'is_active' => true]);
        TransactionType::create(['name' => 'sale']);
        PartyType::create(['name' => 'Customer']);
    }

    private function makeItem(array $overrides = []): Item
    {
        return Item::create(array_merge([
            'item_code' => 'ITM-' . strtoupper(uniqid()),
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->purity->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'labour_cost' => 1500,
            'polish_cost' => 300,
            'purchase_rate_per_gram' => 20000,
            'purchase_price' => 201800,
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
            'date_received' => now()->toDateString(),
        ], $overrides));
    }

    /* ── the shared service itself ─────────────────────────────────── */

    public function test_log_always_writes_a_row(): void
    {
        $this->actingAs($this->user);

        app(AuditLogger::class)->log('item_price', 1, 'purchase_price', '100', '100', 'no change, logged anyway');

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'item_price',
            'field_name' => 'purchase_price',
            'old_value' => '100',
            'new_value' => '100',
            'changed_by_user_id' => $this->user->id,
        ]);
    }

    public function test_log_if_changed_skips_when_the_value_is_the_same(): void
    {
        $this->actingAs($this->user);

        $result = app(AuditLogger::class)->logIfChanged('item_price', 1, 'purchase_price', '100.00', 100.00, 'no-op');

        $this->assertNull($result);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_log_if_changed_writes_when_the_value_differs(): void
    {
        $this->actingAs($this->user);

        app(AuditLogger::class)->logIfChanged('item_price', 1, 'purchase_price', '100.00', '150.00', 'corrected a typo');

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'item_price',
            'field_name' => 'purchase_price',
            'old_value' => '100.00',
            'new_value' => '150.00',
            'reason' => 'corrected a typo',
        ]);
    }

    /* ── item price / weight overrides ───────────────────────────────── */

    public function test_editing_an_items_price_writes_an_audit_row(): void
    {
        $item = $this->makeItem(['purchase_rate_per_gram' => 20000]);

        $payload = array_merge($item->only([
            'item_code', 'item_type', 'metal_type_id', 'purity_id',
            'gross_weight_grams', 'stone_weight_grams', 'cutting_loss_grams',
            'labour_cost', 'polish_cost', 'purchase_price', 'status', 'source_party_id',
        ]), [
            'date_received' => $item->date_received->toDateString(),
            'purchase_rate_per_gram' => 22000,
            'edit_reason' => 'Rate was mistyped at entry',
        ]);

        $this->actingAs($this->user)->put("/items/{$item->id}", $payload)->assertRedirect();

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'item_price',
            'entity_id' => $item->id,
            'field_name' => 'purchase_rate_per_gram',
            'old_value' => '20000.00',
            'new_value' => '22000.00',
            'reason' => 'Rate was mistyped at entry',
        ]);
    }

    public function test_editing_an_items_weight_writes_an_audit_row_with_a_default_reason(): void
    {
        $item = $this->makeItem(['gross_weight_grams' => 10]);

        $payload = array_merge($item->only([
            'item_code', 'item_type', 'metal_type_id', 'purity_id',
            'stone_weight_grams', 'cutting_loss_grams',
            'labour_cost', 'polish_cost', 'purchase_rate_per_gram', 'purchase_price', 'status', 'source_party_id',
        ]), [
            'date_received' => $item->date_received->toDateString(),
            'gross_weight_grams' => 11.5,
        ]);

        $this->actingAs($this->user)->put("/items/{$item->id}", $payload)->assertRedirect();

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'transaction_weight',
            'entity_id' => $item->id,
            'field_name' => 'gross_weight_grams',
            'old_value' => '10.000',
            'new_value' => '11.500',
            'reason' => 'Item details updated via Edit screen',
        ]);
    }

    public function test_editing_an_item_without_touching_price_or_weight_writes_nothing(): void
    {
        $item = $this->makeItem();

        $payload = array_merge($item->only([
            'item_code', 'metal_type_id', 'purity_id',
            'gross_weight_grams', 'stone_weight_grams', 'cutting_loss_grams',
            'labour_cost', 'polish_cost', 'purchase_rate_per_gram', 'purchase_price', 'status', 'source_party_id',
        ]), [
            'date_received' => $item->date_received->toDateString(),
            'item_type' => 'bangle', // changed, but not a protected field
        ]);

        $this->actingAs($this->user)->put("/items/{$item->id}", $payload)->assertRedirect();

        $this->assertDatabaseCount('audit_log', 0);
    }

    /* ── POS discount ─────────────────────────────────────────────── */

    public function test_a_discounted_sale_writes_an_audit_row(): void
    {
        $item = $this->makeItem();

        $payload = [
            'goldRate' => 25000,
            'discount' => 5500,
            'discount_reason' => 'VIP customer',
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [['id' => $item->id]],
        ];

        $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload)->assertStatus(201);

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'discount',
            'field_name' => 'total_discount',
            'new_value' => '5500',
            'reason' => 'VIP customer',
        ]);
    }

    public function test_a_sale_with_no_discount_writes_nothing(): void
    {
        $item = $this->makeItem();

        $payload = [
            'goldRate' => 25000,
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [['id' => $item->id]],
        ];

        $this->actingAs($this->user)->postJson('/api/v1/pos/transaction', $payload)->assertStatus(201);

        $this->assertDatabaseCount('audit_log', 0);
    }
}
