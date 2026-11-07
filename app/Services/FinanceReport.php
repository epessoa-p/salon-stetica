<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de finanzas: el dinero que de verdad entró y salió. Lo usan la web y la API.
 *
 *  - Resultados: ingresos y egresos por categoría, separados en caja y tesorería.
 *    Es a base de efectivo: una compra cuenta cuando se paga y una venta fiada,
 *    cuando se cobra. Por eso no coincide con Ganancias, que cuenta por lo vendido.
 *    Los traspasos y los gastos anulados no cuentan; los aportes del dueño tampoco.
 *  - Movimientos: el detalle. Caja trae todo lo del turno, también traspasos y
 *    anulaciones, para que cuadre con los cierres. Tesorería trae lo de las cuentas.
 *    Todo junta ambos sin contar dos veces lo que pasa por los dos lados.
 *  - Cierres: un resumen por turno de caja.
 */
class FinanceReport
{
    public const TABS = ['resultados', 'movimientos', 'cierres'];
    public const SCOPES = ['caja', 'tesoreria', 'todo'];
    public const DIRECTIONS = ['todos', 'ingresos', 'egresos'];
    public const LIST_LIMIT = 100;

    private const CAPITAL_TYPES = ['apertura', 'manual', 'aporte', 'ajuste'];

    protected Carbon $from;
    protected Carbon $to;

    public function __construct(protected int $companyId, Carbon $from, Carbon $to)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /** Fecha del primer movimiento o turno de la empresa: es el inicio de «Todo». */
    public static function firstDate(int $companyId): Carbon
    {
        $dates = array_filter([
            DB::table('cash_movements')->where('company_id', $companyId)->whereNull('deleted_at')->min(DB::raw('COALESCE(movement_date, DATE(created_at))')),
            DB::table('account_movements')->where('company_id', $companyId)->min('movement_date'),
            DB::table('cash_sessions as s')->join('cash_registers as r', 'r.id', '=', 's.cash_register_id')
                ->where('r.company_id', $companyId)->whereNull('s.deleted_at')->min('s.opened_at'),
        ]);

        return $dates ? Carbon::parse(min(array_map(fn ($d) => substr((string) $d, 0, 10), $dates))) : today();
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    public static function presetRange(?string $preset, int $companyId): ?array
    {
        return $preset === 'todo' ? [self::firstDate($companyId), today()] : ProfitReport::presetRange($preset);
    }

    public function build(string $tab = 'resultados', string $scope = 'caja', string $direction = 'todos'): array
    {
        $base = ['tab' => $tab, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];

        return $base + match ($tab) {
            'movimientos' => $this->movements($scope, $direction),
            'cierres'     => $this->closings(),
            default       => $this->results(),
        };
    }

    // ---------------------------------------------------------------- Resultados

    protected function results(): array
    {
        $rows = $this->cashQuery()->whereNull('cm.cancelled_at')
            ->where(fn ($q) => $q->whereNull('cm.category')->orWhere('cm.category', '!=', CashMovement::CATEGORY_TRANSFER))
            ->selectRaw($this->cashLabel() . ' as label, cm.type, '
                . "CASE WHEN cm.cash_session_id IS NOT NULL THEN 'caja' WHEN cm.financial_account_id IS NOT NULL THEN 'tesoreria' ELSE 'otro' END as origin, "
                . 'SUM(cm.amount) as total')
            ->groupBy('label', 'cm.type', 'origin')
            ->get();

        $income = $this->side($rows->where('type', 'ingreso'));
        $expense = $this->side($rows->where('type', 'egreso'));
        $income['rows'] = $this->splitSales($income['rows']);

        $capital = DB::table('account_movements')->where('company_id', $this->companyId)
            ->whereNull('voided_at')->whereNull('cash_movement_id')
            ->whereIn('type', self::CAPITAL_TYPES)
            ->whereBetween('movement_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount END), 0) as added, COALESCE(SUM(CASE WHEN direction = 'out' THEN amount END), 0) as taken")
            ->first();

        // Lo cobrado sin caja abierta no genera movimiento: se avisa para no darlo por perdido.
        $unregistered = DB::table('sales')->where('company_id', $this->companyId)->whereNull('deleted_at')
            ->where('status', 'completada')->whereNull('cash_session_id')->where('on_credit', false)
            ->whereBetween('sale_date', [$this->from, $this->to])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total - returned_total - credit_applied), 0) as amount')
            ->first();

        return [
            'income'  => $income,
            'expense' => $expense,
            'profit'  => round($income['total'] - $expense['total'], 2),
            'capital' => ['added' => round((float) $capital->added, 2), 'taken' => round((float) $capital->taken, 2)],
            'sales_without_cash' => ['count' => (int) $unregistered->n, 'amount' => round((float) $unregistered->amount, 2)],
            'note'    => 'Cuenta el dinero que entró y salió en el período: una compra cuenta cuando se paga y una venta fiada, cuando se cobra. Por eso no coincide con el reporte de Ganancias.',
        ];
    }

