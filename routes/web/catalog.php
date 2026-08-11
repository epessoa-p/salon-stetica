<?php

use App\Http\Controllers\Catalog\ServiceCategoryController;
use App\Http\Controllers\Catalog\ServiceController;
use Illuminate\Support\Facades\Route;

// Módulo Servicios (catálogo)

// Categorías de servicio
Route::prefix('admin/service-categories')->name('service-categories.')->group(function () {
    Route::get('/', [ServiceCategoryController::class, 'index'])->name('index');
    Route::get('/create', [ServiceCategoryController::class, 'create'])->name('create');
    Route::post('/', [ServiceCategoryController::class, 'store'])->name('store');
    Route::get('/{serviceCategory}/edit', [ServiceCategoryController::class, 'edit'])->name('edit');
    Route::put('/{serviceCategory}', [ServiceCategoryController::class, 'update'])->name('update');
    Route::delete('/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('destroy');
});

// Servicios
Route::prefix('admin/services')->name('services.')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('index');
    Route::get('/create', [ServiceController::class, 'create'])->name('create');
    Route::get('/categories-by-company/{company}', [ServiceController::class, 'categoriesByCompany'])->name('categories-by-company');
    Route::post('/', [ServiceController::class, 'store'])->name('store');
    Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('edit');
    Route::put('/{service}', [ServiceController::class, 'update'])->name('update');
    Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('destroy');
});
