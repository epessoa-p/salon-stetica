<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Reports\ReportController;
use App\Models\CompanySetting;
use App\Services\AnalysisReport;
use App\Services\FinanceReport;
use App\Services\InventoryReport;
use App\Services\ProfitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

/**
 * Reportes en la app: los mismos de la web (indicadores y tablas), con las
 * mismas reglas de plan y permiso por tipo.
 */
class ReportsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->can('reports.view')) {
            return $this->forbidden('No tienes permiso para ver reportes.');
        }

        $company = $this->company();
        $web = app(ReportController::class);
        $available = $web->availableFor(auth()->user(), $company);
        if (!$available) {
            return $this->forbidden('El plan del salón o tus permisos no incluyen ningún reporte.');
        }

        // Menú de reportes de la app: solo qué hay disponible, sin calcular nada.
        if ($request->boolean('meta')) {
            return response()->json(['available' => collect($available)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()]);
        }

        $data = $request->validate([
            'type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.']);

        $type = isset($available[$data['type'] ?? '']) ? $data['type'] : array_key_first($available);
        $from = Carbon::parse($data['from'] ?? today()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? today()->toDateString())->endOfDay();

        return response()->json([
            'available' => collect($available)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'type'      => $type,
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
            'currency'  => CompanySetting::current($company->id)->currency_symbol,
            'report'    => $web->build($type, $from, $to, $company->id),
        ]);
    }

    /** Inventario de hoy: mismas reglas y datos que la pestaña de la web. */
    public function inventory(Request $request): JsonResponse
    {
        if (!$this->can('reports.view')) {
            return $this->forbidden('No tienes permiso para ver reportes.');
        }

        $company = $this->company();
        if (!isset(app(ReportController::class)->availableFor(auth()->user(), $company)['inventario'])) {
            return $this->forbidden('El plan del salón o tus permisos no incluyen este reporte.');
        }

        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $company->id)->whereNull('deleted_at')],
        ], ['warehouse_id.exists' => 'El almacén no pertenece a este salón.']);

        return response()->json(
            (new InventoryReport($company->id))->build(isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null)
            + ['currency' => CompanySetting::current($company->id)->currency_symbol]
        );
    }

    /** Finanzas: mismas reglas y datos que la pestaña de la web. */
    public function finance(Request $request): JsonResponse
    {
        if (!$this->can('reports.view')) {
            return $this->forbidden('No tienes permiso para ver reportes.');
        }

        $company = $this->company();
        if (!isset(app(ReportController::class)->availableFor(auth()->user(), $company)['finanzas'])) {
            return $this->forbidden('El plan del salón o tus permisos no incluyen este reporte.');
        }

        $data = $request->validate([
            'tab'    => ['nullable', 'in:' . implode(',', FinanceReport::TABS)],
            'scope'  => ['nullable', 'in:' . implode(',', FinanceReport::SCOPES)],
            'dir'    => ['nullable', 'in:' . implode(',', FinanceReport::DIRECTIONS)],
            'preset' => ['nullable', 'in:hoy,semana,semana_ant,mes,mes_ant,todo'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.']);

        [$from, $to] = isset($data['from'])
            ? [Carbon::parse($data['from']), Carbon::parse($data['to'] ?? today()->toDateString())]
            : FinanceReport::presetRange($data['preset'] ?? 'mes', $company->id);

        return response()->json(
            (new FinanceReport($company->id, $from, $to))->build($data['tab'] ?? 'resultados', $data['scope'] ?? 'caja', $data['dir'] ?? 'todos')
            + ['currency' => CompanySetting::current($company->id)->currency_symbol, 'all_time' => ($data['preset'] ?? null) === 'todo' && !isset($data['from'])]
        );
    }

    /** Ganancias: mismas reglas y datos que la pestaña de la web. */
    public function profit(Request $request): JsonResponse
    {
        if (!$this->can('reports.view')) {
            return $this->forbidden('No tienes permiso para ver reportes.');
        }

        $company = $this->company();
        if (!isset(app(ReportController::class)->availableFor(auth()->user(), $company)['ganancias'])) {
            return $this->forbidden('El plan del salón o tus permisos no incluyen este reporte.');
        }

        $data = $request->validate([
            'preset' => ['nullable', 'in:hoy,semana,semana_ant,mes,mes_ant'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
            'scope'  => ['nullable', 'in:ambos,productos,servicios'],
        ], ['to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.']);

        [$from, $to] = isset($data['from'])
            ? [Carbon::parse($data['from']), Carbon::parse($data['to'] ?? today()->toDateString())]
            : ProfitReport::presetRange($data['preset'] ?? 'mes');

        return response()->json(
            (new ProfitReport($company->id, $from, $to, $data['scope'] ?? 'ambos'))->build()
            + ['currency' => CompanySetting::current($company->id)->currency_symbol]
        );
    }

    /**
     * Análisis comparativo de la app. Sin `tab` devuelve solo las pestañas
     * disponibles; cada una exige lo mismo que el reporte equivalente.
     */
    public function analysis(Request $request): JsonResponse
    {
        if (!$this->can('reports.view')) {
            return $this->forbidden('No tienes permiso para ver reportes.');
        }

        $company = $this->company();
        $available = app(ReportController::class)->availableFor(auth()->user(), $company);
        if (!isset($available['analisis'])) {
            return $this->forbidden('El plan del salón o tus permisos no incluyen este reporte.');
        }

        $tabs = ['servicios' => 'Servicios', 'ventas' => 'Ventas'];
        if (isset($available['compras'])) {
            $tabs['compras'] = 'Compras';
        }
        $tabs += ['personal' => 'Personal', 'top' => 'Top'];

        $data = [
            'currency' => CompanySetting::current($company->id)->currency_symbol,
            'tabs'     => collect($tabs)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ];

        $tab = $request->input('tab');
        if ($tab === null) {
            return response()->json($data);
        }
        if (!isset($tabs[$tab])) {
            return $this->forbidden('No tienes acceso a esta pestaña.');
        }

        $report = new AnalysisReport($company->id);

        return response()->json($data + ['tab' => $tab] + match ($tab) {
            'personal' => $report->personal($company->planAllows('commissions') && $this->can('commissions.view')),
            'top'      => $report->top(),
            default    => $report->series($tab),
        });
    }
}
