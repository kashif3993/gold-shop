<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartBankQrPaymentRequest;
use App\Models\BankQrPayment;
use App\Models\Setting;
use App\Services\POSSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Pay by scanning our bank QR" — the shop's own static account QR (see
 * Admin\PaymentSettingsController) is shown with a per-sale reference and
 * amount. No bank/PSP is integrated, so confirming a payment arrived is a
 * manual, admin-only step (see confirm()) — this controller only handles
 * the parts any cashier can do: opening an attempt and checking its status.
 */
class BankQrPaymentController extends Controller
{
    public function __construct(private POSSaleService $sales) {}

    /** Open a payment attempt: snapshot the cart, reserve a reference, don't touch stock yet. */
    public function start(StartBankQrPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['paymentMethod'] = 'bank_qr';

        try {
            // Just a stock sanity check for a nicer error now — the real,
            // lock-guarded check happens again at confirm() time.
            $totals = $this->sales->computeTotals($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        $expiryMinutes = (int) config('services.bank_qr.expiry_minutes', 15);

        $payment = BankQrPayment::create([
            'reference' => $this->generateReference(),
            'amount' => $totals['grandTotal'],
            'cart_snapshot' => $validated,
            'status' => 'pending',
            'created_by_user_id' => auth()->id(),
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        return response()->json([
            'success' => true,
            'payment' => $this->present($payment),
        ], 201);
    }

    /** Poll the current state of an attempt — used both by the payer's screen and to detect an admin confirming elsewhere. */
    public function show(string $reference): JsonResponse
    {
        $payment = BankQrPayment::where('reference', $reference)->firstOrFail();

        if ($payment->status === 'pending' && $payment->isExpired()) {
            $payment->update(['status' => 'expired']);
        }

        return response()->json(['success' => true, 'payment' => $this->present($payment)]);
    }

    /**
     * Admin-only (enforced by the 'admin' route middleware): the cashier has
     * checked their own bank app and seen the transfer land — this records
     * who vouched for it and actually creates the sale. One-way: a payment
     * already Confirmed or Expired can never be confirmed again.
     */
    public function confirm(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate([
            'bank_txn_id' => 'required|string|max:100',
        ]);

        $payment = BankQrPayment::where('reference', $reference)->firstOrFail();

        if ($payment->status === 'confirmed') {
            return response()->json(['success' => false, 'message' => 'This payment was already confirmed.'], 409);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => 'expired']);

            return response()->json(['success' => false, 'message' => 'This payment window expired — start a new attempt.'], 410);
        }

        try {
            $invoice = DB::transaction(function () use ($payment, $data) {
                $invoice = $this->sales->createSale($payment->cart_snapshot, auth()->id());

                $payment->update([
                    'status' => 'confirmed',
                    'bank_txn_id' => $data['bank_txn_id'],
                    'invoice_id' => $invoice->id,
                    'confirmed_by_user_id' => auth()->id(),
                    'confirmed_at' => now(),
                ]);

                return $invoice;
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Bank QR confirm error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Could not confirm this payment. Please try again.'], 500);
        }

        // Who confirmed, when, and the transaction ID they entered are already
        // permanently recorded on $payment itself (confirmed_by_user_id,
        // confirmed_at, bank_txn_id) — that row is this action's audit trail,
        // the same way RateFetchLog is its own log rather than going through
        // the generic audit_log table.

        return response()->json([
            'success' => true,
            'payment' => $this->present($payment->fresh()),
            'invoice' => $invoice->load('lineItems', 'party'),
        ], 200);
    }

    private function generateReference(): string
    {
        $dateStr = now()->format('Ymd');
        $last = BankQrPayment::where('reference', 'like', "BQR-{$dateStr}-%")->latest('id')->first();
        $sequence = $last ? ((int) substr($last->reference, -4)) + 1 : 1;

        return sprintf('BQR-%s-%04d', $dateStr, $sequence);
    }

    private function present(BankQrPayment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'expires_at' => $payment->expires_at->toIso8601String(),
            'confirmed_at' => optional($payment->confirmed_at)->toIso8601String(),
            'bank_txn_id' => $payment->bank_txn_id,
            'confirmed_by' => $payment->confirmedBy?->username,
            'invoice_number' => $payment->invoice?->invoice_number,
            'bank' => [
                'qr_image' => Setting::get('bank_qr_image'),
                'account_title' => Setting::get('bank_account_title'),
                'bank_name' => Setting::get('bank_name'),
            ],
        ];
    }
}
