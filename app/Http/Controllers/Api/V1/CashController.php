<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Caja;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Caja desde la app: abrir, registrar un ingreso o gasto rápido y cerrar con
 * arqueo. Mismas reglas y permisos que Finanzas › Arqueos en la web.
 */
class CashController extends ApiController
{
    /** Cajas del salón con su turno abierto, si lo tienen. */
    public function index(): JsonResponse
    {
        if ($r = $this->guard('cash.view')) {
            return $r;
        }

        $company = $this->company();
        $open = CashSession::where('status', 'open')
            ->whereHas('register', fn ($q) => $q->where('company_id', $company->id))
            ->get()->keyBy('cash_register_id');

        $registers = Caja::where('company_id', $company->id)->where('active', true)->orderBy('name')->get()
            ->map(fn ($c) => [
                'id'      => $c->id,
                'name'    => $c->name,
                'session' => isset($open[$c->id]) ? $this->summary($open[$c->id]) : null,
            ])->values();

        return response()->json([
            'currency'  => CompanySetting::current($company->id)->currency_symbol,
            'registers' => $registers,
            'can'       => [
                'open'  => $this->can('cash.open'),
                'move'  => $this->can('cash.move'),
                'close' => $this->can('cash.close'),
            ],
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        if ($r = $this->guard('cash.open')) {
            return $r;
        }

        $data = $request->validate([
            'cash_register_id' => ['required', 'integer'],
            'opening_amount'   => ['required', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ], ['opening_amount.required' => 'Indica con cuánto dinero abres la caja.']);

        $register = Caja::where('company_id', $this->company()->id)->where('active', true)->find($data['cash_register_id']);
        if (!$register) {
            return $this->fail(404, 'not_found', 'Caja no encontrada.');
        }

        $session = DB::transaction(function () use ($register, $data) {
            // Bloquea la caja para que dos aperturas simultáneas no dupliquen el turno.
            Caja::whereKey($register->id)->lockForUpdate()->first();
            if (CashSession::where('cash_register_id', $register->id)->where('status', 'open')->exists()) {
                return null;
            }

            return CashSession::create([
                'cash_register_id' => $register->id,
                'personal_id'      => $this->ownPersonal()?->id,
                'opening_amount'   => $data['opening_amount'],
                'status'           => 'open',
                'opened_at'        => now(),
                'notes'            => $data['notes'] ?? null,
                'opened_by'        => auth()->id(),
            ]);
        });

        if (!$session) {
            return $this->fail(409, 'cash_already_open', 'Esta caja ya tiene un turno abierto.');
        }

        return response()->json(['message' => 'Caja abierta.', 'session' => $this->detailData($session)], 201);
    }

    public function show(CashSession $session): JsonResponse
    {
        if ($r = $this->guard('cash.view') ?? $this->foreign($session)) {
            return $r;
        }

        return response()->json($this->detailData($session));
    }

    /** Ingreso o gasto rápido en un turno abierto. */
    public function movement(Request $request, CashSession $session): JsonResponse
    {
        if ($r = $this->guard('cash.move') ?? $this->foreign($session)) {
            return $r;
        }
        if (!$session->isOpen()) {
            return $this->fail(409, 'cash_closed', 'La caja está cerrada.');
        }

        $company = $this->company();
        $data = $request->validate([
            'type'           => ['required', 'in:ingreso,egreso'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'concept'        => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(PaymentMethod::optionsFor($company->id)))],
        ], [
            'amount.required'  => 'Indica el monto.',
            'concept.required' => 'Indica el concepto (por ejemplo: «Compra de café»).',
        ]);

        $session->movements()->create([
            'company_id'     => $company->id,
            'type'           => $data['type'],
            'amount'         => $data['amount'],
            'concept'        => $data['concept'],
            'movement_date'  => today(),
            'payment_method' => $data['payment_method'] ?? PaymentMethod::defaultCodeFor($company->id),
            'created_by'     => auth()->id(),
        ]);

        return response()->json([
            'message' => $data['type'] === 'ingreso' ? 'Ingreso registrado.' : 'Gasto registrado.',
            'session' => $this->detailData($session->fresh()),
        ], 201);
    }

    /** Cierre con arqueo: lo contado contra lo esperado. */
    public function close(Request $request, CashSession $session): JsonResponse
    {
        if ($r = $this->guard('cash.close') ?? $this->foreign($session)) {
            return $r;
        }

        $data = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ], ['closing_amount.required' => 'Indica cuánto dinero contaste.']);

        $closed = DB::transaction(function () use ($session, $data) {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->first();
            if (!$session->isOpen()) {
                return null;
            }
            $expected = $session->expectedBalance();
            $session->update([
                'closing_amount'  => $data['closing_amount'],
                'expected_amount' => $expected,
                'difference'      => (float) $data['closing_amount'] - $expected,
                'status'          => 'closed',
                'closed_at'       => now(),
                'closed_by'       => auth()->id(),
                'notes'           => $data['notes'] ?? $session->notes,
            ]);

            return $session;
        });

        if (!$closed) {
            return $this->fail(409, 'cash_closed', 'La caja ya está cerrada.');
        }

        $diff = (float) $closed->difference;
        $label = abs($diff) < 0.005 ? 'cuadra exacto' : ($diff > 0 ? 'sobran ' : 'faltan ') . number_format(abs($diff), 2);

        return response()->json(['message' => "Caja cerrada: {$label}.", 'session' => $this->detailData($closed)]);
    }

    // ---- Helpers ----

    protected function guard(string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }

        return $this->can($permission) ? null : $this->forbidden('No tienes permiso para esta acción de caja.');
    }

    protected function foreign(CashSession $session): ?JsonResponse
    {
        return $session->register?->company_id === $this->company()->id
            ? null
            : $this->fail(404, 'not_found', 'Turno de caja no encontrado.');
    }

    protected function summary(CashSession $s): array
    {
        return [
            'id'        => $s->id,
            'status'    => $s->status,
            'opened_at' => $s->opened_at?->toIso8601String(),
            'opening'   => (float) $s->opening_amount,
            'expected'  => round($s->expectedBalance(), 2),
        ];
    }

    protected function detailData(CashSession $s): array
    {
        $s->load(['register', 'movements' => fn ($q) => $q->latest('id')]);
        $companyId = $this->company()->id;
        $in = $s->movements->where('type', 'ingreso');
        $out = $s->movements->where('type', 'egreso');

        return $this->summary($s) + [
            'register'   => $s->register?->name,
            'closed_at'  => $s->closed_at?->toIso8601String(),
            'counted'    => $s->closing_amount !== null ? (float) $s->closing_amount : null,
            'difference' => $s->difference !== null ? (float) $s->difference : null,
            'income'     => round((float) $in->sum('amount'), 2),
            'expense'    => round((float) $out->sum('amount'), 2),
            'sales_count' => $s->sales()->where('status', '!=', 'anulada')->count(),
            // Por forma de pago: ayuda a contar el efectivo aparte del QR o tarjeta.
            'by_method'  => $s->movements->groupBy('payment_method')
                ->map(fn ($g, $code) => [
                    'method' => PaymentMethod::label($code, $companyId),
                    'net'    => round((float) $g->where('type', 'ingreso')->sum('amount') - (float) $g->where('type', 'egreso')->sum('amount'), 2),
                ])->values(),
            'movements'  => $s->movements->take(50)->map(fn ($m) => [
                'id'      => $m->id,
                'type'    => $m->type,
                'amount'  => (float) $m->amount,
                'concept' => $m->concept,
                'method'  => PaymentMethod::label($m->payment_method, $companyId),
                'at'      => $m->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
