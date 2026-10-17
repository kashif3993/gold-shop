<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /** Record a payment against a due/partially-paid invoice — never more than what's actually still owed. */
    public function store(Request $request, Invoice $invoice)
    {
        $due = $invoice->amountDue();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $due)],
            'payment_method' => 'required|in:cash,card,bank_transfer,bank_qr',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($due <= 0) {
            return back()->with('error', 'This invoice is already fully paid.');
        }

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'paid_at' => now(),
            'recorded_by_user_id' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->audit->log(
            'invoice_payment',
            $invoice->id,
            'amount_paid',
            (string) ($due),
            (string) ($due - $validated['amount']),
            "Payment recorded against invoice {$invoice->invoice_number}",
            $request->user()->id,
        );

        return back()->with('success', "Payment of Rs {$payment->amount} recorded.");
    }
}
