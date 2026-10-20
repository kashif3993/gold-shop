<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\GoldRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopkeeper;

    private MetalType $goldMetal;

    private Purity $purity22k;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->shopkeeper = User::factory()->create(); // default role: shopkeeper

        PartyType::create(['name' => 'Customer']);
        TransactionType::create(['name' => 'sale']);
        TransactionType::create(['name' => 'old_gold_exchange']);

        $this->goldMetal = MetalType::create(['name' => 'Gold']);
        $this->purity22k = Purity::create([
            'metal_type_id' => $this->goldMetal->id,
            'name' => '22K',
            'fineness_percent' => 91.6,
        ]);
    }

    private function setRate(Purity $purity, float $ratePerGram): void
    {
        app(GoldRateService::class)->storeRate($purity->metal_type_id, $purity->id, $ratePerGram, 'manual', $this->admin->id);
    }

    private function createInStockItem(array $attributes = []): Item
    {
        $gross = $attributes['gross_weight_grams'] ?? 10.0;

        return Item::create(array_merge([
            'item_code' => 'ITM-'.strtoupper(uniqid()),
            'item_type' => 'ring',
            'metal_type_id' => $this->goldMetal->id,
            'purity_id' => $this->purity22k->id,
            'gross_weight_grams' => $gross,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'net_weight_grams' => $gross,
            'labour_cost' => 1000,
            'polish_cost' => 200,
            'purchase_rate_per_gram' => 15000,
            'purchase_price' => ($gross * 15000) + 1200,
            'status' => 'in_stock',
            'created_by_user_id' => $this->admin->id,
            'date_received' => now()->toDateString(),
        ], $attributes));
    }

    /* ── access control ──────────────────────────────────────────── */

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_shopkeeper_is_forbidden_from_settings(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->shopkeeper)->post('/admin/settings', [])->assertForbidden();
    }

    public function test_admin_can_view_settings_with_defaults(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('settings.buyback_enabled', true)
                ->where('settings.bank_qr_enabled', true)
                ->where('settings.exchange_enabled', true)
                ->where('settings.tax_enabled', false)
                ->where('settings.zakat_enabled', true)
                ->where('settings.zakat_rate_percent', 2.5)
                ->where('settings.maintenance_mode_enabled', false));
    }

    /* ── shop profile ────────────────────────────────────────────── */

    public function test_admin_can_update_shop_profile_and_it_reflects_on_an_invoice(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'shop_name' => 'Zar & Noor Test Branch',
            'shop_address' => '123 Test Bazaar',
        ]))->assertRedirect();

        $this->assertEquals('Zar & Noor Test Branch', Setting::get('shop_name'));

        $party = Party::create(['party_type_id' => 1, 'name' => 'Walk-in Customer', 'phone' => '0000000000']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-20260101-0001',
            'party_id' => $party->id,
            'invoice_date' => now(),
            'grand_total' => 1000,
            'payment_method' => 'cash',
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get("/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('shop.name', 'Zar & Noor Test Branch')
                ->where('shop.address', '123 Test Bazaar'));
    }

    public function test_admin_can_choose_a_receipt_printer_type_and_it_reaches_pos(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'receipt_printer_type' => 'thermal',
        ]))->assertRedirect();

        $this->assertEquals('thermal', Setting::get('receipt_printer_type'));

        $this->actingAs($this->shopkeeper)->get('/pos')
            ->assertInertia(fn ($page) => $page->where('printerType', 'thermal'));
    }

    public function test_invalid_receipt_printer_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/settings')
            ->post('/admin/settings', $this->validPayload(['receipt_printer_type' => 'laser']))
            ->assertSessionHasErrors('receipt_printer_type');
    }

    public function test_admin_can_upload_a_shop_logo_and_it_reaches_pos(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 80, 80)->size(30);

        $this->actingAs($this->admin)->post('/admin/settings', array_merge(
            $this->validPayload(),
            ['shop_logo' => $file],
        ))->assertRedirect()->assertSessionHas('success');

        $this->assertStringStartsWith('data:image/', Setting::get('shop_logo'));

        $this->actingAs($this->shopkeeper)->get('/pos')
            ->assertInertia(fn ($page) => $page->where('shop.logo', fn ($logo) => str_starts_with($logo, 'data:image/')));
    }

    public function test_leaving_the_logo_field_empty_does_not_clear_an_existing_logo(): void
    {
        Setting::put('shop_logo', 'data:image/png;base64,AAAA');

        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload())->assertRedirect();

        $this->assertEquals('data:image/png;base64,AAAA', Setting::get('shop_logo'));
    }

    /* ── feature toggles ─────────────────────────────────────────── */

    public function test_disabling_buyback_blocks_its_routes_and_updates_the_shared_flag(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload(['buyback_enabled' => false]))
            ->assertRedirect();

        $this->actingAs($this->shopkeeper)->get('/buyback')->assertForbidden();
        $this->actingAs($this->shopkeeper)->postJson('/api/v1/buyback', [])->assertForbidden();

        $this->actingAs($this->shopkeeper)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('features.buyback_enabled', false));
    }

    public function test_disabling_bank_qr_blocks_starting_a_new_payment_attempt(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload(['bank_qr_enabled' => false]))
            ->assertRedirect();

        $this->setRate($this->purity22k, 20000);
        $item = $this->createInStockItem();

        $this->actingAs($this->shopkeeper)
            ->postJson('/api/v1/pos/bank-qr', ['items' => [['id' => $item->id]]])
            ->assertForbidden();
    }

    public function test_disabling_exchange_rejects_a_sale_that_includes_one(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload(['exchange_enabled' => false]))
            ->assertRedirect();

        $this->setRate($this->purity22k, 20000);
        $item = $this->createInStockItem();

        $response = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/transaction', [
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [['id' => $item->id]],
            'exchanges' => [[
                'metal_type_id' => $this->goldMetal->id,
                'purity_id' => $this->purity22k->id,
                'weight_grams' => 5,
                'deduction_percent' => 10,
            ]],
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('turned off', $response->json('message'));
    }

    /* ── sales tax ────────────────────────────────────────────────── */

    public function test_enabling_tax_adds_it_to_the_invoice_total(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'tax_enabled' => true,
            'tax_rate_percent' => 5,
        ]))->assertRedirect();

        $this->setRate($this->purity22k, 20000);
        // Net 10g * 20000 = 200,000 metal + 1000 labour + 200 polish = 201,200 subtotal.
        // No discount → taxable = 201,200. Tax @5% = 10,060. Grand total = 211,260.
        $item = $this->createInStockItem(['gross_weight_grams' => 10]);

        $response = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/transaction', [
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [['id' => $item->id]],
        ]);

        $response->assertStatus(201);

        $invoice = Invoice::first();
        $this->assertEquals(10060.00, $invoice->total_tax);
        $this->assertEquals(211260.00, $invoice->grand_total);
    }

    public function test_tax_disabled_by_default_adds_nothing(): void
    {
        $this->setRate($this->purity22k, 20000);
        $item = $this->createInStockItem(['gross_weight_grams' => 10]);

        $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/transaction', [
            'discount' => 0,
            'discountType' => 'flat',
            'paymentMethod' => 'Cash',
            'items' => [['id' => $item->id]],
        ])->assertStatus(201);

        $invoice = Invoice::first();
        $this->assertEquals(0.00, $invoice->total_tax);
        $this->assertEquals(201200.00, $invoice->grand_total);
    }

    /* ── maintenance mode ────────────────────────────────────────── */

    public function test_maintenance_mode_blocks_shopkeeper_but_not_admin_or_guest_login(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'maintenance_mode_enabled' => true,
            'maintenance_message' => 'Closed for stock-taking.',
        ]))->assertRedirect();

        $this->actingAs($this->shopkeeper)
            ->get('/dashboard')
            ->assertStatus(503)
            ->assertInertia(fn ($page) => $page->component('maintenance')->where('message', 'Closed for stock-taking.'));

        $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        // A guest (not the admin session still active on $this) must still reach the login page.
        auth()->logout();
        $this->get('/login')->assertOk();
    }

    public function test_maintenance_mode_change_writes_an_audit_row(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload(['maintenance_mode_enabled' => true]));

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'settings',
            'field_name' => 'maintenance_mode_enabled',
            'new_value' => '1',
        ]);
    }

    /* ── metals dealt in ─────────────────────────────────────────── */

    public function test_disabling_a_metal_removes_it_from_item_entry_pos_and_buyback(): void
    {
        $silver = MetalType::create(['name' => 'Silver']);
        Purity::create(['metal_type_id' => $silver->id, 'name' => 'Fine Silver', 'fineness_percent' => 99.9]);

        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'active_metal_ids' => [$this->goldMetal->id], // Silver switched off
        ]))->assertRedirect();

        $this->assertFalse($silver->fresh()->is_active);

        $this->actingAs($this->shopkeeper)
            ->get('/items/create')
            ->assertInertia(fn ($page) => $page
                ->has('metals', 1)
                ->where('metals.0.name', 'Gold'));

        $this->actingAs($this->shopkeeper)
            ->get('/pos')
            ->assertInertia(fn ($page) => $page->has('metals', 1));

        $this->actingAs($this->shopkeeper)
            ->get('/buyback')
            ->assertInertia(fn ($page) => $page->has('metals', 1));
    }

    public function test_at_least_one_metal_must_remain_active(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/settings')
            ->post('/admin/settings', $this->validPayload(['active_metal_ids' => []]));

        $response->assertRedirect('/admin/settings');
        $response->assertSessionHasErrors('active_metal_ids');
        $this->assertTrue($this->goldMetal->fresh()->is_active);
    }

    /* ── labour cost defaults (FR-24/FR-25) ──────────────────────── */

    public function test_settings_screen_lists_every_item_type_defaulting_to_zero(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/settings')
            ->assertInertia(fn ($page) => $page
                ->has('itemTypeCostDefaults', 16)
                ->where('itemTypeCostDefaults.0.labour_cost_default', 0));
    }

    public function test_admin_can_set_a_labour_cost_default_for_an_item_type_and_it_is_audited(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'item_type_cost_defaults' => [
                ['item_type' => 'ring', 'labour_cost_default' => 500],
            ],
        ]))->assertRedirect();

        $this->assertDatabaseHas('item_type_cost_defaults', [
            'item_type' => 'ring',
            'labour_cost_default' => 500,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'settings',
            'field_name' => 'item_type_cost_default',
            'old_value' => 'Rs 0',
            'new_value' => 'Rs 500',
        ]);
    }

    public function test_unchanged_labour_cost_defaults_write_no_audit_row(): void
    {
        $before = AuditLog::count();

        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload())->assertRedirect();

        $this->assertSame($before, AuditLog::where('field_name', 'item_type_cost_default')->count());
    }

    public function test_item_create_screen_receives_the_saved_labour_cost_defaults(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', $this->validPayload([
            'item_type_cost_defaults' => [
                ['item_type' => 'ring', 'labour_cost_default' => 750],
            ],
        ]))->assertRedirect();

        $this->actingAs($this->shopkeeper)
            ->get('/items/create')
            ->assertInertia(fn ($page) => $page->where('labourCostDefaults.ring', '750.00'));
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'shop_name' => 'Zar & Noor',
            'shop_tagline' => 'Jewellers',
            'shop_address' => 'Main Bazaar',
            'shop_phone' => '0300 0000000',
            'shop_ntn' => '',
            'active_metal_ids' => [$this->goldMetal->id],
            'item_type_cost_defaults' => [],
            'buyback_enabled' => true,
            'bank_qr_enabled' => true,
            'bank_qr_manager_confirm_enabled' => false,
            'receipt_printer_type' => 'regular',
            'item_slip_print_width_mm' => 80,
            'item_slip_print_height_mm' => 0,
            'item_slip_print_padding_mm' => 4,
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
