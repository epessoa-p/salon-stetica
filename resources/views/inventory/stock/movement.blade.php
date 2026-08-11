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
                    <label class="form-label">Producto <span class="text-danger">*</span></label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Seleccionar producto</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ (string) old('product_id', $selectedProduct) === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }} (stock: {{ rtrim(rtrim(number_format($p->current_stock, 2), '0'), '.') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Ajuste = fija el stock al valor indicado.</small>
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
@endsection
