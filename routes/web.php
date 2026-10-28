<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Compositor de rutas web
|--------------------------------------------------------------------------
| Las rutas se organizan por módulo en routes/web/*.php. Aquí solo se define
| la estructura de middlewares y se cargan los archivos de cada módulo.
*/

// Invitados
Route::middleware('guest')->group(function () {
    require __DIR__.'/web/guest.php';
});

// Autenticados
Route::middleware('auth')->group(function () {

    // Cuenta / sesión (sin guard de suscripción)
    require __DIR__.'/web/account.php';

    // Módulos protegidos por estado de suscripción (el super admin nunca se bloquea)
    Route::middleware('check-subscription')->group(function () {
        require __DIR__.'/web/dashboard.php';

        // Módulos gobernados por el plan de la empresa (plan:<módulo>).
        Route::middleware('plan:crm')->group(fn () => require __DIR__.'/web/crm.php');
        Route::middleware('plan:catalog')->group(fn () => require __DIR__.'/web/catalog.php');
        Route::middleware('plan:scheduling')->group(fn () => require __DIR__.'/web/scheduling.php');
        Route::middleware('plan:sales')->group(fn () => require __DIR__.'/web/sales.php');
        Route::middleware('plan:inventory')->group(fn () => require __DIR__.'/web/inventory.php');
        Route::middleware('plan:purchasing')->group(fn () => require __DIR__.'/web/purchasing.php');
        Route::middleware('plan:finance')->group(fn () => require __DIR__.'/web/finance.php');
        Route::middleware('plan:commissions')->group(fn () => require __DIR__.'/web/commissions.php');
        Route::middleware('plan:marketing')->group(fn () => require __DIR__.'/web/marketing.php');

        // Administrativos / datos maestros (siempre disponibles, sin plan:).
        require __DIR__.'/web/system.php';
        require __DIR__.'/web/reports.php';
        require __DIR__.'/web/staff.php';
        require __DIR__.'/web/organization.php';
    });
});

// Fallback
Route::redirect('/', '/dashboard');
