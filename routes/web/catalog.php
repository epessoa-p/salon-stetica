<?php

use App\Http\Controllers\Catalog\ComboController;
use App\Http\Controllers\Catalog\ServiceCategoryController;
use App\Http\Controllers\Catalog\ServiceController;
use App\Http\Controllers\Catalog\ServicePackageController;
use Illuminate\Support\Facades\Route;

// Módulo Servicios (catálogo)

// Categorías de servicio
Route::prefix('admin/service-categories')->name('service-categories.')->middleware('check-permission:service-categories.view')->group(function () {
    Route::get('/', [ServiceCategoryController::class, 'index'])->name('index');
    Route::get('/create', [ServiceCategoryController::class, 'create'])->name('create')->middleware('check-permission:service-categories.create');
    Route::post('/', [ServiceCategoryController::class, 'store'])->name('store')->middleware('check-permission:service-categories.create');
    Route::get('/{serviceCategory}/edit', [ServiceCategoryController::class, 'edit'])->name('edit')->middleware('check-permission:service-categories.edit');
    Route::put('/{serviceCategory}', [ServiceCategoryController::class, 'update'])->name('update')->middleware('check-permission:service-categories.edit');
    Route::delete('/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('destroy')->middleware('check-permission:service-categories.delete');
});

// Servicios
Route::prefix('admin/services')->name('services.')->middleware('check-permission:services.view')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('index');
    Route::get('/create', [ServiceController::class, 'create'])->name('create')->middleware('check-permission:services.create');
    Route::get('/categories-by-company/{company}', [ServiceController::class, 'categoriesByCompany'])->name('categories-by-company');
    Route::post('/', [ServiceController::class, 'store'])->name('store')->middleware('check-permission:services.create');
    Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('edit')->middleware('check-permission:services.edit');
    Route::put('/{service}', [ServiceController::class, 'update'])->name('update')->middleware('check-permission:services.edit');
    Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('destroy')->middleware('check-permission:services.delete');
});

// Combos: servicios y productos a precio especial
Route::prefix('admin/combos')->name('combos.')->middleware('check-permission:combos.view')->group(function () {
    Route::get('/', [ComboController::class, 'index'])->name('index');
    Route::get('/create', [ComboController::class, 'create'])->name('create')->middleware('check-permission:combos.create');
    Route::post('/', [ComboController::class, 'store'])->name('store')->middleware('check-permission:combos.create');
    Route::get('/{combo}/edit', [ComboController::class, 'edit'])->name('edit')->middleware('check-permission:combos.edit');
    Route::put('/{combo}', [ComboController::class, 'update'])->name('update')->middleware('check-permission:combos.edit');
    Route::delete('/{combo}', [ComboController::class, 'destroy'])->name('destroy')->middleware('check-permission:combos.delete');
});

// Paquetes de sesiones prepagadas
Route::prefix('admin/packages')->name('packages.')->middleware('check-permission:packages.view')->group(function () {
    Route::get('/', [ServicePackageController::class, 'index'])->name('index');
    Route::get('/sold', [ServicePackageController::class, 'sold'])->name('sold');
    Route::post('/sold/{clientPackage}/cancel', [ServicePackageController::class, 'cancel'])->name('cancel')->middleware('check-permission:packages.edit');
    Route::get('/create', [ServicePackageController::class, 'create'])->name('create')->middleware('check-permission:packages.create');
    Route::post('/', [ServicePackageController::class, 'store'])->name('store')->middleware('check-permission:packages.create');
    Route::get('/{package}/edit', [ServicePackageController::class, 'edit'])->name('edit')->middleware('check-permission:packages.edit');
    Route::put('/{package}', [ServicePackageController::class, 'update'])->name('update')->middleware('check-permission:packages.edit');
    Route::delete('/{package}', [ServicePackageController::class, 'destroy'])->name('destroy')->middleware('check-permission:packages.delete');
});
