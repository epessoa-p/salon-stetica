<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\CompanySetting;
use App\Models\LoyaltyMovement;
use App\Services\LoyaltyService;
use App\Services\MembershipService;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $search = trim((string) $request->input('q'));

        $query = Client::with('company')->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate(15)->withQueryString();

        return view('crm.clients.index', compact('clients', 'search'));
    }

    public function create()
    {
        return view('crm.clients.create', [
            'client' => null,
            'companies' => $this->companiesForUser(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        try {
            $validated = $this->validateData($request);

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            $validated['company_id'] = $companyId;
            $validated['created_by'] = auth()->id();
            $validated['active'] = $request->boolean('active', true);

            Client::create($validated);

            return redirect()->route('clients.index')->with('success', 'Cliente registrado exitosamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al crear cliente', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el cliente.']);
        }
    }

    public function show(Client $client)
    {
        $this->authorizeClient($client);
        $client->load('company');

        return view('crm.clients.show', ['client' => $client] + $this->history($client) + $this->benefits($client) + $this->prepaid($client));
    }

    /** Paquetes de sesiones y saldo a favor del cliente, según plan y permisos. */
    protected function prepaid(Client $client): array
    {
        $user = auth()->user();
        $company = $client->company;
        $can = fn (string $feature, string $permission) => $user->is_super_admin
            || ($company && $company->planAllows($feature) && $user->hasPermissionInCompany($permission, $company));

        $showPackages = $can('catalog', 'packages.view');
        $showCredit = $can('sales', 'sales.view');
        $packages = app(\App\Services\PackageService::class);

        return [
            'showPackages'    => $showPackages,
            'showCredit'      => $showCredit,
            'clientPackages'  => $showPackages
                ? \App\Models\ClientPackage::with('items.service')->where('client_id', $client->id)->latest('id')->get()
                    ->map(fn ($p) => ['model' => $p] + $packages->summary($p))
                : collect(),
            'creditBalance'   => $showCredit ? app(\App\Services\CreditService::class)->balance($client->company_id, $client->id) : 0.0,
            'creditMovements' => $showCredit
                ? \App\Models\ClientCreditMovement::with(['sale', 'saleReturn'])->where('client_id', $client->id)->latest('id')->limit(15)->get()
                : collect(),
        ];
    }

    /**
     * Membresías y puntos del cliente. Solo si el plan incluye el módulo
     * `loyalty` y el usuario puede ver membresías o fidelización.
     */
    protected function benefits(Client $client): array
    {
        $user = auth()->user();
        $company = $client->company;
        $can = fn (string $permission) => $user->is_super_admin
            || ($company && $company->planAllows('loyalty') && $user->hasPermissionInCompany($permission, $company));

        $showMemberships = $can('memberships.view');
        $showPoints = $can('loyalty.view');
        $memberships = app(MembershipService::class);
        $loyalty = app(LoyaltyService::class);

        $history = $showMemberships
            ? ClientMembership::with('sessions.service')->where('client_id', $client->id)->orderByDesc('starts_on')->get()
            : collect();
        $active = $history->first(fn ($m) => $m->isActiveOn());

        return [
            'showMemberships'   => $showMemberships,
            'showPoints'        => $showPoints,
            'activeMembership'  => $active ? $memberships->summary($active) : null,
            'membershipHistory' => $history,
            'points'            => $showPoints ? $loyalty->summary($client->company_id, $client->id) : null,
            'pointsEnabled'     => $showPoints && $loyalty->enabled($company),
            'pointMovements'    => $showPoints
                ? LoyaltyMovement::with('sale')->where('client_id', $client->id)->orderByDesc('created_at')->orderByDesc('id')->limit(15)->get()
                : collect(),
        ];
    }

    /**
     * Historial del cliente: citas y ventas, más los indicadores que resumen
     * su relación con el salón. Cada bloque se consulta solo si el plan
     * incluye el módulo y el usuario tiene permiso de verlo.
     */
    protected function history(Client $client): array
    {
        $user = auth()->user();
        $company = $client->company;

        $can = function (string $feature, string $permission) use ($user, $company): bool {
            if ($user->is_super_admin) {
                return true;
            }

            return $company
                && $company->planAllows($feature)
                && $user->hasPermissionInCompany($permission, $company);
        };

        $showAppointments = $can('scheduling', 'appointments.view');
        $showSales = $can('sales', 'sales.view');

        $appointments = $showAppointments
            ? Appointment::with(['service', 'specialist'])
                ->where('client_id', $client->id)
                ->orderByDesc('starts_at')
                ->limit(50)
                ->get()
            : collect();

        $sales = $showSales
            ? Sale::with('items.service')
                ->where('client_id', $client->id)
                ->orderByDesc('sale_date')
                ->limit(50)
                ->get()
            : collect();

        // Indicadores: solo se cuentan las ventas no anuladas.
        $paidSales = $sales->where('status', '!=', 'anulada');
        $doneAppointments = $appointments->where('status', 'completada');

        $topService = $doneAppointments->filter(fn ($a) => $a->service)
            ->groupBy('service_id')
            ->sortByDesc->count()
            ->first();

        $lastVisit = collect([
            $doneAppointments->max('starts_at'),
            $paidSales->max('sale_date'),
        ])->filter()->max();

        return [
            'appointments'     => $appointments,
            'sales'            => $sales,
            'showAppointments' => $showAppointments,
            'showSales'        => $showSales,
            'setting'          => CompanySetting::current($client->company_id),
            'stats'            => [
                'visitas'     => $doneAppointments->count(),
                'compras'     => $paidSales->count(),
                // Neto de devoluciones: lo que el cliente efectivamente dejó en el salón.
                'gastado'     => (float) $paidSales->sum(fn ($s) => $s->netTotal()),
                'ticket'      => $paidSales->count() ? (float) $paidSales->sum(fn ($s) => $s->netTotal()) / $paidSales->count() : 0.0,
                'ultima'      => $lastVisit,
                'canceladas'  => $appointments->whereIn('status', ['cancelada', 'no_show'])->count(),
                'favorito'    => $topService?->first()?->service?->name,
            ],
        ];
    }

    public function edit(Client $client)
    {
        $this->authorizeClient($client);

        return view('crm.clients.edit', [
            'client' => $client,
            'companies' => $this->companiesForUser(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $this->authorizeClient($client);

        try {
            $validated = $this->validateData($request);
            $validated['active'] = $request->boolean('active', true);

            // La empresa no se cambia desde aquí (se mantiene la del cliente).
            unset($validated['company_id']);

            $client->update($validated);

            return redirect()->route('clients.index')->with('success', 'Cliente actualizado exitosamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al actualizar cliente', ['client_id' => $client->id, 'message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el cliente.']);
        }
    }

    public function destroy(Client $client)
    {
        $this->authorizeClient($client);
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'full_name'  => ['required', 'string', 'max:255'],
            'id_number'  => ['nullable', 'string', 'max:50'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:255'],
            'birthdate'  => ['nullable', 'date'],
            'gender'     => ['nullable', 'in:femenino,masculino,otro'],
            'address'    => ['nullable', 'string', 'max:255'],
            'source'     => ['nullable', 'string', 'max:255'],
            'notes'      => ['nullable', 'string'],
            'active'     => ['nullable', 'boolean'],
        ]);
    }

    protected function resolveCompanyId(Request $request): int
    {
        $authUser = auth()->user();

        return $authUser->is_super_admin
            ? (int) $request->input('company_id')
            : (int) $authUser->getCurrentCompany()?->id;
    }

    protected function companiesForUser()
    {
        $authUser = auth()->user();

        return $authUser->is_super_admin
            ? Company::orderBy('name')->get()
            : collect([$authUser->getCurrentCompany()])->filter();
    }

    protected function authorizeClient(Client $client): void
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $client->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
