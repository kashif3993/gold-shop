<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    
    Route::get('inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
    
    Route::get('items/create', [\App\Http\Controllers\ItemController::class, 'create'])->name('items.create');
    Route::get('pos', function () {
        return inertia('pos/index', [
            'metals' => \App\Models\MetalType::all(),
            'purities' => \App\Models\Purity::all()
        ]);
    })->name('pos.index');

    // Parties (Customer / Karigar / Wholesaler / Other Shop / Company)
    Route::get('parties', [\App\Http\Controllers\PartyController::class, 'index'])->name('parties.index');
    Route::get('parties/create', [\App\Http\Controllers\PartyController::class, 'create'])->name('parties.create');
    Route::post('parties', [\App\Http\Controllers\PartyController::class, 'store'])->name('parties.store');
    Route::get('parties/{party}', [\App\Http\Controllers\PartyController::class, 'show'])->name('parties.show');
    Route::get('parties/{party}/edit', [\App\Http\Controllers\PartyController::class, 'edit'])->name('parties.edit');
    Route::put('parties/{party}', [\App\Http\Controllers\PartyController::class, 'update'])->name('parties.update');
    Route::delete('parties/{party}', [\App\Http\Controllers\PartyController::class, 'destroy'])->name('parties.destroy');

    // Invoices (read-only — created by the POS module)
    Route::get('invoices', [\App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}/data', [\App\Http\Controllers\InvoiceController::class, 'data'])->name('invoices.data');
    Route::get('invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'show'])->name('invoices.show');

    Route::post('items', [\App\Http\Controllers\ItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}/tag', [\App\Http\Controllers\ItemController::class, 'tag'])->name('items.tag');
    Route::get('items/{item}/edit', [\App\Http\Controllers\ItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{item}', [\App\Http\Controllers\ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [\App\Http\Controllers\ItemController::class, 'destroy'])->name('items.destroy');

    // POS API Routes
    Route::post('api/v1/pos/transaction', [\App\Http\Controllers\POSController::class, 'store'])->name('pos.transaction.store');
    Route::get('api/v1/items/search', [\App\Http\Controllers\Api\V1\ItemController::class, 'search'])->name('items.search');

    // Buy-Back Routes (API only for now — the React screen lands on Day 18)
    Route::get('api/v1/buyback/deduction', [\App\Http\Controllers\Api\V1\BuyBackController::class, 'resolveDeduction'])->name('buyback.deduction');
    Route::get('api/v1/buyback/lookup-sale', [\App\Http\Controllers\Api\V1\BuyBackController::class, 'lookupOriginalSale'])->name('buyback.lookup-sale');
    Route::post('api/v1/buyback', [\App\Http\Controllers\Api\V1\BuyBackController::class, 'store'])->name('buyback.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
