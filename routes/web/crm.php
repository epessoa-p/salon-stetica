<?php

use App\Http\Controllers\Crm\ClientController;
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
