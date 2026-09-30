<?php

use App\Http\Controllers\Finance\CajaController;
use App\Http\Controllers\Finance\CashMovementController;
use App\Http\Controllers\Finance\CashSessionController;
use Illuminate\Support\Facades\Route;

// Módulo Finanzas: Cajas
Route::prefix('admin/cajas')->name('cajas.')->middleware('check-permission:cajas.view')->group(function () {
    Route::get('/', [CajaController::class, 'index'])->name('index');
    Route::get('/create', [CajaController::class, 'create'])->name('create')->middleware('check-permission:cajas.create');
    Route::post('/', [CajaController::class, 'store'])->name('store')->middleware('check-permission:cajas.create');
    Route::get('/{caja}/edit', [CajaController::class, 'edit'])->name('edit')->middleware('check-permission:cajas.edit');
    Route::put('/{caja}', [CajaController::class, 'update'])->name('update')->middleware('check-permission:cajas.edit');
    Route::delete('/{caja}', [CajaController::class, 'destroy'])->name('destroy')->middleware('check-permission:cajas.delete');
});

// Sesiones de caja / arqueos
Route::prefix('admin/cash-sessions')->name('cash-sessions.')->middleware('check-permission:cash.view')->group(function () {
    Route::get('/', [CashSessionController::class, 'index'])->name('index');
    Route::get('/create', [CashSessionController::class, 'create'])->name('create')->middleware('check-permission:cash.open');
    Route::post('/', [CashSessionController::class, 'store'])->name('store')->middleware('check-permission:cash.open');
    Route::get('/{cashSession}', [CashSessionController::class, 'show'])->name('show');
    Route::post('/{cashSession}/movements', [CashSessionController::class, 'addMovement'])->name('movements')->middleware('check-permission:cash.move');
    Route::post('/{cashSession}/close', [CashSessionController::class, 'close'])->name('close')->middleware('check-permission:cash.close');
});

// Ingresos
Route::prefix('admin/incomes')->name('incomes.')
    ->middleware('check-permission:incomes.view')->group(function () {
        Route::get('/', [CashMovementController::class, 'index'])->name('index');
        Route::get('/create', [CashMovementController::class, 'create'])->name('create')->middleware('check-permission:incomes.create');
        Route::post('/', [CashMovementController::class, 'store'])->name('store')->middleware('check-permission:incomes.create');
        Route::delete('/{movement}', [CashMovementController::class, 'destroy'])->name('destroy')->middleware('check-permission:incomes.delete');
    });

// Gastos
Route::prefix('admin/expenses')->name('expenses.')
    ->middleware('check-permission:expenses.view')->group(function () {
        Route::get('/', [CashMovementController::class, 'index'])->name('index');
        Route::get('/create', [CashMovementController::class, 'create'])->name('create')->middleware('check-permission:expenses.create');
        Route::post('/', [CashMovementController::class, 'store'])->name('store')->middleware('check-permission:expenses.create');
        Route::delete('/{movement}', [CashMovementController::class, 'destroy'])->name('destroy')->middleware('check-permission:expenses.delete');
    });
