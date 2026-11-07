<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AccountMovement;
use App\Models\CompanySetting;
use App\Models\FinancialAccount;
use App\Services\AccountService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Cuentas desde la app: mismos permisos y reglas que la web. */
class AccountsController extends ApiController
{
    public function __construct(protected AccountService $accounts)
    {
    }

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
            'types'    => collect(FinancialAccount::TYPES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'can'      => $this->abilities(),
        ]);
    }

    /** Detalle con su extracto: el saldo después de cada movimiento, como el de un banco. */
    public function show(FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.view') ?? $this->notMine($financialAccount)) {
            return $r;
        }

        $balance = $financialAccount->balance();
        $running = $balance;
        $movements = $financialAccount->movements()->orderByDesc('movement_date')->orderByDesc('id')->limit(100)->get()
            ->map(function (AccountMovement $m) use (&$running) {
                $after = $m->isVoided() ? null : round($running, 2);
                if (!$m->isVoided()) {
                    $running -= $m->signedAmount();
                }

                return [
                    'id'            => $m->id,
                    'type'          => $m->type,
                    'origin'        => $m->typeLabel(),
                    'amount'        => $m->signedAmount(),
                    'concept'       => $m->concept,
                    'category'      => $m->category,
                    'date'          => $m->movement_date->toDateString(),
                    'reference'     => $m->reference,
                    'balance_after' => $after,
                    'voided'        => $m->isVoided(),
                    'can_void'      => $m->canVoidHere(),
                ];
            })->values();

        return response()->json($this->row($financialAccount, $balance) + [
            'movements'     => $movements,
            'has_movements' => $financialAccount->movements()->exists(),
            'can'           => $this->abilities(),
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

        $account = DB::transaction(function () use ($data, $companyId, $initial) {
            $account = FinancialAccount::create(FinancialAccount::clean($data) + ['company_id' => $companyId, 'created_by' => auth()->id()]);
            if ($initial > 0) {
                $this->accounts->contribute($account, $initial, ['concept' => 'Saldo inicial'], 'apertura');
            }

            return $account;
        });

        return response()->json(['message' => 'Cuenta creada.', 'id' => $account->id], 201);
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

    /** Ingreso o egreso manual; un ingreso puede venir de una caja abierta u otra cuenta. */
    public function movement(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.move') ?? $this->notMine($financialAccount)) {
            return $r;
        }

        $data = $request->validate([
            'direction'     => ['required', 'in:in,out'],
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'concept'       => ['required', 'string', 'max:255'],
            'movement_date' => ['nullable', 'date', 'before_or_equal:today'],
            'reference'     => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required'  => 'Indica el importe.',
            'amount.min'       => 'El importe tiene que ser mayor que cero.',
            'concept.required' => 'Escribe el concepto.',
            'movement_date.before_or_equal' => 'La fecha no puede ser futura.',
        ]);
        $date = $data['movement_date'] ?? today()->toDateString();

        return $this->attempt(function () use ($request, $financialAccount, $data, $date) {
            if ($data['direction'] === 'in') {
                $this->accounts->fundFromRequest($request, $financialAccount, (float) $data['amount'], $data['concept'], $date, $data['reference'] ?? null);
            } else {
                $this->accounts->withdraw($financialAccount, (float) $data['amount'], ['concept' => $data['concept'], 'movement_date' => $date, 'reference' => $data['reference'] ?? null]);
            }

            return response()->json(['message' => 'Movimiento registrado.'], 201);
        });
    }

    public function transfer(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.move') ?? $this->notMine($financialAccount)) {
            return $r;
        }

        $data = $request->validate([
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'concept'       => ['nullable', 'string', 'max:255'],
            'movement_date' => ['nullable', 'date', 'before_or_equal:today'],
            'destination'   => ['required', 'regex:/^(cuenta|caja):\d+$/'],
        ], [
            'amount.required'      => 'Indica el importe.',
            'amount.min'           => 'El importe tiene que ser mayor que cero.',
            'destination.required' => 'Elige el destino.',
            'destination.regex'    => 'Elige el destino.',
            'movement_date.before_or_equal' => 'La fecha no puede ser futura.',
        ]);
        [$type, $id] = explode(':', $data['destination']);

        return $this->attempt(function () use ($financialAccount, $data, $type, $id) {
            $this->accounts->transfer((int) $financialAccount->company_id, ['cuenta', $financialAccount->id], [$type, (int) $id], (float) $data['amount'], array_filter([
                'concept' => $data['concept'] ?? null, 'movement_date' => $data['movement_date'] ?? null,
            ]));

            return response()->json(['message' => 'Transferencia registrada.'], 201);
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

    protected function abilities(): array
    {
        return [
            'create'  => $this->can('accounts.create'),
            'edit'    => $this->can('accounts.edit'),
            'delete'  => $this->can('accounts.delete'),
            'move'    => $this->can('accounts.move'),
            'expense' => $this->can('expenses.create'),
        ];
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
