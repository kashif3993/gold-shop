<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $type = PartyType::create(['name' => 'Customer']);
        $customer = Party::create(['name' => 'Ayesha', 'phone' => '03001234567', 'party_type_id' => $type->id]);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-20260101-0001',
            'party_id' => $customer->id,
            'invoice_date' => '2026-01-01',
            'total_metal_cost' => 100000,
            'total_labour_cost' => 0,
            'total_polish_cost' => 0,
            'total_tax' => 0,
            'total_discount' => 0,
            'total_exchange_deduction' => 0,
            'grand_total' => 100000,
            'payment_method' => 'cash',
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_guests_cannot_record_a_payment(): void
    {
        $this->postJson("/invoices/{$this->invoice->id}/payments", ['amount' => 1000, 'payment_method' => 'cash'])
            ->assertStatus(401);
    }

    public function test_a_partial_payment_is_recorded_and_updates_the_running_balance(): void
    {
        $this->actingAs($this->user)->post("/invoices/{$this->invoice->id}/payments", [
            'amount' => 40000,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->invoice->refresh();
        $this->assertEquals(40000, $this->invoice->amountPaid());
        $this->assertEquals(60000, $this->invoice->amountDue());
        $this->assertEquals('partial', $this->invoice->paymentStatus());
    }

    public function test_multiple_payments_accumulate_into_a_full_history(): void
    {
        $this->actingAs($this->user)->post("/invoices/{$this->invoice->id}/payments", ['amount' => 30000, 'payment_method' => 'cash']);
        $this->actingAs($this->user)->post("/invoices/{$this->invoice->id}/payments", ['amount' => 70000, 'payment_method' => 'card']);

        $this->invoice->refresh();
        $this->assertEquals(2, $this->invoice->payments()->count());
        $this->assertEquals(100000, $this->invoice->amountPaid());
        $this->assertEquals('paid', $this->invoice->paymentStatus());
    }

    public function test_a_payment_larger_than_the_remaining_due_is_rejected(): void
    {
        $this->actingAs($this->user)->post("/invoices/{$this->invoice->id}/payments", ['amount' => 60000, 'payment_method' => 'cash']);

        // Only 40,000 is left due — asking for 50,000 more must fail.
        $this->actingAs($this->user)->postJson("/invoices/{$this->invoice->id}/payments", [
            'amount' => 50000,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->invoice->refresh();
        $this->assertEquals(60000, $this->invoice->amountPaid());
    }

    public function test_recording_a_payment_on_an_already_paid_invoice_is_rejected(): void
    {
        $this->actingAs($this->user)->post("/invoices/{$this->invoice->id}/payments", ['amount' => 100000, 'payment_method' => 'cash']);

        $this->actingAs($this->user)->postJson("/invoices/{$this->invoice->id}/payments", [
            'amount' => 1,
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }
}
