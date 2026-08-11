<?php

use App\Http\Controllers\Inventory\BrandController;
use App\Http\Controllers\Inventory\ProductCategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockController;
use Illuminate\Support\Facades\Route;

// Módulo Inventario

// Categorías de producto
Route::prefix('admin/product-categories')->name('product-categories.')->group(function () {
    Route::get('/', [ProductCategoryController::class, 'index'])->name('index');
    Route::get('/create', [ProductCategoryController::class, 'create'])->name('create');
    Route::post('/', [ProductCategoryController::class, 'store'])->name('store');
    Route::get('/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->name('edit');
    Route::put('/{productCategory}', [ProductCategoryController::class, 'update'])->name('update');
    Route::delete('/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('destroy');
});

// Marcas
Route::prefix('admin/brands')->name('brands.')->group(function () {
    Route::get('/', [BrandController::class, 'index'])->name('index');
    Route::get('/create', [BrandController::class, 'create'])->name('create');
    Route::post('/', [BrandController::class, 'store'])->name('store');
    Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit');
    Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
    Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('destroy');
});

// Productos
Route::prefix('admin/products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/create', [ProductController::class, 'create'])->name('create');
    Route::get('/meta-by-company/{company}', [ProductController::class, 'metaByCompany'])->name('meta-by-company');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
});

// Existencias y movimientos
Route::prefix('admin/stock')->name('stock.')->group(function () {
    Route::get('/', [StockController::class, 'index'])->name('index');
    Route::get('/movements', [StockController::class, 'movements'])->name('movements');
    Route::get('/movement', [StockController::class, 'createMovement'])->name('movement.create');
    Route::post('/movement', [StockController::class, 'storeMovement'])->name('movement.store');
});
