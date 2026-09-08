<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    /** Paginated, filterable list of sale invoices. */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'payment_method', 'date_from', 'date_to']);

        $base = Invoice::query()
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('party', fn ($p) => $p->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->when($filters['payment_method'] ?? null, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('invoice_date', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('invoice_date', '<=', $d));

        $invoices = (clone $base)
            ->with('party:id,name,phone')
            ->withCount('lineItems')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Totals across the whole filtered set, not just the current page.
        $totals = (clone $base)
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as grand_total')
            ->selectRaw('COALESCE(SUM(total_discount), 0) as total_discount')
            ->selectRaw('COALESCE(SUM(total_exchange_deduction), 0) as total_exchange')
            ->first();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'paymentMethods' => ['cash', 'card', 'bank_transfer', 'credit'],
            'summary' => [
                'count' => (int) $totals->count,
                'grand_total' => (float) $totals->grand_total,
                'total_discount' => (float) $totals->total_discount,
                'total_exchange' => (float) $totals->total_exchange,
            ],
        ]);
    }

    /** Detailed, print-friendly single invoice (standalone page / deep link). */
    public function show(Invoice $invoice)
    {
        return Inertia::render('invoices/show', $this->detailPayload($invoice));
    }

    /** Same detail payload as JSON — used by the quick-view modal on the list. */
    public function data(Invoice $invoice)
    {
        return response()->json($this->detailPayload($invoice));
    }

    /** Build the full detail payload shared by the page and the modal. */
    private function detailPayload(Invoice $invoice): array
    {
        $invoice->load([
            'party.partyType',
            'createdBy:id,full_name',
            'lineItems' => fn ($q) => $q->orderBy('id'),
            'lineItems.item:id,item_code,item_type,metal_type_id,purity_id',
            'lineItems.item.metalType:id,name',
            'lineItems.purity:id,name',
            'transactions' => fn ($q) => $q->whereHas('transactionType', fn ($t) => $t->where('name', 'old_gold_exchange')),
            'transactions.metalType:id,name',
            'transactions.purity:id,name',
        ]);

        $subtotal = round(
            (float) $invoice->total_metal_cost
            + (float) $invoice->total_labour_cost
            + (float) $invoice->total_polish_cost,
            2
        );

        return [
            'invoice' => $invoice,
            'subtotal' => $subtotal,
            'exchangeLines' => $invoice->transactions->map(fn ($t) => [
                'id' => $t->id,
                'description' => trim(($t->metalType->name ?? '') . ' ' . ($t->purity->name ?? '') . ' scrap'),
                'weight_grams' => (float) $t->weight_grams,
                'rate_per_gram' => (float) $t->rate_per_gram,
                'deduction_percent' => (float) $t->deduction_percent_applied,
                'valuation' => (float) $t->total_amount,
            ])->values(),
            'shop' => $this->shopDetails(),
        ];
    }

    /**
     * Shop identity printed on the invoice header. Kept here (not a DB table)
     * until an Admin settings screen exists to manage it.
     */
    private function shopDetails(): array
    {
        return [
            'name' => config('app.name', 'Zar & Noor'),
            'tagline' => 'Jewellers — Gold & Silver',
            'address' => 'Main Bazaar, Rawalpindi, Pakistan',
            'phone' => '+92 300 0000000',
            'ntn' => null,
        ];
    }
}
