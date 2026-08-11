<?php

use App\Http\Controllers\System\CompanyController;
use App\Http\Controllers\System\PlanController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\SubscriptionController;
use App\Http\Controllers\System\SubscriptionPaymentController;
use Illuminate\Support\Facades\Route;

// Módulo Sistema (solo super admin): Empresas, Roles, Planes, Suscripciones

// Empresas
Route::middleware('check-role:super_admin')->prefix('admin/companies')->name('companies.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::get('/create', [CompanyController::class, 'create'])->name('create');
    Route::post('/', [CompanyController::class, 'store'])->name('store');
    Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
    Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('edit');
    Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
    Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');
});

// Roles
Route::prefix('admin/roles')->name('roles.')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::get('/create', [RoleController::class, 'create'])->name('create');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::get('/{role}', [RoleController::class, 'show'])->name('show');
    Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
    Route::put('/{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
});

// SaaS: Planes
Route::prefix('admin/plans')->name('plans.')->group(function () {
    Route::get('/', [PlanController::class, 'index'])->name('index');
    Route::get('/create', [PlanController::class, 'create'])->name('create');
    Route::post('/', [PlanController::class, 'store'])->name('store');
    Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
    Route::put('/{plan}', [PlanController::class, 'update'])->name('update');
    Route::delete('/{plan}', [PlanController::class, 'destroy'])->name('destroy');
});

// SaaS: Suscripciones por empresa
Route::prefix('admin/subscriptions')->name('subscriptions.')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('index');
    Route::get('/{company}', [SubscriptionController::class, 'show'])->name('show');
    Route::get('/{company}/edit', [SubscriptionController::class, 'edit'])->name('edit');
    Route::put('/{company}', [SubscriptionController::class, 'update'])->name('update');
});

// SaaS: Pagos de suscripción
Route::post('admin/subscriptions/{company}/payments', [SubscriptionPaymentController::class, 'store'])->name('subscription-payments.store');
Route::post('admin/subscription-payments/{payment}/confirm', [SubscriptionPaymentController::class, 'confirm'])->name('subscription-payments.confirm');
Route::post('admin/subscription-payments/{payment}/reject', [SubscriptionPaymentController::class, 'reject'])->name('subscription-payments.reject');
