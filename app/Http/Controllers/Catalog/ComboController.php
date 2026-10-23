<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Combos: servicios y productos a precio especial, cobrados en una sola venta. */
class ComboController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('catalog.combos.index', ['noCompany' => true]);
        }

        $combos = Combo::with(['items.service', 'items.product'])
            ->where('company_id', $companyId)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // Veces vendido: un combo se guarda como varias líneas, se cuentan las ventas.
        $sold = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', '!=', 'anulada')
            ->whereNotNull('sale_details.combo_id')
            ->selectRaw('sale_details.combo_id, COUNT(DISTINCT sale_details.sale_id) AS n')
            ->groupBy('sale_details.combo_id')
            ->pluck('n', 'combo_id');

        return view('catalog.combos.index', ['noCompany' => false, 'combos' => $combos, 'sold' => $sold]);
    }

    public function create()
    {
        return view('catalog.combos.form', $this->formData(new Combo(['active' => true])));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        $data = $this->validateCombo($request, $companyId);

        DB::transaction(function () use ($data, $companyId) {
            $combo = Combo::create($data['combo'] + ['company_id' => $companyId, 'created_by' => auth()->id()]);
            $combo->items()->createMany($data['items']);
        });

        return redirect()->route('combos.index')->with('success', 'Combo creado. Ya se puede vender desde el punto de venta.');
    }

    public function edit(Combo $combo)
    {
        $this->authorizeCombo($combo);

        return view('catalog.combos.form', $this->formData($combo->load('items')));
    }

    public function update(Request $request, Combo $combo)
    {
        $this->authorizeCombo($combo);
        $data = $this->validateCombo($request, $combo->company_id);

        DB::transaction(function () use ($combo, $data) {
            $combo->update($data['combo']);
            $combo->items()->delete();
            $combo->items()->createMany($data['items']);
        });

        return redirect()->route('combos.index')->with('success', 'Combo actualizado. Las ventas ya hechas no cambian.');
    }

    public function destroy(Combo $combo)
    {
        $this->authorizeCombo($combo);
        $combo->delete(); // borrado lógico: las ventas lo siguen referenciando

        return redirect()->route('combos.index')->with('success', 'Combo eliminado.');
    }

    // ---- Helpers ----

    protected function validateCombo(Request $request, int $companyId): array
    {
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        $v = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'price'              => ['required', 'numeric', 'min:0'],
            'sort_order'         => ['nullable', 'integer', 'min:0', 'max:999'],
            'items'              => ['nullable', 'array'],
            'items.*.ref'        => ['nullable', 'string', 'regex:/^(service|product):\d+$/'],
            'items.*.quantity'   => ['nullable', 'numeric', 'min:0.01', 'max:999'],
        ]);

        // "service:12" / "product:5" → una fila por componente; repetidos se suman.
        $items = collect($v['items'] ?? [])
            ->filter(fn ($i) => !empty($i['ref']) && !empty($i['quantity']))
            ->groupBy('ref')
            ->map(function ($rows, $ref) {
                [$kind, $id] = explode(':', $ref);

                return [
                    'service_id' => $kind === 'service' ? (int) $id : null,
                    'product_id' => $kind === 'product' ? (int) $id : null,
                    'quantity'   => round((float) $rows->sum('quantity'), 2),
                ];
            })->values();

        if ($items->count() < 2 && $items->sum('quantity') < 2) {
            throw ValidationException::withMessages(['items' => 'Un combo debe tener al menos dos componentes (o un componente con cantidad 2 o más).']);
        }

        $serviceIds = $items->pluck('service_id')->filter();
        $productIds = $items->pluck('product_id')->filter();
        if (Service::where('company_id', $companyId)->whereIn('id', $serviceIds)->count() !== $serviceIds->count()
            || Product::where('company_id', $companyId)->whereIn('id', $productIds)->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['items' => 'El combo incluye servicios o productos que no son de la empresa.']);
        }
        if ($productIds->isNotEmpty() && !auth()->user()->getCurrentCompany()?->planAllows('inventory')) {
            throw ValidationException::withMessages(['items' => 'El plan de la empresa no incluye inventario: el combo solo puede tener servicios.']);
        }

        return [
            'combo' => [
                'name'        => $v['name'],
                'description' => $v['description'] ?? null,
                'price'       => $v['price'],
                'sort_order'  => (int) ($v['sort_order'] ?? 0),
                'active'      => $request->boolean('active'),
            ],
            'items' => $items->all(),
        ];
    }

    protected function formData(Combo $combo): array
    {
        $companyId = $this->companyId();
        $company = auth()->user()->getCurrentCompany();

        $options = Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'price'])
            ->map(fn ($s) => ['ref' => 'service:' . $s->id, 'name' => $s->name, 'price' => (float) $s->price, 'group' => 'Servicios']);

        if ($company?->planAllows('inventory')) {
            $options = $options->concat(Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn ($p) => ['ref' => 'product:' . $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'group' => 'Productos']));
        }

        return ['combo' => $combo, 'options' => $options->values()];
    }

    protected function authorizeCombo(Combo $combo): void
    {
        abort_if($combo->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
