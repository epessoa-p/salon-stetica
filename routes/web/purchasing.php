<?php

use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\SupplierController;
use Illuminate\Support\Facades\Route;

// Módulo Compras

// Proveedores
Route::prefix('admin/suppliers')->name('suppliers.')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::get('/create', [SupplierController::class, 'create'])->name('create');
    Route::post('/', [SupplierController::class, 'store'])->name('store');
    Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
});

// Órdenes de compra + recepciones
Route::prefix('admin/purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/receptions', [PurchaseOrderController::class, 'receptions'])->name('receptions');
    Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
    Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
    Route::post('/{purchaseOrder}/void', [PurchaseOrderController::class, 'void'])->name('void');
});
