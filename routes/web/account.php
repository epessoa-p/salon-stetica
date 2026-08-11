<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Cuenta / sesión (autenticado, SIN guard de suscripción para evitar bucles)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Selección de empresa (multi-empresa)
Route::get('/select-company', [LoginController::class, 'selectCompany'])->name('select-company');
Route::post('/set-company/{companyId}', [LoginController::class, 'setCompany'])->name('set-company');

// Página de suscripción vencida (fuera del guard de suscripción)
Route::get('/suscripcion-vencida', function () {
    $company = auth()->user()->getCurrentCompany();
    return view('subscription.blocked', compact('company'));
})->name('subscription.blocked');
