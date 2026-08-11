<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $search = trim((string) $request->input('q'));

        $query = Product::with(['company', 'category', 'brand'])->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        return view('inventory.products.index', [
            'products' => $query->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create()
    {
        $companyId = $this->resolveCompanyId(request());

        return view('inventory.products.create', array_merge($this->metaFor($companyId), [
            'product' => null,
            'companies' => $this->companiesForUser(),
        ]));
    }

    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $validated = $this->validateData($request);

        if (!$companyId) {
            return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
        }

        $this->assertMetaBelongsToCompany($validated, $companyId);

        $validated['company_id'] = $companyId;
        $validated['sku'] = $validated['sku'] ?: $this->nextSku($companyId);
        $validated['active'] = $request->boolean('active', true);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Producto creado exitosamente.');
    }

    public function edit(Product $product)
    {
        $this->authorizeProduct($product);

        return view('inventory.products.edit', array_merge($this->metaFor($product->company_id), [
            'product' => $product,
            'companies' => $this->companiesForUser(),
        ]));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($product);

        $validated = $this->validateData($request);
        $this->assertMetaBelongsToCompany($validated, $product->company_id);
        $validated['active'] = $request->boolean('active', true);
        unset($validated['company_id']);
        // El stock no se edita aquí; se ajusta desde Existencias/movimientos.
        unset($validated['current_stock']);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeProduct($product);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Producto eliminado.');
    }

    /** API (AJAX): categorías y marcas de una empresa (super admin). */
    public function metaByCompany(Company $company)
    {
        if (!auth()->user()->is_super_admin) {
            abort(403);
        }

        return response()->json([
            'categories' => ProductCategory::where('company_id', $company->id)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::where('company_id', $company->id)->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ---- Helpers ----

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'company_id'          => ['nullable', 'exists:companies,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'brand_id'            => ['nullable', 'exists:brands,id'],
            'name'                => ['required', 'string', 'max:255'],
            'sku'                 => ['nullable', 'string', 'max:100'],
            'description'         => ['nullable', 'string'],
            'unit'                => ['nullable', 'string', 'max:50'],
            'cost'                => ['nullable', 'numeric', 'min:0'],
            'price'               => ['required', 'numeric', 'min:0'],
            'min_stock'           => ['nullable', 'numeric', 'min:0'],
            'current_stock'       => ['nullable', 'numeric', 'min:0'],
            'active'              => ['nullable', 'boolean'],
        ]);
    }

    protected function metaFor(?int $companyId): array
    {
        return [
            'categories' => $companyId ? ProductCategory::where('company_id', $companyId)->where('active', true)->orderBy('name')->get() : collect(),
            'brands' => $companyId ? Brand::where('company_id', $companyId)->where('active', true)->orderBy('name')->get() : collect(),
        ];
    }

    protected function assertMetaBelongsToCompany(array $data, int $companyId): void
    {
        if (!empty($data['product_category_id']) && ProductCategory::whereKey($data['product_category_id'])->where('company_id', $companyId)->doesntExist()) {
            abort(422, 'La categoría no pertenece a la empresa.');
        }
        if (!empty($data['brand_id']) && Brand::whereKey($data['brand_id'])->where('company_id', $companyId)->doesntExist()) {
            abort(422, 'La marca no pertenece a la empresa.');
        }
    }

    protected function nextSku(int $companyId): string
    {
        $n = Product::withTrashed()->where('company_id', $companyId)->count() + 1;

        do {
            $sku = 'P-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
            $n++;
        } while (Product::withTrashed()->where('company_id', $companyId)->where('sku', $sku)->exists());

        return $sku;
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

    protected function authorizeProduct(Product $product): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $product->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
