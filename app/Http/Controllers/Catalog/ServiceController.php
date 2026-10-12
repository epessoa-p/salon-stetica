<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $search = trim((string) $request->input('q'));

        $query = Service::with(['company', 'category'])->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return view('catalog.services.index', [
            'services' => $query->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create()
    {
        $companyId = $this->resolveCompanyId(request());

        return view('catalog.services.create', [
            'service' => null,
            'companies' => $this->companiesForUser(),
            'categories' => $this->categoriesFor($companyId),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $validated = $this->validateData($request);

        if (!$companyId) {
            return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
        }

        // La categoría (si se envía) debe pertenecer a la empresa.
        if (!empty($validated['service_category_id'])) {
            $cat = ServiceCategory::find($validated['service_category_id']);
            if (!$cat || $cat->company_id !== $companyId) {
                return back()->withInput()->withErrors(['service_category_id' => 'La categoría no pertenece a la empresa seleccionada.']);
            }
        }

        $validated['company_id'] = $companyId;
        $validated['created_by'] = auth()->id();
        $validated['active'] = $request->boolean('active', true);

        Service::create($validated);

        return redirect()->route('services.index')->with('success', 'Servicio creado exitosamente.');
    }

    public function edit(Service $service)
    {
        $this->authorizeService($service);

        return view('catalog.services.edit', [
            'service' => $service,
            'companies' => $this->companiesForUser(),
            'categories' => $this->categoriesFor($service->company_id),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeService($service);

        $validated = $this->validateData($request);
        $validated['active'] = $request->boolean('active', true);
        unset($validated['company_id']);

        if (!empty($validated['service_category_id'])) {
            $cat = ServiceCategory::find($validated['service_category_id']);
            if (!$cat || $cat->company_id !== $service->company_id) {
                return back()->withInput()->withErrors(['service_category_id' => 'La categoría no pertenece a la empresa del servicio.']);
            }
        }

        $service->update($validated);

        return redirect()->route('services.index')->with('success', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Service $service)
    {
        $this->authorizeService($service);
        $service->delete();

        return redirect()->route('services.index')->with('success', 'Servicio eliminado.');
    }

    /**
     * API (AJAX): categorías activas de una empresa (para super admin).
     */
    public function categoriesByCompany(Company $company)
    {
        if (!auth()->user()->is_super_admin) {
            abort(403);
        }

        $categories = ServiceCategory::where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($categories);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'company_id'          => ['nullable', 'exists:companies,id'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'price'               => ['required', 'numeric', 'min:0'],
            'duration_minutes'    => ['nullable', 'integer', 'min:0'],
            'active'              => ['nullable', 'boolean'],
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

    protected function categoriesFor(?int $companyId)
    {
        if (!$companyId) {
            return collect();
        }

        return ServiceCategory::where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    protected function authorizeService(Service $service): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $service->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
