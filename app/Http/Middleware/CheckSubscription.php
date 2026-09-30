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

        // Sin acceso (vencida más allá de la gracia / suspendida): bloquear.
        if (!$company->subscriptionAllowsRead()) {
            return redirect()->route('subscription.blocked');
        }

        // En periodo de gracia: solo lectura. Se bloquean las escrituras.
        if (!$company->subscriptionAllowsWrite() && $this->isWrite($request)) {
            return redirect()->back()->withErrors([
                'error' => 'Tu suscripción está en periodo de gracia (solo lectura). Renueva para poder registrar cambios.',
            ]);
        }

        return $next($request);
    }

    protected function isWrite(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
