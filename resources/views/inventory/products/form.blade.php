<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $product ? 'Editar producto' : 'Nuevo producto' }}</h1>
            <p class="text-muted mb-0">Detalle del producto.</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-box"></i> Datos del producto</h6>
                <div class="row g-3">
                    @if(!$product && $companies->count() > 1)
                        <div class="col-md-4">
                            <label class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)<option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif

                    <div class="{{ (!$product && $companies->count() > 1) ? 'col-md-5' : 'col-md-7' }}">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $product?->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control no-uppercase" value="{{ old('sku', $product?->sku) }}" placeholder="Auto">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Categoría</label>
                        <select name="product_category_id" id="product_category_id" class="form-select" data-selected="{{ old('product_category_id', $product?->product_category_id) }}">
                            <option value="">Sin categoría</option>
                            @foreach($categories as $cat)<option value="{{ $cat->id }}" {{ (string) old('product_category_id', $product?->product_category_id) === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Marca</label>
                        <select name="brand_id" id="brand_id" class="form-select" data-selected="{{ old('brand_id', $product?->brand_id) }}">
                            <option value="">Sin marca</option>
                            @foreach($brands as $br)<option value="{{ $br->id }}" {{ (string) old('brand_id', $product?->brand_id) === (string) $br->id ? 'selected' : '' }}>{{ $br->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unidad</label>
                        <input type="text" name="unit" class="form-control" value="{{ old('unit', $product?->unit ?? 'unidad') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Costo</label>
                        <input type="number" step="0.01" min="0" name="cost" class="form-control no-uppercase" value="{{ old('cost', $product?->cost ?? '0.00') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Precio <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control no-uppercase" value="{{ old('price', $product?->price ?? '0.00') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stock mínimo</label>
                        <input type="number" step="0.01" min="0" name="min_stock" class="form-control no-uppercase" value="{{ old('min_stock', $product?->min_stock ?? '0') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stock {{ $product ? 'actual' : 'inicial' }}</label>
                        <input type="number" step="0.01" min="0" name="current_stock" class="form-control no-uppercase {{ $product ? 'bg-light' : '' }}" value="{{ old('current_stock', $product?->current_stock ?? '0') }}" {{ $product ? 'readonly' : '' }}>
                        @if($product)<small class="text-muted">Se ajusta desde Existencias.</small>@endif
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $product?->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $product?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Producto activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $product ? 'Guardar cambios' : 'Crear producto' }}</button>
            <a href="{{ route('products.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

@if(!$product && $companies->count() > 1)
@push('scripts')
<script>
$(function () {
    const $company = $('#company_id');
    const $cat = $('#product_category_id');
    const $brand = $('#brand_id');
    const urlTemplate = '{{ route('products.meta-by-company', ':id') }}';

    $company.on('change', function () {
        const companyId = $(this).val();
        $cat.empty().append(new Option('Sin categoría', ''));
        $brand.empty().append(new Option('Sin marca', ''));
        if (!companyId) return;
        $.getJSON(urlTemplate.replace(':id', companyId)).done(function (data) {
            (data.categories || []).forEach(function (c) { $cat.append(new Option(c.name, c.id)); });
            (data.brands || []).forEach(function (b) { $brand.append(new Option(b.name, b.id)); });
        });
    });
});
</script>
@endpush
@endif
