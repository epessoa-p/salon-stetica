<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa activa (Tenancy) desde la sesión.
 * Super admin ⇒ null (sin filtro, ve todas las empresas).
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenancy = app(Tenancy::class);

        if (auth()->check()) {
            $user = auth()->user();
            $tenancy->set($user->is_super_admin ? null : $user->getCurrentCompany()?->id);
        } else {
            $tenancy->forget();
        }

        return $next($request);
    }
}
