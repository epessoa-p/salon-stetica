<?php

use App\Http\Controllers\Catalog\ServiceCategoryController;
use App\Http\Controllers\Catalog\ServiceController;
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
