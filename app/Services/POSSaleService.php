<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Transaction;
use App\Models\TransactionType;

/**
 * The actual "sell these items, create the invoice" logic — shared by the
 * normal POS checkout (cash/card/credit, paid instantly) and the Bank QR
 * flow (where the sale is only created once an admin confirms the transfer
 * arrived, via GoldRateService... err, via this same service, replaying the
 * cart payload that was snapshotted when the QR was first shown).
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

    public function __construct(private AuditLogger $audit) {}

    /**
     * Compute what this cart would cost, without touching the database.
     * Used both right before creating the real sale, and to show the
     * expected amount when a Bank QR payment attempt is started.
     *
     * @throws \InvalidArgumentException if an item is missing/not in stock
     */
    public function computeTotals(array $validated, bool $lockItems = false): array
    {
        $totalMetalCost = 0;
        $totalLabourCost = 0;
        $totalPolishCost = 0;

        $goldRate = (float) $validated['goldRate'];
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

            $metalCost = round($itemModel->net_weight_grams * $goldRate, 2);
            $labourCost = round((float) $itemModel->labour_cost, 2);
            $polishCost = round((float) $itemModel->polish_cost, 2);
            $lineTotal = round($metalCost + $labourCost + $polishCost, 2);

            $totalMetalCost += $metalCost;
            $totalLabourCost += $labourCost;
            $totalPolishCost += $polishCost;

            $validItems[] = [
                'model' => $itemModel,
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

        $totalExchangeValuation = 0;
        foreach ($validated['exchanges'] ?? [] as $exc) {
            $totalExchangeValuation += round((float) $exc['valuation'], 2);
        }
        $totalExchangeValuation = round($totalExchangeValuation, 2);

        $grandTotal = round($subtotal - $actualDiscount - $totalExchangeValuation, 2);

        return [
            'validItems' => $validItems,
            'totalMetalCost' => $totalMetalCost,
            'totalLabourCost' => $totalLabourCost,
            'totalPolishCost' => $totalPolishCost,
            'actualDiscount' => $actualDiscount,
            'totalExchangeValuation' => $totalExchangeValuation,
            'grandTotal' => $grandTotal,
        ];
    }

    /**
     * Create the invoice, sale/exchange transactions, and lock the items —
     * the one real "this sale happened" write. Must run inside a DB
     * transaction (the caller's), since it locks item rows.
     *
     * @throws \InvalidArgumentException if an item is missing/not in stock
     */
    public function createSale(array $validated, int $userId): Invoice
    {
        $totals = $this->computeTotals($validated, lockItems: true);

        $partyId = $this->resolveParty($validated);
        $goldRate = (float) $validated['goldRate'];
        $dbPaymentMethod = self::PAYMENT_METHOD_MAP[strtolower($validated['paymentMethod'])] ?? 'cash';

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
            'total_tax' => 0,
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
                'rate_per_gram' => $goldRate,
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
                'rate_per_gram' => $goldRate,
                'metal_cost' => $data['metalCost'],
                'labour_cost' => $data['labourCost'],
                'polish_cost' => $data['polishCost'],
                'line_total' => $data['lineTotal'],
            ]);
        }

        $exchangesData = $validated['exchanges'] ?? [];
        if (count($exchangesData) > 0) {
            $exchangeTransactionType = TransactionType::where('name', 'old_gold_exchange')->firstOrFail();

            foreach ($exchangesData as $exc) {
                $netWeight = $exc['weight_grams'] * (1 - ($exc['deduction_percent'] / 100));

                $exchangeItem = Item::create([
                    'item_code' => 'EXC-'.strtoupper(uniqid()),
                    'item_type' => 'old_gold',
                    'metal_type_id' => $exc['metal_type_id'],
                    'purity_id' => $exc['purity_id'],
                    'gross_weight_grams' => $exc['weight_grams'],
                    'stone_weight_grams' => 0,
                    'cutting_loss_grams' => $exc['weight_grams'] - $netWeight,
                    'labour_cost' => 0,
                    'polish_cost' => 0,
                    'purchase_rate_per_gram' => $goldRate,
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
                    'rate_per_gram' => $goldRate,
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

        return $invoice;
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
