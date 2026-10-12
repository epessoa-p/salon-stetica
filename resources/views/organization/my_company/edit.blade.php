@extends('layouts.app')

@section('title', 'Mi empresa')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-shop"></i> Mi empresa</h1>
            <p class="text-muted mb-0">Datos, logo y colores con los que se identifica tu salón en el sistema.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if(!$company)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i>
            Estás en modo global, sin una empresa activa. Las empresas se administran desde
            <a href="{{ route('companies.index') }}" class="alert-link">Plataforma &rsaquo; Empresas</a>.
        </div>
    @else
        <form action="{{ route('my-company.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4">
                {{-- Identidad --}}
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-building text-primary"></i> Datos de la empresa</h6></div>
                        <div class="card-body pt-2">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $company->name) }}" required maxlength="255">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="ruc" class="form-label">RUC / NIT</label>
                                    <input type="text" id="ruc" name="ruc" class="form-control @error('ruc') is-invalid @enderror"
                                           value="{{ old('ruc', $company->ruc) }}" maxlength="20">
                                    @error('ruc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Teléfono</label>
                                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                           value="{{ old('phone', $company->phone) }}" maxlength="20">
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control no-uppercase @error('email') is-invalid @enderror"
                                           value="{{ old('email', $company->email) }}" maxlength="255">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="address" class="form-label">Dirección</label>
                                    <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror"
                                           value="{{ old('address', $company->address) }}" maxlength="255">
                                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label for="description" class="form-label">Descripción</label>
                                    <textarea id="description" name="description" rows="2"
                                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $company->description) }}</textarea>
                                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Logo --}}
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-image text-success"></i> Logo</h6></div>
                        <div class="card-body pt-2">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div style="width:72px; height:72px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; display:grid; place-items:center; overflow:hidden; flex-shrink:0;">
                                    @if($company->logo)
                                        <img src="{{ asset('storage/'.$company->logo) }}" alt="{{ $company->name }}" style="width:100%; height:100%; object-fit:cover;">
                                    @else
                                        <i class="bi bi-shop text-muted fs-3"></i>
                                    @endif
                                </div>
                                <div class="small text-muted">
                                    Aparece en el menú lateral junto al nombre del salón.<br>
                                    JPG, PNG, WEBP o SVG. Máx. 2 MB.
                                </div>
                            </div>
                            <input type="file" id="logo" name="logo" accept="image/*"
                                   class="form-control no-uppercase @error('logo') is-invalid @enderror">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($company->logo)
                                <small class="text-muted d-block mt-2">Subir una imagen reemplaza el logo actual.</small>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Colores --}}
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-palette text-warning"></i> Colores base</h6></div>
                        <div class="card-body pt-2">
                            @include('system.companies.partials.colors', [
                                'primaryColor'   => old('primary_color', $company->primary_color ?? '#1e1e1e'),
                                'secondaryColor' => old('secondary_color', $company->secondary_color ?? '#f8f8f8'),
                            ])
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Los cambios de color se ven al guardar y recargar.</small>
                        </div>
                    </div>
                </div>

                {{-- Suscripción (solo lectura: se gestiona desde Plataforma) --}}
                @if($subscription)
                    <div class="col-12">
                        <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 mb-0">
                            <div class="small">
                                <i class="bi bi-card-checklist me-1"></i>
                                <strong>Plan:</strong> {{ $subscription->plan?->name ?? 'Sin plan asignado' }}
                                · Estado: @include('system.subscriptions._status', ['status' => $subscription->computedStatus()])
                            </div>
                            @if(auth()->user()->is_super_admin)
                                <a href="{{ route('subscriptions.show', $company) }}" class="btn btn-sm btn-outline-primary">Gestionar suscripción</a>
                            @else
                                <span class="text-muted small">Para cambiar de plan, contacta al administrador de la plataforma.</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Guardar cambios</button>
            </div>
        </form>
    @endif
</div>
@endsection
