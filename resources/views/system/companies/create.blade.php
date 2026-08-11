@extends('layouts.app')

@section('page')
<h1><i class="bi bi-plus-circle"></i> Nueva Empresa</h1>

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('companies.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="ruc" class="form-label">RUC</label>
                <input type="text" id="ruc" name="ruc" class="form-control @error('ruc') is-invalid @enderror" value="{{ old('ruc') }}">
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
            </div>

            <div class="form-group mb-3">
                <label for="address" class="form-label">Dirección</label>
                <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}">
            </div>

            <div class="form-group mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
            </div>

            <div class="form-group mb-3">
                <label for="logo" class="form-label">Logo de la empresa</label>
                <input type="file" id="logo" name="logo" accept="image/*" class="form-control no-uppercase @error('logo') is-invalid @enderror">
                <small class="text-muted">Formatos: JPG, PNG, WEBP o SVG. Máx. 2 MB.</small>
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group mb-3">
                <label for="plan_id" class="form-label">Plan de suscripción</label>
                <select id="plan_id" name="plan_id" class="form-select @error('plan_id') is-invalid @enderror">
                    <option value="">Sin plan (asignar después)</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ (string) old('plan_id') === (string) $plan->id ? 'selected' : '' }}>
                            {{ $plan->name }} — {{ number_format($plan->price, 2) }} {{ $plan->currency }} / {{ $plan->billing_days }} días (prueba {{ $plan->trial_days }} días)
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Al asignar un plan, la empresa inicia en periodo de prueba.</small>
                @error('plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @include('system.companies.partials.colors', [
                'primaryColor'   => old('primary_color', '#1e1e1e'),
                'secondaryColor' => old('secondary_color', '#f8f8f8'),
            ])

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Empresa
                </button>
                <a href="{{ route('companies.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
