<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->is_super_admin) {
            $users = User::with('companies')->orderByDesc('is_super_admin')->orderBy('name')->paginate(15);
        } else {
            $company = $user->getCurrentCompany();
            $users = $company->users()->with('companies')->where('is_super_admin', false)->orderBy('name')->paginate(15);
        }

        // Nombres de rol de cada vínculo usuario-empresa (pivot company_user.role_id), en una sola consulta.
        $roleIds = $users->getCollection()->flatMap(fn ($u) => $u->companies->pluck('pivot.role_id'))->filter()->unique();
        $roles = Role::whereIn('id', $roleIds)->pluck('name', 'id');

        return view('staff.users.index', [
            'users'          => $users,
            'roles'          => $roles,
            'currentCompany' => $user->is_super_admin ? null : $user->getCurrentCompany(),
            'can'            => [
                'create' => $this->allowed('users.create'),
                'edit'   => $this->allowed('users.edit'),
                'delete' => $this->allowed('users.delete'),
            ],
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $roles = Role::all();
        $companies = $user->is_super_admin ? Company::all() : $user->companies()->get();

        return view('staff.users.create', compact('roles', 'companies'));
    }

    public function store(StoreUserRequest $request)
    {
        $isSuperAdmin = auth()->user()->is_super_admin;
        $links = $isSuperAdmin ? (array) $request->input('companies', []) : [];

        try {
            // Verifica el límite de usuarios del plan por cada empresa a vincular.
            if ($links) {
                foreach ($links as $companyId => $roleId) {
                    if (!$roleId) {
                        continue;
                    }
                    $company = Company::find($companyId);
                    if ($company && !$company->canCreate('users')) {
                        return back()->withInput()->withErrors(['error' => "La empresa {$company->name} alcanzó el límite de usuarios de su plan."]);
                    }
                }
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'is_super_admin' => $isSuperAdmin && $request->boolean('is_super_admin', false),
            ]);

            if ($links) {
                foreach ($links as $companyId => $roleId) {
                    if ($roleId) {
                        $user->companies()->attach($companyId, ['role_id' => $roleId]);
                    }
                }
            }

            return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente');
        } catch (\Throwable $exception) {
            Log::error('Error al crear usuario', [
                'user' => $request->only('name', 'email'),
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible crear el usuario.']);
        }
    }

    public function show(User $user)
    {
        $this->authorizeTarget($user);

        return view('staff.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorizeTarget($user);

        $roles = Role::all();
        $authUser = auth()->user();
        $companies = $authUser->is_super_admin ? Company::all() : $authUser->companies()->get();

        return view('staff.users.edit', compact('user', 'roles', 'companies'));
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $this->authorizeTarget($user);

        try {
            $data = $request->validated();

            if (!auth()->user()->is_super_admin) {
                // Contraseña y email de una cuenta existente: solo el super admin.
                unset($data['is_super_admin'], $data['password'], $data['email']);
            }

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);

            $passwordChanged = !empty($data['password']);
            if ($passwordChanged) {
                $user->revokeSessions();
            }

            return redirect()->route('users.index')->with('success', $passwordChanged
                ? 'Usuario actualizado. La contraseña cambió y se cerraron sus sesiones abiertas.'
                : 'Usuario actualizado exitosamente');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar usuario', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el usuario.']);
        }
    }

    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->user()->id) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario']);
        }

        // La cuenta es una sola: si también trabaja en otra empresa, no se borra desde esta.
        $company = auth()->user()->getCurrentCompany();
        if (!auth()->user()->is_super_admin && $user->companies()->whereKeyNot($company->id)->exists()) {
            return back()->withErrors(['error' => 'Este usuario también pertenece a otra empresa: no se puede eliminar desde aquí.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado exitosamente');
    }

    /**
     * Asignar rol a usuario en una empresa
     */
    public function assignRole(User $user, Company $company, Role $role)
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $authUser->getCurrentCompany()->id !== $company->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $this->authorizeTarget($user);
        abort_if(!$authUser->is_super_admin && $role->slug === 'super_admin', 403);
        abort_unless($role->isAvailableTo($company->id), 403);

        $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => $role->id]]);

        return back()->with('success', 'Rol asignado exitosamente');
    }

    /** Fuera del super admin: solo cuentas de la empresa activa, nunca un operador. */
    protected function authorizeTarget(User $user): void
    {
        $authUser = auth()->user();
        if ($authUser->is_super_admin) {
            return;
        }

        $company = $authUser->getCurrentCompany();
        abort_if($user->is_super_admin || !$company || !$user->companies()->whereKey($company->id)->exists(), 403);
    }

    protected function allowed(string $permission): bool
    {
        $authUser = auth()->user();
        $company = $authUser->getCurrentCompany();

        return $authUser->is_super_admin || ($company && $authUser->hasPermissionInCompany($permission, $company));
    }
}
