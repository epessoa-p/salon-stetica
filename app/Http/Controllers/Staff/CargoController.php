<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CargoController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = Cargo::with(['company', 'role'])->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        $cargos = $query->paginate(15);

        return view('staff.cargos.index', compact('cargos'));
    }

    public function create()
    {
        $authUser = auth()->user();

        return view('staff.cargos.create', [
            'roles' => $this->assignableRoles($authUser->is_super_admin ? null : $authUser->getCurrentCompany()?->id),
            'permissions' => Permission::all()->groupBy('module'),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id')
            : (int) $authUser->getCurrentCompany()?->id;

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'role_mode' => ['required', 'in:existing,new'],
                'role_id' => ['required_if:role_mode,existing', 'nullable', Rule::exists('roles', 'id')->whereIn('id', $this->assignableRoles($companyId)->pluck('id')->all())],
                // Al crear, el rol siempre es nuevo y toma el nombre del cargo si no se envía uno.
                'new_role_name' => ['nullable', 'string', 'max:255'],
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('cargos', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
                'description' => ['nullable', 'string', 'max:2000'],
                'active' => ['nullable', 'boolean'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['exists:permissions,id'],
            ], ['role_id.exists' => 'Ese rol no está disponible para esta empresa.']);

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            DB::transaction(function () use ($validated, $companyId, $request) {
                $role = $this->resolveRole($validated, $companyId, $request);

                Cargo::create([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);
            });

            return redirect()->route('cargos.index')->with('success', 'Cargo creado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear cargo', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el cargo.']);
        }
    }

    public function edit(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);
        $authUser = auth()->user();

        $cargo->load('role.permissions');

        return view('staff.cargos.edit', [
            'cargo' => $cargo,
            'roles' => $this->assignableRoles($cargo->company_id),
            'permissions' => Permission::all()->groupBy('module'),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Request $request, Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id', $cargo->company_id)
            : (int) $cargo->company_id;

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'role_mode' => ['required', 'in:existing,new'],
                'role_id' => ['required_if:role_mode,existing', 'nullable', Rule::exists('roles', 'id')->whereIn('id', $this->assignableRoles($companyId)->pluck('id')->all())],
                'new_role_name' => ['required_if:role_mode,new', 'nullable', 'string', 'max:255'],
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('cargos', 'name')
                        ->ignore($cargo->id)
                        ->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
                'description' => ['nullable', 'string', 'max:2000'],
                'active' => ['nullable', 'boolean'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['exists:permissions,id'],
            ], ['role_id.exists' => 'Ese rol no está disponible para esta empresa.']);

            DB::transaction(function () use ($validated, $cargo, $companyId, $request) {
                $previousRoleId = (int) $cargo->role_id;
                $role = $this->resolveRole($validated, $companyId, $request);

                // Quienes tienen este cargo pasan al rol nuevo (en esta empresa).
                if ($role->id !== $previousRoleId) {
                    DB::table('company_user')
                        ->where('company_id', $companyId)
                        ->whereIn('user_id', $cargo->personals()->whereNotNull('user_id')->pluck('user_id'))
                        ->update(['role_id' => $role->id]);
                }

                $cargo->update([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', false),
                ]);
            });

            return redirect()->route('cargos.index')->with('success', 'Cargo actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar cargo', ['cargo_id' => $cargo->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el cargo.']);
        }
    }

    public function destroy(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        if ($cargo->personals()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un cargo con personal asignado.']);
        }

        $cargo->delete();

        return redirect()->route('cargos.index')->with('success', 'Cargo eliminado exitosamente.');
    }

    /**
     * API: return permissions for a given role (AJAX).
     */
    public function rolePermissions(Role $role)
    {
        if (!auth()->user()->is_super_admin) {
            abort_unless($this->assignableRoles(auth()->user()->getCurrentCompany()?->id)->contains('id', $role->id), 403);
        }

        return response()->json($role->permissions->pluck('id'));
    }

    /**
     * Roles generales y los de la empresa. El rol super_admin abre la
     * administración de la plataforma: no se asigna desde una empresa.
     */
    protected function assignableRoles(?int $companyId)
    {
        return Role::availableTo($companyId)
            ->when(!auth()->user()->is_super_admin, fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->orderBy('name')
            ->get();
    }

    /**
     * El rol que usará el cargo. Los roles generales los comparten todas las
     * empresas: si se cambian sus permisos desde aquí, la empresa recibe una
     * copia propia y el original no se toca. Los generales se editan en Roles.
     */
    protected function resolveRole(array $validated, int $companyId, Request $request): Role
    {
        $permissions = array_map('intval', $validated['permissions'] ?? []);

        if ($validated['role_mode'] === 'new') {
            // Si no se envía un nombre de rol, se usa el nombre del cargo.
            $name = trim($validated['new_role_name'] ?? '') ?: trim($validated['name']);
            $role = Role::create(['company_id' => $companyId, 'name' => $name, 'slug' => Role::uniqueSlug($name)]);
            $role->permissions()->sync($permissions);

            return $role;
        }

        $role = Role::findOrFail($validated['role_id']);
        if (!$request->has('permissions')) {
            return $role;
        }

        $current = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id)->all();
        if (!array_diff($permissions, $current) && !array_diff($current, $permissions)) {
            return $role;
        }

        if ($role->company_id === null) {
            $name = "{$role->name} (personalizado)";
            $role = Role::create(['company_id' => $companyId, 'name' => $name, 'slug' => Role::uniqueSlug($name), 'description' => $role->description]);
        }
        $role->permissions()->sync($permissions);

        return $role;
    }

    protected function authorizeCargo(Cargo $cargo): void
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $cargo->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
