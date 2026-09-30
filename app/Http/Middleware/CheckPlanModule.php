<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el plan de la empresa activa incluya el módulo <feature>.
 * Super admin pasa siempre. Sin empresa activa, deja pasar (otros guards actúan).
 */
class CheckPlanModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $user = auth()->user();
        if ($user->is_super_admin) {
            return $next($request);
        }

        $company = $user->getCurrentCompany();
        if (!$company) {
            return $next($request);
        }

        if (!$company->planAllows($module)) {
            $label = Plan::MODULES[$module] ?? $module;
            return response()->view('errors.plan-module', ['module' => $label], 403);
        }

        return $next($request);
    }
}
