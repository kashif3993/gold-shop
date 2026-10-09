<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\GoldRateService;
use App\Services\ZakatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZakatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopkeeper;

    private MetalType $gold;

    private MetalType $silver;

    private Purity $gold24k;

    private Purity $fineSilver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->shopkeeper = User::factory()->create();

        PartyType::create(['name' => 'Customer']);
        TransactionType::create(['name' => 'sale']);

        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->silver = MetalType::create(['name' => 'Silver']);
        $this->gold24k = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '24K', 'fineness_percent' => 99.9]);
        $this->fineSilver = Purity::create(['metal_type_id' => $this->silver->id, 'name' => 'Fine Silver', 'fineness_percent' => 99.9]);
    }

    private function setRate(Purity $purity, float $ratePerGram): void
    {
        app(GoldRateService::class)->storeRate($purity->metal_type_id, $purity->id, $ratePerGram, 'manual', $this->admin->id);
    }

    /* ── access control ───────────────────────────────────────────── */

    public function test_zakat_page_requires_login(): void
    {
        $this->get('/zakat')->assertRedirect('/login');
    }

    public function test_disabling_zakat_blocks_the_page(): void
    {
        Setting::put('zakat_enabled', false);

        $this->actingAs($this->shopkeeper)->get('/zakat')->assertForbidden();
    }

    public function test_zakat_enabled_by_default(): void
    {
        $this->actingAs($this->shopkeeper)->get('/zakat')->assertOk();
    }

    /* ── nisab ────────────────────────────────────────────────────── */

    public function test_nisab_uses_the_configured_grams_and_lower_threshold(): void
    {
        $this->setRate($this->gold24k, 1000); // gold nisab value = 87.48 * 1000 = 87,480
        $this->setRate($this->fineSilver, 100); // silver nisab value = 612.36 * 100 = 61,236

        $nisab = app(ZakatService::class)->nisab();

        $this->assertEquals(87480.0, $nisab['gold']['value']);
        $this->assertEquals(61236.0, $nisab['silver']['value']);
        $this->assertEquals(61236.0, $nisab['threshold']); // the lower of the two
    }

    public function test_nisab_is_configurable_from_settings(): void
    {
        $this->setRate($this->gold24k, 1000);
        $this->setRate($this->fineSilver, 100);
        Setting::put('zakat_nisab_gold_grams', 50);
        Setting::put('zakat_nisab_silver_grams', 500);

        $nisab = app(ZakatService::class)->nisab();

        $this->assertEquals(50000.0, $nisab['gold']['value']); // 50 * 1000
        $this->assertEquals(50000.0, $nisab['silver']['value']); // 500 * 100
        $this->assertEquals(50000.0, $nisab['threshold']);
    }

    public function test_nisab_side_is_null_when_a_metal_has_no_live_rate(): void
    {
        $this->setRate($this->gold24k, 1000);
        // Silver never priced.

        $nisab = app(ZakatService::class)->nisab();

        $this->assertNotNull($nisab['gold']);
        $this->assertNull($nisab['silver']);
        $this->assertEquals($nisab['gold']['value'], $nisab['threshold']);
    }

    /* ── shop stock ───────────────────────────────────────────────── */

    public function test_shop_stock_zakat_is_due_once_it_clears_nisab(): void
    {
        $this->setRate($this->gold24k, 1000);
        $this->setRate($this->fineSilver, 100);
        Setting::put('zakat_nisab_gold_grams', 50);
        Setting::put('zakat_nisab_silver_grams', 500); // threshold = min(50000, 50000) = 50000

        Item::create([
            'item_code' => 'ITM-1', 'item_type' => 'ring',
            'metal_type_id' => $this->gold->id, 'purity_id' => $this->gold24k->id,
            'gross_weight_grams' => 100, 'stone_weight_grams' => 0, 'cutting_loss_grams' => 0,
            'net_weight_grams' => 100, 'labour_cost' => 0, 'polish_cost' => 0,
            'purchase_rate_per_gram' => 900, 'purchase_price' => 90000, 'status' => 'in_stock',
            'created_by_user_id' => $this->admin->id, 'date_received' => now()->toDateString(),
        ]);

        $stock = app(ZakatService::class)->shopStock();

        // 100g * 1000/g = 100,000 stock value — clears the 50,000 threshold.
        $this->assertEquals(100000.0, $stock['stock_value']);
        $this->assertTrue($stock['meets_nisab']);
        $this->assertEquals(2500.0, $stock['zakat_due']); // 2.5% of 100,000
    }

    public function test_shop_stock_below_nisab_owes_nothing(): void
    {
        $this->setRate($this->gold24k, 1000);
        $this->setRate($this->fineSilver, 100);
        // Default Nisab (87.48g / 612.36g) is far above this tiny stock.

        Item::create([
            'item_code' => 'ITM-2', 'item_type' => 'ring',
            'metal_type_id' => $this->gold->id, 'purity_id' => $this->gold24k->id,
            'gross_weight_grams' => 1, 'stone_weight_grams' => 0, 'cutting_loss_grams' => 0,
            'net_weight_grams' => 1, 'labour_cost' => 0, 'polish_cost' => 0,
            'purchase_rate_per_gram' => 900, 'purchase_price' => 900, 'status' => 'in_stock',
            'created_by_user_id' => $this->admin->id, 'date_received' => now()->toDateString(),
        ]);

        $stock = app(ZakatService::class)->shopStock();

        $this->assertFalse($stock['meets_nisab']);
        $this->assertEquals(0.0, $stock['zakat_due']);
    }

    public function test_zakat_rate_percent_is_configurable(): void
    {
        Setting::put('zakat_rate_percent', 10);
        $this->setRate($this->gold24k, 1000);
        $this->setRate($this->fineSilver, 100);
        Setting::put('zakat_nisab_gold_grams', 10);
        Setting::put('zakat_nisab_silver_grams', 10); // threshold well below stock value

        Item::create([
            'item_code' => 'ITM-3', 'item_type' => 'ring',
            'metal_type_id' => $this->gold->id, 'purity_id' => $this->gold24k->id,
            'gross_weight_grams' => 100, 'stone_weight_grams' => 0, 'cutting_loss_grams' => 0,
            'net_weight_grams' => 100, 'labour_cost' => 0, 'polish_cost' => 0,
            'purchase_rate_per_gram' => 900, 'purchase_price' => 90000, 'status' => 'in_stock',
            'created_by_user_id' => $this->admin->id, 'date_received' => now()->toDateString(),
        ]);

        $stock = app(ZakatService::class)->shopStock();

        $this->assertEquals(10000.0, $stock['zakat_due']); // 10% of 100,000
    }

    /* ── admin settings integration ──────────────────────────────── */

    public function test_admin_can_update_zakat_settings_and_it_is_audited(): void
    {
        $payload = $this->settingsPayload([
            'zakat_rate_percent' => 5,
            'zakat_nisab_gold_grams' => 100,
            'zakat_nisab_silver_grams' => 700,
        ]);

        $this->actingAs($this->admin)->post('/admin/settings', $payload)->assertRedirect();

        $this->assertEquals('5', Setting::get('zakat_rate_percent'));
        $this->assertEquals('100', Setting::get('zakat_nisab_gold_grams'));
        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'settings',
            'field_name' => 'zakat_rate_percent',
        ]);
    }

    public function test_disabling_zakat_from_settings_hides_it_and_blocks_the_route(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->settingsPayload(['zakat_enabled' => false]))
            ->assertRedirect();

        $this->actingAs($this->shopkeeper)->get('/zakat')->assertForbidden();

        $this->actingAs($this->shopkeeper)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('features.zakat_enabled', false));
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'shop_name' => 'Zar & Noor',
            'shop_tagline' => 'Jewellers',
            'shop_address' => 'Main Bazaar',
            'shop_phone' => '0300 0000000',
            'shop_ntn' => '',
            'active_metal_ids' => [$this->gold->id, $this->silver->id],
            'buyback_enabled' => true,
            'bank_qr_enabled' => true,
            'bank_qr_manager_confirm_enabled' => false,
            'receipt_printer_type' => 'regular',
            'exchange_enabled' => true,
            'rate_auto_refresh_enabled' => true,
            'tax_enabled' => false,
            'tax_rate_percent' => 0,
            'zakat_enabled' => true,
            'zakat_rate_percent' => 2.5,
            'zakat_nisab_gold_grams' => 87.48,
            'zakat_nisab_silver_grams' => 612.36,
            'maintenance_mode_enabled' => false,
            'maintenance_message' => '',
        ], $overrides);
    }
}
