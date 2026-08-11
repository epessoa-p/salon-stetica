<div class="container-fluid" style="max-width: 780px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $supplier ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
            <p class="text-muted mb-0">Datos del proveedor.</p>
        </div>
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    @if(!$supplier && $companies->count() > 1)
                        <div class="col-md-4">
                            <label class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)<option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="{{ (!$supplier && $companies->count() > 1) ? 'col-md-8' : 'col-md-7' }}">
                        <label class="form-label">Nombre / Razón social <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $supplier?->name) }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Contacto</label>
                        <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $supplier?->contact_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier?->phone) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control no-uppercase" value="{{ old('email', $supplier?->email) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NIT / RUC</label>
                        <input type="text" name="tax_id" class="form-control no-uppercase" value="{{ old('tax_id', $supplier?->tax_id) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address', $supplier?->address) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $supplier?->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $supplier?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Proveedor activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $supplier ? 'Guardar cambios' : 'Crear proveedor' }}</button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
