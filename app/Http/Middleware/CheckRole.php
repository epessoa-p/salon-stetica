<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $user = auth()->user();

        // Super admin siempre puede pasar
        if ($user->is_super_admin) {
            return $next($request);
        }

        // Obtener empresa actual
        $company = $user->getCurrentCompany();
        if (!$company) {
            auth()->logout();
            return redirect('login')->with('error', 'No tienes acceso a ninguna empresa');
        }

        // Verificar si el usuario tiene alguno de los roles requeridos en esta empresa
        foreach ($roles as $role) {
            if ($user->hasRoleInCompany($role, $company)) {
                return $next($request);
            }
        }

        return response()->view('errors.403', [], 403);
    }
}
