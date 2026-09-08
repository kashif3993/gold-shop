<?php

use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\PartyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/items/search', [ItemController::class, 'search']);
    Route::post('/items', [ItemController::class, 'store']);

    Route::get('/parties/{party}/ledger', [PartyController::class, 'ledger']);
});
