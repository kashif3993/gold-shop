<?php

namespace Tests\Feature;

use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_entry_math_and_purchase_price_locking()
    {
        $user = User::factory()->create();
        
        $partyType = PartyType::create(['name' => 'Karigar']);
        $party = Party::create([
            'party_type_id' => $partyType->id,
            'name' => 'Test Karigar',
            'phone' => '1234567890'
        ]);

        $metal = MetalType::create(['name' => 'Gold']);
        $purity = Purity::create([
            'metal_type_id' => $metal->id,
            'name' => '22K',
            'fineness_percent' => 91.6
        ]);

        // Scenario: 
        // Gross: 10g
        // Stone: 1g
        // Cutting: 0.5g
        // Expected Net: 8.5g
        // Rate: 10000 per gram
        // Labour: 2000
        // Polish: 500
        // Expected Purchase Price: (8.5 * 10000) + 2000 + 500 = 85000 + 2500 = 87500

        $payload = [
            'item_code' => 'ITM-001',
            'item_type' => 'ring',
            'metal_type_id' => $metal->id,
            'purity_id' => $purity->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 1,
            'cutting_loss_grams' => 0.5,
            'labour_cost' => 2000,
            'polish_cost' => 500,
            'purchase_rate_per_gram' => 10000,
            'purchase_price' => 999999, // Intentional fake price from frontend
            'source_party_id' => $party->id,
            'date_received' => now()->toDateString(),
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(201);

        // Verify the database has the correctly calculated math
        $this->assertDatabaseHas('items', [
            'item_code' => 'ITM-001',
            'net_weight_grams' => 8.5, // 10 - 1 - 0.5
            'purchase_price' => 87500, // Enforced server-side override
        ]);
    }

    public function test_stone_and_cutting_cannot_exceed_gross_weight()
    {
        $user = User::factory()->create();
        
        $partyType = PartyType::create(['name' => 'Karigar']);
        $party = Party::create(['party_type_id' => $partyType->id, 'name' => 'Test', 'phone' => '123']);
        $metal = MetalType::create(['name' => 'Gold']);
        $purity = Purity::create(['metal_type_id' => $metal->id, 'name' => '22K', 'fineness_percent' => 91.6]);

        $payload = [
            'item_code' => 'ITM-002',
            'item_type' => 'ring',
            'metal_type_id' => $metal->id,
            'purity_id' => $purity->id,
            'gross_weight_grams' => 5,
            'stone_weight_grams' => 3,
            'cutting_loss_grams' => 3, // 3+3 = 6 > 5
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => 10000,
            'purchase_price' => 0,
            'source_party_id' => $party->id,
            'date_received' => now()->toDateString(),
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gross_weight_grams']);
    }

    public function test_ring_cannot_use_24k_purity()
    {
        $user = User::factory()->create();

        $partyType = PartyType::create(['name' => 'Karigar']);
        $party = Party::create(['party_type_id' => $partyType->id, 'name' => 'Test', 'phone' => '123']);
        $metal = MetalType::create(['name' => 'Gold']);
        $purity = Purity::create(['metal_type_id' => $metal->id, 'name' => '24K', 'fineness_percent' => 99.9]);

        $payload = [
            'item_code' => 'ITM-003',
            'item_type' => 'ring',
            'metal_type_id' => $metal->id,
            'purity_id' => $purity->id,
            'gross_weight_grams' => 5,
            'stone_weight_grams' => 0.5,
            'cutting_loss_grams' => 0.2,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => 10000,
            'purchase_price' => 0,
            'source_party_id' => $party->id,
            'date_received' => now()->toDateString(),
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['purity_id']);
    }

    public function test_biscuit_or_nugget_can_use_24k_purity()
    {
        $user = User::factory()->create();

        $partyType = PartyType::create(['name' => 'Supplier']);
        $party = Party::create(['party_type_id' => $partyType->id, 'name' => 'Gold Dealer', 'phone' => '12345']);
        $metal = MetalType::create(['name' => 'Gold']);
        $purity = Purity::create(['metal_type_id' => $metal->id, 'name' => '24K', 'fineness_percent' => 99.9]);

        $payload = [
            'item_code' => 'ITM-BSC-001',
            'item_type' => 'biscuit',
            'metal_type_id' => $metal->id,
            'purity_id' => $purity->id,
            'gross_weight_grams' => 116.64,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => 10000,
            'purchase_price' => 1166400,
            'source_party_id' => $party->id,
            'date_received' => now()->toDateString(),
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'item_code' => 'ITM-BSC-001',
            'item_type' => 'biscuit',
            'purity_id' => $purity->id,
        ]);
    }

    public function test_ring_can_use_22k_purity()
    {
        $user = User::factory()->create();

        $partyType = PartyType::create(['name' => 'Karigar']);
        $party = Party::create(['party_type_id' => $partyType->id, 'name' => 'Karigar A', 'phone' => '12345']);
        $metal = MetalType::create(['name' => 'Gold']);
        $purity = Purity::create(['metal_type_id' => $metal->id, 'name' => '22K', 'fineness_percent' => 91.6]);

        $payload = [
            'item_code' => 'ITM-RNG-001',
            'item_type' => 'ring',
            'metal_type_id' => $metal->id,
            'purity_id' => $purity->id,
            'gross_weight_grams' => 4.5,
            'stone_weight_grams' => 0.5,
            'cutting_loss_grams' => 0.1,
            'labour_cost' => 500,
            'polish_cost' => 100,
            'purchase_rate_per_gram' => 9000,
            'purchase_price' => 0,
            'source_party_id' => $party->id,
            'date_received' => now()->toDateString(),
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'item_code' => 'ITM-RNG-001',
            'item_type' => 'ring',
            'purity_id' => $purity->id,
        ]);
    }
}
