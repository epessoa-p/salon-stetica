<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Services\ReceivableService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Cuentas por cobrar: cartera, estado de cuenta del cliente, cobros y recibos. */
class ReceivableController extends Controller
{
    public function __construct(protected ReceivableService $receivables)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('finance.receivables.index', ['noCompany' => true]);
        }

        $filter = $request->input('filter', 'pending');
        $term = trim((string) $request->input('q'));
        $today = today()->toDateString();
        $balance = DB::raw('amount - paid_amount - adjusted_amount');

        $list = Receivable::with(['client', 'sale'])
            ->where('company_id', $companyId)
            ->when($term !== '', fn ($q) => $q->whereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($filter === 'overdue', fn ($q) => $q->where('status', 'pending')->where('due_date', '<', $today))
            ->when($filter === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->orderBy('due_date')->orderBy('id')
            ->paginate(25)->withQueryString();

        $pending = Receivable::where('company_id', $companyId)->where('status', 'pending');

        return view('finance.receivables.index', [
            'noCompany' => false,
            'list'      => $list,
            'filter'    => $filter,
            'term'      => $term,
            'kpis'      => [
                'balance'   => (float) (clone $pending)->sum($balance),
                'overdue'   => (float) (clone $pending)->where('due_date', '<', $today)->sum($balance),
                'clients'   => (clone $pending)->distinct()->count('client_id'),
                'collected' => (float) ReceivablePayment::where('company_id', $companyId)->where('paid_at', '>=', today()->startOfMonth())->sum('amount'),
            ],
            'aging'     => $this->aging($companyId),
        ]);
    }

    /** Estado de cuenta del cliente con el formulario de cobro. */
    public function client(Client $client)
    {
        $this->authorizeClient($client);
        $companyId = $client->company_id;

        return view('finance.receivables.client', [
            'client'         => $client,
            'summary'        => $this->receivables->summary($client),
            'pending'        => $this->receivables->pendingFor($companyId, $client->id),
            'history'        => Receivable::with('sale')->where('client_id', $client->id)->where('status', '!=', 'pending')->latest('id')->limit(20)->get(),
            'receipts'       => ReceivablePayment::withTrashed()->where('client_id', $client->id)
                ->selectRaw('receipt_number, MIN(paid_at) AS paid_at, SUM(amount) AS amount, MIN(payment_method) AS payment_method, MAX(deleted_at) AS deleted_at')
                ->groupBy('receipt_number')->orderByDesc('paid_at')->limit(20)->get(),
            'setting'        => CompanySetting::current($companyId),
            'paymentMethods' => PaymentMethod::optionsFor($companyId),
            'openSessions'   => CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))->get(),
        ]);
    }

    public function collect(Request $request, Client $client)
    {
        $this->authorizeClient($client);
        $data = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'payment_method'  => ['required', Rule::in(array_keys(PaymentMethod::optionsFor($client->company_id)))],
            'cash_session_id' => ['nullable', 'integer'],
            'receivable_id'   => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:255'],
        ], ['amount.required' => 'Indica el monto a cobrar.']);

        try {
            $number = $this->receivables->collect($client, (float) $data['amount'], $data['payment_method'],
                $data['cash_session_id'] ?? null, $data['receivable_id'] ?? null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('receivables.receipt', $number)->with('success', 'Cobro registrado: recibo ' . $number . '.');
    }

    /** Recibo de cobro (imprimible en 80 mm). */
    public function receipt(string $number)
    {
        $payments = ReceivablePayment::withTrashed()->with(['receivable.sale', 'client', 'creator', 'session.register'])
            ->where('company_id', $this->companyId())->where('receipt_number', $number)->get();
        abort_if($payments->isEmpty(), 404);

        $client = $payments->first()->client;

        return view('finance.receivables.receipt', [
            'number'   => $number,
            'payments' => $payments,
            'client'   => $client,
            'voided'   => $payments->first()->trashed(),
            'balance'  => $client ? $this->receivables->openBalance($client->company_id, $client->id) : 0,
            'setting'  => CompanySetting::current($this->companyId()),
            'company'  => auth()->user()->getCurrentCompany(),
        ]);
    }

    public function voidReceipt(string $number)
    {
        try {
            $this->receivables->voidReceipt($this->companyId(), $number);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Recibo ' . $number . ' anulado: la deuda volvió a quedar pendiente y se quitó el ingreso.');
    }

    /** Antigüedad de la cartera pendiente: por vencer, 1-30, 31-60, 61-90 y más de 90 días. */
    protected function aging(int $companyId): array
    {
        $buckets = ['Por vencer' => 0.0, '1–30 días' => 0.0, '31–60 días' => 0.0, '61–90 días' => 0.0, 'Más de 90' => 0.0];

        Receivable::where('company_id', $companyId)->where('status', 'pending')->get()->each(function ($r) use (&$buckets) {
            $days = $r->due_date->toDateString() < today()->toDateString() ? (int) $r->due_date->diffInDays(today()) : 0;
            $key = match (true) {
                $days === 0 => 'Por vencer',
                $days <= 30 => '1–30 días',
                $days <= 60 => '31–60 días',
                $days <= 90 => '61–90 días',
                default     => 'Más de 90',
            };
            $buckets[$key] += $r->balance();
        });

        return $buckets;
    }

    protected function authorizeClient(Client $client): void
    {
        abort_if($client->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
