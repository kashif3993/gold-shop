<?php

namespace Tests\Feature;

use App\Models\DailyRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\User;
use App\Services\GoldRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $shopkeeper;
    private MetalType $gold;
    private Purity $purity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->shopkeeper = User::factory()->create(); // default role: shopkeeper
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->purity = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6, 'is_active' => true]);
    }

    /* ── role gating ──────────────────────────────────────────────── */

    public function test_guest_is_redirected_from_admin_index(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_shopkeeper_is_forbidden_from_admin_index(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_view_admin_index(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }

    public function test_shopkeeper_is_forbidden_from_purities(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/purities')->assertForbidden();
        $this->actingAs($this->shopkeeper)->post('/admin/purities', [
            'metal_type_id' => $this->gold->id, 'name' => '18K', 'fineness_percent' => 75,
        ])->assertForbidden();
    }

    public function test_shopkeeper_is_forbidden_from_daily_rate_history(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/daily-rates')->assertForbidden();
    }

    public function test_shopkeeper_is_forbidden_from_overriding_a_rate(): void
    {
        $this->actingAs($this->shopkeeper)->post('/admin/rate-management/override', [
            'purity_id' => $this->purity->id, 'new_rate' => 25000, 'reason' => 'test',
        ])->assertForbidden();
    }

    /* ── purity management ────────────────────────────────────────── */

    public function test_admin_can_add_a_purity(): void
    {
        $this->actingAs($this->admin)->post('/admin/purities', [
            'metal_type_id' => $this->gold->id,
            'name' => '18K',
            'fineness_percent' => 75,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('purities', ['name' => '18K', 'fineness_percent' => 75.00, 'is_active' => true]);
        $this->assertDatabaseHas('audit_log', ['entity_type' => 'purity', 'field_name' => 'created']);
    }

    public function test_admin_can_edit_a_purity_with_a_reason(): void
    {
        $this->actingAs($this->admin)->put("/admin/purities/{$this->purity->id}", [
            'name' => '22K Gold',
            'fineness_percent' => 91.7,
            'reason' => 'Corrected fineness per assay report',
        ])->assertRedirect()->assertSessionHas('success');

        $this->purity->refresh();
        $this->assertSame('22K Gold', $this->purity->name);
        $this->assertEquals(91.70, (float) $this->purity->fineness_percent);
        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'purity',
            'entity_id' => $this->purity->id,
            'reason' => 'Corrected fineness per assay report',
        ]);
    }

    public function test_admin_can_deactivate_and_reactivate_a_purity(): void
    {
        $this->actingAs($this->admin)->post("/admin/purities/{$this->purity->id}/toggle")
            ->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($this->purity->fresh()->is_active);

        $this->actingAs($this->admin)->post("/admin/purities/{$this->purity->id}/toggle")
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($this->purity->fresh()->is_active);

        $this->assertDatabaseHas('audit_log', ['entity_type' => 'purity', 'field_name' => 'is_active', 'new_value' => 'inactive']);
        $this->assertDatabaseHas('audit_log', ['entity_type' => 'purity', 'field_name' => 'is_active', 'new_value' => 'active']);
    }

    /* ── daily rate history ───────────────────────────────────────── */

    public function test_daily_rate_history_lists_and_filters(): void
    {
        app(GoldRateService::class)->storeRate($this->gold->id, $this->purity->id, 24000, 'api', $this->admin->id);
        app(GoldRateService::class)->storeRate($this->gold->id, $this->purity->id, 25000, 'manual', $this->admin->id);

        $response = $this->actingAs($this->admin)->get('/admin/daily-rates')->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/daily-rates/index')
            ->has('rates.data', 2));

        $filtered = $this->actingAs($this->admin)->get('/admin/daily-rates?source=api')->assertOk();
        $filtered->assertInertia(fn ($page) => $page->has('rates.data', 1));
    }

    /* ── manual price override ────────────────────────────────────── */

    public function test_admin_can_override_a_rate_with_a_required_reason(): void
    {
        app(GoldRateService::class)->storeRate($this->gold->id, $this->purity->id, 24000, 'api', $this->admin->id);

        $this->actingAs($this->admin)->post('/admin/rate-management/override', [
            'purity_id' => $this->purity->id,
            'new_rate' => 26000,
            'reason' => 'Matched competitor counter price today',
        ])->assertRedirect()->assertSessionHas('success');

        $current = DailyRate::where('purity_id', $this->purity->id)->where('is_current', true)->first();
        $this->assertEquals(26000.00, (float) $current->rate_per_gram);
        $this->assertSame('manual', $current->source);
        $this->assertNull($current->api_raw_rate_per_gram);

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'rate',
            'entity_id' => $this->purity->id,
            'field_name' => 'manual_override',
            'new_value' => '26000',
            'reason' => 'Matched competitor counter price today',
        ]);
    }

    public function test_override_requires_a_reason(): void
    {
        $this->actingAs($this->admin)->post('/admin/rate-management/override', [
            'purity_id' => $this->purity->id,
            'new_rate' => 26000,
        ])->assertSessionHasErrors('reason');
    }

    public function test_override_retires_the_previous_current_row(): void
    {
        $original = app(GoldRateService::class)->storeRate($this->gold->id, $this->purity->id, 24000, 'api', $this->admin->id);

        $this->actingAs($this->admin)->post('/admin/rate-management/override', [
            'purity_id' => $this->purity->id, 'new_rate' => 26000, 'reason' => 'test',
        ]);

        $this->assertFalse($original->fresh()->is_current);
        $this->assertEquals(1, DailyRate::where('purity_id', $this->purity->id)->where('is_current', true)->count());
    }
}
