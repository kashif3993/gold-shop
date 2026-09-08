<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $customer;
    private MetalType $gold;
    private Purity $p22;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $type = PartyType::create(['name' => 'Customer']);
        $this->customer = Party::create(['name' => 'Ayesha', 'phone' => '03001234567', 'party_type_id' => $type->id]);
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->p22 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6]);
        TransactionType::create(['name' => 'sale']);
        TransactionType::create(['name' => 'old_gold_exchange']);
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        $invoice = Invoice::create(array_merge([
            'invoice_number' => 'INV-20260101-0001',
            'party_id' => $this->customer->id,
            'invoice_date' => '2026-01-01',
            'total_metal_cost' => 200000,
            'total_labour_cost' => 5000,
            'total_polish_cost' => 1000,
            'total_tax' => 0,
            'total_discount' => 6000,
            'discount_reason' => 'Loyalty',
            'total_exchange_deduction' => 0,
            'grand_total' => 200000,
            'payment_method' => 'cash',
            'created_by_user_id' => $this->user->id,
        ], $overrides));

        $item = Item::create([
            'item_code' => 'GLD-RNG-' . substr(md5($invoice->invoice_number), 0, 8),
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'net_weight_grams' => 10,
            'labour_cost' => 5000,
            'polish_cost' => 1000,
            'purchase_rate_per_gram' => 18000,
            'purchase_price' => 186000,
            'status' => 'sold',
            'created_by_user_id' => $this->user->id,
            'date_received' => '2025-12-01',
        ]);

        $txn = Transaction::create([
            'party_id' => $this->customer->id,
            'transaction_type_id' => TransactionType::where('name', 'sale')->value('id'),
            'direction' => 'OUT',
            'item_id' => $item->id,
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'weight_grams' => 10,
            'rate_per_gram' => 20000,
            'metal_cost' => 200000,
            'labour_cost' => 5000,
            'polish_cost' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'deduction_percent_applied' => 0,
            'total_amount' => 206000,
            'invoice_id' => $invoice->id,
            'payment_method' => 'cash',
            'transaction_date' => '2026-01-01',
            'created_by_user_id' => $this->user->id,
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'item_id' => $item->id,
            'transaction_id' => $txn->id,
            'weight_grams' => 10,
            'purity_id' => $this->p22->id,
            'rate_per_gram' => 20000,
            'metal_cost' => 200000,
            'labour_cost' => 5000,
            'polish_cost' => 1000,
            'line_total' => 206000,
        ]);

        return $invoice;
    }

    public function test_guests_cannot_access_invoices(): void
    {
        $this->get('/invoices')->assertRedirect('/login');
        $invoice = $this->makeInvoice();
        $this->get("/invoices/{$invoice->id}")->assertRedirect('/login');
    }

    public function test_index_lists_and_filters_invoices(): void
    {
        $this->makeInvoice(['invoice_number' => 'INV-20260101-0001', 'payment_method' => 'cash']);
        $this->makeInvoice(['invoice_number' => 'INV-20260115-0002', 'payment_method' => 'card', 'invoice_date' => '2026-01-15']);

        $this->actingAs($this->user)->get('/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/index')
                ->where('summary.count', 2)
                ->has('invoices.data', 2));

        // Filter by payment method
        $this->actingAs($this->user)->get('/invoices?payment_method=card')
            ->assertInertia(fn ($page) => $page->where('summary.count', 1)->has('invoices.data', 1));

        // Filter by customer name search
        $this->actingAs($this->user)->get('/invoices?search=Ayesha')
            ->assertInertia(fn ($page) => $page->where('summary.count', 2));

        // Filter by date range
        $this->actingAs($this->user)->get('/invoices?date_from=2026-01-10')
            ->assertInertia(fn ($page) => $page->where('summary.count', 1));
    }

    public function test_show_returns_line_items_and_totals_that_reconcile(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user)->get("/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/show')
                ->where('invoice.invoice_number', 'INV-20260101-0001')
                ->where('subtotal', 206000)
                ->has('invoice.line_items', 1)
                ->where('invoice.line_items.0.metal_cost', '200000.00')
                ->has('shop.name'));
    }

    public function test_data_endpoint_returns_invoice_detail_as_json(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user)->getJson("/invoices/{$invoice->id}/data")
            ->assertOk()
            ->assertJsonPath('invoice.invoice_number', 'INV-20260101-0001')
            ->assertJsonPath('subtotal', 206000)
            ->assertJsonCount(1, 'invoice.line_items')
            ->assertJsonPath('shop.name', config('app.name'));
    }

    public function test_data_endpoint_requires_auth(): void
    {
        $invoice = $this->makeInvoice();
        $this->getJson("/invoices/{$invoice->id}/data")->assertUnauthorized();
    }

    public function test_show_exposes_old_gold_exchange_lines(): void
    {
        $invoice = $this->makeInvoice(['total_exchange_deduction' => 90000, 'grand_total' => 110000]);

        Transaction::create([
            'party_id' => $this->customer->id,
            'transaction_type_id' => TransactionType::where('name', 'old_gold_exchange')->value('id'),
            'direction' => 'IN',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'weight_grams' => 5,
            'rate_per_gram' => 20000,
            'metal_cost' => 90000,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'deduction_percent_applied' => 10,
            'total_amount' => 90000,
            'invoice_id' => $invoice->id,
            'payment_method' => 'cash',
            'transaction_date' => '2026-01-01',
            'created_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)->get("/invoices/{$invoice->id}")
            ->assertInertia(fn ($page) => $page
                ->has('exchangeLines', 1)
                ->where('exchangeLines.0.valuation', 90000)
                ->where('exchangeLines.0.deduction_percent', 10));
    }
}
