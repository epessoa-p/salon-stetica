<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AccountMovement;
use App\Models\CashMovement;
use App\Models\CompanySetting;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Supplier;
use App\Services\AccountService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cuentas y gastos desde la app: mismos permisos y reglas que la web. */
class AccountsController extends ApiController
{
    public function __construct(protected AccountService $accounts)
    {
    }

    // ---- Cuentas ----

    public function index(): JsonResponse
    {
        if ($r = $this->guard('accounts.view')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $list = FinancialAccount::forCompany($companyId)->select('financial_accounts.*')->withBalance()
            ->orderByDesc('active')->orderBy('name')->get();

        return response()->json([
            'currency' => CompanySetting::current($companyId)->currency_symbol,
            'accounts' => $list->map(fn ($a) => $this->row($a, (float) $a->saldo))->values(),
            'total'    => round((float) $list->where('active', true)->sum('saldo'), 2),
            'can'      => $this->accountAbilities(),
        ]);
    }

    public function show(FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.view') ?? $this->notMine($financialAccount)) {
            return $r;
        }

        return response()->json($this->row($financialAccount, $financialAccount->balance()) + [
            'movements' => $financialAccount->movements()->orderByDesc('movement_date')->orderByDesc('id')->limit(100)->get()
                ->map(fn (AccountMovement $m) => [
                    'id'       => $m->id,
                    'type'     => $m->type,
                    'label'    => $m->typeLabel(),
                    'amount'   => $m->signedAmount(),
                    'concept'  => $m->concept,
                    'category' => $m->category,
                    'date'     => $m->movement_date->toDateString(),
                    'voided'   => $m->isVoided(),
                    'can_void' => !$m->isVoided() && $this->voidable($m),
                ])->values(),
            'has_movements' => $financialAccount->movements()->exists(),
            'can'           => $this->accountAbilities(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->guard('accounts.create')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $data = $request->validate(FinancialAccount::rules($companyId) + ['initial_amount' => ['nullable', 'numeric', 'min:0']], FinancialAccount::messages());
        $initial = round((float) ($data['initial_amount'] ?? 0), 2);
        unset($data['initial_amount']);

        return $this->attempt(function () use ($request, $data, $companyId, $initial) {
            $account = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data, $companyId, $initial) {
                $account = FinancialAccount::create(FinancialAccount::clean($data) + ['company_id' => $companyId, 'created_by' => auth()->id()]);
                if ($initial > 0) {
                    $this->accounts->fundFromRequest($request, $account, $initial, 'Aporte inicial', today()->toDateString());
                }

                return $account;
            });

            return response()->json(['message' => 'Cuenta creada.', 'id' => $account->id], 201);
        });
    }

    public function update(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.edit') ?? $this->notMine($financialAccount)) {
            return $r;
        }

        $data = FinancialAccount::clean($request->validate(FinancialAccount::rules($this->company()->id, $financialAccount->id), FinancialAccount::messages()));
        $data['active'] = $request->boolean('active', true);

        if (!$data['active'] && abs($financialAccount->balance()) > 0.001) {
            return $this->fail(422, 'has_balance', 'La cuenta tiene saldo de ' . number_format($financialAccount->balance(), 2) . ': trasládalo antes de desactivarla.');
        }

        $financialAccount->update($data);

        return response()->json(['message' => 'Cuenta actualizada.']);
    }

    public function destroy(FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.delete') ?? $this->notMine($financialAccount)) {
            return $r;
        }
        if ($financialAccount->movements()->exists()) {
            return $this->fail(422, 'has_movements', 'La cuenta tiene movimientos: desactívala en lugar de eliminarla.');
        }

        $financialAccount->delete();

