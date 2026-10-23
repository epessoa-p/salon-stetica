<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\LoyaltyMovement;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Programa de puntos: configuración, indicadores, movimientos y ajustes. */
class LoyaltyController extends Controller
{
    public function __construct(protected LoyaltyService $loyalty)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        if (!$company) {
            return view('crm.loyalty.index', ['noCompany' => true]);
        }

        $companyId = $company->id;
        $setting = CompanySetting::current($companyId);
        $clientId = $request->integer('client_id') ?: null;
        $monthStart = today()->startOfMonth();

        $base = LoyaltyMovement::where('company_id', $companyId);
        $inCirculation = (int) (clone $base)->sum('points');

        $movements = LoyaltyMovement::with(['client', 'sale', 'author'])
            ->where('company_id', $companyId)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $topClients = LoyaltyMovement::where('company_id', $companyId)
            ->selectRaw('client_id, SUM(points) AS balance')->groupBy('client_id')
            ->havingRaw('SUM(points) > 0')->orderByDesc('balance')->limit(5)
            ->with('client')->get();

        $can = fn ($p) => $user->is_super_admin || $user->hasPermissionInCompany($p, $company);

        return view('crm.loyalty.index', [
            'noCompany'   => false,
            'setting'     => $setting,
            'kpis'        => [
                'circulation' => $inCirculation,
                'liability'   => $setting->pointsValue(max(0, $inCirculation)),
                'earned'      => (int) (clone $base)->where('type', 'earn')->where('created_at', '>=', $monthStart)->sum('points'),
                'redeemed'    => (int) -(clone $base)->where('type', 'redeem')->where('created_at', '>=', $monthStart)->sum('points'),
            ],
            'movements'   => $movements,
            'topClients'  => $topClients,
            'client'      => $clientId ? Client::where('company_id', $companyId)->find($clientId) : null,
            'clients'     => Client::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'canEdit'     => $can('loyalty.edit'),
            'canAdjust'   => $can('loyalty.adjust'),
        ]);
    }

    /** Guarda la configuración del programa (en las preferencias de la empresa). */
    public function updateSettings(Request $request)
    {
        $companyId = auth()->user()->getCurrentCompany()?->id;
        abort_unless($companyId, 403);

        $data = $request->validate([
            'loyalty_amount_per_point' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'loyalty_point_value'      => ['required', 'numeric', 'min:0.0001', 'max:1000'],
            'loyalty_min_redeem'       => ['required', 'integer', 'min:1', 'max:1000000'],
            'loyalty_expiry_months'    => ['nullable', 'integer', 'min:1', 'max:120'],
        ], [
            'loyalty_amount_per_point.min' => 'El monto por punto debe ser mayor que cero.',
            'loyalty_point_value.min'      => 'El valor del punto debe ser mayor que cero.',
        ]);

        $data['loyalty_enabled'] = $request->boolean('loyalty_enabled');
        // Vacío o "no vencen" = NULL.
        $data['loyalty_expiry_months'] = $request->boolean('loyalty_expires') ? ($data['loyalty_expiry_months'] ?? 12) : null;

        CompanySetting::updateOrCreate(['company_id' => $companyId], $data);
        CompanySetting::forget($companyId);

        return redirect()->route('loyalty.index')->with('success', $data['loyalty_enabled']
            ? 'Programa de puntos guardado y activo: las ventas con cliente ya acumulan puntos.'
            : 'Configuración guardada. El programa está desactivado: no se acumulan ni canjean puntos.');
    }

    /** Ajuste manual de puntos con motivo obligatorio. */
    public function adjust(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->getCurrentCompany()?->id;
        abort_unless($companyId, 403);

        $data = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'direction' => ['required', Rule::in(['add', 'sub'])],
            'points'    => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason'    => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'Indica el motivo del ajuste: queda en el historial del cliente.']);

        $points = $data['direction'] === 'add' ? $data['points'] : -$data['points'];
        $this->loyalty->adjust($companyId, (int) $data['client_id'], $points, $data['reason'], $user);

        return redirect()->route('loyalty.index', ['client_id' => $data['client_id']])
            ->with('success', ($points > 0 ? 'Se sumaron ' : 'Se restaron ') . abs($points) . ' puntos.');
    }
}
