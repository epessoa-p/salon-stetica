<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fija la empresa activa (Tenancy) en cada petición web autenticada.
        $middleware->web(append: [
            \App\Http\Middleware\SetTenant::class,
        ]);

        $middleware->alias([
            'set-tenant' => \App\Http\Middleware\SetTenant::class,
            'check-role' => \App\Http\Middleware\CheckRole::class,
            'check-company' => \App\Http\Middleware\CheckCompany::class,
            'check-permission' => \App\Http\Middleware\CheckPermission::class,
            'check-subscription' => \App\Http\Middleware\CheckSubscription::class,
            'plan' => \App\Http\Middleware\CheckPlanModule::class,
            'api.company' => \App\Http\Middleware\ApiCompany::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API (app móvil) responde siempre en JSON, aunque el cliente no envíe Accept.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();
