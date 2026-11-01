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

            {{-- La empresa queda lista para usarse: sucursal, almacén, administrador y caja. --}}
            <div class="border rounded-3 p-3 mb-3" style="background: var(--accent-soft, #faf6f8)">
                <h6 class="fw-bold mb-1"><i class="bi bi-shop"></i> Sucursal principal</h6>
                <small class="text-muted d-block mb-3">Se crea con su almacén y la caja principal. Luego se pueden agregar más sucursales.</small>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="branch_name" class="form-label">Nombre de la sucursal</label>
                        <input type="text" id="branch_name" name="branch_name" class="form-control @error('branch_name') is-invalid @enderror"
                               value="{{ old('branch_name') }}" placeholder="SUCURSAL PRINCIPAL">
                        @error('branch_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="branch_address" class="form-label">Dirección de la sucursal</label>
                        <input type="text" id="branch_address" name="branch_address" class="form-control @error('branch_address') is-invalid @enderror"
                               value="{{ old('branch_address') }}" placeholder="La de la empresa">
                        @error('branch_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="border rounded-3 p-3 mb-3" style="background: var(--accent-soft, #faf6f8)">
                <h6 class="fw-bold mb-1"><i class="bi bi-person-badge"></i> Acceso del administrador</h6>
                <small class="text-muted d-block mb-3">Se crea su usuario y su ficha de personal en la sucursal principal. Al guardar verás sus credenciales para entregárselas al cliente.</small>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="admin_name" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" id="admin_name" name="admin_name" class="form-control @error('admin_name') is-invalid @enderror"
                               value="{{ old('admin_name') }}" required>
                        @error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="admin_email" class="form-label">Correo (para iniciar sesión) <span class="text-danger">*</span></label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control no-uppercase @error('admin_email') is-invalid @enderror"
                               value="{{ old('admin_email') }}" required>
                        @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="admin_password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" id="admin_password" name="admin_password" autocomplete="new-password"
                                   class="form-control no-uppercase @error('admin_password') is-invalid @enderror" placeholder="Vacía = se genera una segura">
                            <button class="btn btn-outline-secondary" type="button" title="Mostrar u ocultar"
                                    onclick="const i = document.getElementById('admin_password'); i.type = i.type === 'password' ? 'text' : 'password';">
                                <i class="bi bi-eye"></i>
                            </button>
                            @error('admin_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Mínimo 8 caracteres. Si la dejas vacía, el sistema genera una.</small>
                    </div>
                </div>
            </div>

            @include('system.companies.partials.colors', [
                'primaryColor'   => old('primary_color', \App\Models\Company::DEFAULT_PRIMARY_COLOR),
                'secondaryColor' => old('secondary_color', \App\Models\Company::DEFAULT_SECONDARY_COLOR),
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