    /** Filas por categoría y total, con lo que corresponde a caja y a tesorería. */
    protected function side($rows): array
    {
        $byOrigin = fn (string $origin) => round((float) $rows->where('origin', $origin)->sum('total'), 2);

        return [
            'rows' => $rows->groupBy('label')
                ->map(fn ($g, $label) => ['label' => $label, 'amount' => round((float) $g->sum('total'), 2)])
                ->sortByDesc('amount')->values()->all(),
            'total'     => round((float) $rows->sum('total'), 2),
            'caja'      => $byOrigin('caja'),
            'tesoreria' => $byOrigin('tesoreria'),
            'otro'      => $byOrigin('otro'),
        ];
    }

    /** Una venta puede traer productos y servicios: lo cobrado se reparte en proporción. */
    protected function splitSales(array $rows): array
    {
        $index = array_search('Ventas', array_column($rows, 'label'), true);
        if ($index === false) {
            return $rows;
        }

        $lines = DB::table('sale_details as d')->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('s.company_id', $this->companyId)->whereNull('d.deleted_at')
            ->groupBy('d.sale_id')
            ->selectRaw('d.sale_id, SUM(CASE WHEN d.product_id IS NOT NULL THEN d.total ELSE 0 END) as products, SUM(d.total) as lines_total');

        $products = (float) $this->cashQuery()->whereNull('cm.cancelled_at')
            ->whereRaw($this->cashLabel() . " = 'Ventas'")
            ->joinSub($lines, 'l', fn ($j) => $j->on('l.sale_id', '=', DB::raw('CAST(cm.reference AS UNSIGNED)')))
            ->where('l.lines_total', '>', 0)
            ->sum(DB::raw('cm.amount * l.products / l.lines_total'));

        $total = $rows[$index]['amount'];
        $products = round(min($products, $total), 2);
        $split = array_filter([
            ['label' => 'Ventas de servicios', 'amount' => round($total - $products, 2)],
            ['label' => 'Ventas de productos', 'amount' => $products],
        ], fn ($r) => $r['amount'] > 0);

        array_splice($rows, $index, 1, array_values($split));
        usort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $rows;
    }

    // --------------------------------------------------------------- Movimientos

