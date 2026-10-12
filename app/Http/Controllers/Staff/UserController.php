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
            $users = $company->users()->with('companies')->orderBy('name')->paginate(15);
        }

        // Nombres de rol de cada vínculo usuario-empresa (pivot company_user.role_id), en una sola consulta.
        $roleIds = $users->getCollection()->flatMap(fn ($u) => $u->companies->pluck('pivot.role_id'))->filter()->unique();
        $roles = Role::whereIn('id', $roleIds)->pluck('name', 'id');

        return view('staff.users.index', [
            'users'          => $users,
            'roles'          => $roles,
            'currentCompany' => $user->is_super_admin ? null : $user->getCurrentCompany(),
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
        try {
            // Verifica el límite de usuarios del plan por cada empresa a vincular.
            if ($request->has('companies') && $request->companies) {
                foreach ($request->companies as $companyId => $roleId) {
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
                'is_super_admin' => $request->boolean('is_super_admin', false),
            ]);

            if ($request->has('companies') && $request->companies) {
                foreach ($request->companies as $companyId => $roleId) {
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
        return view('staff.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $authUser = auth()->user();
        $companies = $authUser->is_super_admin ? Company::all() : $authUser->companies()->get();

        return view('staff.users.edit', compact('user', 'roles', 'companies'));
    }

    public function update(StoreUserRequest $request, User $user)
    {
        try {
            $data = $request->validated();

            if (!auth()->user()->is_super_admin) {
                unset($data['is_super_admin']);
            }

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);

            return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente');
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
        if ($user->id === auth()->user()->id) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario']);
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

        $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => $role->id]]);

        return back()->with('success', 'Rol asignado exitosamente');
    }
}
