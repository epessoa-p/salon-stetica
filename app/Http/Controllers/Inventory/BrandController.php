<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Company;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = Brand::with('company')->withCount('products')->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        return view('inventory.brands.index', ['brands' => $query->paginate(15)]);
    }

    public function create()
    {
        return view('inventory.brands.create', ['brand' => null, 'companies' => $this->companiesForUser()]);
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

        Brand::create($validated);

        return redirect()->route('brands.index')->with('success', 'Marca creada exitosamente.');
    }

    public function edit(Brand $brand)
    {
        $this->authorizeItem($brand);
        return view('inventory.brands.edit', ['brand' => $brand, 'companies' => $this->companiesForUser()]);
    }

    public function update(Request $request, Brand $brand)
    {
        $this->authorizeItem($brand);

        $validated = $this->validateData($request);
        $validated['active'] = $request->boolean('active', true);
        unset($validated['company_id']);

        $brand->update($validated);

        return redirect()->route('brands.index')->with('success', 'Marca actualizada exitosamente.');
    }

    public function destroy(Brand $brand)
    {
        $this->authorizeItem($brand);

        if ($brand->products()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar una marca con productos asociados.']);
        }

        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Marca eliminada.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'name'       => ['required', 'string', 'max:255'],
            'active'     => ['nullable', 'boolean'],
        ]);
    }

    protected function resolveCompanyId(Request $request): int
    {
        $authUser = auth()->user();
        return $authUser->is_super_admin ? (int) $request->input('company_id') : (int) $authUser->getCurrentCompany()?->id;
    }

    protected function companiesForUser()
    {
        $authUser = auth()->user();
        return $authUser->is_super_admin ? Company::orderBy('name')->get() : collect([$authUser->getCurrentCompany()])->filter();
    }

    protected function authorizeItem(Brand $item): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $item->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
