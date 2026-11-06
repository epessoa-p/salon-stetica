<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;

/**
 * Perfil de la sesión: usuario, salón activo, su ficha de personal y qué
 * puede hacer. La app arma su menú con esto.
 */
class MeController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $user = auth()->user();
        $company = $this->company();
        $personal = $this->ownPersonal()?->load('cargo');
        $setting = CompanySetting::current($company->id);

        return response()->json([
            'user'     => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_super_admin' => (bool) $user->is_super_admin],
            'company'  => AuthController::companyCard($company) + ['currency_symbol' => $setting->currency_symbol],
            'personal' => $personal ? ['id' => $personal->id, 'full_name' => $personal->full_name, 'cargo' => $personal->cargo?->name] : null,
            // Lo que la app puede mostrar: módulo en el plan + permiso (o lo propio).
            'abilities' => [
                'agenda_own'      => $company->planAllows('scheduling') && (bool) $personal,
                'agenda_all'      => $company->planAllows('scheduling') && $this->can('appointments.view'),
                'appointments_create' => $company->planAllows('scheduling') && $this->can('appointments.create'),
                'appointments_edit'   => $company->planAllows('scheduling') && $this->can('appointments.edit'),
                'clients_view'    => $company->planAllows('crm') && $this->can('clients.view'),
                'clients_create'  => $company->planAllows('crm') && $this->can('clients.create'),
                'clients_edit'    => $company->planAllows('crm') && $this->can('clients.edit'),
                'reminders'       => $company->planAllows('scheduling') && $this->can('reminders.view'),
                'birthdays'       => $company->planAllows('marketing') && $this->can('marketing.view'),
                'schedule'        => (bool) $personal,
                // Gerencia: panel si ve al menos ventas, agenda general, caja o asistencia del equipo.
                'dashboard'       => collect(app(ManagementController::class)->sections())->only(['sales', 'agenda', 'cash', 'team', 'receivables'])->contains(true),
                'receivables'     => $company->planAllows('finance') && $this->can('receivables.view'),
                'receivables_collect' => $company->planAllows('finance') && $this->can('receivables.collect'),
                'attendance'      => (bool) $personal,
                'commissions_own' => $company->planAllows('commissions') && (bool) $personal,
                'photos_view'     => $company->planAllows('crm') && $this->can('photos.view'),
                'photos_create'   => $company->planAllows('crm') && $this->can('photos.create'),
                'photos_delete'   => $company->planAllows('crm') && $this->can('photos.delete'),
                'sales_create'    => $company->planAllows('sales') && $this->can('sales.create'),
                'sales_view'      => $company->planAllows('sales') && $this->can('sales.view'),
                'cash_view'       => $company->planAllows('finance') && $this->can('cash.view'),
                'cash_open'       => $company->planAllows('finance') && $this->can('cash.open'),
                'cash_move'       => $company->planAllows('finance') && $this->can('cash.move'),
                'cash_close'      => $company->planAllows('finance') && $this->can('cash.close'),
                'accounts_view'   => $company->planAllows('finance') && $this->can('accounts.view'),
                'accounts_move'   => $company->planAllows('finance') && $this->can('accounts.move'),
                'expenses_view'   => $company->planAllows('finance') && $this->can('expenses.view'),
                'expenses_create' => $company->planAllows('finance') && $this->can('expenses.create'),
                'services_view'   => $company->planAllows('catalog') && $this->can('services.view'),
                'services_create' => $company->planAllows('catalog') && $this->can('services.create'),
                'services_edit'   => $company->planAllows('catalog') && $this->can('services.edit'),
                // Configuración (sin módulo de plan, como en la web: solo permiso).
                'company_view'    => $this->can('my-company.view'),
                'company_edit'    => $this->can('my-company.edit'),
                'branches_view'   => $this->can('branches.view'),
                'branches_create' => $this->can('branches.create'),
                'branches_edit'   => $this->can('branches.edit'),
                'schedules_view'  => $this->can('schedules.view'),
                'schedules_edit'  => $this->can('schedules.edit'),
                'payment_methods_view'   => $this->can('payment-methods.view'),
                'payment_methods_create' => $this->can('payment-methods.create'),
                'payment_methods_edit'   => $this->can('payment-methods.edit'),
                'reports'         => $this->can('reports.view') && (bool) app(\App\Http\Controllers\Reports\ReportController::class)->availableFor(auth()->user(), $company),
            ],
        ]);
    }
}
