<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * "Mi empresa": permite a la propia empresa editar sus datos de identidad
 * (nombre, contacto, logo y colores) sin pasar por el módulo de Plataforma,
 * que es exclusivo del super admin.
 */
class MyCompanyController extends Controller
{
    public function edit()
    {
        $company = $this->currentCompany();

        return view('organization.my_company.edit', [
            'company'      => $company,
            'subscription' => $company?->currentSubscription(),
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->currentCompany();

        if (!$company) {
            return back()->withErrors(['error' => 'No hay una empresa activa para editar.']);
        }

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            // Se ignora la propia empresa para no marcar su RUC como repetido.
            'ruc'             => ['nullable', 'string', 'max:20', Rule::unique('companies', 'ruc')->ignore($company->id)],
            'address'         => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'email'           => ['nullable', 'email', 'max:255'],
            'description'     => ['nullable', 'string'],
            'primary_color'   => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'logo'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ], [
            'ruc.unique' => 'Este RUC ya está registrado en otra empresa.',
        ]);

        try {
            if ($request->hasFile('logo')) {
                if ($company->logo) {
                    Storage::disk('public')->delete($company->logo);
                }
                $validated['logo'] = $request->file('logo')->store('logos', 'public');
            }

            $company->update($validated);

            return redirect()->route('my-company.edit')->with('success', 'Datos de la empresa actualizados.');
        } catch (\Throwable $e) {
            Log::error('Error al actualizar mi empresa', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar los datos de la empresa.']);
        }
    }

    /** El super admin en modo global no tiene empresa activa. */
    protected function currentCompany(): ?Company
    {
        return auth()->user()->getCurrentCompany();
    }
}
