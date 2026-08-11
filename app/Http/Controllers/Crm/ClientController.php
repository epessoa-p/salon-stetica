<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
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

        return view('crm.clients.show', compact('client'));
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
