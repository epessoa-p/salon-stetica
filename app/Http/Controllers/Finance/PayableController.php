<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\PaymentMethod;
use App\Services\PayableService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Cuentas por pagar a proveedores: deudas por órdenes a crédito y sus pagos. */
class PayableController extends Controller
{
    public function __construct(protected PayableService $payables)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('finance.payables.index', ['noCompany' => true]);
        }

        $filter = $request->input('filter', 'pending');
        $term = trim((string) $request->input('q'));
        $today = today()->toDateString();
        $balance = DB::raw('amount - paid_amount - adjusted_amount');

        $list = Payable::with(['provider', 'order'])
            ->where('company_id', $companyId)
            ->when($term !== '', fn ($q) => $q->whereHas('provider', fn ($s) => $s->where('name', 'like', "%{$term}%")))
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($filter === 'overdue', fn ($q) => $q->where('status', 'pending')->where('due_date', '<', $today))
            ->when($filter === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->orderBy('due_date')->orderBy('id')
            ->paginate(25)->withQueryString();

        $pending = Payable::where('company_id', $companyId)->where('status', 'pending');

        return view('finance.payables.index', [
            'noCompany' => false,
            'list'      => $list,
            'filter'    => $filter,
            'term'      => $term,
            'kpis'      => [
                'balance'  => (float) (clone $pending)->sum($balance),
                'overdue'  => (float) (clone $pending)->where('due_date', '<', $today)->sum($balance),
                'week'     => (float) (clone $pending)->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()])->sum($balance),
                'paid'     => (float) PayablePayment::where('company_id', $companyId)->where('paid_at', '>=', today()->startOfMonth())->sum('amount'),
            ],
        ]);
    }

    public function show(Payable $payable)
    {
        $this->authorizePayable($payable);
        $payable->load(['provider', 'order.items.product', 'payments' => fn ($q) => $q->withTrashed()->with(['creator', 'session.register'])->latest('paid_at')]);
        $companyId = $payable->company_id;

        return view('finance.payables.show', [
            'payable'        => $payable,
            'returns'        => \App\Models\PurchaseReturn::where('purchase_order_id', $payable->purchase_order_id)->where('applied_to_payable', '>', 0)->get(),
            'setting'        => CompanySetting::current($companyId),
            'paymentMethods' => PaymentMethod::optionsFor($companyId),
            'openSessions'   => CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))->get(),
        ]);
    }

    public function pay(Request $request, Payable $payable)
    {
        $this->authorizePayable($payable);
        $data = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'payment_method'  => ['required', Rule::in(array_keys(PaymentMethod::optionsFor($payable->company_id)))],
            'cash_session_id' => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $payment = $this->payables->pay($payable, (float) $data['amount'], $data['payment_method'], $data['cash_session_id'] ?? null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pago ' . $payment->receipt_number . ' registrado por ' . CompanySetting::current($payable->company_id)->money($payment->amount) . '.');
    }

    public function voidPayment(PayablePayment $payment)
    {
        abort_if($payment->company_id !== $this->companyId(), 403);

        try {
            $this->payables->voidPayment($payment);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pago ' . $payment->receipt_number . ' anulado.');
    }

    protected function authorizePayable(Payable $payable): void
    {
        abort_if($payable->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
