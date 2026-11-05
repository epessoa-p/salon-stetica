<?php

namespace App\Providers;

use App\Support\Tenancy;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La empresa activa vive durante todo el request (una sola instancia).
        $this->app->singleton(Tenancy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La sesión puede seguir viva tras salir: sin esto, quien entre después
        // con otra cuenta chocaría con la huella de contraseña de la anterior.
        Event::listen(Logout::class, function () {
            session()->forget('password_hash_web');
        });
    }
}
