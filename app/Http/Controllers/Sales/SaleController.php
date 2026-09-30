<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Commission;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    /** Listado de ventas. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.sales.index', ['sales' => null, 'noCompany' => true]);
        }

        $sales = Sale::with(['client'])
            ->where('company_id', $companyId)
            ->latest('sale_date')
            ->paginate(15);

        return view('sales.sales.index', ['sales' => $sales, 'noCompany' => false]);
    }

    public function show(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load(['items.service', 'client', 'session.register']);

        return view('sales.sales.show', ['sale' => $sale]);
    }

    /** Pantalla de Punto de Venta. */
    public function pos()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.pos', ['noCompany' => true]);
        }

        return view('sales.pos', [
            'noCompany' => false,
            'clients' => Client::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'services' => Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'branches' => Branch::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'specialists' => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'openSessions' => CashSession::with('register')
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                ->get(),
        ]);
    }

    /** Registra una venta con sus ítems y su movimiento de caja. */
    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'client_id'       => ['nullable', 'exists:clients,id'],
            'client_name'     => ['nullable', 'string', 'max:255'],
            'branch_id'       => ['nullable', 'exists:branches,id'],
            'cash_session_id' => ['nullable', 'exists:cash_sessions,id'],
            'payment_method'  => ['required', 'string', 'max:50'],
            'global_discount' => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.service_id'  => ['nullable', 'exists:services,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
            'items.*.personal_id' => ['nullable', 'exists:personals,id'],
        ]);

        // Cada línea debe tener servicio o descripción.
        foreach ($validated['items'] as $i => $item) {
            if (empty($item['service_id']) && empty($item['description'])) {
                return back()->withInput()->withErrors(['error' => 'Cada línea debe tener un servicio o una descripción.']);
            }
        }

        try {
            $sale = DB::transaction(function () use ($validated, $companyId, $request) {
                // Totales
                $subtotal = 0;
                $lineDiscounts = 0;
                $lines = [];

                foreach ($validated['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $price = (float) $item['unit_price'];
                    $disc = (float) ($item['discount'] ?? 0);
                    $lineTotal = max(0, $qty * $price - $disc);
                    $subtotal += $qty * $price;
                    $lineDiscounts += $disc;

                    $lines[] = [
                        'service_id'  => $item['service_id'] ?? null,
                        'personal_id' => $item['personal_id'] ?? null,
                        'description' => $item['description'] ?? (Service::find($item['service_id'] ?? 0)?->name),
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'discount'    => $disc,
                        'total'       => $lineTotal,
                    ];
                }

                $globalDiscount = (float) ($validated['global_discount'] ?? 0);
                $totalDiscount = $lineDiscounts + $globalDiscount;
                $total = max(0, $subtotal - $totalDiscount);

                // Sesión de caja (opcional): debe estar abierta y ser de la empresa.
                $session = null;
                if (!empty($validated['cash_session_id'])) {
                    $session = CashSession::where('id', $validated['cash_session_id'])
                        ->where('status', 'open')
                        ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                        ->first();
                }

                $number = $this->nextSaleNumber($companyId);

                $sale = Sale::create([
                    'company_id'      => $companyId,
                    'branch_id'       => $validated['branch_id'] ?? null,
                    'client_id'       => $validated['client_id'] ?? null,
                    'cash_session_id' => $session?->id,
                    'sale_number'     => $number,
                    'client_name'     => $validated['client_name'] ?? null,
                    'sale_date'       => now(),
                    'subtotal'        => $subtotal,
                    'tax'             => 0,
                    'discount'        => $totalDiscount,
                    'total'           => $total,
                    'payment_method'  => $validated['payment_method'],
                    'status'          => 'completada',
                    'notes'           => $validated['notes'] ?? null,
                    'created_by'      => auth()->id(),
                ]);

                foreach ($lines as $line) {
                    $item = $sale->items()->create($line);
                    $this->createCommission($sale, $item);
                }

                // Ingreso en caja si hay sesión abierta.
                if ($session) {
                    $session->movements()->create([
                        'type'           => 'ingreso',
                        'amount'         => $total,
                        'concept'        => 'Venta ' . $number,
                        'payment_method' => $validated['payment_method'],
                        'reference'      => (string) $sale->id,
                        'created_by'     => auth()->id(),
                    ]);
                }

                return $sale;
            });

            return redirect()->route('sales.show', $sale)->with('success', 'Venta registrada: ' . $sale->sale_number);
        } catch (\Throwable $e) {
            Log::error('Error al registrar venta', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la venta.']);
        }
    }

    /** Anula una venta y revierte su ingreso de caja si la sesión sigue abierta. */
    public function void(Sale $sale)
    {
        $this->authorizeSale($sale);

        if ($sale->status === 'anulada') {
            return back()->withErrors(['error' => 'La venta ya está anulada.']);
        }

        DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'anulada']);

            // Las comisiones generadas por esta venta quedan sin efecto.
            Commission::where('sale_id', $sale->id)->where('status', 'pendiente')->delete();

            $session = $sale->session;
            if ($session && $session->isOpen()) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $sale->total,
                    'concept'        => 'Anulación venta ' . $sale->sale_number,
                    'payment_method' => $sale->payment_method,
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);
            }
        });

        return back()->with('success', 'Venta anulada.');
    }

    // ---- Helpers ----

    /**
     * Genera la comision del especialista por una linea de servicio vendida.
     * Solo aplica si la linea tiene especialista y el servicio define un porcentaje.
     */
    protected function createCommission(Sale $sale, SaleItem $item): void
    {
        if (!$item->personal_id || !$item->service_id) {
            return;
        }

        $rate = (float) (Service::find($item->service_id)?->commission_percent ?? 0);
        if ($rate <= 0) {
            return;
        }

        Commission::create([
            'company_id'     => $sale->company_id,
            'personal_id'    => $item->personal_id,
            'sale_id'        => $sale->id,
            'sale_detail_id' => $item->id,
            'amount'         => round((float) $item->total * $rate / 100, 2),
            'rate'           => $rate,
            'status'         => 'pendiente',
            'period_start'   => $sale->sale_date?->toDateString(),
            'period_end'     => $sale->sale_date?->toDateString(),
            'created_by'     => auth()->id(),
        ]);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function nextSaleNumber(int $companyId): string
    {
        $count = Sale::withTrashed()->where('company_id', $companyId)->count() + 1;
        return 'V-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    protected function authorizeSale(Sale $sale): void
    {
        if ($sale->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
