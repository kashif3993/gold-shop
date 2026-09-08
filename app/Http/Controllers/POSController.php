<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePOSTransactionRequest;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function store(StorePOSTransactionRequest $request)
    {
        $validated = $request->validated();
        
        try {
            return DB::transaction(function () use ($validated) {
                // 1. Handle Party (Customer)
                $partyType = PartyType::where('name', 'Customer')->first();
                $partyId = null;
                
                if (!empty($validated['customer_phone'])) {
                    $party = Party::firstOrCreate(
                        ['phone' => $validated['customer_phone']],
                        [
                            'name' => !empty($validated['customer_name']) ? $validated['customer_name'] : 'Walk-in Customer',
                            'party_type_id' => $partyType?->id
                        ]
                    );
                    $partyId = $party->id;
                } else {
                    // Fallback to a default Walk-in Customer if no details provided, since party_id cannot be null
                    $party = Party::firstOrCreate(
                        ['name' => 'Walk-in Customer', 'phone' => '0000000000'],
                        [
                            'party_type_id' => $partyType?->id
                        ]
                    );
                    $partyId = $party->id;
                }

                // 2. Aggregate Totals & Lock Items
                $totalMetalCost = 0;
                $totalLabourCost = 0;
                $totalPolishCost = 0;
                $totalTax = 0;
                
                $goldRate = $validated['goldRate'];
                $discountAmount = $validated['discount'];
                $discountType = $validated['discountType'];
                
                $validItems = [];
                $itemsData = $validated['items'] ?? [];
                foreach ($itemsData as $cartItem) {
                    $itemModel = Item::where('id', $cartItem['id'])->lockForUpdate()->first();
                    
                    if (!$itemModel) {
                        throw new \InvalidArgumentException("Item not found or already sold.");
                    }
                    if ($itemModel->status !== 'in_stock') {
                        throw new \InvalidArgumentException("Item {$itemModel->item_code} is no longer in stock.");
                    }
                    
                    // Round every line component to 2dp here so the invoice totals below
                    // are the exact sum of what actually gets stored on each line item.
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
                        'lineTotal' => $lineTotal
                    ];
                }

                $subtotal = round($totalMetalCost + $totalLabourCost + $totalPolishCost, 2);

                if ($discountType === 'percentage') {
                    $actualDiscount = round(($subtotal * $discountAmount) / 100, 2);
                } else {
                    $actualDiscount = round((float) $discountAmount, 2);
                }

                $totalExchangeValuation = 0;
                $exchangesData = $validated['exchanges'] ?? [];
                foreach ($exchangesData as $exc) {
                    $totalExchangeValuation += round((float) $exc['valuation'], 2);
                }
                $totalExchangeValuation = round($totalExchangeValuation, 2);

                $grandTotal = round($subtotal - $actualDiscount - $totalExchangeValuation, 2);

                $paymentMethodMap = [
                    'cash' => 'cash',
                    'card' => 'card',
                    'transfer' => 'bank_transfer',
                    'bank_transfer' => 'bank_transfer',
                    'credit' => 'credit',
                ];
                $dbPaymentMethod = $paymentMethodMap[strtolower($validated['paymentMethod'])] ?? 'cash';

                // 3. Create Invoice
                $dateStr = now()->format('Ymd');
                $lastInvoice = Invoice::where('invoice_number', 'like', "INV-{$dateStr}-%")->latest('id')->first();
                $sequence = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, -4)) + 1 : 1;
                $invoiceNumber = sprintf("INV-%s-%04d", $dateStr, $sequence);

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'party_id' => $partyId,
                    'invoice_date' => now(),
                    'total_metal_cost' => $totalMetalCost,
                    'total_labour_cost' => $totalLabourCost,
                    'total_polish_cost' => $totalPolishCost,
                    'total_tax' => $totalTax,
                    'total_discount' => $actualDiscount,
                    'discount_reason' => $validated['discount_reason'] ?? null,
                    'total_exchange_deduction' => $totalExchangeValuation,
                    'grand_total' => $grandTotal,
                    'payment_method' => $dbPaymentMethod,
                    'created_by_user_id' => auth()->id(),
                ]);

                // 4. Create Transactions & Line Items
                $saleTransactionType = TransactionType::where('name', 'sale')->firstOrFail();

                foreach ($validItems as $data) {
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
                        'created_by_user_id' => auth()->id(),
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

                // 5. Create Exchange Items & Transactions
                if (count($exchangesData) > 0) {
                    $exchangeTransactionType = TransactionType::where('name', 'old_gold_exchange')->firstOrFail();

                    foreach ($exchangesData as $exc) {
                        // Create item for tracking physical gold scrap
                        $netWeight = $exc['weight_grams'] * (1 - ($exc['deduction_percent'] / 100));
                        
                        $exchangeItem = Item::create([
                            'item_code' => 'EXC-' . strtoupper(uniqid()),
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
                            'created_by_user_id' => auth()->id(),
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
                            'metal_cost' => $exc['valuation'], // Using valuation as metal cost here
                            'labour_cost' => 0,
                            'polish_cost' => 0,
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                            'deduction_percent_applied' => $exc['deduction_percent'],
                            'total_amount' => $exc['valuation'],
                            'invoice_id' => $invoice->id,
                            'payment_method' => $dbPaymentMethod,
                            'transaction_date' => now(),
                            'created_by_user_id' => auth()->id(),
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'invoice' => $invoice->load('lineItems', 'party')
                ], 201);
            });
        } catch (\InvalidArgumentException $e) {
            // These are safe to show to the user
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            // Prevent raw exception strings from leaking sensitive DB structure/details
            \Illuminate\Support\Facades\Log::error("POS Transaction Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the transaction. Please try again.'
            ], 500);
        }
    }
}
