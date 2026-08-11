<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
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

        // Verificar si el usuario tiene alguno de los permisos requeridos
        foreach ($permissions as $permission) {
            if ($user->hasPermissionInCompany($permission, $company)) {
                return $next($request);
            }
        }

        return response()->view('errors.403', [], 403);
    }
}
