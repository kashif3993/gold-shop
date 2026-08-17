<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
        ])->refresh();

        return (new ItemResource($item))
            ->response()
            ->setStatusCode(201);
    }
}
