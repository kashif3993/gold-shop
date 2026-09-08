<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function store(StoreItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $gross = (float) ($validated['gross_weight_grams'] ?? 0);
        $stone = (float) ($validated['stone_weight_grams'] ?? 0);
        $cutting = (float) ($validated['cutting_loss_grams'] ?? 0);
        $net = max(0, $gross - $stone - $cutting);

        $rate = (float) ($validated['purchase_rate_per_gram'] ?? 0);
        $labour = (float) ($validated['labour_cost'] ?? 0);
        $polish = (float) ($validated['polish_cost'] ?? 0);

        // Server-side purchase price locking
        $validated['purchase_price'] = ($net * $rate) + $labour + $polish;
        $validated['created_by_user_id'] = $request->user()->id;

        $item = Item::create($validated)->refresh();

        return (new ItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['data' => []]);
        }

        $items = Item::with(['metalType', 'purity'])
            ->where('status', 'in_stock')
            ->where(function ($q) use ($query) {
                $q->where('item_code', 'like', "%{$query}%")
                  ->orWhere('qr_payload', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return ItemResource::collection($items)->response();
    }
}
