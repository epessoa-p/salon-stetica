<?php

use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\SaleController;
use Illuminate\Support\Facades\Route;

// Módulo Ventas (POS)
Route::prefix('admin/sales')->name('sales.')->middleware('check-permission:sales.view')->group(function () {
    Route::get('/', [SaleController::class, 'index'])->name('index');

    // Punto de venta y sus consultas en vivo
    Route::prefix('pos')->name('pos')->middleware('check-permission:sales.create')->group(function () {
        Route::get('/', [PosController::class, 'index']);
        Route::get('/clients', [PosController::class, 'clients'])->name('.clients');
        Route::post('/clients', [PosController::class, 'storeClient'])->name('.clients.store');
        Route::get('/clients/{client}', [PosController::class, 'client'])->name('.client');
        Route::post('/quote', [PosController::class, 'quote'])->name('.quote');
    });

    Route::post('/', [SaleController::class, 'store'])->name('store')->middleware('check-permission:sales.create');
    Route::get('/{sale}', [SaleController::class, 'show'])->name('show');
    Route::post('/{sale}/void', [SaleController::class, 'void'])->name('void')->middleware('check-permission:sales.void');
});
