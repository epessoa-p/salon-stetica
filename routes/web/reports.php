<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

// Módulo Reportes (administrativo: no depende del plan, solo del permiso)
Route::prefix('admin/reports')->name('reports.')->middleware('check-permission:reports.view')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
});
