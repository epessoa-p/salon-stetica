<?php

use App\Http\Controllers\Commissions\CommissionController;
use App\Http\Controllers\Commissions\CommissionRuleController;
use App\Http\Controllers\Commissions\CommissionSettlementController;
use Illuminate\Support\Facades\Route;

// Módulo Comisiones: resumen, liquidaciones, reglas y vista del propio profesional.

Route::prefix('admin/commissions')->name('commissions.')->group(function () {
    Route::get('/', [CommissionController::class, 'index'])->name('index')->middleware('check-permission:commissions.view');
    // Sin permiso: cada profesional ve solo lo suyo (se filtra por su ficha de personal).
    Route::get('/mine', [CommissionController::class, 'mine'])->name('mine');
});

Route::prefix('admin/commission-settlements')->name('commission-settlements.')->group(function () {
    Route::get('/', [CommissionSettlementController::class, 'index'])->name('index')->middleware('check-permission:commissions.view');
    Route::post('/', [CommissionSettlementController::class, 'store'])->name('store')->middleware('check-permission:commissions.pay');
    Route::get('/{settlement}', [CommissionSettlementController::class, 'show'])->name('show')->middleware('check-permission:commissions.view');
});

Route::prefix('admin/commission-rules')->name('commission-rules.')->group(function () {
    Route::get('/', [CommissionRuleController::class, 'index'])->name('index')->middleware('check-permission:commission-rules.view');
    Route::get('/create', [CommissionRuleController::class, 'create'])->name('create')->middleware('check-permission:commission-rules.create');
    Route::post('/', [CommissionRuleController::class, 'store'])->name('store')->middleware('check-permission:commission-rules.create');
    Route::get('/{commissionRule}/edit', [CommissionRuleController::class, 'edit'])->name('edit')->middleware('check-permission:commission-rules.edit');
    Route::put('/{commissionRule}', [CommissionRuleController::class, 'update'])->name('update')->middleware('check-permission:commission-rules.edit');
    Route::delete('/{commissionRule}', [CommissionRuleController::class, 'destroy'])->name('destroy')->middleware('check-permission:commission-rules.delete');
});
