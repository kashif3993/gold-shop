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
    Route::post('items', [\App\Http\Controllers\ItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}/edit', [\App\Http\Controllers\ItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{item}', [\App\Http\Controllers\ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [\App\Http\Controllers\ItemController::class, 'destroy'])->name('items.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
