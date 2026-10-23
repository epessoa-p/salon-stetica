<?php

use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\QuoteController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleReturnController;
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

// Cotizaciones: se arman en el punto de venta
Route::prefix('admin/quotes')->name('quotes.')->middleware('check-permission:quotes.view')->group(function () {
    Route::get('/', [QuoteController::class, 'index'])->name('index');
    Route::post('/', [QuoteController::class, 'store'])->name('store')->middleware('check-permission:quotes.create');
    Route::get('/{quote}', [QuoteController::class, 'show'])->name('show');
    Route::post('/{quote}/void', [QuoteController::class, 'void'])->name('void')->middleware('check-permission:quotes.delete');
});

// Devoluciones de clientes
Route::prefix('admin/returns')->name('returns.')->middleware('check-permission:returns.view')->group(function () {
    Route::get('/', [SaleReturnController::class, 'index'])->name('index');
    Route::get('/find', [SaleReturnController::class, 'find'])->name('find')->middleware('check-permission:returns.create');
    Route::get('/sale/{sale}', [SaleReturnController::class, 'create'])->name('create')->middleware('check-permission:returns.create');
    Route::post('/sale/{sale}', [SaleReturnController::class, 'store'])->name('store')->middleware('check-permission:returns.create');
    Route::get('/{return}', [SaleReturnController::class, 'show'])->name('show');
});
