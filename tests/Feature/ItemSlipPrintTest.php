<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Item Entry's "Save & Print" / Edit's "Update & Print": saving always stays
 * on the same page (so a shopkeeper isn't bounced away — to Create for a new
 * item, to Edit for an existing one) and flashes the item back — with its
 * real, current database values — for the page to print in place. The
 * dedicated slip page/route (items.slip) stays available on its own for a
 * later reprint, carrying the shop identity, printer type, and configurable
 * print size from Settings.
 */
class ItemSlipPrintTest extends TestCase
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
        PartyType::create(['name' => 'Karigar']);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->purity->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 1,
            'cutting_loss_grams' => 0.5,
            'polish_weight_grams' => 0.2,
            'labour_cost' => 2000,
            'purchase_rate_per_gram' => 10000,
            'purchase_price' => 0,
            'source_party_id' => null,
            'date_received' => now()->toDateString(),
        ], $overrides);
    }

    public function test_a_plain_save_stays_on_the_create_page(): void
    {
        $response = $this->actingAs($this->user)->post('/items', $this->itemPayload());

        $response->assertRedirect(route('items.create'));

        $item = Item::firstOrFail();
        $response->assertSessionHas('success', "Item {$item->item_code} created successfully.");
    }

    public function test_save_and_print_flashes_the_saved_item_with_its_real_item_code(): void
    {
        $response = $this->actingAs($this->user)->post('/items', $this->itemPayload(['print_after_save' => true]));

        $response->assertRedirect(route('items.create'));

        $item = Item::firstOrFail();
        $response->assertSessionHas('createdItem', function (array $createdItem) use ($item) {
            return $createdItem['item_code'] === $item->item_code
                && $createdItem['metal_type']['name'] === 'Gold'
                && $createdItem['purity']['name'] === '22K';
        });
    }

    public function test_a_plain_update_stays_on_the_edit_page(): void
    {
        $item = Item::create($this->itemPayload([
            'item_code' => 'ITM-SLIP-001',
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
        ]));

        $payload = array_merge($item->only([
            'item_code', 'item_type', 'metal_type_id', 'purity_id',
            'gross_weight_grams', 'stone_weight_grams', 'cutting_loss_grams', 'polish_weight_grams',
            'labour_cost', 'purchase_rate_per_gram', 'purchase_price', 'status', 'source_party_id',
        ]), [
            'date_received' => $item->date_received->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->put("/items/{$item->id}", $payload);

        $response->assertRedirect(route('items.edit', $item));
        $response->assertSessionHas('success', "Item {$item->item_code} updated successfully.");
    }

    public function test_update_and_print_flashes_the_updated_item(): void
    {
        $item = Item::create($this->itemPayload([
            'item_code' => 'ITM-SLIP-001',
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
        ]));

        $payload = array_merge($item->only([
            'item_code', 'item_type', 'metal_type_id', 'purity_id',
            'gross_weight_grams', 'stone_weight_grams', 'cutting_loss_grams', 'polish_weight_grams',
            'labour_cost', 'purchase_rate_per_gram', 'purchase_price', 'status', 'source_party_id',
        ]), [
            'date_received' => $item->date_received->toDateString(),
            'labour_cost' => 2500,
            'print_after_save' => true,
        ]);

        $response = $this->actingAs($this->user)->put("/items/{$item->id}", $payload);

        $response->assertRedirect(route('items.edit', $item));
        $response->assertSessionHas('createdItem', function (array $createdItem) {
            return $createdItem['item_code'] === 'ITM-SLIP-001'
                && (float) $createdItem['labour_cost'] === 2500.0;
        });
    }

    public function test_the_slip_page_carries_shop_identity_printer_type_and_print_dimensions(): void
    {
        Setting::put('shop_name', 'Zar Test Jewellers');
        Setting::put('receipt_printer_type', 'thermal');
        Setting::put('item_slip_print_width_mm', '58');
        Setting::put('item_slip_print_padding_mm', '2');

        $item = Item::create($this->itemPayload([
            'item_code' => 'ITM-SLIP-002',
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
        ]));

        $response = $this->actingAs($this->user)->get("/items/{$item->id}/slip");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('items/slip')
            ->where('shop.name', 'Zar Test Jewellers')
            ->where('printerType', 'thermal')
            ->where('printDimensions.widthMm', 58)
            ->where('printDimensions.paddingMm', 2)
            ->where('item.item_code', 'ITM-SLIP-002')
        );
    }

    public function test_a_guest_cannot_view_the_slip_page(): void
    {
        $item = Item::create($this->itemPayload([
            'item_code' => 'ITM-SLIP-003',
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
        ]));

        $this->get("/items/{$item->id}/slip")->assertRedirect(route('login'));
    }
}
