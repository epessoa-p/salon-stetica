<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-role:super_admin');
    }

    public function index()
    {
        $companies = Company::paginate(15);
        return view('system.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('system.companies.create', [
            'plans' => Plan::where('active', true)->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();
        $planId = $data['plan_id'] ?? null;
        unset($data['plan_id']);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $company = Company::create($data);

        // Si se seleccionó un plan, se crea la suscripción iniciando el periodo de prueba.
        if ($planId && ($plan = Plan::find($planId))) {
            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays((int) ($plan->trial_days ?? config('saas.default_trial_days'))),
                'starts_at' => now(),
                'ends_at' => null,
                'amount' => $plan->price,
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('companies.index')->with('success', 'Empresa creada exitosamente');
    }

    public function show(Company $company)
    {
        $users = $company->users()->paginate(10);
        return view('system.companies.show', compact('company', 'users'));
    }

    public function edit(Company $company)
    {
        return view('system.companies.edit', compact('company'));
    }

    public function update(StoreCompanyRequest $request, Company $company)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            // Elimina el logo anterior si existía.
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $company->update($data);

        return redirect()->route('companies.show', $company)->with('success', 'Empresa actualizada exitosamente');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada exitosamente');
    }
}
