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

    // Rate Management
    Route::get('api/v1/rates/current', [\App\Http\Controllers\Api\V1\RateController::class, 'current'])->name('rates.current');
    Route::get('rate-management', [\App\Http\Controllers\RateManagementController::class, 'index'])->name('rate-management.index');
    Route::post('rate-management/refresh', [\App\Http\Controllers\RateManagementController::class, 'refresh'])->name('rate-management.refresh');
    Route::post('rate-management/fx', [\App\Http\Controllers\RateManagementController::class, 'saveFx'])->name('rate-management.fx');
    Route::post('rate-management/manual', [\App\Http\Controllers\RateManagementController::class, 'saveManual'])->name('rate-management.manual');
    Route::post('rate-management/adjustment', [\App\Http\Controllers\RateManagementController::class, 'saveAdjustment'])->name('rate-management.adjustment.save');
    Route::delete('rate-management/adjustment/{adjustment}', [\App\Http\Controllers\RateManagementController::class, 'deleteAdjustment'])->name('rate-management.adjustment.delete');

    // Admin Panel (role=admin only): purity management, full rate history, manual overrides
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'index'])->name('index');

        Route::get('purities', [\App\Http\Controllers\Admin\PurityController::class, 'index'])->name('purities.index');
        Route::post('purities', [\App\Http\Controllers\Admin\PurityController::class, 'store'])->name('purities.store');
        Route::put('purities/{purity}', [\App\Http\Controllers\Admin\PurityController::class, 'update'])->name('purities.update');
        Route::post('purities/{purity}/toggle', [\App\Http\Controllers\Admin\PurityController::class, 'toggle'])->name('purities.toggle');

        Route::get('daily-rates', [\App\Http\Controllers\Admin\DailyRateController::class, 'index'])->name('daily-rates.index');

        Route::post('rate-management/override', [\App\Http\Controllers\RateManagementController::class, 'overrideRate'])->name('rate-management.override');

        Route::get('payment', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'edit'])->name('payment.edit');
        Route::post('payment', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'update'])->name('payment.update');

        Route::post('pos/bank-qr/{reference}/confirm', [\App\Http\Controllers\Api\V1\BankQrPaymentController::class, 'confirm'])->name('pos.bank-qr.confirm');
    });

    // Reports (sales / profit / stock valuation — server-computed)
    Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

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

    // Bank QR payment — starting an attempt and checking its status are open to
    // any cashier; confirming it is admin-only (see the admin group above).
    Route::post('api/v1/pos/bank-qr', [\App\Http\Controllers\Api\V1\BankQrPaymentController::class, 'start'])->name('pos.bank-qr.start');
    Route::get('api/v1/pos/bank-qr/{reference}', [\App\Http\Controllers\Api\V1\BankQrPaymentController::class, 'show'])->name('pos.bank-qr.show');

    // Buy-Back
    Route::get('buyback', function () {
        return inertia('buyback/index', [
            'metals' => \App\Models\MetalType::all(),
            'purities' => \App\Models\Purity::all(),
            'currentRates' => \App\Models\DailyRate::where('is_current', true)
                ->pluck('rate_per_gram', 'purity_id'),
        ]);
    })->name('buyback.index');
    Route::get('api/v1/buyback/deduction', [\App\Http\Controllers\Api\V1\BuyBackController::class, 'resolveDeduction'])->name('buyback.deduction');
    Route::get('api/v1/buyback/lookup-sale', [\App\Http\Controllers\Api\V1\BuyBackController::class, 'lookupOriginalSale'])->name('buyback.lookup-sale');
    Route::post('api/v1/buyback', [\App\Http\Controllers\Api\V1\BuyBackController::class, 'store'])->name('buyback.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
