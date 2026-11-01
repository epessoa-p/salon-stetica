@extends('layouts.app')

@section('title', 'Nuevo traspaso')

@section('page')
<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-left-right"></i> Nuevo traspaso</h1>
            <p class="text-muted mb-0">Los productos salen del origen y entran al destino en el momento.</p>
        </div>
        <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ route('stock-transfers.store') }}" method="POST" id="transfer-form">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Desde <span class="text-danger">*</span></label>
                        <select name="from_warehouse_id" id="tr-from" class="form-select" required>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((string) old('from_warehouse_id', $defaultFrom) === (string) $w->id)>{{ $w->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 text-center pb-2"><i class="bi bi-arrow-right fs-4 text-muted"></i></div>
                    <div class="col-md-5">
                        <label class="form-label">Hacia <span class="text-danger">*</span></label>
                        <select name="to_warehouse_id" id="tr-to" class="form-select" required>
                            <option value="">Seleccionar destino</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((string) old('to_warehouse_id') === (string) $w->id)>{{ $w->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-box"></i> Productos</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="tr-add"><i class="bi bi-plus-lg"></i> Agregar producto</button>
            </div>
            <div class="card-body pt-2">
                <div id="tr-lines"></div>
                <small class="text-muted">La cantidad no puede pasar lo que hay en el almacén de origen.</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Ej.: reposición semanal de la sucursal norte">
            </div>
        </div>

        <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check-lg"></i> Registrar traspaso</button>
        <a href="{{ route('stock-transfers.index') }}" class="btn btn-light border">Cancelar</a>
    </form>
</div>

<template id="tr-line">
    <div class="row g-2 align-items-center mb-2 tr-line">
        <div class="col-md-7">
            <select class="form-select tr-product" required>
                <option value="">Seleccionar producto</option>
                @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <input type="number" step="0.01" min="0.01" class="form-control no-uppercase tr-qty" placeholder="Cantidad" required>
        </div>
        <div class="col-4 col-md-1 small text-muted tr-have"></div>
        <div class="col-2 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger tr-remove" title="Quitar"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
    (function () {
        var stocks = @json($stocks), old = @json(old('items', []));
        var from = document.getElementById('tr-from'), lines = document.getElementById('tr-lines'), tpl = document.getElementById('tr-line');

        // Nombres items[i][campo] consecutivos para que el servidor reciba un arreglo limpio.
        var renumber = function () {
            lines.querySelectorAll('.tr-line').forEach(function (row, i) {
                row.querySelector('.tr-product').name = 'items[' + i + '][product_id]';
                row.querySelector('.tr-qty').name = 'items[' + i + '][quantity]';
            });
        };
        // Muestra lo disponible en el origen y lo pone como tope.
        var refresh = function (row) {
            var pid = row.querySelector('.tr-product').value, have = pid ? ((stocks[pid] || {})[from.value] || 0) : null;
            row.querySelector('.tr-have').textContent = have === null ? '' : 'Hay ' + (Math.round(have * 100) / 100);
            row.querySelector('.tr-qty').max = have === null ? '' : have;
        };
        var add = function (data) {
            var row = tpl.content.firstElementChild.cloneNode(true);
            if (data) { row.querySelector('.tr-product').value = data.product_id || ''; row.querySelector('.tr-qty').value = data.quantity || ''; }
            row.querySelector('.tr-product').addEventListener('change', function () { refresh(row); });
            row.querySelector('.tr-remove').addEventListener('click', function () { row.remove(); renumber(); });
            lines.appendChild(row);
            renumber();
            refresh(row);
        };

        document.getElementById('tr-add').addEventListener('click', function () { add(); });
        from.addEventListener('change', function () { lines.querySelectorAll('.tr-line').forEach(refresh); });
        (old.length ? old : [null]).forEach(add);
    })();
</script>
@endpush
