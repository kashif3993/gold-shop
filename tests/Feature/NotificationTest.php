<?php

namespace Tests\Feature;

use App\Models\BankQrPayment;
use App\Models\DailyRate;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\GoldRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
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
        $this->shopkeeper = User::factory()->create();
        PartyType::create(['name' => 'Customer']);
        TransactionType::create(['name' => 'sale']);
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->purity = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6, 'is_active' => true]);

        // A fresh rate so isFeedStale() is false by default — otherwise
        // every test here would also trip the "no rate ever fetched" case,
        // which isn't what most of these are actually testing.
        app(GoldRateService::class)->storeRate($this->gold->id, $this->purity->id, 25000, 'manual', $this->admin->id);
    }

    private function makeItem(string $status = 'in_stock'): Item
    {
        return Item::create([
            'item_code' => 'ITM-'.strtoupper(uniqid()),
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->purity->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => 20000,
            'purchase_price' => 200000,
            'status' => $status,
            'created_by_user_id' => $this->admin->id,
            'date_received' => now()->toDateString(),
        ]);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_empty_when_nothing_needs_attention(): void
    {
        $this->makeItem('in_stock');

        $this->actingAs($this->shopkeeper)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_out_of_stock_purity_is_reported_once_it_was_previously_stocked(): void
    {
        // A purity nobody has ever stocked is excluded — only one with a
        // history (any item at all) but zero "in_stock" right now counts.
        $this->makeItem('sold');

        $response = $this->actingAs($this->shopkeeper)->getJson('/api/v1/notifications');

        $response->assertOk()->assertJsonCount(1, 'notifications');
        $response->assertJsonPath('notifications.0.id', "out-of-stock-{$this->purity->id}");
        $response->assertJsonPath('notifications.0.severity', 'info');
    }

    public function test_a_never_stocked_purity_produces_no_notification(): void
    {
        // No items ever created for $this->purity at all.
        $this->actingAs($this->shopkeeper)->getJson('/api/v1/notifications')
            ->assertJsonCount(0, 'notifications');
    }

    public function test_stale_rate_feed_is_reported_to_everyone(): void
    {
        // Age the current rate from setUp() rather than creating a second
        // "current" row — isFeedStale() looks at the newest fetched_at
        // among current rows, so a second fresh row would mask this.
        DailyRate::where('is_current', true)->update(['fetched_at' => now()->subHours(20)]);

        $response = $this->actingAs($this->shopkeeper)->getJson('/api/v1/notifications');

        $response->assertOk();
        $this->assertContains('rate-feed-stale', collect($response->json('notifications'))->pluck('id'));
    }

    public function test_pending_bank_qr_is_shown_to_admin_but_not_shopkeeper(): void
    {
        BankQrPayment::create([
            'reference' => 'BQR-20260101-0001',
            'amount' => 1000,
            'cart_snapshot' => [],
            'status' => 'pending',
            'created_by_user_id' => $this->shopkeeper->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        $adminResponse = $this->actingAs($this->admin)->getJson('/api/v1/notifications');
        $this->assertContains('bank-qr-pending', collect($adminResponse->json('notifications'))->pluck('id'));

        $shopkeeperResponse = $this->actingAs($this->shopkeeper)->getJson('/api/v1/notifications');
        $this->assertNotContains('bank-qr-pending', collect($shopkeeperResponse->json('notifications'))->pluck('id'));
    }

    public function test_an_expired_pending_payment_is_not_counted(): void
    {
        BankQrPayment::create([
            'reference' => 'BQR-20260101-0002',
            'amount' => 1000,
            'cart_snapshot' => [],
            'status' => 'pending',
            'created_by_user_id' => $this->shopkeeper->id,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/notifications');

        $this->assertNotContains('bank-qr-pending', collect($response->json('notifications'))->pluck('id'));
    }

    public function test_manager_sees_pending_bank_qr_once_granted_the_permission(): void
    {
        Setting::put('bank_qr_manager_confirm_enabled', true);
        $manager = User::factory()->manager()->create();

        BankQrPayment::create([
            'reference' => 'BQR-20260101-0003',
            'amount' => 1000,
            'cart_snapshot' => [],
            'status' => 'pending',
            'created_by_user_id' => $this->shopkeeper->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($manager)->getJson('/api/v1/notifications');

        $this->assertContains('bank-qr-pending', collect($response->json('notifications'))->pluck('id'));
    }
}
