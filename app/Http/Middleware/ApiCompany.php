<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Empresa activa de la API (la app móvil no tiene sesión).
 *
 * - Toma la empresa del header X-Company-Id y verifica que el usuario
 *   pertenezca a ella (o sea super admin). Si el usuario tiene una sola
 *   empresa, el header es opcional.
 * - Fija Tenancy y la empresa que devuelve User::getCurrentCompany(), así los
 *   servicios existentes funcionan igual que en la web.
 * - Aplica la suscripción: sin acceso → 402; en gracia solo lectura → 423.
 * Responde siempre en JSON con un `code` estable para la app.
 */
class ApiCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user->active) {
            $user->currentAccessToken()?->delete();
            return $this->fail(401, 'user_inactive', 'Tu usuario está desactivado.');
        }

        $companies = $user->is_super_admin
            ? Company::where('active', true)->get()
            : $user->activeCompanies()->get();

        $requested = $request->header('X-Company-Id');
        $company = $requested
            ? $companies->firstWhere('id', (int) $requested)
            : ($companies->count() === 1 ? $companies->first() : null);

        if (!$company) {
            return $requested
                ? $this->fail(403, 'company_forbidden', 'No tienes acceso a esa empresa.')
                : $this->fail(409, 'company_required', 'Elige con qué empresa quieres trabajar.', [
                    'companies' => $companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                ]);
        }

        if (!$user->is_super_admin) {
            if (!$company->subscriptionAllowsRead()) {
                return $this->fail(402, 'subscription_blocked', 'La suscripción del salón está vencida. Contacta al administrador.');
            }
            if (!$company->subscriptionAllowsWrite() && !$request->isMethodSafe()) {
                return $this->fail(423, 'subscription_read_only', 'La suscripción está en periodo de gracia: solo se puede consultar.');
            }
        }

        app()->instance('api.company', $company);
        app(Tenancy::class)->set($company->id);

        return $next($request);
    }

    protected function fail(int $status, string $code, string $message, array $extra = []): Response
    {
        return response()->json(['code' => $code, 'message' => $message] + $extra, $status);
    }
}
