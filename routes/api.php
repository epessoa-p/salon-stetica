<?php

use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CommissionController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
| API de la app móvil Glamora (equipo del salón).
| Autenticación: token de Sanctum (Authorization: Bearer …).
| Empresa activa: header X-Company-Id (opcional si el usuario tiene una sola).
| Errores: JSON con `code` estable y `message` en español.
*/

Route::prefix('v1')->name('api.')->group(function () {
    // Límite de intentos por IP para frenar ataques de fuerza bruta.
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('auth/companies', [AuthController::class, 'companies'])->name('companies');

        Route::middleware('api.company')->group(function () {
            Route::get('me', MeController::class)->name('me');

            Route::get('agenda', [AgendaController::class, 'index'])->name('agenda');
            Route::get('agenda/options', [AgendaController::class, 'options'])->name('agenda.options');
            Route::get('agenda/availability', [AgendaController::class, 'availability'])->name('agenda.availability');
            Route::post('appointments', [AgendaController::class, 'store'])->name('appointments.store');
            Route::post('appointments/{appointment}/status', [AgendaController::class, 'status'])->name('appointments.status');

            Route::get('clients', [ClientController::class, 'index'])->name('clients');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');

            Route::get('attendance', [AttendanceController::class, 'show'])->name('attendance');
            Route::post('attendance', [AttendanceController::class, 'mark'])->name('attendance.mark');

            Route::get('commissions', [CommissionController::class, 'index'])->name('commissions');
        });
    });
});
