<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Support\Collection;

/**
 * The actual "sell these items, create the invoice" logic — shared by the
 * normal POS checkout (cash/card/credit, paid instantly) and the Bank QR
 * flow (where the sale is only created once an admin confirms the transfer
 * arrived, replaying the cart payload that was snapshotted when the QR was
 * first shown).
 *
 * Pricing is never trusted from the client: every item and every old-gold
 * exchange line is priced here from Rate Management's current rate for its
 * own purity — a 22K ring and a 24K bar in the same cart each get their own
 * correct number, not one rate typed into a box and applied to everything.
 */
class POSSaleService
{
    private const PAYMENT_METHOD_MAP = [
        'cash' => 'cash',
        'card' => 'card',
        'transfer' => 'bank_transfer',
        'bank_transfer' => 'bank_transfer',
        'bank_qr' => 'bank_transfer',
        'credit' => 'credit',
    ];

    public function __construct(private AuditLogger $audit, private GoldRateService $rates) {}

    /**
     * Compute what this cart would cost, without touching the database.
     * Used both right before creating the real sale, and to show the
     * expected amount when a Bank QR payment attempt is started.
     *
     * @throws \InvalidArgumentException if an item is missing/not in stock,
     *                                   or has no current rate set for its purity
     */
    public function computeTotals(array $validated, bool $lockItems = false): array
    {
        $currentRates = $this->rates->currentRates(); // keyed by purity_id

        $totalMetalCost = 0;
        $totalLabourCost = 0;
        $totalPolishCost = 0;
        $validItems = [];

        foreach ($validated['items'] ?? [] as $cartItem) {
            $query = Item::where('id', $cartItem['id']);
            if ($lockItems) {
                $query->lockForUpdate();
            }
            $itemModel = $query->first();

            if (! $itemModel) {
                throw new \InvalidArgumentException('Item not found or already sold.');
            }
            if ($itemModel->status !== 'in_stock') {
                throw new \InvalidArgumentException("Item {$itemModel->item_code} is no longer in stock.");
            }

            $detectedRate = $this->ratePerGramFor($currentRates, $itemModel->purity_id, $itemModel->item_code);

            // A shopkeeper can negotiate a line's rate for a specific sale —
            // the detected rate stays the baseline everyone sees, an override
            // is a deliberate deviation from it and always audited below.
            $overrideRate = isset($cartItem['rate_override']) ? (float) $cartItem['rate_override'] : null;
            $ratePerGram = $overrideRate ?? $detectedRate;

            $metalCost = round($itemModel->net_weight_grams * $ratePerGram, 2);
            $labourCost = round((float) $itemModel->labour_cost, 2);
            $polishCost = round((float) $itemModel->polish_cost, 2);
            $lineTotal = round($metalCost + $labourCost + $polishCost, 2);

            $totalMetalCost += $metalCost;
            $totalLabourCost += $labourCost;
            $totalPolishCost += $polishCost;

            $validItems[] = [
                'model' => $itemModel,
                'ratePerGram' => $ratePerGram,
                'detectedRate' => $detectedRate,
                'overridden' => $overrideRate !== null && round($overrideRate, 2) !== round($detectedRate, 2),
                'metalCost' => $metalCost,
                'labourCost' => $labourCost,
                'polishCost' => $polishCost,
                'lineTotal' => $lineTotal,
            ];
        }

        $subtotal = round($totalMetalCost + $totalLabourCost + $totalPolishCost, 2);

        $discountAmount = (float) ($validated['discount'] ?? 0);
        $actualDiscount = ($validated['discountType'] ?? 'flat') === 'percentage'
            ? round(($subtotal * $discountAmount) / 100, 2)
            : round($discountAmount, 2);

        if (! empty($validated['exchanges']) && ! Setting::bool('exchange_enabled', true)) {
            throw new \InvalidArgumentException('Old gold/silver exchange is currently turned off — an admin can re-enable it from Settings.');
        }

        $validExchanges = [];
        $totalExchangeValuation = 0;
        foreach ($validated['exchanges'] ?? [] as $exc) {
            $ratePerGram = $this->ratePerGramFor($currentRates, (int) $exc['purity_id'], 'this exchange item');
            $netWeight = (float) $exc['weight_grams'] * (1 - ((float) $exc['deduction_percent'] / 100));
            $valuation = round($netWeight * $ratePerGram, 2);

            $totalExchangeValuation += $valuation;

            $validExchanges[] = [
                'metal_type_id' => $exc['metal_type_id'],
                'purity_id' => $exc['purity_id'],
                'weight_grams' => (float) $exc['weight_grams'],
                'deduction_percent' => (float) $exc['deduction_percent'],
                'net_weight_grams' => $netWeight,
                'ratePerGram' => $ratePerGram,
                'valuation' => $valuation,
            ];
        }
        $totalExchangeValuation = round($totalExchangeValuation, 2);

        // Tax (Admin → Settings) applies to the taxable sale amount — after
        // discount, before the old-gold exchange deduction, since an exchange
        // is a separate trade-in being netted against the bill, not a price cut.
        $taxRatePercent = Setting::bool('tax_enabled', false) ? (float) Setting::get('tax_rate_percent', 0) : 0.0;
        $taxableAmount = max(0, $subtotal - $actualDiscount);
        $taxAmount = $taxRatePercent > 0 ? round($taxableAmount * $taxRatePercent / 100, 2) : 0.0;

        $grandTotal = round($subtotal - $actualDiscount + $taxAmount - $totalExchangeValuation, 2);

        return [
            'validItems' => $validItems,
            'validExchanges' => $validExchanges,
            'totalMetalCost' => $totalMetalCost,
            'totalLabourCost' => $totalLabourCost,
            'totalPolishCost' => $totalPolishCost,
            'actualDiscount' => $actualDiscount,
            'taxRatePercent' => $taxRatePercent,
            'taxAmount' => $taxAmount,
            'totalExchangeValuation' => $totalExchangeValuation,
            'grandTotal' => $grandTotal,
        ];
    }

