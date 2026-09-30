<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Models\Plan;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-role:super_admin');
    }

    public function index()
    {
        $plans = Plan::orderBy('sort_order')->orderBy('price')->paginate(15);
        return view('system.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('system.plans.create');
    }

    public function store(StorePlanRequest $request)
    {
        $data = $this->prepare($request->validated());
        Plan::create($data);

        return redirect()->route('plans.index')->with('success', 'Plan creado exitosamente.');
    }

    public function edit(Plan $plan)
    {
        return view('system.plans.edit', compact('plan'));
    }

    public function update(StorePlanRequest $request, Plan $plan)
    {
        $data = $this->prepare($request->validated(), $plan);
        $plan->update($data);

        return redirect()->route('plans.index')->with('success', 'Plan actualizado exitosamente.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un plan con suscripciones asociadas.']);
        }

        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plan eliminado exitosamente.');
    }

    /**
     * Normaliza los datos: slug automático y bandera active.
     */
    protected function prepare(array $data, ?Plan $plan = null): array
    {
        $data['slug'] = $data['slug'] ?? null;
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']) ?: 'plan';
        }

        // Garantiza slug único.
        $base = $data['slug'];
        $counter = 1;
        while (Plan::where('slug', $data['slug'])->when($plan, fn ($q) => $q->where('id', '!=', $plan->id))->exists()) {
            $data['slug'] = $base . '-' . $counter;
            $counter++;
        }

        $data['currency'] = $data['currency'] ?? config('saas.currency', 'BOB');
        $data['active'] = (bool) ($data['active'] ?? false);
        // Módulos incluidos: si no llega ninguno, se guarda lista vacía (ningún módulo).
        $data['features'] = $data['features'] ?? [];

        return $data;
    }
}
