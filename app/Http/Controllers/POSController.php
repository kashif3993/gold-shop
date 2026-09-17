<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePOSTransactionRequest;
use App\Services\POSSaleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSController extends Controller
{
    public function __construct(private POSSaleService $sales) {}

    public function store(StorePOSTransactionRequest $request)
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated) {
                $invoice = $this->sales->createSale($validated, auth()->id());

                return response()->json([
                    'success' => true,
                    'invoice' => $invoice->load('lineItems', 'party'),
                ], 201);
            });
        } catch (\InvalidArgumentException $e) {
            // These are safe to show to the user
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            // Prevent raw exception strings from leaking sensitive DB structure/details
            Log::error('POS Transaction Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the transaction. Please try again.',
            ], 500);
        }
    }
}
