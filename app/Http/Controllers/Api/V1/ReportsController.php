<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Reports\ReportController;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
