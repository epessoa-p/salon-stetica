<div class="container-fluid" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $plan ? 'Editar plan' : 'Nuevo plan' }}</h1>
            <p class="text-muted mb-0">Configura precio, ciclo de cobro, prueba y límites de uso.</p>
        </div>
        <a href="{{ route('plans.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-box-seam"></i> Datos del plan</h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $plan?->name) }}" required placeholder="Ej: Plan Pro">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control no-uppercase" value="{{ old('slug', $plan?->slug) }}" placeholder="se genera solo">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $plan?->description) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-cash-coin"></i> Cobro</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Precio <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control no-uppercase" value="{{ old('price', $plan?->price ?? '0.00') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Moneda</label>
                        <input type="text" name="currency" class="form-control" value="{{ old('currency', $plan?->currency ?? config('saas.currency')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ciclo (días) <span class="text-danger">*</span></label>
                        <input type="number" min="1" name="billing_days" class="form-control no-uppercase" value="{{ old('billing_days', $plan?->billing_days ?? 30) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Prueba (días) <span class="text-danger">*</span></label>
                        <input type="number" min="0" name="trial_days" class="form-control no-uppercase" value="{{ old('trial_days', $plan?->trial_days ?? config('saas.default_trial_days')) }}" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-sliders"></i> Límites de uso</h6>
                <p class="text-muted small mb-3">Deja el campo vacío para <strong>ilimitado</strong>.</p>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Sucursales</label>
                        <input type="number" min="0" name="max_branches" class="form-control no-uppercase" value="{{ old('max_branches', $plan?->max_branches) }}" placeholder="∞">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Personal</label>
                        <input type="number" min="0" name="max_personal" class="form-control no-uppercase" value="{{ old('max_personal', $plan?->max_personal) }}" placeholder="∞">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cajas</label>
                        <input type="number" min="0" name="max_cajas" class="form-control no-uppercase" value="{{ old('max_cajas', $plan?->max_cajas) }}" placeholder="∞">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Usuarios</label>
                        <input type="number" min="0" name="max_users" class="form-control no-uppercase" value="{{ old('max_users', $plan?->max_users) }}" placeholder="∞">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="form-label">Orden</label>
                        <input type="number" min="0" name="sort_order" class="form-control no-uppercase" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}">
                    </div>
                    <div class="col-md-9 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $plan?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Plan activo (disponible para asignar)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $plan ? 'Guardar cambios' : 'Crear plan' }}</button>
            <a href="{{ route('plans.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
