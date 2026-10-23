<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Inicio y cierre de sesión de la app. Un token por dispositivo, con
 * vencimiento; cerrar sesión lo revoca.
 */
class AuthController extends Controller
{
    /** Días de validez del token: luego la app pide volver a iniciar sesión. */
    public const TOKEN_DAYS = 90;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login'       => ['required', 'string', 'max:255'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        // Igual que en la web: correo o nombre de usuario.
        $login = trim($data['login']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        $user = User::where($field, $login)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['code' => 'invalid_credentials', 'message' => 'Usuario o contraseña incorrectos.'], 422);
        }
        if (!$user->active) {
            return response()->json(['code' => 'user_inactive', 'message' => 'Tu usuario está desactivado.'], 403);
        }

        $companies = $this->companiesFor($user);
        if ($companies->isEmpty()) {
            return response()->json(['code' => 'no_company', 'message' => 'Tu usuario no tiene acceso a ningún salón.'], 403);
        }

        $token = $user->createToken($data['device_name'], ['*'], now()->addDays(self::TOKEN_DAYS));

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'       => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'companies'  => $companies->map(fn ($c) => $this->companyCard($c))->values(),
        ]);
    }

    /** Empresas a las que puede entrar (para el selector de la app). */
    public function companies(Request $request): JsonResponse
    {
        return response()->json($this->companiesFor($request->user())->map(fn ($c) => $this->companyCard($c))->values());
    }

    /** Revoca el token del dispositivo actual. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    protected function companiesFor(User $user)
    {
        return $user->is_super_admin
            ? Company::where('active', true)->orderBy('name')->get()
            : $user->activeCompanies()->orderBy('name')->get();
    }

    public static function companyCard(Company $c): array
    {
        return [
            'id'              => $c->id,
            'name'            => $c->name,
            'logo_url'        => $c->logo ? asset('storage/' . $c->logo) : null,
            'primary_color'   => $c->primary_color,
            'secondary_color' => $c->secondary_color,
        ];
    }
}
