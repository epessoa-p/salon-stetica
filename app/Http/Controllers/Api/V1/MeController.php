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
            ],
        ]);
    }
}
