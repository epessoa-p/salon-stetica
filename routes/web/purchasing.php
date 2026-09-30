<?php

use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\SupplierController;
use Illuminate\Support\Facades\Route;

// Módulo Compras

// Proveedores
Route::prefix('admin/suppliers')->name('suppliers.')->middleware('check-permission:suppliers.view')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::get('/create', [SupplierController::class, 'create'])->name('create')->middleware('check-permission:suppliers.create');
    Route::post('/', [SupplierController::class, 'store'])->name('store')->middleware('check-permission:suppliers.create');
    Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit')->middleware('check-permission:suppliers.edit');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update')->middleware('check-permission:suppliers.edit');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy')->middleware('check-permission:suppliers.delete');
});

// Órdenes de compra + recepciones
Route::prefix('admin/purchase-orders')->name('purchase-orders.')->middleware('check-permission:purchases.view')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/receptions', [PurchaseOrderController::class, 'receptions'])->name('receptions');
    Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create')->middleware('check-permission:purchases.create');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store')->middleware('check-permission:purchases.create');
    Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive')->middleware('check-permission:purchases.receive');
    Route::post('/{purchaseOrder}/void', [PurchaseOrderController::class, 'void'])->name('void')->middleware('check-permission:purchases.void');
});
