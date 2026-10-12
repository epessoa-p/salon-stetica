<?php

use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\PaymentMethodController;
use App\Http\Controllers\Organization\SettingController;
use Illuminate\Support\Facades\Route;

// Módulo Organización: Sucursales
Route::prefix('admin/branches')->name('branches.')->group(function () {
    Route::get('/', [BranchController::class, 'index'])->name('index');
    Route::get('/create', [BranchController::class, 'create'])->name('create');
    Route::post('/', [BranchController::class, 'store'])->name('store');
    Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
    Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
    Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
    Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy');
});

// Métodos de pago (catálogo configurable por empresa)
Route::prefix('admin/payment-methods')->name('payment-methods.')
    ->middleware('check-permission:payment-methods.view')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
        Route::get('/create', [PaymentMethodController::class, 'create'])->name('create')->middleware('check-permission:payment-methods.create');
        Route::post('/', [PaymentMethodController::class, 'store'])->name('store')->middleware('check-permission:payment-methods.create');
        Route::post('/seed-defaults', [PaymentMethodController::class, 'seedDefaults'])->name('seed-defaults')->middleware('check-permission:payment-methods.create');
        Route::get('/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('edit')->middleware('check-permission:payment-methods.edit');
        Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('update')->middleware('check-permission:payment-methods.edit');
        Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('destroy')->middleware('check-permission:payment-methods.delete');
    });

// Preferencias de la empresa (moneda, impuestos, facturación, folios)
Route::prefix('admin/settings')->name('settings.')
    ->middleware('check-permission:settings.view')->group(function () {
        Route::get('/', [SettingController::class, 'edit'])->name('edit');
        Route::put('/', [SettingController::class, 'update'])->name('update')->middleware('check-permission:settings.edit');
    });
