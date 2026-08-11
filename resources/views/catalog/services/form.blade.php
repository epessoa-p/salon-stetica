<div class="container-fluid" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $service ? 'Editar servicio' : 'Nuevo servicio' }}</h1>
            <p class="text-muted mb-0">Detalle del servicio del salón.</p>
        </div>
        <a href="{{ route('services.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-scissors"></i> Datos del servicio</h6>
                <div class="row g-3">
                    @if(!$service && $companies->count() > 1)
                        <div class="col-md-4">
                            <label class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="{{ (!$service && $companies->count() > 1) ? 'col-md-8' : 'col-md-7' }}">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $service?->name) }}" required placeholder="Ej: Corte de cabello">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Categoría</label>
                        <select name="service_category_id" id="service_category_id" class="form-select"
                                data-selected="{{ old('service_category_id', $service?->service_category_id) }}">
                            <option value="">{{ (!$service && $companies->count() > 1 && $categories->isEmpty()) ? 'Selecciona primero una empresa' : 'Sin categoría' }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (string) old('service_category_id', $service?->service_category_id) === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Precio <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control no-uppercase" value="{{ old('price', $service?->price ?? '0.00') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duración (min)</label>
                        <input type="number" min="0" name="duration_minutes" class="form-control no-uppercase" value="{{ old('duration_minutes', $service?->duration_minutes) }}" placeholder="Ej: 45">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Comisión (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="commission_percent" class="form-control no-uppercase" value="{{ old('commission_percent', $service?->commission_percent) }}" placeholder="Ej: 10">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $service?->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $service?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Servicio activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $service ? 'Guardar cambios' : 'Crear servicio' }}</button>
            <a href="{{ route('services.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

@if(!$service && $companies->count() > 1)
@push('scripts')
<script>
$(function () {
    const $company = $('#company_id');
    const $category = $('#service_category_id');
    const urlTemplate = '{{ route('services.categories-by-company', ':id') }}';

    $company.on('change', function () {
        const companyId = $(this).val();
        if (!companyId) {
            $category.empty().append(new Option('Selecciona primero una empresa', ''));
            return;
        }
        $category.prop('disabled', true).empty().append(new Option('Cargando...', ''));
        $.getJSON(urlTemplate.replace(':id', companyId))
            .done(function (cats) {
                $category.empty().append(new Option('Sin categoría', ''));
                cats.forEach(function (c) { $category.append(new Option(c.name, c.id)); });
            })
            .fail(function () { $category.empty().append(new Option('Error al cargar', '')); })
            .always(function () { $category.prop('disabled', false); });
    });
});
</script>
@endpush
@endif
