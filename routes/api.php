<?php

use App\Http\Controllers\Api\V1\AccountsController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CommissionController;
use App\Http\Controllers\Api\V1\ExpensesController;
use App\Http\Controllers\Api\V1\ManagementController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\SalesController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SettingsController;
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
            Route::post('appointments/{appointment}/reschedule', [AgendaController::class, 'reschedule'])->name('appointments.reschedule');

            Route::get('clients', [ClientController::class, 'index'])->name('clients');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
            Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
            Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::get('clients/{client}/photos', [ClientController::class, 'photos'])->name('clients.photos');
            Route::post('clients/{client}/photos', [ClientController::class, 'storePhoto'])->name('clients.photos.store');
            Route::get('photos/{photo}/{size?}', [ClientController::class, 'photoImage'])->where('size', 'full|thumb')->name('photos.image');
            Route::delete('photos/{photo}', [ClientController::class, 'destroyPhoto'])->name('photos.destroy');

            Route::get('attendance', [AttendanceController::class, 'show'])->name('attendance');
            Route::post('attendance', [AttendanceController::class, 'mark'])->name('attendance.mark');

            Route::get('commissions', [CommissionController::class, 'index'])->name('commissions');

            Route::get('schedule', [ScheduleController::class, 'week'])->name('schedule');
            Route::get('reminders', [MessagingController::class, 'reminders'])->name('reminders');
            Route::get('birthdays', [MessagingController::class, 'birthdays'])->name('birthdays');
            Route::post('messages/whatsapp', [MessagingController::class, 'whatsapp'])->name('messages.whatsapp');

            // Gerencia: panel del día y cuentas por cobrar
            Route::get('dashboard', [ManagementController::class, 'dashboard'])->name('dashboard');
            Route::get('receivables', [ManagementController::class, 'receivables'])->name('receivables');
            Route::get('receivables/{client}', [ManagementController::class, 'receivableClient'])->name('receivables.client');
            Route::post('receivables/{client}/collect', [ManagementController::class, 'collect'])->name('receivables.collect');

            // Cobro (punto de venta) y mis ventas
            Route::get('pos/options', [SalesController::class, 'options'])->name('pos.options');
            Route::get('pos/clients', [SalesController::class, 'clients'])->name('pos.clients');
            Route::post('pos/quote', [SalesController::class, 'quote'])->name('pos.quote');
            Route::get('sales', [SalesController::class, 'index'])->name('sales');
            Route::post('sales', [SalesController::class, 'store'])->name('sales.store');
            Route::get('sales/{sale}', [SalesController::class, 'show'])->name('sales.show');

            // Caja: abrir, ingreso/gasto rápido y cierre con arqueo
            Route::get('cash', [CashController::class, 'index'])->name('cash');
            Route::post('cash/open', [CashController::class, 'open'])->name('cash.open');
            Route::get('cash/{session}', [CashController::class, 'show'])->name('cash.show');
            Route::post('cash/{session}/movements', [CashController::class, 'movement'])->name('cash.movement');
            Route::post('cash/{session}/close', [CashController::class, 'close'])->name('cash.close');

            // Catálogo de servicios
            Route::get('services', [CatalogController::class, 'services'])->name('services');
            Route::post('services', [CatalogController::class, 'store'])->name('services.store');
            Route::put('services/{service}', [CatalogController::class, 'update'])->name('services.update');
            Route::post('service-categories', [CatalogController::class, 'storeCategory'])->name('service-categories.store');

            // Reportes (los mismos de la web)
            Route::get('reports', [ReportsController::class, 'index'])->name('reports');
            Route::get('reports/analysis', [ReportsController::class, 'analysis'])->name('reports.analysis');
            Route::get('reports/profit', [ReportsController::class, 'profit'])->name('reports.profit');
            Route::get('reports/finance', [ReportsController::class, 'finance'])->name('reports.finance');
            Route::get('reports/inventory', [ReportsController::class, 'inventory'])->name('reports.inventory');

            // Cuentas y gastos
            Route::get('finance/options', [ExpensesController::class, 'options'])->name('finance.options');
            Route::get('finance/basics', [ExpensesController::class, 'basics'])->name('finance.basics');
            Route::get('accounts', [AccountsController::class, 'index'])->name('accounts');
            Route::post('accounts', [AccountsController::class, 'store'])->name('accounts.store');
            Route::get('accounts/{financialAccount}', [AccountsController::class, 'show'])->name('accounts.show');
            Route::put('accounts/{financialAccount}', [AccountsController::class, 'update'])->name('accounts.update');
            Route::delete('accounts/{financialAccount}', [AccountsController::class, 'destroy'])->name('accounts.destroy');
            Route::post('accounts/{financialAccount}/movements', [AccountsController::class, 'movement'])->name('accounts.movement');
            Route::post('accounts/{financialAccount}/transfer', [AccountsController::class, 'transfer'])->name('accounts.transfer');
            Route::post('accounts/{financialAccount}/movements/{movement}/void', [AccountsController::class, 'voidMovement'])->name('accounts.movements.void');
            Route::get('expenses', [ExpensesController::class, 'index'])->name('expenses');
            Route::post('expenses', [ExpensesController::class, 'store'])->name('expenses.store');
            Route::post('expenses/{movement}/cancel', [ExpensesController::class, 'cancel'])->name('expenses.cancel');
            Route::get('expense-categories', [ExpensesController::class, 'categories'])->name('expense-categories');
            Route::post('expense-categories', [ExpensesController::class, 'storeCategory'])->name('expense-categories.store');
            Route::put('expense-categories/{category}', [ExpensesController::class, 'updateCategory'])->name('expense-categories.update');
            Route::delete('expense-categories/{category}', [ExpensesController::class, 'destroyCategory'])->name('expense-categories.destroy');
            Route::post('suppliers', [ExpensesController::class, 'storeSupplier'])->name('suppliers.store');
            Route::get('commissions/pending', [ExpensesController::class, 'pendingCommissions'])->name('commissions.pending');
            Route::post('commissions/{personal}/settle', [ExpensesController::class, 'settle'])->name('commissions.settle');

            // Configuración: Mi empresa, Sucursales, Horarios y Métodos de pago
            Route::get('company', [SettingsController::class, 'showCompany'])->name('company');
            Route::put('company', [SettingsController::class, 'updateCompany'])->name('company.update');
            Route::post('company/logo', [SettingsController::class, 'logo'])->name('company.logo');
            Route::get('branches', [SettingsController::class, 'branches'])->name('branches');
            Route::post('branches', [SettingsController::class, 'storeBranch'])->name('branches.store');
            Route::put('branches/{branch}', [SettingsController::class, 'updateBranch'])->name('branches.update');
            Route::get('schedules', [SettingsController::class, 'schedules'])->name('schedules');
            Route::get('schedules/{personal}', [SettingsController::class, 'schedule'])->name('schedules.show');
            Route::put('schedules/{personal}', [SettingsController::class, 'updateSchedule'])->name('schedules.update');
            Route::get('payment-methods', [SettingsController::class, 'paymentMethods'])->name('payment-methods');
            Route::post('payment-methods', [SettingsController::class, 'storePaymentMethod'])->name('payment-methods.store');
            Route::put('payment-methods/{paymentMethod}', [SettingsController::class, 'updatePaymentMethod'])->name('payment-methods.update');
        });
    });
});
