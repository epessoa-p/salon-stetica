<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Devoluciones a proveedores, siempre sobre una orden de compra recibida:
 * de cada línea se puede devolver hasta lo recibido menos lo ya devuelto.
 * Saca el stock al costo de la compra. Si el proveedor devuelve el dinero se
 * registra como ingreso ("Devolución de proveedor"); si emite una nota de
 * crédito solo queda el registro.
 */
class PurchaseReturnController extends Controller
{
    public function __construct(protected StockService $stock)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('purchasing.returns.index', ['noCompany' => true]);
        }

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();
        $base = PurchaseReturn::where('company_id', $companyId)->whereBetween('return_date', [$from, $to]);

        return view('purchasing.returns.index', [
            'noCompany' => false,
            'returns'   => (clone $base)->with(['provider', 'order', 'creator'])->latest('return_date')->paginate(20)->withQueryString(),
            'total'     => (float) (clone $base)->sum('total'),
            'count'     => (clone $base)->count(),
            'orders'    => PurchaseOrder::with('provider')->where('company_id', $companyId)->where('status', 'recibida')->latest('entry_date')->limit(50)->get(),
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
        ]);
    }

    public function create(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        if ($purchaseOrder->status !== 'recibida') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => 'Solo se devuelve mercadería de órdenes ya recibidas.']);
        }

        $lines = $this->returnableLines($purchaseOrder);
        if ($lines->every(fn ($l) => $l['returnable'] <= 0)) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => 'Ya se devolvió todo lo de esta orden.']);
        }

        $companyId = $purchaseOrder->company_id;

        return view('purchasing.returns.create', [
            'order'          => $purchaseOrder,
            'lines'          => $lines,
            'setting'        => CompanySetting::current($companyId),
            'paymentMethods' => PaymentMethod::optionsFor($companyId),
            'openSessions'   => CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))->get(),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        $companyId = $purchaseOrder->company_id;

        $data = $request->validate([
            'reason'                  => ['required', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'refund_method'           => ['required', Rule::in(array_keys(PurchaseReturn::REFUND_METHODS))],
            'payment_method'          => ['nullable', Rule::in(array_keys(PaymentMethod::optionsFor($companyId)))],
            'cash_session_id'         => ['nullable', 'integer'],
            'items'                   => ['required', 'array'],
            'items.*.entry_detail_id' => ['required', 'integer'],
            'items.*.quantity'        => ['nullable', 'numeric', 'min:0'],
        ], ['reason.required' => 'Indica el motivo de la devolución.']);

        try {
            $return = DB::transaction(fn () => $this->persist($purchaseOrder, $data));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-returns.show', $return)
            ->with('success', 'Devolución ' . $return->return_number . ' registrada: se descontó el stock'
                . ($return->refund_method === 'cash' ? ' y se registró el ingreso.' : '. Queda como nota de crédito del proveedor.'));
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        abort_if($purchaseReturn->company_id !== $this->companyId(), 403);
        $purchaseReturn->load(['provider', 'order', 'creator', 'items.product', 'cashMovement']);

        return view('purchasing.returns.show', ['return' => $purchaseReturn, 'setting' => CompanySetting::current($purchaseReturn->company_id)]);
    }

    // ---- Helpers ----

    protected function persist(PurchaseOrder $order, array $data): PurchaseReturn
    {
        $order = PurchaseOrder::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $lines = $this->returnableLines($order)->keyBy(fn ($l) => $l['item']->id);

        $rows = [];
        foreach ($data['items'] as $row) {
            $qty = round((float) ($row['quantity'] ?? 0), 2);
            if ($qty <= 0) {
                continue;
            }
            $line = $lines[(int) $row['entry_detail_id']] ?? null;
            if (!$line) {
                throw new DomainException('La devolución incluye una línea que no es de esta orden.');
            }
            if ($qty > $line['returnable'] + 0.001) {
                throw new DomainException('De ' . ($line['item']->product?->name ?? 'un producto') . ' se pueden devolver hasta ' . rtrim(rtrim(number_format($line['returnable'], 2), '0'), '.') . '.');
            }
            $rows[] = ['item' => $line['item'], 'quantity' => $qty, 'total' => round($qty * (float) $line['item']->unit_cost, 2)];
        }

        if (!$rows) {
            throw new DomainException('Indica qué se devuelve: al menos un producto con cantidad.');
        }

        $total = round(array_sum(array_column($rows, 'total')), 2);
        $setting = CompanySetting::current($order->company_id);
        $count = PurchaseReturn::withoutGlobalScopes()->where('company_id', $order->company_id)->count() + 1;
        $number = $setting->documentFolio('DPR-', $count);

        $return = PurchaseReturn::create([
            'company_id'        => $order->company_id,
            'supplier_id'       => $order->supplier_id,
            'purchase_order_id' => $order->id,
            'return_number'     => $number,
            'return_date'       => now(),
            'reason'            => $data['reason'],
            'refund_method'     => $data['refund_method'],
            'total'             => $total,
            'notes'             => $data['notes'] ?? null,
            'created_by'        => auth()->id(),
        ]);

        foreach ($rows as $row) {
            $return->items()->create([
                'entry_detail_id' => $row['item']->id,
                'product_id'      => $row['item']->product_id,
                'quantity'        => $row['quantity'],
                'unit_cost'       => $row['item']->unit_cost,
                'total'           => $row['total'],
            ]);
            $this->stock->move((int) $row['item']->product_id, -$row['quantity'], 'salida', $order->company_id, $number, 'Devolución a proveedor · OC ' . $order->entry_number);
        }

        // El proveedor devolvió el dinero: entra como ingreso (en caja si se elige una abierta).
        if ($data['refund_method'] === 'cash' && $total > 0) {
            $sessionId = null;
            if (!empty($data['cash_session_id'])) {
                $sessionId = CashSession::where('id', $data['cash_session_id'])->where('status', 'open')
                    ->whereHas('register', fn ($q) => $q->where('company_id', $order->company_id))->value('id');
                if (!$sessionId) {
                    throw new DomainException('La caja elegida no está abierta.');
                }
            }

            $movement = CashMovement::create([
                'company_id'      => $order->company_id,
                'cash_session_id' => $sessionId,
                'type'            => 'ingreso',
                'amount'          => $total,
                'concept'         => 'Devolución ' . $number . ' · ' . $order->supplierDisplay(),
                'category'        => 'Devolución de proveedor',
                'movement_date'   => today(),
                'payment_method'  => $data['payment_method'] ?? 'efectivo',
                'reference'       => $number,
                'created_by'      => auth()->id(),
            ]);
            $return->update(['cash_movement_id' => $movement->id]);
        }

        return $return;
    }

    /** Líneas de la orden con lo que todavía se puede devolver. */
    protected function returnableLines(PurchaseOrder $order)
    {
        $order->loadMissing('items.product');
        $returned = PurchaseReturnItem::query()
            ->whereIn('entry_detail_id', $order->items->pluck('id'))
            ->selectRaw('entry_detail_id, SUM(quantity) AS q')->groupBy('entry_detail_id')
            ->pluck('q', 'entry_detail_id');

        return $order->items->map(fn ($item) => [
            'item'       => $item,
            'returned'   => (float) ($returned[$item->id] ?? 0),
            'returnable' => max(0, round((float) $item->quantity - (float) ($returned[$item->id] ?? 0), 2)),
            'stock'      => (float) ($item->product?->current_stock ?? 0),
        ]);
    }

    protected function authorizeOrder(PurchaseOrder $order): void
    {
        abort_if($order->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
