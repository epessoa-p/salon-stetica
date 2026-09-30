<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-role:super_admin');
    }

    public function index()
    {
        $companies = Company::with(['subscriptions' => fn ($q) => $q->with('plan')->latest('id')])
            ->orderBy('name')
            ->paginate(15);

        return view('system.subscriptions.index', compact('companies'));
    }

    /**
     * Detalle de la suscripción de una empresa + pagos.
     */
    public function show(Company $company)
    {
        $subscription = $company->currentSubscription();
        $payments = $subscription
            ? $subscription->payments()->latest('id')->get()
            : collect();

        return view('system.subscriptions.show', compact('company', 'subscription', 'payments'));
    }

    /**
     * Formulario para asignar / cambiar el plan de una empresa.
     */
    public function edit(Company $company)
    {
        $plans = Plan::where('active', true)->orderBy('sort_order')->orderBy('price')->get();
        $subscription = $company->currentSubscription();

        return view('system.subscriptions.edit', compact('company', 'plans', 'subscription'));
    }

    /**
     * Asigna un plan: crea una nueva suscripción iniciando el periodo de prueba.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'trial_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $trialDays = $validated['trial_days'] ?? $plan->trial_days ?? config('saas.default_trial_days');

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays((int) $trialDays),
            'starts_at' => now(),
            'ends_at' => null,
            'amount' => $plan->price,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('subscriptions.show', $company)
            ->with('success', 'Plan asignado. La suscripción inició en periodo de prueba.');
    }

    /**
     * Actualiza overrides (límites/módulos por empresa) y días de gracia de la suscripción vigente.
     */
    public function updateOverrides(Request $request, Company $company)
    {
        $subscription = $company->currentSubscription();
        if (!$subscription) {
            return back()->withErrors(['error' => 'La empresa no tiene una suscripción asignada.']);
        }

        $validated = $request->validate([
            'max_users_override'    => 'nullable|integer|min:0',
            'max_branches_override' => 'nullable|integer|min:0',
            'max_cajas_override'    => 'nullable|integer|min:0',
            'max_personal_override' => 'nullable|integer|min:0',
            'max_products_override' => 'nullable|integer|min:0',
            'grace_days'            => 'nullable|integer|min:0',
            'features_override'     => 'nullable|array',
            'features_override.*'   => ['string', \Illuminate\Validation\Rule::in(array_keys(Plan::MODULES))],
            'use_features_override'  => 'nullable|boolean',
        ]);

        // Si no se activa el override de módulos, se limpia (hereda el plan).
        $features = $request->boolean('use_features_override')
            ? ($validated['features_override'] ?? [])
            : null;

        $subscription->update([
            'max_users_override'    => $validated['max_users_override'] ?? null,
            'max_branches_override' => $validated['max_branches_override'] ?? null,
            'max_cajas_override'    => $validated['max_cajas_override'] ?? null,
            'max_personal_override' => $validated['max_personal_override'] ?? null,
            'max_products_override' => $validated['max_products_override'] ?? null,
            'grace_days'            => $validated['grace_days'] ?? null,
            'features_override'     => $features,
        ]);

        return redirect()->route('subscriptions.show', $company)
            ->with('success', 'Overrides de la empresa actualizados.');
    }
}
