<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\BranchResolver;
use App\Services\StockService;
use App\Services\StockTransferService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Traspasos de productos entre almacenes (sucursales). */
class StockTransferController extends Controller
{
    public function __construct(protected StockTransferService $transfers)
    {
    }

    public function index()
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 404);

        return view('inventory.transfers.index', [
            'transfers' => StockTransfer::with(['from.branches', 'to.branches', 'creator'])->withCount('items')
                ->where('company_id', $companyId)->latest('transferred_at')->latest('id')->paginate(20),
            'warehouseCount' => Warehouse::where('company_id', $companyId)->where('active', true)->count(),
        ]);
    }

    public function create(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 404);

        $warehouses = Warehouse::with('branches:id,warehouse_id,name')->where('company_id', $companyId)->where('active', true)->orderBy('id')->get();
        $products = Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit']);

        return view('inventory.transfers.create', [
            'warehouses'  => $warehouses,
            'products'    => $products,
            // Origen sugerido: el almacén de la sucursal del usuario.
            'defaultFrom' => app(StockService::class)->warehouseFor($companyId, app(BranchResolver::class)->defaultFor($companyId, auth()->id())),
            'stocks'      => ProductStock::whereIn('product_id', $products->pluck('id'))->get()
                ->groupBy('product_id')->map(fn ($rows) => $rows->pluck('quantity', 'warehouse_id')->map(fn ($q) => (float) $q)),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 404);

        $data = $request->validate([
            'from_warehouse_id'    => ['required', 'integer'],
            'to_warehouse_id'      => ['required', 'integer', 'different:from_warehouse_id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ], [
            'to_warehouse_id.different' => 'El almacén de destino debe ser distinto al de origen.',
            'items.required'            => 'Agrega al menos un producto.',
            'items.*.quantity.min'      => 'Cada cantidad debe ser mayor a cero.',
        ]);

        try {
            $transfer = DB::transaction(fn () => $this->transfers->create(
                $companyId, (int) $data['from_warehouse_id'], (int) $data['to_warehouse_id'], $data['items'], $data['notes'] ?? null,
            ));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('stock-transfers.show', $transfer)->with('success', "Traspaso {$transfer->transfer_number} registrado.");
    }

    public function show(StockTransfer $stockTransfer)
    {
        abort_if($stockTransfer->company_id !== $this->companyId(), 404);
        $stockTransfer->load(['items.product', 'from.branches', 'to.branches', 'creator']);

        return view('inventory.transfers.show', ['transfer' => $stockTransfer]);
    }

    public function void(StockTransfer $stockTransfer)
    {
        abort_if($stockTransfer->company_id !== $this->companyId(), 404);

        try {
            DB::transaction(fn () => $this->transfers->void($stockTransfer));
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage() . ' No se puede anular el traspaso.']);
        }

        return back()->with('success', "Traspaso {$stockTransfer->transfer_number} anulado: los productos volvieron al origen.");
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