        return response()->json(['message' => 'Cuenta eliminada.']);
    }

    public function contribute(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.move') ?? $this->notMine($financialAccount)) {
            return $r;
        }
        $data = $this->moneyData($request);

        return $this->attempt(function () use ($request, $financialAccount, $data) {
            $this->accounts->fundFromRequest($request, $financialAccount, (float) $data['amount'], $data['concept'] ?? 'Aporte', $data['movement_date'] ?? today()->toDateString());

            return response()->json(['message' => 'Aporte registrado.'], 201);
        });
    }

    public function transfer(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.move') ?? $this->notMine($financialAccount)) {
            return $r;
        }
        $data = $this->moneyData($request);
        $request->validate(['destination' => ['required', 'regex:/^(cuenta|caja):\d+$/']], ['destination.required' => 'Elige el destino.', 'destination.regex' => 'Elige el destino.']);
        [$type, $id] = explode(':', $request->input('destination'));

        return $this->attempt(function () use ($financialAccount, $data, $type, $id) {
            $this->accounts->transfer((int) $financialAccount->company_id, ['cuenta', $financialAccount->id], [$type, (int) $id], (float) $data['amount'], array_filter([
                'concept' => $data['concept'] ?? null, 'movement_date' => $data['movement_date'] ?? null,
            ]));

            return response()->json(['message' => 'Traspaso registrado.'], 201);
        });
    }

    public function adjust(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.move') ?? $this->notMine($financialAccount)) {
            return $r;
        }
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'reason'    => ['required', 'string', 'max:200'],
        ], ['reason.required' => 'Indica el motivo del ajuste.', 'amount.required' => 'Indica el monto.', 'amount.min' => 'El monto debe ser mayor a 0.']);

        return $this->attempt(function () use ($financialAccount, $data) {
            $this->accounts->adjust($financialAccount, $data['direction'], (float) $data['amount'], $data['reason']);

            return response()->json(['message' => 'Ajuste registrado.'], 201);
        });
    }

    public function voidMovement(FinancialAccount $financialAccount, AccountMovement $movement): JsonResponse
    {
        if ($r = $this->guard('accounts.move') ?? $this->notMine($financialAccount)) {
            return $r;
        }
        if ((int) $movement->financial_account_id !== $financialAccount->id) {
            return $this->fail(404, 'not_found', 'Movimiento no encontrado.');
        }

        return $this->attempt(function () use ($movement) {
            $this->accounts->void($movement);

            return response()->json(['message' => 'Movimiento anulado.']);
        });
    }

    // ---- Gastos ----

    /** Lo necesario para los formularios: categorías, métodos, cajas abiertas y cuentas. */
    public function options(): JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }
        if (!$this->can('accounts.view') && !$this->can('expenses.create')) {
            return $this->forbidden();
        }

        $companyId = $this->company()->id;
        $sources = $this->accounts->sourceOptions($companyId);
        $forExpense = $this->can('expenses.create');

        return response()->json([
            'currency'         => CompanySetting::current($companyId)->currency_symbol,
            'types'            => collect(FinancialAccount::TYPES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'categories'       => config('saas.expense_categories', []),
            'payment_methods'  => collect(PaymentMethod::optionsFor($companyId))->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
            'default_method'   => PaymentMethod::defaultCodeFor($companyId),
            'sessions'         => $sources['sessions']->map(fn ($s) => ['id' => $s->id, 'name' => $s->register?->name ?? 'Caja'])->values(),
            'accounts'         => $sources['accounts']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'balance' => round((float) $a->saldo, 2)])->values(),
            'can_use_accounts' => $sources['canUseAccounts'],
            'suppliers'        => $forExpense ? Supplier::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values() : [],
            'people'           => $forExpense ? Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name])->values() : [],
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        if ($r = $this->guard('expenses.view')) {
            return $r;
        }

        $data = $request->validate([
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
            'source' => ['nullable', 'in:caja,cuenta,ninguno'],
        ], ['to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.']);

        $companyId = $this->company()->id;
        $from = $data['from'] ?? today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        $source = $data['source'] ?? null;

        $query = CashMovement::with(['session.register', 'account', 'supplierRef', 'personal'])
            ->notTransfer()->where('company_id', $companyId)->where('type', 'egreso')
            ->whereBetween('movement_date', [$from, $to])
            ->when($source === 'caja', fn ($q) => $q->whereNotNull('cash_session_id'))
            ->when($source === 'cuenta', fn ($q) => $q->whereNotNull('financial_account_id'))
            ->when($source === 'ninguno', fn ($q) => $q->whereNull('cash_session_id')->whereNull('financial_account_id'));

        $canDelete = $this->can('expenses.delete');

        return response()->json([
            'currency' => CompanySetting::current($companyId)->currency_symbol,
            'from'     => $from,
            'to'       => $to,
            'total'    => round((float) (clone $query)->sum('amount'), 2),
            'expenses' => $query->orderByDesc('movement_date')->orderByDesc('id')->limit(100)->get()->map(fn (CashMovement $m) => [
                'id'         => $m->id,
                'date'       => optional($m->movement_date)->toDateString(),
                'concept'    => $m->concept,
                'category'   => $m->category,
                'method'     => PaymentMethod::label($m->payment_method, $companyId),
                'amount'     => (float) $m->amount,
                'reference'  => $m->reference,
                'origin'     => $m->account ? ['type' => 'cuenta', 'name' => $m->account->name]
                    : ($m->session ? ['type' => 'caja', 'name' => $m->session->register?->name ?? 'Caja'] : null),
                'paid_to'    => $m->supplierRef?->name ?? $m->personal?->full_name,
                'can_delete' => $canDelete && !$m->isGenerated(),
            ])->values(),
            'can' => ['create' => $this->can('expenses.create')],
        ]);
    }

    /** Registra un gasto desde una caja abierta o una cuenta. */
    public function storeExpense(Request $request): JsonResponse
    {
        if ($r = $this->guard('expenses.create')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'concept'        => ['required', 'string', 'max:255'],
            'category'       => ['nullable', 'string', 'max:100'],
            'movement_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::in(array_keys(PaymentMethod::optionsFor($companyId)))],
            'reference'      => ['nullable', 'string', 'max:255'],
            'supplier_id'    => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'personal_id'    => ['nullable', Rule::exists('personals', 'id')->where('company_id', $companyId)],
        ], [
            'amount.required'  => 'Indica el monto.',
            'amount.min'       => 'El monto debe ser mayor a 0.',
            'concept.required' => 'Escribe el concepto.',
            'movement_date.before_or_equal' => 'La fecha no puede ser futura.',
        ]);

        return $this->attempt(function () use ($request, $companyId, $data) {
            [$sessionId, $accountId] = $this->accounts->sourceFromRequest($request, $companyId);
            $movement = $this->accounts->spend($companyId, $sessionId, $accountId, $data);

            return response()->json(['message' => 'Gasto registrado.', 'id' => $movement->id], 201);
        });
    }

    public function destroyExpense(CashMovement $movement): JsonResponse
    {
        if ($r = $this->guard('expenses.delete')) {
            return $r;
        }
        if ((int) $movement->company_id !== $this->company()->id || $movement->type !== 'egreso') {
            return $this->fail(404, 'not_found', 'Gasto no encontrado.');
        }
        if ($movement->isGenerated()) {
            return $this->fail(422, 'generated', 'Este gasto proviene de otro módulo (venta, pago o compra): anúlalo desde su origen.');
        }

        $this->accounts->voidCashMovement($movement->id);

        return response()->json(['message' => 'Gasto eliminado.']);
    }

    // ---- Helpers ----

    protected function guard(string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }

        return $this->can($permission) ? null : $this->forbidden();
    }

    protected function notMine(FinancialAccount $account): ?JsonResponse
    {
        return (int) $account->company_id === $this->company()->id ? null : $this->fail(404, 'not_found', 'Cuenta no encontrada.');
    }

    /** Ejecuta una operación con reglas de negocio y devuelve su mensaje en JSON. */
    protected function attempt(callable $operation): JsonResponse
    {
        try {
            return $operation();
        } catch (DomainException $e) {
            return $this->fail(422, 'invalid_operation', $e->getMessage());
        }
    }

    protected function moneyData(Request $request): array
    {
        return $request->validate([
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'concept'       => ['nullable', 'string', 'max:255'],
            'movement_date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'amount.required' => 'Indica el monto.',
            'amount.min'      => 'El monto debe ser mayor a 0.',
            'movement_date.before_or_equal' => 'La fecha no puede ser futura.',
        ]);
    }

    protected function accountAbilities(): array
    {
        return [
            'create'  => $this->can('accounts.create'),
            'edit'    => $this->can('accounts.edit'),
            'delete'  => $this->can('accounts.delete'),
            'move'    => $this->can('accounts.move'),
            'expense' => $this->can('expenses.create'),
        ];
    }

    /** Lo generado por otro módulo se anula desde su origen. */
    protected function voidable(AccountMovement $movement): bool
    {
        $cash = $movement->cash_movement_id ? CashMovement::withoutGlobalScopes()->find($movement->cash_movement_id) : null;

        return !$cash || !$cash->isGenerated() || $cash->category === CashMovement::CATEGORY_TRANSFER;
    }

    protected function row(FinancialAccount $a, float $balance): array
    {
        return [
            'id'             => $a->id,
            'name'           => $a->name,
            'type'           => $a->type,
            'type_label'     => $a->typeLabel(),
            'bank_name'      => $a->bank_name,
            'account_number' => $a->account_number,
            'notes'          => $a->notes,
            'active'         => (bool) $a->active,
            'balance'        => round($balance, 2),
        ];
    }
}
