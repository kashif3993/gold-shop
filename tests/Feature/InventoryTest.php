<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MetalType $gold;
    private Purity $p22;
    private Party $karigar;
    private Party $wholesaler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->p22 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6]);

        $karigarType = PartyType::create(['name' => 'Karigar']);
        $wholesalerType = PartyType::create(['name' => 'Wholesaler']);
        $this->karigar = Party::create(['name' => 'Ustad Bashir', 'party_type_id' => $karigarType->id]);
        $this->wholesaler = Party::create(['name' => 'Al-Madina Traders', 'party_type_id' => $wholesalerType->id]);
    }

    private function makeItem(array $overrides = []): Item
    {
        return Item::create(array_merge([
            'item_code' => 'GLD-RNG-' . fake()->unique()->numerify('####'),
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'net_weight_grams' => 10,
            'labour_cost' => 1000,
            'polish_cost' => 200,
            'purchase_rate_per_gram' => 20000,
            'purchase_price' => 201200,
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
            'date_received' => now()->toDateString(),
        ], $overrides));
    }

    public function test_inventory_screen_lists_items_and_passes_source_parties(): void
    {
        $this->makeItem(['source_party_id' => $this->karigar->id]);
        $this->makeItem(['source_party_id' => $this->wholesaler->id]);
        $this->makeItem(); // no source party

        $this->actingAs($this->user)->get('/inventory')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/index')
                ->has('items.data', 3)
                // only parties that actually sourced an item show in the filter dropdown
                ->has('parties', 2));
    }

    public function test_inventory_filters_by_source_party(): void
    {
        $fromKarigar = $this->makeItem(['source_party_id' => $this->karigar->id]);
        $this->makeItem(['source_party_id' => $this->wholesaler->id]);

        $this->actingAs($this->user)->get('/inventory?source_party_id=' . $this->karigar->id)
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.id', $fromKarigar->id)
                ->where('filters.source_party_id', (string) $this->karigar->id));
    }

    public function test_inventory_filters_combine(): void
    {
        $silver = MetalType::create(['name' => 'Silver']);
        $sp = Purity::create(['metal_type_id' => $silver->id, 'name' => 'Fine Silver', 'fineness_percent' => 99.9]);

        $target = $this->makeItem(['source_party_id' => $this->karigar->id, 'item_type' => 'bangle']);
        $this->makeItem(['source_party_id' => $this->karigar->id, 'item_type' => 'ring']);
        $this->makeItem(['source_party_id' => $this->wholesaler->id, 'item_type' => 'bangle']);
        $this->makeItem(['source_party_id' => $this->karigar->id, 'item_type' => 'bangle', 'metal_type_id' => $silver->id, 'purity_id' => $sp->id]);

        $this->actingAs($this->user)
            ->get('/inventory?source_party_id=' . $this->karigar->id . '&item_type=bangle&metal_type_id=' . $this->gold->id)
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.id', $target->id));
    }

    public function test_guest_is_redirected_from_inventory(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
    }

    public function test_stock_summary_totals_in_stock_and_bought_back_weight_per_metal(): void
    {
        $this->makeItem(['gross_weight_grams' => 10, 'net_weight_grams' => 10, 'status' => 'in_stock']);
        $this->makeItem(['gross_weight_grams' => 5, 'net_weight_grams' => 5, 'status' => 'in_stock']);
        $this->makeItem(['gross_weight_grams' => 3, 'net_weight_grams' => 3, 'status' => 'bought_back']);
        $this->makeItem(['gross_weight_grams' => 100, 'net_weight_grams' => 100, 'status' => 'sold']); // excluded

        $this->actingAs($this->user)->get('/inventory')
            ->assertInertia(fn ($page) => $page
                ->has('stockSummary', 1)
                ->where('stockSummary.0.metal', 'Gold')
                ->where('stockSummary.0.in_stock_grams', 15)
                ->where('stockSummary.0.in_stock_count', 2)
                ->where('stockSummary.0.bought_back_grams', 3)
                ->where('stockSummary.0.bought_back_count', 1));
    }
}
