<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = ProductCategory::with('company')->withCount('products')->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        return view('inventory.categories.index', ['categories' => $query->paginate(15)]);
    }

    public function create()
    {
        return view('inventory.categories.create', ['category' => null, 'companies' => $this->companiesForUser()]);
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

        ProductCategory::create($validated);

        return redirect()->route('product-categories.index')->with('success', 'Categoría creada exitosamente.');
    }

    public function edit(ProductCategory $productCategory)
    {
        $this->authorizeItem($productCategory);
        return view('inventory.categories.edit', ['category' => $productCategory, 'companies' => $this->companiesForUser()]);
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $this->authorizeItem($productCategory);

        $validated = $this->validateData($request);
        $validated['active'] = $request->boolean('active', true);
        unset($validated['company_id']);

        $productCategory->update($validated);

        return redirect()->route('product-categories.index')->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $this->authorizeItem($productCategory);

        if ($productCategory->products()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar una categoría con productos asociados.']);
        }

        $productCategory->delete();

        return redirect()->route('product-categories.index')->with('success', 'Categoría eliminada.');
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
        return $authUser->is_super_admin ? (int) $request->input('company_id') : (int) $authUser->getCurrentCompany()?->id;
    }

    protected function companiesForUser()
    {
        $authUser = auth()->user();
        return $authUser->is_super_admin ? Company::orderBy('name')->get() : collect([$authUser->getCurrentCompany()])->filter();
    }

    protected function authorizeItem(ProductCategory $item): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $item->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
