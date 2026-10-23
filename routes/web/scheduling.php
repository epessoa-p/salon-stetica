<?php

use App\Http\Controllers\Scheduling\AppointmentController;
use Illuminate\Support\Facades\Route;

// Módulo Agenda y Citas
Route::prefix('admin/appointments')->name('appointments.')->middleware('check-permission:appointments.view')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->name('index');
    Route::get('/calendar', [AppointmentController::class, 'calendar'])->name('calendar');
    Route::get('/events', [AppointmentController::class, 'events'])->name('events');
    Route::get('/schedule-events', [AppointmentController::class, 'scheduleEvents'])->name('schedule-events');
    Route::get('/availability', [AppointmentController::class, 'availability'])->name('availability');
    Route::get('/create', [AppointmentController::class, 'create'])->name('create')->middleware('check-permission:appointments.create');
    Route::post('/', [AppointmentController::class, 'store'])->name('store')->middleware('check-permission:appointments.create');
    Route::get('/{appointment}/edit', [AppointmentController::class, 'edit'])->name('edit')->middleware('check-permission:appointments.edit');
    Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update')->middleware('check-permission:appointments.edit');
    Route::post('/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('status')->middleware('check-permission:appointments.edit');
    Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->name('destroy')->middleware('check-permission:appointments.delete');
});
