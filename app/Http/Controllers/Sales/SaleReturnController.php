<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Devoluciones de clientes: listado, registro desde una venta y detalle. */
class SaleReturnController extends Controller
{
    public function __construct(protected SaleReturnService $returns)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.returns.index', ['noCompany' => true]);
        }

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();

        $base = SaleReturn::where('company_id', $companyId)->whereBetween('return_date', [$from, $to]);

        return view('sales.returns.index', [
            'noCompany' => false,
            'returns'   => (clone $base)->with(['sale', 'client', 'creator'])->latest('return_date')->paginate(20)->withQueryString(),
            'totals'    => [
                'count'  => (clone $base)->count(),
                'amount' => (float) (clone $base)->sum('total'),
                'cash'   => (float) (clone $base)->where('refund_method', 'cash')->sum('total'),
                'credit' => (float) (clone $base)->where('refund_method', 'credit')->sum('total'),
            ],
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
        ]);
    }

    /** Busca la venta por su número y abre el formulario de devolución. */
    public function find(Request $request)
    {
        $number = trim((string) $request->input('sale_number'));
        $sale = Sale::where('company_id', $this->companyId())
            ->where('sale_number', $number)
            ->first();

        if (!$sale) {
            return back()->withInput()->withErrors(['sale_number' => "No se encontró la venta {$number}."]);
        }

        return redirect()->route('returns.create', $sale);
    }

    public function create(Sale $sale)
    {
        $this->authorizeSale($sale);
        if ($sale->isVoided()) {
            return redirect()->route('sales.show', $sale)->withErrors(['error' => 'La venta está anulada: no se puede devolver.']);
        }

        $sale->load(['items.service', 'items.product', 'items.combo', 'items.membershipPlan', 'items.servicePackage', 'client', 'returns']);
        $factor = $this->returns->factor($sale);

        $lines = $sale->items->map(fn ($item) => [
            'item'       => $item,
            'returnable' => $item->returnableQuantity(),
            'unit'       => $this->returns->maxRefund($item, 1, $factor),
            'max'        => $this->returns->maxRefund($item, $item->returnableQuantity(), $factor),
        ]);

        if ($lines->every(fn ($l) => $l['returnable'] <= 0)) {
            return redirect()->route('sales.show', $sale)->withErrors(['error' => 'Todo lo de esta venta ya se devolvió.']);
        }

        $companyId = $sale->company_id;

        return view('sales.returns.create', [
            'sale'           => $sale,
            'lines'          => $lines,
            'setting'        => CompanySetting::current($companyId),
            'paymentMethods' => PaymentMethod::optionsFor($companyId),
            'openSessions'   => CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))->get(),
            'cashRefundable' => $this->returns->cashRefundable($sale),
            'pending'        => round((float) $sale->total - (float) $sale->returned_total, 2),
        ]);
    }

    public function store(Request $request, Sale $sale)
    {
        $this->authorizeSale($sale);

        $data = $request->validate([
            'reason'                 => ['required', 'string', 'max:255'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'refund_method'          => ['required', Rule::in(array_keys(SaleReturn::REFUND_METHODS))],
            'payment_method'         => ['nullable', 'string', Rule::in(array_keys(PaymentMethod::optionsFor($sale->company_id)))],
            'cash_session_id'        => ['nullable', 'integer'],
            'items'                  => ['required', 'array'],
            'items.*.sale_detail_id' => ['required', 'integer'],
            'items.*.quantity'       => ['nullable', 'numeric', 'min:0'],
            'items.*.amount'         => ['nullable', 'numeric', 'min:0'],
            'items.*.restock'        => ['nullable', 'boolean'],
        ], ['reason.required' => 'Indica el motivo de la devolución.']);

        try {
            $return = $this->returns->create($sale, $data);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $setting = CompanySetting::current($sale->company_id);
        $message = 'Devolución ' . $return->return_number . ' registrada por ' . $setting->money($return->total)
            . ($return->refund_method === 'credit' ? ' como saldo a favor del cliente.' : ' en ' . mb_strtolower(PaymentMethod::label($return->payment_method)) . '.');

        return redirect()->route('returns.show', $return)->with('success', $message);
    }

    public function show(SaleReturn $return)
    {
        abort_if($return->company_id !== $this->companyId(), 403);
        $return->load(['sale', 'client', 'creator', 'session.register', 'items.saleItem.service', 'items.saleItem.product']);

        return view('sales.returns.show', [
            'return'  => $return,
            'setting' => CompanySetting::current($return->company_id),
        ]);
    }

    protected function authorizeSale(Sale $sale): void
    {
        abort_if($sale->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
