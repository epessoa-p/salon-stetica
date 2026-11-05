<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AccountMovement;
use App\Models\CompanySetting;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
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

    public function index(): JsonResponse
    {
        if ($r = $this->guard('accounts.view')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $list = FinancialAccount::forCompany($companyId)->where('active', true)->select('financial_accounts.*')->withBalance()->orderBy('name')->get();

        return response()->json([
            'currency' => CompanySetting::current($companyId)->currency_symbol,
            'accounts' => $list->map(fn ($a) => $this->row($a, (float) $a->saldo))->values(),
            'total'    => round((float) $list->sum('saldo'), 2),
            'can'      => ['move' => $this->can('accounts.move'), 'expense' => $this->can('expenses.create')],
        ]);
    }

    public function show(FinancialAccount $financialAccount): JsonResponse
    {
        if ($r = $this->guard('accounts.view')) {
            return $r;
        }
        if ((int) $financialAccount->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Cuenta no encontrada.');
        }

        return response()->json($this->row($financialAccount, $financialAccount->balance()) + [
            'movements' => $financialAccount->movements()->orderByDesc('movement_date')->orderByDesc('id')->limit(50)->get()
                ->map(fn (AccountMovement $m) => [
                    'id'      => $m->id,
                    'type'    => $m->type,
                    'label'   => $m->typeLabel(),
                    'amount'  => $m->signedAmount(),
                    'concept' => $m->concept,
                    'date'    => $m->movement_date->toDateString(),
                    'voided'  => $m->isVoided(),
                ])->values(),
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
        ], ['amount.required' => 'Indica el monto.', 'concept.required' => 'Escribe el concepto.']);

        try {
            [$sessionId, $accountId] = $this->accounts->sourceFromRequest($request, $companyId);
            $movement = $this->accounts->spend($companyId, $sessionId, $accountId, $data);
        } catch (DomainException $e) {
            return $this->fail(422, 'invalid_source', $e->getMessage());
        }

        return response()->json(['message' => 'Gasto registrado.', 'id' => $movement->id], 201);
    }

    protected function guard(string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }

        return $this->can($permission) ? null : $this->forbidden();
    }

    protected function row(FinancialAccount $a, float $balance): array
    {
        return ['id' => $a->id, 'name' => $a->name, 'type' => $a->type, 'type_label' => $a->typeLabel(), 'balance' => round($balance, 2)];
    }
}
