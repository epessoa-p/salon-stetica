<?php

use App\Http\Controllers\Crm\ClientController;
use App\Http\Controllers\Crm\ClientMembershipController;
use App\Http\Controllers\Crm\LoyaltyController;
use App\Http\Controllers\Crm\MembershipPlanController;
use Illuminate\Support\Facades\Route;

// Módulo Clientes (CRM)
Route::prefix('admin/clients')->name('clients.')->middleware('check-permission:clients.view')->group(function () {
    Route::get('/', [ClientController::class, 'index'])->name('index');
    Route::get('/create', [ClientController::class, 'create'])->name('create')->middleware('check-permission:clients.create');
    Route::post('/', [ClientController::class, 'store'])->name('store')->middleware('check-permission:clients.create');
    Route::get('/{client}', [ClientController::class, 'show'])->name('show');
    Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('edit')->middleware('check-permission:clients.edit');
    Route::put('/{client}', [ClientController::class, 'update'])->name('update')->middleware('check-permission:clients.edit');
    Route::delete('/{client}', [ClientController::class, 'destroy'])->name('destroy')->middleware('check-permission:clients.delete');
});

// Membresías y fidelización: módulo de plan aparte
Route::middleware('plan:loyalty')->group(function () {
    Route::prefix('admin/memberships')->name('memberships.')->middleware('check-permission:memberships.view')->group(function () {
        Route::get('/', [ClientMembershipController::class, 'index'])->name('index');
        Route::post('/{membership}/cancel', [ClientMembershipController::class, 'cancel'])->name('cancel')->middleware('check-permission:memberships.edit');

        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/', [MembershipPlanController::class, 'index'])->name('index');
            Route::get('/create', [MembershipPlanController::class, 'create'])->name('create')->middleware('check-permission:memberships.create');
            Route::post('/', [MembershipPlanController::class, 'store'])->name('store')->middleware('check-permission:memberships.create');
            Route::get('/{plan}/edit', [MembershipPlanController::class, 'edit'])->name('edit')->middleware('check-permission:memberships.edit');
            Route::put('/{plan}', [MembershipPlanController::class, 'update'])->name('update')->middleware('check-permission:memberships.edit');
            Route::delete('/{plan}', [MembershipPlanController::class, 'destroy'])->name('destroy')->middleware('check-permission:memberships.delete');
        });
    });

    Route::prefix('admin/loyalty')->name('loyalty.')->middleware('check-permission:loyalty.view')->group(function () {
        Route::get('/', [LoyaltyController::class, 'index'])->name('index');
        Route::put('/settings', [LoyaltyController::class, 'updateSettings'])->name('settings')->middleware('check-permission:loyalty.edit');
        Route::post('/adjust', [LoyaltyController::class, 'adjust'])->name('adjust')->middleware('check-permission:loyalty.adjust');
    });
});
