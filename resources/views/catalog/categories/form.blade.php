<div class="container-fluid" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $category ? 'Editar categoría' : 'Nueva categoría' }}</h1>
            <p class="text-muted mb-0">Categoría del catálogo de servicios.</p>
        </div>
        <a href="{{ route('service-categories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    @if(!$category && $companies->count() > 1)
                        <div class="col-md-5">
                            <label class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="{{ (!$category && $companies->count() > 1) ? 'col-md-7' : 'col-12' }}">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $category?->name) }}" required placeholder="Ej: Cabello, Uñas, Facial">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $category?->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $category?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Categoría activa</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $category ? 'Guardar cambios' : 'Crear categoría' }}</button>
            <a href="{{ route('service-categories.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
