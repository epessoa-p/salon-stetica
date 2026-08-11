<?php

use App\Http\Controllers\Scheduling\AppointmentController;
use Illuminate\Support\Facades\Route;

// Módulo Agenda y Citas
Route::prefix('admin/appointments')->name('appointments.')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->name('index');
    Route::get('/calendar', [AppointmentController::class, 'calendar'])->name('calendar');
    Route::get('/events', [AppointmentController::class, 'events'])->name('events');
    Route::get('/create', [AppointmentController::class, 'create'])->name('create');
    Route::post('/', [AppointmentController::class, 'store'])->name('store');
    Route::get('/{appointment}/edit', [AppointmentController::class, 'edit'])->name('edit');
    Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update');
    Route::post('/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('status');
    Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->name('destroy');
});
