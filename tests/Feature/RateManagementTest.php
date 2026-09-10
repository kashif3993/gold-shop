<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DailyRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RateAdjustmentSetting;
use App\Models\RateFetchLog;
use App\Models\User;
use App\Services\GoldRateService;
use App\Services\RateAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MetalType $gold;
    private MetalType $silver;
    private Purity $g24;
    private Purity $g22;
    private Purity $s999;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->silver = MetalType::create(['name' => 'Silver']);
        $this->g24 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '24K', 'fineness_percent' => 99.9, 'is_active' => true]);
        $this->g22 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6, 'is_active' => true]);
        $this->s999 = Purity::create(['metal_type_id' => $this->silver->id, 'name' => 'Fine Silver', 'fineness_percent' => 99.9, 'is_active' => true]);

        RateAdjustmentSetting::create([
            'metal_type_id' => null, 'purity_id' => null,
            'adjustment_type' => 'amount', 'adjustment_value' => 0, 'is_shop_default' => true,
        ]);
    }

    /* ── adjustment cascade ─────────────────────────────────────── */

    public function test_adjustment_resolution_cascade(): void
    {
        $svc = app(RateAdjustmentService::class);

        // shop default is amount/0
        $this->assertEquals(['type' => 'amount', 'value' => 0.0], $svc->resolveAdjustment($this->g22->id, $this->gold->id));

        RateAdjustmentSetting::where('is_shop_default', true)->update(['adjustment_value' => 300]);
        $this->assertEquals(300.0, $svc->resolveAdjustment($this->g22->id, $this->gold->id)['value']);

        // metal-level beats shop default
        RateAdjustmentSetting::create([
            'metal_type_id' => $this->gold->id, 'purity_id' => null,
            'adjustment_type' => 'amount', 'adjustment_value' => 450, 'is_shop_default' => false,
        ]);
        $this->assertEquals(450.0, $svc->resolveAdjustment($this->g22->id, $this->gold->id)['value']);
        $this->assertEquals(300.0, $svc->resolveAdjustment($this->s999->id, $this->silver->id)['value']);

        // purity-specific beats metal
        RateAdjustmentSetting::create([
            'metal_type_id' => $this->gold->id, 'purity_id' => $this->g22->id,
            'adjustment_type' => 'percent', 'adjustment_value' => 2, 'is_shop_default' => false,
        ]);
        $this->assertEquals(['type' => 'percent', 'value' => 2.0], $svc->resolveAdjustment($this->g22->id, $this->gold->id));
        $this->assertEquals(450.0, $svc->resolveAdjustment($this->g24->id, $this->gold->id)['value']);
    }

    public function test_apply_adjustment_amount_and_percent(): void
    {
        $svc = app(RateAdjustmentService::class);

        RateAdjustmentSetting::where('is_shop_default', true)->update(['adjustment_type' => 'amount', 'adjustment_value' => 400]);
        $this->assertEquals(24400.00, $svc->applyAdjustment(24000, $this->g22->id, $this->gold->id));

        RateAdjustmentSetting::where('is_shop_default', true)->update(['adjustment_type' => 'percent', 'adjustment_value' => 1.5]);
        $this->assertEquals(24360.00, $svc->applyAdjustment(24000, $this->g22->id, $this->gold->id));
    }

    /* ── storage / is_current ───────────────────────────────────── */

    public function test_store_rate_retires_the_previous_current_row(): void
    {
        $rates = app(GoldRateService::class);

        $rates->storeRate($this->gold->id, $this->g22->id, 22000, 'manual', $this->user->id);
        $rates->storeRate($this->gold->id, $this->g22->id, 22100, 'manual', $this->user->id);

        $this->assertEquals(2, DailyRate::where('purity_id', $this->g22->id)->count());
        $this->assertEquals(1, DailyRate::where('purity_id', $this->g22->id)->where('is_current', true)->count());
        $this->assertEquals(22100.00, DailyRate::where('purity_id', $this->g22->id)->where('is_current', true)->value('rate_per_gram'));
    }

    public function test_manual_entry_derives_every_purity_from_fineness(): void
    {
        app(GoldRateService::class)->applyManual(['Gold' => 24000, 'Silver' => 300], $this->user->id);

        $this->assertEqualsWithDelta(24000 * 0.999, (float) DailyRate::where('purity_id', $this->g24->id)->where('is_current', true)->value('rate_per_gram'), 0.01);
        $this->assertEqualsWithDelta(24000 * 0.916, (float) DailyRate::where('purity_id', $this->g22->id)->where('is_current', true)->value('rate_per_gram'), 0.01);
        $this->assertEqualsWithDelta(300 * 0.999, (float) DailyRate::where('purity_id', $this->s999->id)->where('is_current', true)->value('rate_per_gram'), 0.01);
    }

    /* ── fetch: success + offline fallback ──────────────────────── */

    public function test_api_fetch_stores_rates_and_logs_success(): void
    {
        Http::fake([
            '*/price/XAU' => Http::response(['price' => 2650.0]),
            '*/price/XAG' => Http::response(['price' => 31.0]),
        ]);

        $log = app(GoldRateService::class)->refreshFromApi($this->user->id);

        $this->assertTrue($log->success);
        $this->assertFalse((bool) $log->fallback_used);
        $this->assertEquals(3, DailyRate::where('is_current', true)->count());
        $this->assertEquals('api', DailyRate::where('purity_id', $this->g22->id)->where('is_current', true)->value('source'));
    }

    public function test_api_failure_keeps_last_rate_and_logs_a_fallback(): void
    {
        // an existing current rate the shop is billing on
        $existing = app(GoldRateService::class)->storeRate($this->gold->id, $this->g22->id, 22000, 'manual', $this->user->id);

        Http::fake(['*' => Http::response('', 503)]);

        $log = app(GoldRateService::class)->refreshFromApi($this->user->id);

        $this->assertFalse($log->success);
        $this->assertTrue((bool) $log->fallback_used);

        // the cached rate is untouched — billing keeps working
        $existing->refresh();
        $this->assertTrue((bool) $existing->is_current);
        $this->assertEquals(22000.00, (float) $existing->rate_per_gram);
        $this->assertEquals(1, DailyRate::count());
    }

    public function test_stale_flag_is_raised_when_the_feed_is_old(): void
    {
        $old = app(GoldRateService::class)->storeRate($this->gold->id, $this->g22->id, 22000, 'manual', $this->user->id);
        $old->forceFill(['fetched_at' => now()->subHours(20)])->save();

        Http::fake(['*' => Http::response('', 500)]);
        app(GoldRateService::class)->refreshFromApi();

        $this->assertTrue((bool) $old->fresh()->is_stale);
        $this->assertTrue(app(GoldRateService::class)->isFeedStale());
    }

    /* ── screen + endpoints ─────────────────────────────────────── */

    public function test_guest_is_redirected(): void
    {
        $this->get('/rate-management')->assertRedirect('/login');
    }

    public function test_screen_renders(): void
    {
        app(GoldRateService::class)->applyManual(['Gold' => 24000], $this->user->id);

        $this->actingAs($this->user)->get('/rate-management')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('rate-management/index')
                ->has('rates', 3)
                ->has('metals', 2)
                ->has('shopDefault')
                ->has('fetchLog'));
    }

    public function test_refresh_endpoint_runs_a_fetch(): void
    {
        Http::fake([
            '*/price/XAU' => Http::response(['price' => 2600.0]),
            '*/price/XAG' => Http::response(['price' => 30.0]),
        ]);

        $this->actingAs($this->user)->post('/rate-management/refresh')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(1, RateFetchLog::where('success', true)->count());
        $this->assertGreaterThan(0, DailyRate::where('is_current', true)->count());
    }

    public function test_manual_endpoint_saves_and_audits(): void
    {
        $this->actingAs($this->user)->post('/rate-management/manual', ['gold_spot' => 24500])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertGreaterThan(0, DailyRate::where('source', 'manual')->where('is_current', true)->count());
        $this->assertDatabaseHas('audit_log', ['entity_type' => 'rate', 'field_name' => 'manual_spot']);
    }

    public function test_save_adjustment_upserts_reapplies_and_audits(): void
    {
        // a current rate with a known raw so we can watch the effective change
        $row = app(GoldRateService::class)->storeRate($this->gold->id, $this->g22->id, 20000, 'api', $this->user->id);
        $this->assertEquals(20000.00, (float) $row->fresh()->rate_per_gram); // no adjustment yet

        $this->actingAs($this->user)->post('/rate-management/adjustment', [
            'scope' => 'metal',
            'metal_type_id' => $this->gold->id,
            'adjustment_type' => 'amount',
            'adjustment_value' => 500,
            'reason' => 'Counter margin',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('rate_adjustment_settings', [
            'metal_type_id' => $this->gold->id, 'purity_id' => null, 'adjustment_value' => 500,
        ]);
        $this->assertEquals(20500.00, (float) $row->fresh()->rate_per_gram); // reapplied
        $this->assertDatabaseHas('audit_log', ['entity_type' => 'rate', 'field_name' => 'adjustment.metal']);
    }

    public function test_ticker_endpoint_returns_per_gram_and_per_tola(): void
    {
        app(GoldRateService::class)->storeRate($this->gold->id, $this->g22->id, 24000, 'manual', $this->user->id);

        $this->actingAs($this->user)->getJson('/api/v1/rates/current')
            ->assertOk()
            ->assertJsonPath('rates.0.metal', 'Gold')
            ->assertJsonPath('rates.0.purity', '22K')
            ->assertJsonPath('rates.0.per_gram', 24000)
            ->assertJsonPath('rates.0.per_tola', round(24000 * 11.6638038, 2))
            ->assertJsonPath('feed_stale', false);
    }

    public function test_ticker_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v1/rates/current')->assertUnauthorized();
    }

    public function test_usd_pkr_setting_overrides_the_config_default(): void
    {
        $svc = app(GoldRateService::class);
        $this->assertEquals((float) config('services.gold_api.usd_pkr'), $svc->usdPkr());

        \App\Models\Setting::put('usd_pkr', 284.5);
        $this->assertEquals(284.5, $svc->usdPkr());
    }

    public function test_auto_fx_is_fetched_stored_and_used_on_refresh(): void
    {
        \App\Models\Setting::put('usd_pkr_auto', true);

        Http::fake([
            '*open.er-api.com*' => Http::response(['result' => 'success', 'rates' => ['PKR' => 285.25]]),
            '*/price/XAU' => Http::response(['price' => 4400.0]),
            '*/price/XAG' => Http::response(['price' => 50.0]),
        ]);

        $log = app(GoldRateService::class)->refreshFromApi($this->user->id);

        $this->assertTrue($log->success);
        $this->assertEquals(285.25, (float) \App\Models\Setting::get('usd_pkr'));
        $this->assertNotNull(\App\Models\Setting::get('usd_pkr_fetched_at'));

        // 24K raw ≈ 4400 / 31.1034768 × 285.25 × 0.999
        $expected = round(4400 / GoldRateService::TROY_OUNCE_GRAMS * 285.25 * 0.999, 2);
        $this->assertEqualsWithDelta(
            $expected,
            (float) DailyRate::where('purity_id', $this->g24->id)->where('is_current', true)->value('api_raw_rate_per_gram'),
            0.5,
        );
    }

    public function test_auto_fx_failure_falls_back_to_the_stored_rate(): void
    {
        \App\Models\Setting::put('usd_pkr_auto', true);
        \App\Models\Setting::put('usd_pkr', 280);

        Http::fake([
            '*open.er-api.com*' => Http::response('', 500),
            '*/price/XAU' => Http::response(['price' => 4400.0]),
            '*/price/XAG' => Http::response(['price' => 50.0]),
        ]);

        $log = app(GoldRateService::class)->refreshFromApi($this->user->id);

        $this->assertTrue($log->success); // gold still fetched
        $this->assertEquals(280.0, (float) \App\Models\Setting::get('usd_pkr')); // unchanged
        $expected = round(4400 / GoldRateService::TROY_OUNCE_GRAMS * 280 * 0.999, 2);
        $this->assertEqualsWithDelta(
            $expected,
            (float) DailyRate::where('purity_id', $this->g24->id)->where('is_current', true)->value('api_raw_rate_per_gram'),
            0.5,
        );
    }

    public function test_save_fx_endpoint_updates_setting_audits_and_refreshes(): void
    {
        Http::fake([
            '*/price/XAU' => Http::response(['price' => 4400.0]),
            '*/price/XAG' => Http::response(['price' => 50.0]),
        ]);

        $this->actingAs($this->user)->post('/rate-management/fx', ['usd_pkr' => 286.75, 'auto' => false])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(286.75, (float) \App\Models\Setting::get('usd_pkr'));
        $this->assertEquals('0', \App\Models\Setting::get('usd_pkr_auto'));
        $this->assertDatabaseHas('audit_log', ['entity_type' => 'rate', 'field_name' => 'usd_pkr']);
        $this->assertGreaterThan(0, DailyRate::where('is_current', true)->count());
    }

    public function test_rates_fetch_command_runs(): void
    {
        Http::fake([
            '*/price/XAU' => Http::response(['price' => 2700.0]),
            '*/price/XAG' => Http::response(['price' => 32.0]),
        ]);

        $this->artisan('rates:fetch')->assertExitCode(0);
        $this->assertEquals(1, RateFetchLog::where('success', true)->count());
    }
}
