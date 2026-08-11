<?php

use App\Http\Controllers\Finance\CajaController;
use App\Http\Controllers\Finance\CashSessionController;
use Illuminate\Support\Facades\Route;

// Módulo Finanzas: Cajas
Route::prefix('admin/cajas')->name('cajas.')->group(function () {
    Route::get('/', [CajaController::class, 'index'])->name('index');
    Route::get('/create', [CajaController::class, 'create'])->name('create');
    Route::post('/', [CajaController::class, 'store'])->name('store');
    Route::get('/{caja}/edit', [CajaController::class, 'edit'])->name('edit');
    Route::put('/{caja}', [CajaController::class, 'update'])->name('update');
    Route::delete('/{caja}', [CajaController::class, 'destroy'])->name('destroy');
});

// Sesiones de caja / arqueos
Route::prefix('admin/cash-sessions')->name('cash-sessions.')->group(function () {
    Route::get('/', [CashSessionController::class, 'index'])->name('index');
    Route::get('/create', [CashSessionController::class, 'create'])->name('create');
    Route::post('/', [CashSessionController::class, 'store'])->name('store');
    Route::get('/{cashSession}', [CashSessionController::class, 'show'])->name('show');
    Route::post('/{cashSession}/movements', [CashSessionController::class, 'addMovement'])->name('movements');
    Route::post('/{cashSession}/close', [CashSessionController::class, 'close'])->name('close');
});