    /**
     * Create the invoice, sale/exchange transactions, and lock the items —
     * the one real "this sale happened" write. Must run inside a DB
     * transaction (the caller's), since it locks item rows.
     *
     * @throws \InvalidArgumentException if an item is missing/not in stock,
     *                                   or has no current rate set for its purity
     */
    public function createSale(array $validated, int $userId): Invoice
    {
        $totals = $this->computeTotals($validated, lockItems: true);

        $partyId = $this->resolveParty($validated);
        $dbPaymentMethod = self::PAYMENT_METHOD_MAP[strtolower($validated['paymentMethod'])] ?? 'cash';

        // Bank QR is always paid in full by its own confirm flow — the
        // partial-payment field only applies to Cash/Card/Transfer. Anything
        // else defaults to the full total, same as before this existed.
        $isBankQr = strtolower($validated['paymentMethod']) === 'bank_qr' || strtolower($validated['paymentMethod']) === 'bank qr';
        $amountPaid = $isBankQr ? $totals['grandTotal'] : (float) ($validated['amount_paid'] ?? $totals['grandTotal']);

        if ($amountPaid < 0 || $amountPaid > $totals['grandTotal']) {
            throw new \InvalidArgumentException(
                "Amount paid (Rs {$amountPaid}) must be between 0 and the grand total (Rs {$totals['grandTotal']})."
            );
        }

        $dateStr = now()->format('Ymd');
        $lastInvoice = Invoice::where('invoice_number', 'like', "INV-{$dateStr}-%")->latest('id')->first();
        $sequence = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, -4)) + 1 : 1;
        $invoiceNumber = sprintf('INV-%s-%04d', $dateStr, $sequence);

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'party_id' => $partyId,
            'invoice_date' => now(),
            'total_metal_cost' => $totals['totalMetalCost'],
            'total_labour_cost' => $totals['totalLabourCost'],
            'total_polish_cost' => $totals['totalPolishCost'],
            'total_tax' => $totals['taxAmount'],
            'total_discount' => $totals['actualDiscount'],
            'discount_reason' => $validated['discount_reason'] ?? null,
            'total_exchange_deduction' => $totals['totalExchangeValuation'],
            'grand_total' => $totals['grandTotal'],
            'payment_method' => $dbPaymentMethod,
            'created_by_user_id' => $userId,
        ]);

        if ($totals['actualDiscount'] > 0) {
            $this->audit->log(
                'discount',
                $invoice->id,
                'total_discount',
                null,
                (string) $totals['actualDiscount'],
                $validated['discount_reason'] ?? 'No reason provided',
                $userId,
            );
        }

        $saleTransactionType = TransactionType::where('name', 'sale')->firstOrFail();

        foreach ($totals['validItems'] as $data) {
            $item = $data['model'];
            $item->update(['status' => 'sold']);

            $transaction = Transaction::create([
                'party_id' => $partyId,
                'transaction_type_id' => $saleTransactionType->id,
                'direction' => 'OUT',
                'item_id' => $item->id,
                'metal_type_id' => $item->metal_type_id,
                'purity_id' => $item->purity_id,
                'weight_grams' => $item->net_weight_grams,
                'rate_per_gram' => $data['ratePerGram'],
                'metal_cost' => $data['metalCost'],
                'labour_cost' => $data['labourCost'],
                'polish_cost' => $data['polishCost'],
                'tax_amount' => 0,
                'discount_amount' => 0,
                'deduction_percent_applied' => 0,
                'total_amount' => $data['lineTotal'],
                'invoice_id' => $invoice->id,
                'payment_method' => $dbPaymentMethod,
                'transaction_date' => now(),
                'created_by_user_id' => $userId,
            ]);

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'transaction_id' => $transaction->id,
                'weight_grams' => $item->net_weight_grams,
                'purity_id' => $item->purity_id,
                'rate_per_gram' => $data['ratePerGram'],
                'metal_cost' => $data['metalCost'],
                'labour_cost' => $data['labourCost'],
                'polish_cost' => $data['polishCost'],
                'line_total' => $data['lineTotal'],
            ]);

            if ($data['overridden']) {
                $this->audit->log(
                    'item_price',
                    $item->id,
                    'rate_per_gram',
                    (string) $data['detectedRate'],
                    (string) $data['ratePerGram'],
                    "Manual rate override during sale (invoice {$invoice->invoice_number})",
                    $userId,
                );
            }
        }

        if (count($totals['validExchanges']) > 0) {
            $exchangeTransactionType = TransactionType::where('name', 'old_gold_exchange')->firstOrFail();

            foreach ($totals['validExchanges'] as $exc) {
                $exchangeItem = Item::create([
                    'item_code' => 'EXC-'.strtoupper(uniqid()),
                    'item_type' => 'old_gold',
                    'metal_type_id' => $exc['metal_type_id'],
                    'purity_id' => $exc['purity_id'],
                    'gross_weight_grams' => $exc['weight_grams'],
                    'stone_weight_grams' => 0,
                    'cutting_loss_grams' => $exc['weight_grams'] - $exc['net_weight_grams'],
                    'labour_cost' => 0,
                    'polish_cost' => 0,
                    'purchase_rate_per_gram' => $exc['ratePerGram'],
                    'purchase_price' => $exc['valuation'],
                    'source_party_id' => $partyId,
                    'date_received' => now(),
                    'status' => 'bought_back',
                    'created_by_user_id' => $userId,
                ]);

                Transaction::create([
                    'party_id' => $partyId,
                    'transaction_type_id' => $exchangeTransactionType->id,
                    'direction' => 'IN',
                    'item_id' => $exchangeItem->id,
                    'metal_type_id' => $exc['metal_type_id'],
                    'purity_id' => $exc['purity_id'],
                    'weight_grams' => $exc['weight_grams'],
                    'rate_per_gram' => $exc['ratePerGram'],
                    'metal_cost' => $exc['valuation'],
                    'labour_cost' => 0,
                    'polish_cost' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'deduction_percent_applied' => $exc['deduction_percent'],
                    'total_amount' => $exc['valuation'],
                    'invoice_id' => $invoice->id,
                    'payment_method' => $dbPaymentMethod,
                    'transaction_date' => now(),
                    'created_by_user_id' => $userId,
                ]);
            }
        }

        // Only a real payment creates a row — a due invoice with nothing
        // paid yet starts with zero rows in its payment history, not a
        // zero-amount one.
        if ($amountPaid > 0) {
            // invoice_payments' method is how this one payment physically
            // arrived — 'credit' isn't a real payment method, so it can
            // never land here; the POS UI doesn't offer it as a tab anyway.
            $paymentRowMethod = $isBankQr ? 'bank_qr' : $dbPaymentMethod;
            if (! in_array($paymentRowMethod, ['cash', 'card', 'bank_transfer', 'bank_qr'], true)) {
                $paymentRowMethod = 'cash';
            }

            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amountPaid,
                'payment_method' => $paymentRowMethod,
                'paid_at' => now(),
                'recorded_by_user_id' => $userId,
            ]);
        }

        return $invoice;
    }

    /** @throws \InvalidArgumentException if no current rate is set for this purity */
    private function ratePerGramFor(Collection $currentRates, int $purityId, string $label): float
    {
        $rate = $currentRates->get($purityId);

        if (! $rate) {
            throw new \InvalidArgumentException("No current rate is set for {$label}'s purity — set one in Rate Management first.");
        }

        return (float) $rate->rate_per_gram;
    }

    private function resolveParty(array $validated): int
    {
        $partyType = PartyType::where('name', 'Customer')->first();

        if (! empty($validated['customer_phone'])) {
            $party = Party::firstOrCreate(
                ['phone' => $validated['customer_phone']],
                [
                    'name' => ! empty($validated['customer_name']) ? $validated['customer_name'] : 'Walk-in Customer',
                    'party_type_id' => $partyType?->id,
                ],
            );

            return $party->id;
        }

        $party = Party::firstOrCreate(
            ['name' => 'Walk-in Customer', 'phone' => '0000000000'],
            ['party_type_id' => $partyType?->id],
        );

        return $party->id;
    }
}
