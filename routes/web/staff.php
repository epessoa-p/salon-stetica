<?php

use App\Http\Controllers\Staff\CargoController;
use App\Http\Controllers\Staff\CommissionController;
use App\Http\Controllers\Staff\PersonalController;
use App\Http\Controllers\Staff\UserController;
use Illuminate\Support\Facades\Route;

// Módulo Personal y accesos: Usuarios, Cargos, Personal

// Usuarios
Route::prefix('admin/users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('/{user}/assign-role/{company}/{role}', [UserController::class, 'assignRole'])->name('assign-role');
});

// Cargos
Route::prefix('admin/cargos')->name('cargos.')->group(function () {
    Route::get('/', [CargoController::class, 'index'])->name('index');
    Route::get('/create', [CargoController::class, 'create'])->name('create');
    Route::post('/', [CargoController::class, 'store'])->name('store');
    Route::get('/role-permissions/{role}', [CargoController::class, 'rolePermissions'])->name('role-permissions');
    Route::get('/{cargo}/edit', [CargoController::class, 'edit'])->name('edit');
    Route::put('/{cargo}', [CargoController::class, 'update'])->name('update');
    Route::delete('/{cargo}', [CargoController::class, 'destroy'])->name('destroy');
});

// Personal
Route::prefix('admin/personal')->name('personal.')->group(function () {
    Route::get('/', [PersonalController::class, 'index'])->name('index');
    Route::get('/create', [PersonalController::class, 'create'])->name('create');
    Route::get('/cargos-by-company/{company}', [PersonalController::class, 'cargosByCompany'])->name('cargos-by-company');
    Route::post('/', [PersonalController::class, 'store'])->name('store');
    Route::get('/{personal}/edit', [PersonalController::class, 'edit'])->name('edit');
    Route::put('/{personal}', [PersonalController::class, 'update'])->name('update');
    Route::delete('/{personal}', [PersonalController::class, 'destroy'])->name('destroy');
});

// Comisiones (derivadas de los servicios vendidos)
Route::prefix('admin/commissions')->name('commissions.')->middleware('check-permission:commissions.view')->group(function () {
    Route::get('/', [CommissionController::class, 'index'])->name('index');
    Route::post('/{commission}/pay', [CommissionController::class, 'pay'])->name('pay')->middleware('check-permission:commissions.pay');
    Route::post('/pay-bulk', [CommissionController::class, 'payBulk'])->name('pay-bulk')->middleware('check-permission:commissions.pay');
});
