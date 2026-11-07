<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use Illuminate\Support\Carbon;

/**
 * Control de los servicios básicos de un mes: cuáles se pagaron y cuáles faltan.
 *
 *   mensual  pagado si hubo un pago en ese mes
 *   anual    pagado si hubo uno en los 12 meses que terminan ese mes
 *
 * El último pago (aunque sea de otro mes) se propone como importe del siguiente.
 * Los gastos anulados no cuentan.
 */
class BasicServicesMonth
{
    public const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** `$month` en formato YYYY-MM; inválido o vacío, el mes en curso. */
    public static function parse(?string $month): Carbon
    {
        if ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    public function for(int $companyId, Carbon $month): array
    {
        ExpenseCategory::ensureDefaults($companyId);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $categories = ExpenseCategory::forCompany($companyId)->active()->inGroup('basicos')->ordered()->get();

        // Los pagos hasta el fin del mes: de ahí sale la ventana y el último importe.
        $payments = CashMovement::forCompany($companyId)->live()
            ->whereIn('expense_category_id', $categories->pluck('id'))
            ->where('type', 'egreso')
            ->whereDate('movement_date', '<=', $end->toDateString())
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->get(['id', 'expense_category_id', 'amount', 'movement_date'])
            ->groupBy('expense_category_id');

        $rows = $categories->map(function (ExpenseCategory $category) use ($payments, $start, $end) {
            $mine = $payments->get($category->id, collect());
            $annual = $category->isAnnual();
            $from = $annual ? $end->copy()->subYear()->addDay()->startOfDay() : $start;
            $inWindow = $mine->filter(fn ($p) => $p->movement_date->between($from, $end));
            $last = $mine->first();

            return [
                'id'           => $category->id,
                'name'         => $category->name,
                'frequency'    => $category->frequency ?: 'mensual',
                'status'       => $inWindow->isNotEmpty() ? 'pagado' : 'pendiente',
                'paid'         => round((float) $inWindow->sum('amount'), 2),
                'last_paid_on' => $last?->movement_date->toDateString(),
                'last_amount'  => $last ? (float) $last->amount : null,
                // Un anual vuelve a tocar al año del último pago.
                'next_due_on'  => $annual && $last ? $last->movement_date->copy()->addYear()->toDateString() : null,
            ];
        })->values();

        $next = $start->copy()->addMonth();

        return [
            'month'         => $start->format('Y-m'),
            'label'         => ucfirst(self::MONTHS[$start->month - 1]) . ' ' . $start->year,
            'previous'      => $start->copy()->subMonth()->format('Y-m'),
            'next'          => $next->lte(now()->startOfMonth()) ? $next->format('Y-m') : null,
            'services'      => $rows->all(),
            'paid_count'    => $rows->where('status', 'pagado')->count(),
            'pending_count' => $rows->where('status', 'pendiente')->count(),
            'total_paid'    => round((float) $rows->sum('paid'), 2),
        ];
    }
}
