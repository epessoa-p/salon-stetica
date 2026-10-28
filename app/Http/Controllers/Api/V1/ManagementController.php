<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\AttendanceService;
use App\Services\ReceivableService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gerencia en la app: panel del día y cuentas por cobrar. Cada bloque del
 * panel sale solo si el plan lo incluye y el usuario tiene el permiso (las
 * mismas reglas que el dashboard web).
 */
class ManagementController extends ApiController
{
    public function __construct(protected AttendanceService $attendance, protected ReceivableService $receivables)
    {
    }

    /** Bloques del panel que este usuario puede ver. */
    public function sections(): array
    {
        $company = $this->company();
        $can = fn (string $feature, string $permission) => $company->planAllows($feature) && $this->can($permission);

        return [
            'sales'       => $can('sales', 'sales.view'),
            'agenda'      => $can('scheduling', 'appointments.view'),
            'cash'        => $can('finance', 'cash.view'),
            'team'        => $this->can('attendance.view'),
            'receivables' => $can('finance', 'receivables.view'),
            'clients'     => $can('crm', 'clients.view'),
            'stock'       => $can('inventory', 'stock.view'),
        ];
    }

    public function dashboard(): JsonResponse
    {
        $show = $this->sections();
        if (!collect($show)->except(['clients', 'stock'])->contains(true)) {
            return $this->forbidden('No tienes permiso para ver el panel del salón.');
        }

        $company = $this->company();
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $data = ['date' => $today->toDateString(), 'currency' => CompanySetting::current($company->id)->currency_symbol, 'sections' => $show];

        if ($show['sales']) {
            $todaySales = Sale::where('company_id', $company->id)->where('status', 'completada')->whereDate('sale_date', $today);
            $returnsToday = (float) SaleReturn::where('company_id', $company->id)->whereDate('return_date', $today)->sum('total');
            $data['sales'] = [
                'today'        => round((float) (clone $todaySales)->sum('total') - $returnsToday, 2),
                'today_count'  => (clone $todaySales)->count(),
                'tips_today'   => round((float) (clone $todaySales)->sum('tip'), 2),
                'month'        => round((float) Sale::where('company_id', $company->id)->where('status', 'completada')->where('sale_date', '>=', $monthStart)->sum('total')
                    - (float) SaleReturn::where('company_id', $company->id)->where('return_date', '>=', $monthStart)->sum('total'), 2),
                'by_method'    => (clone $todaySales)->selectRaw('payment_method, SUM(total) AS amount, COUNT(*) AS n')->groupBy('payment_method')->get()
                    ->map(fn ($r) => ['method' => PaymentMethod::label($r->payment_method, $company->id), 'amount' => round((float) $r->amount, 2), 'count' => (int) $r->n])->values(),
                'last'         => Sale::with('client')->where('company_id', $company->id)->where('status', 'completada')->latest('sale_date')->limit(5)->get()
                    ->map(fn ($s) => ['number' => $s->sale_number, 'client' => $s->clientDisplay(), 'total' => (float) $s->total, 'at' => $s->sale_date?->toIso8601String()])->values(),
            ];
        }

        if ($show['agenda']) {
            $appts = Appointment::with(['client', 'service', 'specialist'])->where('company_id', $company->id)->whereDate('starts_at', $today)->orderBy('starts_at')->get();
            $data['agenda'] = [
                'total'     => $appts->count(),
                'pending'   => $appts->whereIn('status', ['pendiente', 'confirmada'])->count(),
                'completed' => $appts->where('status', 'completada')->count(),
                'canceled'  => $appts->whereIn('status', ['cancelada', 'no_show'])->count(),
                'next'      => $appts->whereIn('status', ['pendiente', 'confirmada'])->filter(fn ($a) => $a->ends_at === null || $a->ends_at->isFuture())
                    ->take(5)->map(fn ($a) => AgendaController::appointment($a, $this->ownPersonal()))->values(),
            ];
        }

        if ($show['cash']) {
            $data['cash'] = CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $company->id))->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->register?->name ?? 'Caja', 'opened_at' => $s->opened_at?->toIso8601String(), 'expected' => round($s->expectedBalance(), 2)])
                ->values();
        }

        if ($show['team']) {
            $board = collect($this->attendance->board($company->id));
            $data['team'] = [
                'working' => $board->where('state', 'working')->count(),
                'late'    => $board->where('state', 'late')->count(),
                'absent'  => $board->whereIn('state', ['absent', 'missing'])->count(),
                'people'  => $board->map(fn ($row) => [
                    'name'     => $row['personal']->full_name,
                    'state'    => $row['state'],
                    'label'    => $row['label'],
                    'check_in' => optional(collect($row['records'])->first())->check_in?->format('H:i'),
                ])->values(),
            ];
        }

        if ($show['receivables']) {
            $pending = Receivable::where('company_id', $company->id)->where('status', 'pending');
            $balance = DB::raw('amount - paid_amount - adjusted_amount');
            $data['receivables'] = [
                'balance'         => round((float) (clone $pending)->sum($balance), 2),
                'overdue'         => round((float) (clone $pending)->where('due_date', '<', $today->toDateString())->sum($balance), 2),
                'collected_today' => round((float) ReceivablePayment::where('company_id', $company->id)->whereDate('paid_at', $today)->sum('amount'), 2),
            ];
        }

        if ($show['clients']) {
            $data['clients'] = [
                'new_month'      => Client::where('company_id', $company->id)->where('created_at', '>=', $monthStart)->count(),
                'birthdays_today' => Client::where('company_id', $company->id)->whereNotNull('birthdate')
                    ->whereMonth('birthdate', $today->month)->whereDay('birthdate', $today->day)->count(),
            ];
        }

        if ($show['stock']) {
            $data['stock_low'] = Product::where('company_id', $company->id)->where('active', true)
                ->where('min_stock', '>', 0)->whereColumn('current_stock', '<=', 'min_stock')->count();
        }

        return response()->json($data);
    }

    /** Clientes con deuda, el más atrasado primero. */
    public function receivables(Request $request): JsonResponse
    {
        if ($r = $this->receivablesGuard('receivables.view')) {
            return $r;
        }
        $company = $this->company();
        $today = today()->toDateString();
        $term = trim((string) $request->input('q'));

        $rows = Receivable::with('client')->where('company_id', $company->id)->where('status', 'pending')
            ->when($term !== '', fn ($q) => $q->whereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->get()->groupBy('client_id')
            ->map(fn ($list) => [
                'client'      => ClientController::client($list->first()->client),
                'debt'        => round($list->sum(fn ($r) => $r->balance()), 2),
                'accounts'    => $list->count(),
                'oldest_due'  => $list->min(fn ($r) => $r->due_date->toDateString()),
                'overdue'     => $list->contains(fn ($r) => $r->due_date->toDateString() < $today),
            ])
            ->sortBy([['overdue', 'desc'], ['oldest_due', 'asc']])->values();

        return response()->json([
            'currency' => CompanySetting::current($company->id)->currency_symbol,
            'total'    => round($rows->sum('debt'), 2),
            'overdue'  => round($rows->where('overdue', true)->sum('debt'), 2),
            'clients'  => $rows,
        ]);
    }

    /** Estado de cuenta del cliente: cuentas pendientes, últimos recibos y datos para cobrar. */
    public function receivableClient(Client $client): JsonResponse
    {
        if ($r = $this->receivablesGuard('receivables.view', $client)) {
            return $r;
        }
        $company = $this->company();

        return response()->json([
            'client'   => ClientController::client($client),
            'currency' => CompanySetting::current($company->id)->currency_symbol,
            'summary'  => $this->receivables->summary($client),
            'accounts' => $this->receivables->pendingFor($company->id, $client->id)->map(fn ($r) => [
                'id'       => $r->id,
                'sale'     => $r->sale?->sale_number,
                'amount'   => (float) $r->amount,
                'balance'  => $r->balance(),
                'due_date' => $r->due_date->toDateString(),
                'overdue'  => $r->state() === 'overdue',
            ])->values(),
            'receipts' => ReceivablePayment::where('client_id', $client->id)
                ->selectRaw('receipt_number, MIN(paid_at) AS paid_at, SUM(amount) AS amount, MIN(payment_method) AS payment_method')
                ->groupBy('receipt_number')->orderByDesc('paid_at')->limit(10)->get()
                ->map(fn ($p) => ['number' => $p->receipt_number, 'paid_at' => \Illuminate\Support\Carbon::parse($p->paid_at)->toIso8601String(),
                    'amount' => round((float) $p->amount, 2), 'method' => PaymentMethod::label($p->payment_method, $company->id)])->values(),
            'payment_methods' => collect(PaymentMethod::optionsFor($company->id))->map(fn ($label, $code) => ['code' => $code, 'name' => $label])->values(),
            'cash_sessions'   => CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $company->id))->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->register?->name ?? 'Caja'])->values(),
            'require_cash' => (bool) CompanySetting::current($company->id)->require_cash_session,
        ]);
    }

    /** Registra un cobro (recibo REC-), aplicado a lo más antiguo o a una cuenta. */
    public function collect(Request $request, Client $client): JsonResponse
    {
        if ($r = $this->receivablesGuard('receivables.collect', $client)) {
            return $r;
        }
        $company = $this->company();
        $data = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'payment_method'  => ['required', Rule::in(array_keys(PaymentMethod::optionsFor($company->id)))],
            'cash_session_id' => ['nullable', 'integer'],
            'receivable_id'   => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:255'],
        ], ['amount.required' => 'Indica el monto a cobrar.']);

        try {
            $number = $this->receivables->collect($client, (float) $data['amount'], $data['payment_method'],
                $data['cash_session_id'] ?? null, $data['receivable_id'] ?? null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return $this->fail(422, 'collect_rejected', $e->getMessage());
        }

        return response()->json([
            'receipt' => $number,
            'amount'  => round((float) $data['amount'], 2),
            'debt'    => $this->receivables->openBalance($company->id, $client->id),
            'message' => 'Cobro registrado: recibo ' . $number . '.',
        ], 201);
    }

    protected function receivablesGuard(string $permission, ?Client $client = null): ?JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }
        if (!$this->can($permission)) {
            return $this->forbidden('No tienes permiso para las cuentas por cobrar.');
        }
        if ($client && $client->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Cliente no encontrado.');
        }

        return null;
    }
}
