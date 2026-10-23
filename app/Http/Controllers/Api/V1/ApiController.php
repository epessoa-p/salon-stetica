<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Personal;
use Illuminate\Http\JsonResponse;

/**
 * Base de la API de la app móvil. La empresa activa la fija el middleware
 * ApiCompany (header X-Company-Id). Los errores salen como JSON con un
 * `code` estable que la app interpreta.
 */
abstract class ApiController extends Controller
{
    protected function company(): Company
    {
        return app('api.company');
    }

    protected function can(string $permission): bool
    {
        $user = auth()->user();

        return $user->is_super_admin || $user->hasPermissionInCompany($permission, $this->company());
    }

    /** Ficha de personal del usuario conectado en la empresa activa. */
    protected function ownPersonal(): ?Personal
    {
        return Personal::where('company_id', $this->company()->id)
            ->where('user_id', auth()->id())
            ->first();
    }

    protected function fail(int $status, string $code, string $message, array $extra = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message] + $extra, $status);
    }

    protected function forbidden(string $message = 'No tienes permiso para esta acción.'): JsonResponse
    {
        return $this->fail(403, 'forbidden', $message);
    }

    /** El módulo debe estar en el plan del salón. */
    protected function planMissing(string $feature): ?JsonResponse
    {
        return $this->company()->planAllows($feature)
            ? null
            : $this->fail(403, 'plan_module_missing', 'El plan del salón no incluye este módulo.');
    }

    protected function noPersonal(): JsonResponse
    {
        return $this->fail(422, 'no_personal', 'Tu usuario no está vinculado a una ficha de personal en este salón.');
    }
}
