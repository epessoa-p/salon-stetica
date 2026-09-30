<?php

use App\Http\Controllers\Inventory\BrandController;
use App\Http\Controllers\Inventory\ProductCategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockController;
use Illuminate\Support\Facades\Route;

// Módulo Inventario

// Categorías de producto
Route::prefix('admin/product-categories')->name('product-categories.')->middleware('check-permission:product-categories.view')->group(function () {
    Route::get('/', [ProductCategoryController::class, 'index'])->name('index');
    Route::get('/create', [ProductCategoryController::class, 'create'])->name('create')->middleware('check-permission:product-categories.create');
    Route::post('/', [ProductCategoryController::class, 'store'])->name('store')->middleware('check-permission:product-categories.create');
    Route::get('/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->name('edit')->middleware('check-permission:product-categories.edit');
    Route::put('/{productCategory}', [ProductCategoryController::class, 'update'])->name('update')->middleware('check-permission:product-categories.edit');
    Route::delete('/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('destroy')->middleware('check-permission:product-categories.delete');
});

// Marcas
Route::prefix('admin/brands')->name('brands.')->middleware('check-permission:brands.view')->group(function () {
    Route::get('/', [BrandController::class, 'index'])->name('index');
    Route::get('/create', [BrandController::class, 'create'])->name('create')->middleware('check-permission:brands.create');
    Route::post('/', [BrandController::class, 'store'])->name('store')->middleware('check-permission:brands.create');
    Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit')->middleware('check-permission:brands.edit');
    Route::put('/{brand}', [BrandController::class, 'update'])->name('update')->middleware('check-permission:brands.edit');
    Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('destroy')->middleware('check-permission:brands.delete');
});

// Productos
Route::prefix('admin/products')->name('products.')->middleware('check-permission:products.view')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/create', [ProductController::class, 'create'])->name('create')->middleware('check-permission:products.create');
    Route::get('/meta-by-company/{company}', [ProductController::class, 'metaByCompany'])->name('meta-by-company');
    Route::post('/', [ProductController::class, 'store'])->name('store')->middleware('check-permission:products.create');
    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit')->middleware('check-permission:products.edit');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update')->middleware('check-permission:products.edit');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy')->middleware('check-permission:products.delete');
});

// Existencias y movimientos
Route::prefix('admin/stock')->name('stock.')->middleware('check-permission:stock.view')->group(function () {
    Route::get('/', [StockController::class, 'index'])->name('index');
    Route::get('/movements', [StockController::class, 'movements'])->name('movements');
    Route::get('/movement', [StockController::class, 'createMovement'])->name('movement.create')->middleware('check-permission:stock.manage');
    Route::post('/movement', [StockController::class, 'storeMovement'])->name('movement.store')->middleware('check-permission:stock.manage');
});
