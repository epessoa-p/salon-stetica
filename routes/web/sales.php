<?php

use App\Http\Controllers\Sales\SaleController;
use Illuminate\Support\Facades\Route;

// Módulo Ventas (POS)
Route::prefix('admin/sales')->name('sales.')->middleware('check-permission:sales.view')->group(function () {
    Route::get('/', [SaleController::class, 'index'])->name('index');
    Route::get('/pos', [SaleController::class, 'pos'])->name('pos')->middleware('check-permission:sales.create');
    Route::post('/', [SaleController::class, 'store'])->name('store')->middleware('check-permission:sales.create');
    Route::get('/{sale}', [SaleController::class, 'show'])->name('show');
    Route::post('/{sale}/void', [SaleController::class, 'void'])->name('void')->middleware('check-permission:sales.void');
});
