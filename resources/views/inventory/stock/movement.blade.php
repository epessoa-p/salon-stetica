@extends('layouts.app')

@section('title', 'Movimiento de stock')

@section('page')
<div class="container-fluid" style="max-width: 620px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-down-up"></i> Movimiento de stock</h1>
            <p class="text-muted mb-0">Entrada, salida o ajuste de inventario.</p>
        </div>
        <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('stock.movement.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Almacén <span class="text-danger">*</span></label>
                    <select name="warehouse_id" id="mv-warehouse" class="form-select" required>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string) old('warehouse_id', $defaultWarehouse) === (string) $w->id ? 'selected' : '' }}>{{ $w->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Producto <span class="text-danger">*</span></label>
                    <select name="product_id" id="mv-product" class="form-select" required>
                        <option value="">Seleccionar producto</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ (string) old('product_id', $selectedProduct) === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }} (total: {{ rtrim(rtrim(number_format($p->current_stock, 2), '0'), '.') }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted" id="mv-available"></small>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Ajuste = fija el stock de ese almacén al valor indicado (conteo físico). Para pasar productos a otra sucursal usa Traspasos.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="quantity" class="form-control no-uppercase" value="{{ old('quantity') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Costo unitario (opcional)</label>
                        <input type="number" step="0.01" min="0" name="unit_cost" class="form-control no-uppercase" value="{{ old('unit_cost') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> Registrar movimiento</button>
                    <a href="{{ route('stock.index') }}" class="btn btn-light border">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
    // Existencia del producto en el almacén elegido.
    (function () {
        var stocks = @json($stocks), w = document.getElementById('mv-warehouse'), p = document.getElementById('mv-product'), out = document.getElementById('mv-available');
        var show = function () {
            if (!p.value) { out.textContent = ''; return; }
            var q = (stocks[p.value] || {})[w.value] || 0;
            out.textContent = 'En este almacén: ' + (Math.round(q * 100) / 100);
        };
        w.addEventListener('change', show); p.addEventListener('change', show); show();
    })();
</script>
@endpush
@endsection
