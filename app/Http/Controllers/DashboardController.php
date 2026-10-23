<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        // El operador de la plataforma (sin empresa activa) ve el panel global.
        if ($user->is_super_admin && !$company) {
            return $this->globalDashboard();
        }

        return $this->salonDashboard($user, $company);
    }

    /**
     * Panel del salón (empresa activa). Cada bloque respeta el plan y los permisos.
     */
    protected function salonDashboard($user, ?Company $company)
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();

        // Qué bloques puede ver: módulo incluido en el plan + permiso.
        $can = fn (string $feature, string $perm) => $company
            && $company->planAllows($feature)
            && $user->hasPermissionInCompany($perm, $company);

        $show = [
            'agenda'     => $can('scheduling', 'appointments.view'),
            'ventas'     => $can('sales', 'sales.view'),
            'clientes'   => $can('crm', 'clients.view'),
            'inventario' => $can('inventory', 'stock.view'),
            'caja'       => $can('finance', 'cash.view'),
        ];

        $data = ['company' => $company, 'show' => $show];

        // — Agenda: citas de hoy —
        if ($show['agenda']) {
            $data['citasHoy'] = Appointment::whereDate('starts_at', $today)->count();
            $data['citasPendientes'] = Appointment::whereDate('starts_at', $today)
                ->whereIn('status', ['pendiente', 'confirmada'])->count();
            $data['citasHoyList'] = Appointment::with(['client', 'service', 'specialist'])
                ->whereDate('starts_at', $today)
                ->orderBy('starts_at')
                ->limit(8)
                ->get();
        }

        // — Ventas del día y del mes —
        if ($show['ventas']) {
            $ventasHoy = Sale::where('status', 'completada')->whereDate('sale_date', $today);
            // Neto de devoluciones del mismo periodo.
            $data['ventasHoyTotal'] = (float) (clone $ventasHoy)->sum('total')
                - (float) \App\Models\SaleReturn::whereDate('return_date', $today)->sum('total');
            $data['ventasHoyCount'] = (clone $ventasHoy)->count();
            $data['ventasMesTotal'] = (float) Sale::where('status', 'completada')
                ->where('sale_date', '>=', $monthStart)->sum('total')
                - (float) \App\Models\SaleReturn::where('return_date', '>=', $monthStart)->sum('total');
            $data['ultimasVentas'] = Sale::with('client')
                ->where('status', 'completada')
                ->latest('sale_date')->limit(5)->get();
        }

        // — Clientes —
        if ($show['clientes']) {
            $data['clientesTotal'] = Client::count();
            $data['clientesNuevosMes'] = Client::where('created_at', '>=', $monthStart)->count();
            $data['cumpleanosHoy'] = Client::whereNotNull('birthdate')
                ->whereMonth('birthdate', $today->month)
                ->whereDay('birthdate', $today->day)
                ->get();
        }

        // — Inventario: stock bajo —
        if ($show['inventario']) {
            $stockBajo = Product::where('active', true)
                ->where('min_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'min_stock');
            $data['stockBajoCount'] = (clone $stockBajo)->count();
            $data['stockBajoList'] = (clone $stockBajo)->orderBy('current_stock')->limit(6)->get();
        }

        // — Caja abierta (las sesiones se aíslan por su caja/empresa) —
        if ($show['caja'] && $company) {
            $data['cajaAbierta'] = CashSession::with('register')
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $company->id))
                ->first();
        }

        return view('dashboard.index', $data);
    }

    /**
     * Panel global del operador de la plataforma (super admin sin empresa activa).
     */
    protected function globalDashboard()
    {
        $monthStart = today()->startOfMonth();

        $companies = Company::with(['subscriptions' => fn ($q) => $q->with('plan')->latest('id')])
            ->orderBy('name')->get();

        // Estado de suscripción por empresa (derivado de fechas).
        $estados = ['trial' => 0, 'active' => 0, 'grace' => 0, 'expired' => 0, 'none' => 0];
        $porVencer = collect();

        foreach ($companies as $c) {
            $sub = $c->subscriptions->first();
            $estado = $sub ? $sub->computedStatus() : 'none';
            $estados[$estado] = ($estados[$estado] ?? 0) + 1;

            if ($sub && in_array($estado, ['trial', 'active', 'grace'], true)) {
                $dias = $sub->daysLeft();
                if ($dias !== null && $dias <= 10) {
                    $porVencer->push(['company' => $c, 'sub' => $sub, 'dias' => $dias, 'estado' => $estado]);
                }
            }
        }

        return view('dashboard.admin', [
            'totalCompanies' => $companies->count(),
            'activeCompanies' => $companies->where('active', true)->count(),
            'estados' => $estados,
            'totalUsers' => User::count(),
            'totalPersonal' => Personal::count(),
            'ingresosMes' => (float) SubscriptionPayment::where('status', 'confirmed')
                ->where('confirmed_at', '>=', $monthStart)->sum('amount'),
            'pagosPendientes' => SubscriptionPayment::where('status', 'pending')->count(),
            'porVencer' => $porVencer->sortBy('dias')->take(8),
            'totalSubscriptions' => Subscription::count(),
        ]);
    }
}
