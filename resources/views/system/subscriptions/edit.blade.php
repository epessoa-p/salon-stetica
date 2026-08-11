@extends('layouts.app')

@section('title', 'Asignar plan')

@section('page')
<div class="container-fluid" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-repeat"></i> Asignar / cambiar plan</h1>
            <p class="text-muted mb-0">Empresa: <strong>{{ $company->name }}</strong></p>
        </div>
        <a href="{{ route('subscriptions.show', $company) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($subscription)
        <div class="alert alert-info small">
            Plan actual: <strong>{{ $subscription->plan?->name }}</strong> ·
            Estado: @include('system.subscriptions._status', ['status' => $subscription->computedStatus()])
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('subscriptions.update', $company) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Plan <span class="text-danger">*</span></label>
                    <select name="plan_id" class="form-select" required>
                        <option value="">Seleccionar plan...</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ (string) old('plan_id', $subscription?->plan_id) === (string) $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} — {{ number_format($plan->price, 2) }} {{ $plan->currency }} / {{ $plan->billing_days }} días
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Al asignar, la suscripción inicia en periodo de prueba.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Días de prueba (opcional)</label>
                    <input type="number" min="0" name="trial_days" class="form-control no-uppercase" value="{{ old('trial_days') }}" placeholder="Usa los días del plan si se deja vacío">
                </div>

                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                </div>

                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> Asignar plan</button>
                <a href="{{ route('subscriptions.show', $company) }}" class="btn btn-light border">Cancelar</a>
            </form>
        </div>
    </div>
</div>
@endsection