    protected function movements(string $scope, string $direction): array
    {
        $all = $this->scoped($scope);
        $wrap = fn () => DB::query()->fromSub($all, 'm');

        $totals = $wrap()->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount END), 0) as income, COALESCE(SUM(CASE WHEN direction = 'out' THEN amount END), 0) as expense")->first();

        $filtered = fn () => $wrap()->when($direction !== 'todos', fn ($q) => $q->where('direction', $direction === 'ingresos' ? 'in' : 'out'));

        $categories = $filtered()->selectRaw('label, direction, SUM(amount) as total')->groupBy('label', 'direction')->orderByDesc('total')->get();
        $top = (float) $categories->max('total');

        $methods = [];
        $methodName = function (?string $code) use (&$methods) {
            return $code === null ? null : ($methods[$code] ??= PaymentMethod::label($code, $this->companyId));
        };

        $rows = $filtered()->orderByDesc('day')->orderByDesc('created_at')->orderByDesc('id')->limit(self::LIST_LIMIT)->get();

        return [
            'scope'     => $scope,
            'direction' => $direction,
            'summary'   => [
                'income'  => round((float) $totals->income, 2),
                'expense' => round((float) $totals->expense, 2),
                'balance' => round((float) $totals->income - (float) $totals->expense, 2),
            ],
            'categories' => $categories->map(fn ($c) => [
                'label'     => $c->label,
                'direction' => $c->direction,
                'amount'    => round((float) $c->total, 2),
                'share'     => $top > 0 ? round((float) $c->total / $top, 4) : 0,
            ])->values()->all(),
            'count'  => $filtered()->count(),
            'limit'  => self::LIST_LIMIT,
            'items'  => $rows->map(fn ($m) => [
                'concept'   => $m->concept,
                'category'  => $m->label,
                'direction' => $m->direction,
                'amount'    => round((float) $m->amount, 2),
                'date'      => substr((string) $m->day, 0, 10),
                'time'      => $m->created_at ? substr((string) $m->created_at, 11, 5) : null,
                'origin'    => $m->origin,
                'method'    => $methodName($m->payment_method),
                'cancelled' => (bool) $m->cancelled,
            ])->all(),
        ];
    }

    /** Filas del alcance elegido, con las mismas columnas de ambos orígenes. */
    protected function scoped(string $scope): Builder
    {
        if ($scope === 'tesoreria') {
            return $this->accountRows();
        }

        $cash = $this->cashRows();
        if ($scope === 'caja') {
            return $cash->whereNotNull('cm.cash_session_id');
        }

        // Todo: lo que mueve la cuenta por su cuenta se suma a los movimientos del
        // negocio. Un gasto de cuenta ya está en cash_movements y los traspasos no son
        // ni ingreso ni egreso: mueven el dinero de lugar.
        $cash->whereNull('cm.cancelled_at')
            ->where(fn ($q) => $q->whereNull('cm.category')->orWhere('cm.category', '!=', CashMovement::CATEGORY_TRANSFER));

        return $cash->unionAll(
            $this->accountRows()->whereNull('am.cash_movement_id')->whereNotIn('am.type', ['traspaso_entrada', 'traspaso_salida'])
        );
    }

    protected function cashRows(): Builder
    {
        return $this->cashQuery()->selectRaw(
            "cm.id, CASE WHEN cm.type = 'ingreso' THEN 'in' ELSE 'out' END as direction, cm.amount, "
            . $this->text('cm.concept') . ' as concept, ' . $this->cashLabel() . ' as label, '
            . 'COALESCE(cm.movement_date, DATE(cm.created_at)) as day, cm.created_at, '
            . 'COALESCE(' . $this->text('cr.name') . ', ' . $this->text('fa.name') . ') as origin, '
            . $this->text('cm.payment_method') . ' as payment_method, '
            . 'CASE WHEN cm.cancelled_at IS NULL THEN 0 ELSE 1 END as cancelled'
        );
    }

    protected function accountRows(): Builder
    {
        $label = "CASE WHEN am.type IN ('traspaso_entrada', 'traspaso_salida') THEN 'Transferencia'"
            . " WHEN am.type = 'apertura' THEN 'Saldo inicial'"
            . ' WHEN ec.name IS NOT NULL THEN ' . $this->text('ec.name')
            . " WHEN cm.category IS NOT NULL AND cm.category <> '' THEN " . $this->text('cm.category')
            . " WHEN am.category IS NOT NULL AND am.category <> '' THEN " . $this->text('am.category')
            . " WHEN am.direction = 'in' THEN 'Aporte de capital' ELSE 'Retiro' END";

        return DB::table('account_movements as am')
            ->join('financial_accounts as fa', 'fa.id', '=', 'am.financial_account_id')
            ->leftJoin('cash_movements as cm', 'cm.id', '=', 'am.cash_movement_id')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 'cm.expense_category_id')
            ->where('am.company_id', $this->companyId)->whereNull('am.voided_at')
            ->whereBetween('am.movement_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw(
                'am.id, am.direction, am.amount, ' . $this->text('am.concept') . ' as concept, ' . $label . ' as label, '
                . 'am.movement_date as day, am.created_at, ' . $this->text('fa.name') . ' as origin, '
                . $this->text('cm.payment_method') . ' as payment_method, 0 as cancelled'
            );
    }

    protected function cashQuery(): Builder
    {
        return DB::table('cash_movements as cm')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 'cm.expense_category_id')
            ->leftJoin('cash_sessions as cs', 'cs.id', '=', 'cm.cash_session_id')
            ->leftJoin('cash_registers as cr', 'cr.id', '=', 'cs.cash_register_id')
            ->leftJoin('financial_accounts as fa', 'fa.id', '=', 'cm.financial_account_id')
            ->where('cm.company_id', $this->companyId)->whereNull('cm.deleted_at')
            ->whereRaw('COALESCE(cm.movement_date, DATE(cm.created_at)) BETWEEN ? AND ?', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    /** Nombre de la categoría de un movimiento; ventas y anulaciones no traen categoría. */
    protected function cashLabel(): string
    {
        return 'CASE WHEN ec.name IS NOT NULL THEN ' . $this->text('ec.name')
            . " WHEN cm.category IS NOT NULL AND cm.category <> '' THEN " . $this->text('cm.category')
            . " WHEN cm.concept LIKE 'Venta %' THEN 'Ventas'"
            . " WHEN cm.concept LIKE 'Anulaci%n venta%' THEN 'Anulación de ventas'"
            . " WHEN cm.type = 'ingreso' THEN 'Otros ingresos' ELSE 'Otros egresos' END";
    }

    /** Producción mezcla cotejos: se unifica para poder comparar y unir textos. */
    private function text(string $column): string
    {
        return "CONVERT($column USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    // ------------------------------------------------------------------- Cierres

    /** Todo turno que estuvo abierto en algún momento del período. */
    protected function closings(): array
    {
        $registerIds = Caja::withoutGlobalScopes()->where('company_id', $this->companyId)->pluck('id');

        $query = CashSession::query()->whereIn('cash_register_id', $registerIds)
            ->where('opened_at', '<=', $this->to)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $this->from));

        $count = (clone $query)->count();
        $sessions = $query->with(['register' => fn ($q) => $q->withoutGlobalScopes()->with(['branch' => fn ($b) => $b->withoutGlobalScopes()])])
            ->orderByDesc('opened_at')->orderByDesc('id')->limit(self::LIST_LIMIT)->get();

        $movements = DB::table('cash_movements')->whereIn('cash_session_id', $sessions->pluck('id'))->whereNull('deleted_at')
            ->groupBy('cash_session_id', 'payment_method', 'type')
            ->selectRaw('cash_session_id, payment_method, type, SUM(amount) as total')
            ->get()->groupBy('cash_session_id');

        $users = DB::table('users')->whereIn('id', $sessions->pluck('opened_by')->merge($sessions->pluck('closed_by'))->filter()->unique())->pluck('name', 'id');

        $inCash = $this->cashMethods();
        $methodName = fn (string $code) => PaymentMethod::label($code, $this->companyId);

        return [
            'count'    => $count,
            'limit'    => self::LIST_LIMIT,
            'sessions' => $sessions->map(function (CashSession $s) use ($movements, $users, $inCash, $methodName) {
                $cashIn = $cashOut = 0.0;
                $others = [];
                foreach ($movements->get($s->id, collect()) as $m) {
                    $code = $m->payment_method ?: 'efectivo';
                    $amount = (float) $m->total;
                    if ($inCash($code)) {
                        $m->type === 'ingreso' ? $cashIn += $amount : $cashOut += $amount;
                    } else {
                        $others[$code] = ($others[$code] ?? 0) + ($m->type === 'ingreso' ? $amount : -$amount);
                    }
                }
                $othersNet = array_sum($others);
                $opening = (float) $s->opening_amount;
                $closed = !$s->isOpen();

                return [
                    'id'         => $s->id,
                    'register'   => $s->register?->name,
                    'branch'     => $s->register?->branch?->name,
                    'status'     => $closed ? 'closed' : 'open',
                    'opened_by'  => $users[$s->opened_by] ?? null,
                    'opened_at'  => $s->opened_at?->format('Y-m-d H:i'),
                    'closed_by'  => $users[$s->closed_by] ?? null,
                    'closed_at'  => $s->closed_at?->format('Y-m-d H:i'),
                    'opening'    => round($opening, 2),
                    'cash_in'    => round($cashIn, 2),
                    'cash_out'   => round($cashOut, 2),
                    'others_net' => round($othersNet, 2),
                    'others'     => collect($others)->filter(fn ($v) => abs($v) >= 0.005)
                        ->map(fn ($v, $code) => ['method' => $methodName((string) $code), 'amount' => round($v, 2)])->values()->all(),
                    // El arqueo guardado manda: es lo que se vio y firmó al cerrar.
                    'expected'   => round($closed && $s->expected_amount !== null ? (float) $s->expected_amount : $opening + $cashIn - $cashOut + $othersNet, 2),
                    'counted'    => $s->closing_amount !== null ? round((float) $s->closing_amount, 2) : null,
                    'difference' => $s->difference !== null ? round((float) $s->difference, 2) : null,
                ];
            })->values()->all(),
        ];
    }

    /** ¿Ese medio de pago entra al cajón? Lo dice la empresa; si no lo configuró, el catálogo base. */
    protected function cashMethods(): \Closure
    {
        $configured = PaymentMethod::withoutGlobalScopes()->where('company_id', $this->companyId)->pluck('affects_cash', 'code');

        return fn (string $code) => $configured->has($code)
            ? (bool) $configured[$code]
            : (bool) (PaymentMethod::FALLBACK[$code][2] ?? false);
    }
}
