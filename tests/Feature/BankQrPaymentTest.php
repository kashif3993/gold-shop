<?php

namespace Tests\Feature;

use App\Models\BankQrPayment;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\GoldRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankQrPaymentTest extends TestCase
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

        // Pricing is always resolved server-side from the purity's current
        // rate — never trusted from the request. 25000/g matches every
        // amount assertion below: (10g * 25000) + 1500 + 300 = 251800.
        app(GoldRateService::class)->storeRate($this->gold->id, $this->purity->id, 25000, 'manual', $this->admin->id);
    }

    private function makeItem(): Item
    {
        return Item::create([
            'item_code' => 'ITM-'.strtoupper(uniqid()),
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
            'created_by_user_id' => $this->admin->id,
            'date_received' => now()->toDateString(),
        ]);
    }

    private function cartPayload(Item $item): array
    {
        return [
            'discount' => 0,
            'discountType' => 'flat',
            'items' => [['id' => $item->id]],
        ];
    }

    /* ── starting an attempt ──────────────────────────────────────── */

    public function test_guest_cannot_start_a_bank_qr_payment(): void
    {
        $item = $this->makeItem();
        $this->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item))->assertUnauthorized();
    }

    public function test_any_authenticated_user_can_start_a_bank_qr_payment(): void
    {
        $item = $this->makeItem();

        $response = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));

        $response->assertStatus(201)->assertJsonPath('success', true);
        $response->assertJsonPath('payment.status', 'pending');
        $response->assertJsonPath('payment.amount', 251800); // (10g * 25000) + 1500 + 300

        $this->assertDatabaseHas('bank_qr_payments', ['status' => 'pending', 'created_by_user_id' => $this->shopkeeper->id]);
        $this->assertSame('in_stock', $item->fresh()->status); // nothing consumed yet
    }

    public function test_starting_rejects_an_item_that_is_not_in_stock(): void
    {
        $item = $this->makeItem();
        $item->update(['status' => 'sold']);

        $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item))
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /* ── status polling / expiry ──────────────────────────────────── */

    public function test_show_returns_the_current_status(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        $this->actingAs($this->shopkeeper)->getJson("/api/v1/pos/bank-qr/{$reference}")
            ->assertOk()
            ->assertJsonPath('payment.status', 'pending');
    }

    public function test_show_flips_a_stale_pending_payment_to_expired(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        BankQrPayment::where('reference', $reference)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->shopkeeper)->getJson("/api/v1/pos/bank-qr/{$reference}")
            ->assertJsonPath('payment.status', 'expired');

        $this->assertSame('expired', BankQrPayment::where('reference', $reference)->value('status'));
    }

    /* ── confirming — admin only ──────────────────────────────────── */

    public function test_shopkeeper_cannot_confirm_a_bank_qr_payment(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        $this->actingAs($this->shopkeeper)
            ->postJson("/admin/pos/bank-qr/{$reference}/confirm", ['bank_txn_id' => '482917'])
            ->assertForbidden();

        $this->assertSame('in_stock', $item->fresh()->status);
    }

    public function test_admin_can_confirm_and_it_creates_the_sale(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/pos/bank-qr/{$reference}/confirm", ['bank_txn_id' => '482917']);

        $response->assertOk()->assertJsonPath('success', true);
        $response->assertJsonPath('payment.status', 'confirmed');
        $response->assertJsonPath('payment.bank_txn_id', '482917');
        $response->assertJsonPath('payment.confirmed_by', $this->admin->username);

        $this->assertSame('sold', $item->fresh()->status);

        $payment = BankQrPayment::where('reference', $reference)->first();
        $this->assertNotNull($payment->invoice_id);
        $this->assertNotNull($payment->confirmed_at);
        $this->assertDatabaseHas('invoices', ['id' => $payment->invoice_id, 'payment_method' => 'bank_transfer']);
    }

    public function test_confirm_requires_a_bank_transaction_id(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        $this->actingAs($this->admin)
            ->postJson("/admin/pos/bank-qr/{$reference}/confirm", [])
            ->assertJsonValidationErrors('bank_txn_id');
    }

    public function test_confirming_twice_is_rejected_and_does_not_double_sell(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        $this->actingAs($this->admin)->postJson("/admin/pos/bank-qr/{$reference}/confirm", ['bank_txn_id' => '111111'])->assertOk();
        $this->actingAs($this->admin)->postJson("/admin/pos/bank-qr/{$reference}/confirm", ['bank_txn_id' => '222222'])
            ->assertStatus(409);

        $this->assertEquals(1, \App\Models\Invoice::count());
        $this->assertSame('111111', BankQrPayment::where('reference', $reference)->value('bank_txn_id'));
    }

    public function test_confirming_an_expired_payment_is_rejected(): void
    {
        $item = $this->makeItem();
        $start = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $reference = $start->json('payment.reference');

        BankQrPayment::where('reference', $reference)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->admin)->postJson("/admin/pos/bank-qr/{$reference}/confirm", ['bank_txn_id' => '482917'])
            ->assertStatus(410);

        $this->assertEquals(0, \App\Models\Invoice::count());
        $this->assertSame('expired', BankQrPayment::where('reference', $reference)->value('status'));
    }

    /** Two attempts opened for the same item; confirming one locks the item out from under the other — the core double-payment guard. */
    public function test_two_pending_attempts_for_the_same_item_only_one_can_ever_be_confirmed(): void
    {
        $item = $this->makeItem();
        $first = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));
        $second = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));

        $this->actingAs($this->admin)
            ->postJson('/admin/pos/bank-qr/'.$first->json('payment.reference').'/confirm', ['bank_txn_id' => 'AAA'])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson('/admin/pos/bank-qr/'.$second->json('payment.reference').'/confirm', ['bank_txn_id' => 'BBB'])
            ->assertStatus(400); // item no longer in stock

        $this->assertEquals(1, \App\Models\Invoice::count());
    }

    /* ── admin payment settings ───────────────────────────────────── */

    public function test_shopkeeper_is_forbidden_from_payment_settings(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/payment')->assertForbidden();
        $this->actingAs($this->shopkeeper)->post('/admin/payment', [
            'account_title' => 'Shop', 'bank_name' => 'Bank',
        ])->assertForbidden();
    }

    public function test_admin_can_save_payment_settings_with_a_qr_image(): void
    {
        $file = UploadedFile::fake()->image('qr.png', 50, 50)->size(20);

        $this->actingAs($this->admin)->post('/admin/payment', [
            'account_title' => 'Zar & Noor Jewellers',
            'bank_name' => 'Meezan Bank',
            'qr_image' => $file,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Zar & Noor Jewellers', Setting::get('bank_account_title'));
        $this->assertSame('Meezan Bank', Setting::get('bank_name'));
        $this->assertStringStartsWith('data:image/', Setting::get('bank_qr_image'));
    }

    public function test_saved_bank_details_are_shown_when_starting_a_payment(): void
    {
        Setting::put('bank_account_title', 'Zar & Noor Jewellers');
        Setting::put('bank_name', 'Meezan Bank');
        Setting::put('bank_qr_image', 'data:image/png;base64,AAAA');

        $item = $this->makeItem();
        $response = $this->actingAs($this->shopkeeper)->postJson('/api/v1/pos/bank-qr', $this->cartPayload($item));

        $response->assertJsonPath('payment.bank.account_title', 'Zar & Noor Jewellers');
        $response->assertJsonPath('payment.bank.bank_name', 'Meezan Bank');
        $response->assertJsonPath('payment.bank.qr_image', 'data:image/png;base64,AAAA');
    }
}
