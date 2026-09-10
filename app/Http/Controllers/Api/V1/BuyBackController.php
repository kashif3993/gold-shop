<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuyBackRequest;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Services\BuybackDeductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyBackController extends Controller
{
    public function __construct(
        protected BuybackDeductionService $deductionService
    ) {}

    /**
     * Resolve deduction percentage and calculate live valuation preview.
     */
    public function resolveDeduction(Request $request): JsonResponse
    {
        $purityId = $request->query('purity_id') ? (int) $request->query('purity_id') : null;
        $metalTypeId = $request->query('metal_type_id') ? (int) $request->query('metal_type_id') : null;
        $weightGrams = (float) $request->query('weight_grams', 0);
        $ratePerGram = (float) $request->query('rate_per_gram', 0);

        $deductionPercent = $this->deductionService->resolveDeductionPercent($purityId, $metalTypeId);

        $valuation = null;
        if ($weightGrams > 0 && $ratePerGram > 0) {
            $valuation = $this->deductionService->calculateValuation($weightGrams, $ratePerGram, $deductionPercent);
        }

        return response()->json([
            'deduction_percent' => $deductionPercent,
            'valuation' => $valuation,
        ]);
    }

    /**
     * Search original sale record for reference display only.
     */
    public function lookupOriginalSale(Request $request): JsonResponse
    {
        $query = $request->query('query');
        if (!$query) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        // Search by item_code or invoice_number
        $item = Item::with(['metalType', 'purity'])
            ->where('item_code', $query)
            ->first();

        $saleTransaction = null;
        if ($item) {
            $saleTransaction = Transaction::with(['invoice', 'party', 'metalType', 'purity'])
                ->where('item_id', $item->id)
                ->where('direction', 'OUT')
                ->latest('id')
                ->first();
        } else {
            // Try searching by invoice number
            $saleTransaction = Transaction::with(['invoice', 'party', 'item', 'metalType', 'purity'])
                ->whereHas('invoice', function ($q) use ($query) {
                    $q->where('invoice_number', $query);
                })
                ->where('direction', 'OUT')
                ->latest('id')
                ->first();
        }

        if (!$saleTransaction && !$item) {
            return response()->json(['message' => 'Original sale record not found'], 404);
        }

        return response()->json([
            'reference_only' => true,
            'disclaimer' => 'Original sale is for reference only. Buy-back valuation is computed independently based on re-weighed weight and current rates.',
            'item' => $item,
            'sale_transaction' => $saleTransaction,
        ]);
    }

    /**
     * Process buy-back transaction.
     */
    public function store(StoreBuyBackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated) {
                // 1. Resolve Customer / Party
                $partyId = $validated['party_id'] ?? null;
                if (!$partyId) {
                    $partyType = PartyType::where('name', 'Customer')->first();
                    if (!empty($validated['customer_phone'])) {
                        $party = Party::firstOrCreate(
                            ['phone' => $validated['customer_phone']],
                            [
                                'name' => !empty($validated['customer_name']) ? $validated['customer_name'] : 'Customer',
                                'party_type_id' => $partyType?->id,
                            ]
                        );
                        $partyId = $party->id;
                    } else {
                        $party = Party::firstOrCreate(
                            ['name' => 'Walk-in Customer', 'phone' => '0000000000'],
                            ['party_type_id' => $partyType?->id]
                        );
                        $partyId = $party->id;
                    }
                }

                // 2. Resolve Deduction % & Compute Valuation
                $purityId = (int) $validated['purity_id'];
                $metalTypeId = (int) $validated['metal_type_id'];
                $weightGrams = (float) $validated['weight_grams'];
                $ratePerGram = (float) $validated['rate_per_gram'];

                // The jeweller either cuts a fixed weight ("3 ratti") or applies a
                // percentage; the fixed cut wins when given, else fall back to the
                // supplied percentage, else resolve the shop's configured percentage.
                $deductionWeightGrams = isset($validated['deduction_weight_grams'])
                    ? (float) $validated['deduction_weight_grams']
                    : null;

                $deductionPercent = isset($validated['deduction_percent'])
                    ? (float) $validated['deduction_percent']
                    : $this->deductionService->resolveDeductionPercent($purityId, $metalTypeId);

                $valuation = $this->deductionService->calculateValuation(
                    $weightGrams,
                    $ratePerGram,
                    $deductionPercent,
                    $deductionWeightGrams
                );

                // Store the effective percentage (derived from the cut when weight-based).
                $deductionPercent = $valuation['deduction_percent'];
                $buybackTotal = $valuation['total_amount'];

                // 3. Payment Method normalization
                $paymentMethodMap = [
                    'cash' => 'cash',
                    'card' => 'card',
                    'transfer' => 'bank_transfer',
                    'bank_transfer' => 'bank_transfer',
                    'credit' => 'credit',
                ];
                $dbPaymentMethod = $paymentMethodMap[strtolower($validated['payment_method'])] ?? 'cash';

                // 4. Handle Item tracking
                $itemId = $validated['item_id'] ?? null;
                if ($itemId) {
                    $item = Item::find($itemId);
                    if ($item) {
                        $item->update([
                            'status' => 'bought_back',
                        ]);
                    }
                } else {
                    // Create new scrap / bought back item
                    $item = Item::create([
                        'item_code' => 'BB-' . strtoupper(uniqid()),
                        'item_type' => 'old_gold',
                        'metal_type_id' => $metalTypeId,
                        'purity_id' => $purityId,
                        'gross_weight_grams' => $weightGrams,
                        'stone_weight_grams' => 0,
                        'cutting_loss_grams' => round($weightGrams - $valuation['effective_weight_grams'], 3),
                        'net_weight_grams' => $valuation['effective_weight_grams'],
                        'labour_cost' => 0,
                        'polish_cost' => 0,
                        'purchase_rate_per_gram' => $ratePerGram,
                        'purchase_price' => $buybackTotal,
                        'source_party_id' => $partyId,
                        'date_received' => now()->toDateString(),
                        'status' => 'bought_back',
                        'created_by_user_id' => auth()->id(),
                    ]);
                    $itemId = $item->id;
                }

                // 5. Create Buy-Back Transaction
                $buybackType = TransactionType::where('name', 'buy_back')->firstOrFail();

                $transaction = Transaction::create([
                    'party_id' => $partyId,
                    'transaction_type_id' => $buybackType->id,
                    'direction' => 'IN',
                    'item_id' => $itemId,
                    'metal_type_id' => $metalTypeId,
                    'purity_id' => $purityId,
                    'weight_grams' => $weightGrams,
                    'rate_per_gram' => $ratePerGram,
                    'metal_cost' => $buybackTotal,
                    'labour_cost' => 0,
                    'polish_cost' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'deduction_percent_applied' => $deductionPercent,
                    'total_amount' => $buybackTotal,
                    'original_sale_transaction_id' => $validated['original_sale_transaction_id'] ?? null,
                    'payment_method' => $dbPaymentMethod,
                    'transaction_date' => now(),
                    'notes' => $validated['notes'] ?? null,
                    'created_by_user_id' => auth()->id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Buy-back transaction processed successfully.',
                    'transaction' => $transaction->load(['party', 'metalType', 'purity', 'originalSale']),
                    'valuation' => $valuation,
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Buy-Back Transaction Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the buy-back transaction.',
            ], 500);
        }
    }
}
