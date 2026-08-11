<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = Personal::with(['cargo.role', 'company', 'user'])->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        $personals = $query->paginate(15);

        return view('staff.personal.index', compact('personals'));
    }

    public function create()
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin ? request()->integer('company_id') : $authUser->getCurrentCompany()?->id;

        // Super admin sin empresa seleccionada: la lista de cargos se carga por AJAX al elegir empresa.
        $cargos = $companyId
            ? Cargo::where('company_id', $companyId)
                ->where('active', true)
                ->with('role')
                ->orderBy('name')
                ->get()
            : collect();

        return view('staff.personal.create', [
            'cargos' => $cargos,
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    /**
     * API (AJAX): devuelve los cargos activos de una empresa. Solo para super admin.
     */
    public function cargosByCompany(Company $company)
    {
        if (!auth()->user()->is_super_admin) {
            abort(403);
        }

        $cargos = Cargo::where('company_id', $company->id)
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get()
            ->map(fn ($cargo) => [
                'id' => $cargo->id,
                'label' => $cargo->name . ' · Rol: ' . ($cargo->role?->name ?? '—'),
            ]);

        return response()->json($cargos);
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
                'cargo_id' => ['required', 'exists:cargos,id'],
                'full_name' => ['required', 'string', 'max:255'],
                'id_number' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'required_if:create_access,1', 'email', 'max:255', 'unique:users,email'],
                'address' => ['nullable', 'string', 'max:255'],
                'hire_date' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
                'password' => ['nullable', 'required_if:create_access,1', 'string', 'min:8', 'confirmed'],
                'active' => ['nullable', 'boolean'],
                'create_access' => ['nullable', 'boolean'],
            ]);

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            $createAccess = $request->boolean('create_access');

            $targetCompany = Company::find($companyId);
            if ($targetCompany && !$targetCompany->canCreate('personal')) {
                return back()->withInput()->withErrors(['error' => 'Has alcanzado el límite de personal de tu plan. Actualiza tu plan para agregar más.']);
            }

            // El límite de usuarios solo aplica si se crea acceso al sistema.
            if ($createAccess && $targetCompany && !$targetCompany->canCreate('users')) {
                return back()->withInput()->withErrors(['error' => 'Has alcanzado el límite de usuarios de tu plan. Actualiza tu plan para agregar más.']);
            }

            $cargo = Cargo::findOrFail($validated['cargo_id']);
            if ($cargo->company_id !== $companyId) {
                return back()->withInput()->withErrors(['cargo_id' => 'El cargo seleccionado no pertenece a la empresa actual.']);
            }

            DB::transaction(function () use ($validated, $companyId, $cargo, $request, $createAccess) {
                $userId = null;

                if ($createAccess) {
                    $user = User::create([
                        'name' => $this->makeUniqueUsername($validated['full_name']),
                        'email' => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'phone' => $validated['phone'] ?? null,
                        'active' => $request->boolean('active', true),
                        'is_super_admin' => false,
                    ]);

                    $user->companies()->syncWithoutDetaching([
                        $companyId => [
                            'role_id' => $cargo->role_id,
                            'active' => true,
                        ],
                    ]);

                    $userId = $user->id;
                }

                Personal::create([
                    'company_id' => $companyId,
                    'cargo_id' => $cargo->id,
                    'user_id' => $userId,
                    'full_name' => trim($validated['full_name']),
                    'id_number' => $validated['id_number'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'hire_date' => $validated['hire_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);
            });

            return redirect()->route('personal.index')->with('success',
                $createAccess ? 'Personal creado con acceso al sistema.' : 'Personal creado (sin acceso al sistema).');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear personal', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el personal.']);
        }
    }

    public function edit(Personal $personal)
    {
        $this->authorizePersonal($personal);
        $authUser = auth()->user();

        $companyId = $authUser->is_super_admin
            ? $personal->company_id
            : $authUser->getCurrentCompany()?->id;

        $cargos = Cargo::where('company_id', $companyId)
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        $personal->load('user', 'cargo.role');

        return view('staff.personal.edit', [
            'personal' => $personal,
            'cargos' => $cargos,
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Request $request, Personal $personal)
    {
        $this->authorizePersonal($personal);

        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id', $personal->company_id)
            : (int) $personal->company_id;

        try {
            $hasUser = (bool) $personal->user_id;
            // Se requiere email si ya hay usuario, o si se está creando el acceso ahora.
            $wantsAccess = $hasUser || $request->boolean('create_access');

            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'cargo_id' => ['required', 'exists:cargos,id'],
                'full_name' => ['required', 'string', 'max:255'],
                'id_number' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => [$wantsAccess ? 'required' : 'nullable', 'email', 'max:255', 'unique:users,email,' . $personal->user_id],
                'address' => ['nullable', 'string', 'max:255'],
                'hire_date' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
                // Contraseña obligatoria solo al crear un acceso nuevo; opcional si ya existe.
                'password' => ['nullable', 'required_if:create_access,1', 'string', 'min:8', 'confirmed'],
                'active' => ['nullable', 'boolean'],
                'create_access' => ['nullable', 'boolean'],
            ]);

            $cargo = Cargo::findOrFail($validated['cargo_id']);
            if ($cargo->company_id !== $companyId) {
                return back()->withInput()->withErrors(['cargo_id' => 'El cargo seleccionado no pertenece a la empresa actual.']);
            }

            // Si se va a crear un acceso nuevo, validar el límite de usuarios del plan.
            if (!$hasUser && $request->boolean('create_access')) {
                $targetCompany = Company::find($companyId);
                if ($targetCompany && !$targetCompany->canCreate('users')) {
                    return back()->withInput()->withErrors(['error' => 'Has alcanzado el límite de usuarios de tu plan. Actualiza tu plan para agregar más.']);
                }
            }

            DB::transaction(function () use ($validated, $personal, $cargo, $companyId, $request, $hasUser) {
                $user = $personal->user;

                // Crear usuario nuevo si no tenía y se solicitó acceso.
                if (!$user && $request->boolean('create_access')) {
                    $user = User::create([
                        'name' => $this->makeUniqueUsername($validated['full_name']),
                        'email' => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'phone' => $validated['phone'] ?? null,
                        'active' => $request->boolean('active', false),
                        'is_super_admin' => false,
                    ]);
                }

                if ($user) {
                    $userData = [
                        'email' => $validated['email'],
                        'phone' => $validated['phone'] ?? null,
                        'active' => $request->boolean('active', false),
                    ];
                    if (!empty($validated['password'])) {
                        $userData['password'] = Hash::make($validated['password']);
                    }
                    $user->update($userData);

                    $user->companies()->syncWithoutDetaching([
                        $companyId => [
                            'role_id' => $cargo->role_id,
                            'active' => true,
                        ],
                    ]);
                }

                $personal->update([
                    'company_id' => $companyId,
                    'cargo_id' => $cargo->id,
                    'user_id' => $user?->id,
                    'full_name' => trim($validated['full_name']),
                    'id_number' => $validated['id_number'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'hire_date' => $validated['hire_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'active' => $request->boolean('active', false),
                ]);
            });

            return redirect()->route('personal.index')->with('success', 'Personal actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar personal', ['personal_id' => $personal->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el personal.']);
        }
    }

    public function destroy(Personal $personal)
    {
        $this->authorizePersonal($personal);

        DB::transaction(function () use ($personal) {
            $user = $personal->user;
            $personal->delete();
            if ($user) {
                $user->delete();
            }
        });

        return redirect()->route('personal.index')->with('success', 'Registro de personal eliminado.');
    }

    protected function authorizePersonal(Personal $personal): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $personal->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    protected function makeUniqueUsername(string $fullName): string
    {
        $base = Str::of($fullName)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->trim()
            ->replace(' ', '_')
            ->toString();

        if ($base === '') {
            $base = 'usuario';
        }

        $candidate = $base;
        $counter = 1;

        while (User::where('name', $candidate)->exists()) {
            $candidate = $base . '_' . $counter;
            $counter++;
        }

        return $candidate;
    }
}
