<?php

use App\Http\Controllers\Staff\AttendanceController;
use App\Http\Controllers\Staff\CargoController;
use App\Http\Controllers\Staff\PersonalController;
use App\Http\Controllers\Staff\ScheduleController;
use App\Http\Controllers\Staff\UserController;
use Illuminate\Support\Facades\Route;

// Módulo Personal y accesos: Usuarios, Cargos, Personal

// Usuarios
Route::prefix('admin/users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('/{user}/assign-role/{company}/{role}', [UserController::class, 'assignRole'])->name('assign-role');
});

// Cargos
Route::prefix('admin/cargos')->name('cargos.')->group(function () {
    Route::get('/', [CargoController::class, 'index'])->name('index');
    Route::get('/create', [CargoController::class, 'create'])->name('create');
    Route::post('/', [CargoController::class, 'store'])->name('store');
    Route::get('/role-permissions/{role}', [CargoController::class, 'rolePermissions'])->name('role-permissions');
    Route::get('/{cargo}/edit', [CargoController::class, 'edit'])->name('edit');
    Route::put('/{cargo}', [CargoController::class, 'update'])->name('update');
    Route::delete('/{cargo}', [CargoController::class, 'destroy'])->name('destroy');
});

// Personal
Route::prefix('admin/personal')->name('personal.')->group(function () {
    Route::get('/', [PersonalController::class, 'index'])->name('index');
    Route::get('/create', [PersonalController::class, 'create'])->name('create');
    Route::get('/cargos-by-company/{company}', [PersonalController::class, 'cargosByCompany'])->name('cargos-by-company');
    Route::post('/', [PersonalController::class, 'store'])->name('store');
    Route::get('/{personal}/edit', [PersonalController::class, 'edit'])->name('edit');
    Route::put('/{personal}', [PersonalController::class, 'update'])->name('update');
    Route::delete('/{personal}', [PersonalController::class, 'destroy'])->name('destroy');
});

// Horarios del personal (semanal + excepciones)
Route::prefix('admin/schedules')->name('schedules.')->middleware('check-permission:schedules.view')->group(function () {
    Route::get('/', [ScheduleController::class, 'index'])->name('index');
    Route::get('/{personal}', [ScheduleController::class, 'edit'])->name('edit');
    Route::put('/{personal}', [ScheduleController::class, 'update'])->name('update')->middleware('check-permission:schedules.edit');
    Route::post('/{personal}/exceptions', [ScheduleController::class, 'storeException'])->name('exceptions.store')->middleware('check-permission:schedules.edit');
    Route::delete('/{personal}/exceptions/{exception}', [ScheduleController::class, 'destroyException'])->name('exceptions.destroy')->middleware('check-permission:schedules.edit');
});

// Asistencia
Route::prefix('admin/attendance')->name('attendance.')->group(function () {
    Route::get('/', [AttendanceController::class, 'index'])->name('index')->middleware('check-permission:attendance.view');
    // Sin permiso: cada quien marca y ve lo suyo (se filtra por su ficha de personal).
    Route::get('/mine', [AttendanceController::class, 'mine'])->name('mine');
    Route::post('/self', [AttendanceController::class, 'self'])->name('self');
    Route::post('/mark', [AttendanceController::class, 'mark'])->name('mark')->middleware('check-permission:attendance.mark');
    Route::put('/{attendance}', [AttendanceController::class, 'update'])->name('update')->middleware('check-permission:attendance.edit');
});
