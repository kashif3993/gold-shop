<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PartyType $customerType;
    private PartyType $karigarType;
    private MetalType $gold;
    private Purity $purity22k;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customerType = PartyType::create(['name' => 'Customer']);
        $this->karigarType = PartyType::create(['name' => 'Karigar']);
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->purity22k = Purity::create([
            'metal_type_id' => $this->gold->id,
            'name' => '22K',
            'fineness_percent' => 91.6,
        ]);
    }

    private function makeParty(array $overrides = []): Party
    {
        return Party::create(array_merge([
            'party_type_id' => $this->customerType->id,
            'name' => 'Test Party',
            'phone' => '03001234567',
            'is_active' => true,
        ], $overrides));
    }

    private function makeTransaction(Party $party, string $direction, float $amount, array $overrides = []): Transaction
    {
        $type = TransactionType::firstOrCreate(['name' => $overrides['type_name'] ?? 'purchase']);
        unset($overrides['type_name']);

        return Transaction::create(array_merge([
            'party_id' => $party->id,
            'transaction_type_id' => $type->id,
            'direction' => $direction,
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->purity22k->id,
            'weight_grams' => 10,
            'rate_per_gram' => $amount / 10,
            'metal_cost' => $amount,
            'total_amount' => $amount,
            'transaction_date' => now(),
            'created_by_user_id' => $this->user->id,
        ], $overrides));
    }

    public function test_guests_cannot_access_parties_or_ledger(): void
    {
        $party = $this->makeParty();

        $this->get('/parties')->assertRedirect('/login');
        $this->getJson("/api/v1/parties/{$party->id}/ledger")->assertStatus(401);
    }

    public function test_index_lists_and_filters_parties(): void
    {
        $this->makeParty(['name' => 'Ahmed Khan', 'party_type_id' => $this->customerType->id]);
        $this->makeParty(['name' => 'Ustad Bashir', 'party_type_id' => $this->karigarType->id, 'phone' => '03339988776']);

        $this->actingAs($this->user)->get('/parties')->assertOk();

        // Filter by type
        $this->actingAs($this->user)
            ->get('/parties?party_type_id=' . $this->karigarType->id)
            ->assertOk()
            ->assertSee('Ustad Bashir')
            ->assertDontSee('Ahmed Khan');

        // Search by phone fragment
        $this->actingAs($this->user)
            ->get('/parties?search=9988776')
            ->assertOk()
            ->assertSee('Ustad Bashir')
            ->assertDontSee('Ahmed Khan');
    }

    public function test_can_create_party(): void
    {
        $payload = [
            'party_type_id' => $this->karigarType->id,
            'name' => 'Rehmat Goldsmith',
            'phone' => '0345 1230000',
            'address' => 'Anarkali, Lahore',
            'notes' => 'Specialises in kundan work',
        ];

        $response = $this->actingAs($this->user)->post('/parties', $payload);

        $party = Party::where('name', 'Rehmat Goldsmith')->first();
        $this->assertNotNull($party);
        $response->assertRedirect('/parties');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('parties', [
            'name' => 'Rehmat Goldsmith',
            'party_type_id' => $this->karigarType->id,
            'phone' => '0345 1230000',
            'is_active' => true,
        ]);
    }

    public function test_create_validation_rejects_missing_name_bad_type_and_bad_phone(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/parties/create')
            ->post('/parties', [
                'party_type_id' => 999999,
                'name' => '',
                'phone' => 'not-a-phone!!',
            ]);

        $response->assertRedirect('/parties/create');
        $response->assertSessionHasErrors(['name', 'party_type_id', 'phone']);
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_can_update_party(): void
    {
        $party = $this->makeParty(['name' => 'Old Name']);

        $response = $this->actingAs($this->user)->put("/parties/{$party->id}", [
            'party_type_id' => $this->karigarType->id,
            'name' => 'New Name',
            'phone' => '03007777777',
            'address' => 'Updated address',
            'notes' => '',
            'is_active' => false,
        ]);

        $response->assertRedirect("/parties/{$party->id}");
        $this->assertDatabaseHas('parties', [
            'id' => $party->id,
            'name' => 'New Name',
            'party_type_id' => $this->karigarType->id,
            'is_active' => false,
        ]);
    }

    public function test_clean_party_can_be_deleted_but_one_with_dealings_cannot(): void
    {
        $clean = $this->makeParty(['name' => 'Deletable']);
        $this->actingAs($this->user)->delete("/parties/{$clean->id}")->assertRedirect('/parties');
        $this->assertDatabaseMissing('parties', ['id' => $clean->id]);

        $linked = $this->makeParty(['name' => 'Has Dealings']);
        $this->makeTransaction($linked, 'OUT', 50000);

        $this->actingAs($this->user)
            ->from("/parties/{$linked->id}")
            ->delete("/parties/{$linked->id}")
            ->assertRedirect("/parties/{$linked->id}");

        $this->assertDatabaseHas('parties', ['id' => $linked->id]);
    }

    public function test_ledger_sums_by_direction_and_keeps_a_running_balance(): void
    {
        $party = $this->makeParty(['name' => 'Ledger Party']);

        // A sale invoice (OUT) — grand_total is the source of truth.
        Invoice::create([
            'invoice_number' => 'INV-20260101-0001',
            'party_id' => $party->id,
            'invoice_date' => now()->subDays(3),
            'total_metal_cost' => 200000,
            'grand_total' => 206000,
            'payment_method' => 'cash',
            'created_by_user_id' => $this->user->id,
        ]);

        // A standalone buy-back / purchase from the party (IN), no invoice_id.
        $this->makeTransaction($party, 'IN', 50000, [
            'type_name' => 'buy_back',
            'transaction_date' => now()->subDay(),
        ]);

        // An exchange transaction that IS tied to an invoice must be ignored (no double count).
        $this->makeTransaction($party, 'IN', 999999, [
            'type_name' => 'old_gold_exchange',
            'invoice_id' => Invoice::first()->id,
        ]);

        $ledger = app(\App\Services\PartyLedgerService::class)->build($party->fresh());

        $this->assertEquals(206000.0, $ledger['total_out']);
        $this->assertEquals(50000.0, $ledger['total_in']);
        $this->assertEquals(156000.0, $ledger['balance']);
        $this->assertStringContainsString('Receivable', $ledger['balance_label']);

        // Two entries, chronological, with a carried balance.
        $this->assertCount(2, $ledger['entries']);
        $this->assertEquals(206000.0, $ledger['entries'][0]['debit']);
        $this->assertEquals(206000.0, $ledger['entries'][0]['balance']);
        $this->assertEquals(50000.0, $ledger['entries'][1]['credit']);
        $this->assertEquals(156000.0, $ledger['entries'][1]['balance']);
    }

    public function test_ledger_reports_payable_when_shop_owes_the_party(): void
    {
        $karigar = $this->makeParty(['name' => 'Ustad Bashir', 'party_type_id' => $this->karigarType->id]);

        $this->makeTransaction($karigar, 'OUT', 40000, ['type_name' => 'karigar_issue']);
        $this->makeTransaction($karigar, 'IN', 100000, ['type_name' => 'karigar_return']);

        $ledger = app(\App\Services\PartyLedgerService::class)->build($karigar->fresh());

        $this->assertEquals(-60000.0, $ledger['balance']);
        $this->assertStringContainsString('Payable', $ledger['balance_label']);
    }

    public function test_ledger_reconciles_shop_to_shop_transfers_by_direction(): void
    {
        $otherShopType = PartyType::create(['name' => 'Other Shop']);
        $shop = $this->makeParty(['name' => 'Noor Jewellers', 'party_type_id' => $otherShopType->id]);

        // We lend stock out (OUT — they owe us) then take some back (IN — reduces it).
        $this->makeTransaction($shop, 'OUT', 350000, [
            'type_name' => 'shop_transfer',
            'transaction_date' => now()->subDays(6),
        ]);
        $this->makeTransaction($shop, 'IN', 120000, [
            'type_name' => 'shop_transfer',
            'transaction_date' => now()->subDays(2),
        ]);

        $ledger = app(\App\Services\PartyLedgerService::class)->build($shop->fresh());

        $this->assertEquals(350000.0, $ledger['total_out']);
        $this->assertEquals(120000.0, $ledger['total_in']);
        $this->assertEquals(230000.0, $ledger['balance']);
        $this->assertStringContainsString('Receivable', $ledger['balance_label']);

        // Running balance carries correctly with no manual adjustment.
        $this->assertCount(2, $ledger['entries']);
        $this->assertEquals(350000.0, $ledger['entries'][0]['balance']);
        $this->assertEquals(230000.0, $ledger['entries'][1]['balance']);
    }

    public function test_ledger_api_endpoint_returns_json(): void
    {
        $party = $this->makeParty(['name' => 'Api Party']);
        $this->makeTransaction($party, 'OUT', 75000);

        $this->actingAs($this->user)
            ->getJson("/api/v1/parties/{$party->id}/ledger")
            ->assertOk()
            ->assertJsonPath('party.id', $party->id)
            ->assertJsonPath('ledger.total_out', 75000)
            ->assertJsonPath('ledger.balance', 75000);
    }
}
