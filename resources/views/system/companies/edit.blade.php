@extends('layouts.app')

@section('page')
<h1><i class="bi bi-pencil"></i> Editar Empresa</h1>

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('companies.update', $company) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $company->name) }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="ruc" class="form-label">RUC</label>
                <input type="text" id="ruc" name="ruc" class="form-control @error('ruc') is-invalid @enderror" value="{{ old('ruc', $company->ruc) }}">
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $company->email) }}">
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $company->phone) }}">
            </div>

            <div class="form-group mb-3">
                <label for="address" class="form-label">Dirección</label>
                <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $company->address) }}">
            </div>

            <div class="form-group mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $company->description) }}</textarea>
            </div>

            <div class="form-group mb-3">
                <label for="logo" class="form-label">Logo de la empresa</label>
                @if($company->logo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/'.$company->logo) }}" alt="{{ $company->name }}"
                             style="height: 60px; width: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                @endif
                <input type="file" id="logo" name="logo" accept="image/*" class="form-control no-uppercase @error('logo') is-invalid @enderror">
                <small class="text-muted">Sube una imagen para reemplazar el logo actual. Formatos: JPG, PNG, WEBP o SVG. Máx. 2 MB.</small>
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @php $companySub = $company->currentSubscription(); @endphp
            <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small">
                    <i class="bi bi-card-checklist me-1"></i>
                    <strong>Suscripción:</strong>
                    {{ $companySub?->plan?->name ?? 'Sin plan asignado' }}
                    @if($companySub) · Estado: @include('system.subscriptions._status', ['status' => $companySub->computedStatus()]) @endif
                </div>
                <a href="{{ route('subscriptions.show', $company) }}" class="btn btn-sm btn-outline-primary">Gestionar suscripción</a>
            </div>

            @include('system.companies.partials.colors', [
                'primaryColor'   => old('primary_color', $company->primary_color ?? '#1e1e1e'),
                'secondaryColor' => old('secondary_color', $company->secondary_color ?? '#f8f8f8'),
            ])

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Actualizar
                </button>
                <a href="{{ route('companies.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
