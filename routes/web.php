<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DailyRateController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\PurityController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\V1\BankQrPaymentController;
use App\Http\Controllers\Api\V1\BuyBackController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\RateManagementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ZakatController;
use App\Models\DailyRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Setting;
use App\Services\GoldRateService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

    Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
    Route::get('pos', function (GoldRateService $rates) {
        return inertia('pos/index', [
            'metals' => MetalType::active()->get(),
            // Only purities of a metal the shop currently deals in — narrows
            // the old-gold exchange picker; item search itself is unaffected
            // since existing stock can still be sold regardless.
            'purities' => Purity::whereHas('metalType', fn ($q) => $q->where('is_active', true))->get(),
            // Keyed by purity_id — lets the POS screen price each cart item at
            // its own purity's live rate the moment it's added, instead of one
            // shared manually-typed number applied to everything.
            'currentRates' => $rates->currentRates()->map(fn ($r) => [
                'rate_per_gram' => (float) $r->rate_per_gram,
                'is_stale' => (bool) $r->is_stale,
            ]),
            'tax' => [
                'enabled' => Setting::bool('tax_enabled', false),
                'rate_percent' => (float) Setting::get('tax_rate_percent', 0),
            ],
            // For the printable quotation slip (see PosPrintSlip) — the same
            // identity already printed on invoices, plus which layout
            // (Admin → Settings → Receipt Printing) the Print button uses.
            'shop' => [
                'name' => Setting::get('shop_name', config('app.name', 'Zar & Noor')),
                'address' => Setting::get('shop_address'),
                'phone' => Setting::get('shop_phone'),
                'logo' => Setting::get('shop_logo'),
            ],
            'printerType' => Setting::get('receipt_printer_type', 'regular'),
        ]);
    })->name('pos.index');

    // Header bell — polled every so often (see NotificationBell), computed
    // fresh each call from live data (pending Bank QR, stock, rate staleness).
    Route::get('api/v1/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Parties (Customer / Karigar / Wholesaler / Other Shop / Company)
    Route::get('parties', [PartyController::class, 'index'])->name('parties.index');
    Route::get('parties/create', [PartyController::class, 'create'])->name('parties.create');
    Route::post('parties', [PartyController::class, 'store'])->name('parties.store');
    Route::get('parties/{party}', [PartyController::class, 'show'])->name('parties.show');
    Route::get('parties/{party}/edit', [PartyController::class, 'edit'])->name('parties.edit');
    Route::put('parties/{party}', [PartyController::class, 'update'])->name('parties.update');
    Route::delete('parties/{party}', [PartyController::class, 'destroy'])->name('parties.destroy');

    // Rate Management
    Route::get('api/v1/rates/current', [RateController::class, 'current'])->name('rates.current');
    Route::get('rate-management', [RateManagementController::class, 'index'])->name('rate-management.index');
    Route::post('rate-management/refresh', [RateManagementController::class, 'refresh'])->name('rate-management.refresh');
    Route::post('rate-management/fx', [RateManagementController::class, 'saveFx'])->name('rate-management.fx');
    Route::post('rate-management/manual', [RateManagementController::class, 'saveManual'])->name('rate-management.manual');
    Route::post('rate-management/adjustment', [RateManagementController::class, 'saveAdjustment'])->name('rate-management.adjustment.save');
    Route::delete('rate-management/adjustment/{adjustment}', [RateManagementController::class, 'deleteAdjustment'])->name('rate-management.adjustment.delete');

    // Admin Panel (role=admin only): purity management, full rate history, manual overrides
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');

        Route::get('purities', [PurityController::class, 'index'])->name('purities.index');
        Route::post('purities', [PurityController::class, 'store'])->name('purities.store');
        Route::put('purities/{purity}', [PurityController::class, 'update'])->name('purities.update');
        Route::post('purities/{purity}/toggle', [PurityController::class, 'toggle'])->name('purities.toggle');

        Route::get('daily-rates', [DailyRateController::class, 'index'])->name('daily-rates.index');

        Route::post('rate-management/override', [RateManagementController::class, 'overrideRate'])->name('rate-management.override');

        Route::get('payment', [PaymentSettingsController::class, 'edit'])->name('payment.edit');
        Route::post('payment', [PaymentSettingsController::class, 'update'])->name('payment.update');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups/run', [BackupController::class, 'run'])->name('backups.run');
        Route::get('backups/{disk}/{path}/download', [BackupController::class, 'download'])->name('backups.download');

        Route::get('settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [SystemSettingsController::class, 'update'])->name('settings.update');
    });

    // Confirming a Bank QR payment: admin always, or a manager once an admin
    // has granted it (Admin → Settings) — see EnsureCanConfirmBankQr. Kept
    // out of the blanket 'admin' group above so this one action can be
    // opened to managers without exposing the rest of /admin to them.
    Route::middleware(['bank-qr-confirm'])->prefix('admin')->name('admin.')->group(function () {
        Route::post('pos/bank-qr/{reference}/confirm', [BankQrPaymentController::class, 'confirm'])->name('pos.bank-qr.confirm');
        Route::post('pos/bank-qr/{reference}/scan-slip', [BankQrPaymentController::class, 'scanSlip'])->name('pos.bank-qr.scan-slip');
        Route::get('pos/bank-qr/{reference}/slip', [BankQrPaymentController::class, 'slip'])->name('pos.bank-qr.slip');
    });

    // Reports (sales / profit / stock valuation — server-computed)
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    // Zakat — shop-stock report + a manual calculator, both priced live off
    // the current rate (config/Admin → Settings controls the rate % and Nisab).
    Route::get('zakat', [ZakatController::class, 'index'])->middleware('feature:zakat')->name('zakat.index');

    // Invoices (read-only — created by the POS module)
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}/data', [InvoiceController::class, 'data'])->name('invoices.data');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}/tag', [ItemController::class, 'tag'])->name('items.tag');
    Route::get('items/{item}/slip', [ItemController::class, 'slip'])->name('items.slip');
    Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

    // POS API Routes
    Route::post('api/v1/pos/transaction', [POSController::class, 'store'])->name('pos.transaction.store');
    Route::get('api/v1/items/search', [App\Http\Controllers\Api\V1\ItemController::class, 'search'])->name('items.search');

    // Bank QR payment — starting an attempt and checking its status are open to
    // any cashier; confirming it is admin-only (see the admin group above).
    // Starting a new attempt is gated by the feature toggle; an attempt already
    // in flight can still be checked/confirmed even if it's switched off mid-sale.
    Route::post('api/v1/pos/bank-qr', [BankQrPaymentController::class, 'start'])->middleware('feature:bank_qr')->name('pos.bank-qr.start');
    Route::get('api/v1/pos/bank-qr/{reference}', [BankQrPaymentController::class, 'show'])->name('pos.bank-qr.show');

    // Buy-Back — the whole module is gated by the feature toggle (Admin → Settings).
    Route::middleware('feature:buyback')->group(function () {
        Route::get('buyback', function () {
            return inertia('buyback/index', [
                'metals' => MetalType::active()->get(),
                'purities' => Purity::whereHas('metalType', fn ($q) => $q->where('is_active', true))->get(),
                'currentRates' => DailyRate::where('is_current', true)
                    ->pluck('rate_per_gram', 'purity_id'),
            ]);
        })->name('buyback.index');
        Route::get('api/v1/buyback/deduction', [BuyBackController::class, 'resolveDeduction'])->name('buyback.deduction');
        Route::get('api/v1/buyback/lookup-sale', [BuyBackController::class, 'lookupOriginalSale'])->name('buyback.lookup-sale');
        Route::post('api/v1/buyback', [BuyBackController::class, 'store'])->name('buyback.store');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
