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
        require __DIR__.'/web/crm.php';
        require __DIR__.'/web/catalog.php';
        require __DIR__.'/web/scheduling.php';
        require __DIR__.'/web/sales.php';
        require __DIR__.'/web/inventory.php';
        require __DIR__.'/web/purchasing.php';
        require __DIR__.'/web/system.php';
        require __DIR__.'/web/staff.php';
        require __DIR__.'/web/organization.php';
        require __DIR__.'/web/finance.php';
    });
});

// Fallback
Route::redirect('/', '/dashboard');
