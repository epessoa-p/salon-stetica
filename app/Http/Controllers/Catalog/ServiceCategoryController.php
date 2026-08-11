<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = ServiceCategory::with('company')->withCount('services')->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        return view('catalog.categories.index', ['categories' => $query->paginate(15)]);
    }

    public function create()
    {
        return view('catalog.categories.create', [
            'category' => null,
            'companies' => $this->companiesForUser(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $validated = $this->validateData($request);

        if (!$companyId) {
            return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
        }

        $validated['company_id'] = $companyId;
        $validated['created_by'] = auth()->id();
        $validated['active'] = $request->boolean('active', true);

        ServiceCategory::create($validated);

        return redirect()->route('service-categories.index')->with('success', 'Categoría creada exitosamente.');
    }

    public function edit(ServiceCategory $serviceCategory)
    {
        $this->authorizeCategory($serviceCategory);

        return view('catalog.categories.edit', [
            'category' => $serviceCategory,
            'companies' => $this->companiesForUser(),
        ]);
    }

    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $this->authorizeCategory($serviceCategory);

        $validated = $this->validateData($request);
        $validated['active'] = $request->boolean('active', true);
        unset($validated['company_id']);

        $serviceCategory->update($validated);

        return redirect()->route('service-categories.index')->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy(ServiceCategory $serviceCategory)
    {
        $this->authorizeCategory($serviceCategory);

        if ($serviceCategory->services()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar una categoría con servicios asociados.']);
        }

        $serviceCategory->delete();

        return redirect()->route('service-categories.index')->with('success', 'Categoría eliminada.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'company_id'  => ['nullable', 'exists:companies,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active'      => ['nullable', 'boolean'],
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

    protected function authorizeCategory(ServiceCategory $category): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $category->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
