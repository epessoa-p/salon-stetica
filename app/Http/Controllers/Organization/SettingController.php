<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function edit(Request $request)
    {
        $user = auth()->user();
        $companies = $user->is_super_admin ? Company::orderBy('name')->get() : collect();
        $company = $this->targetCompany($request);

        return view('organization.settings.edit', [
            'company'    => $company,
            'companies'  => $companies,
            'setting'    => $company ? CompanySetting::firstOrNew(['company_id' => $company->id]) : new CompanySetting(),
            'currencies' => CompanySetting::CURRENCIES,
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->targetCompany($request);

        if (!$company) {
            return back()->withInput()->withErrors(['error' => 'Selecciona una empresa para guardar sus preferencias.']);
        }

        $validated = $request->validate([
            'currency'                 => ['required', 'string', 'max:10'],
            'currency_symbol'          => ['required', 'string', 'max:10'],
            'tax_rate'                 => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_label'                => ['required', 'string', 'max:30'],
            'invoice_business_name'    => ['nullable', 'string', 'max:255'],
            'invoice_nit'              => ['nullable', 'string', 'max:30'],
            'invoice_address'          => ['nullable', 'string', 'max:255'],
            'invoice_phone'            => ['nullable', 'string', 'max:40'],
            'invoice_footer'           => ['nullable', 'string', 'max:500'],
            'sale_prefix'              => ['required', 'string', 'max:10'],
            'sale_padding'             => ['required', 'integer', 'min:1', 'max:12'],
            'appointment_slot_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'attendance_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        $validated['tax_included'] = $request->boolean('tax_included');
        $validated['require_cash_session'] = $request->boolean('require_cash_session');

        try {
            CompanySetting::updateOrCreate(['company_id' => $company->id], $validated);
            CompanySetting::forget($company->id);

            return redirect()
                ->route('settings.edit', $request->only('company_id'))
                ->with('success', 'Preferencias guardadas.');
        } catch (\Throwable $e) {
            Log::error('Error al guardar preferencias', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible guardar las preferencias.']);
        }
    }

    /** El super admin elige empresa por querystring; los demás usan la suya. */
    protected function targetCompany(Request $request): ?Company
    {
        $user = auth()->user();

        if (!$user->is_super_admin) {
            return $user->getCurrentCompany();
        }

        $id = (int) $request->input('company_id');

        return $id ? Company::find($id) : null;
    }
}
