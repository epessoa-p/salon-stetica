<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $user = auth()->user();

        // El super admin nunca se bloquea.
        if ($user->is_super_admin) {
            return $next($request);
        }

        $company = $user->getCurrentCompany();

        // Sin empresa activa: dejar pasar (otros middlewares/flujos lo manejan).
        if (!$company) {
            return $next($request);
        }

        // Si la empresa no tiene acceso (vencida más allá de la gracia), bloquear.
        if (!$company->hasAccess()) {
            return redirect()->route('subscription.blocked');
        }

        return $next($request);
    }
}
