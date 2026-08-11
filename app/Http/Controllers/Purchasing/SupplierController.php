<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $search = trim((string) $request->input('q'));
        $query = Supplier::with('company')->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }

        return view('purchasing.suppliers.index', ['suppliers' => $query->paginate(15)->withQueryString(), 'search' => $search]);
    }

    public function create()
    {
        return view('purchasing.suppliers.create', ['supplier' => null, 'companies' => $this->companiesForUser()]);
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

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Proveedor creado exitosamente.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizeItem($supplier);
        return view('purchasing.suppliers.edit', ['supplier' => $supplier, 'companies' => $this->companiesForUser()]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeItem($supplier);

        $validated = $this->validateData($request);
        $validated['active'] = $request->boolean('active', true);
        unset($validated['company_id']);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Proveedor actualizado exitosamente.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizeItem($supplier);

        if ($supplier->purchaseOrders()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un proveedor con órdenes de compra.']);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'company_id'   => ['nullable', 'exists:companies,id'],
            'name'         => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:255'],
            'tax_id'       => ['nullable', 'string', 'max:50'],
            'address'      => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
            'active'       => ['nullable', 'boolean'],
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

    protected function authorizeItem(Supplier $item): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $item->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
