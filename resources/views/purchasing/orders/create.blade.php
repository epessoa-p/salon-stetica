@extends('layouts.app')

@section('title', 'Nueva orden de compra')

@section('page')
<div class="container-fluid" style="max-width: 960px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-file-earmark-plus"></i> Nueva orden de compra</h1>
            <p class="text-muted mb-0">Registra una compra a un proveedor.</p>
        </div>
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ route('purchase-orders.store') }}" method="POST" id="po-form">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Seleccionar proveedor</option>
                            @foreach($suppliers as $sup)<option value="{{ $sup->id }}" {{ (string) old('supplier_id') === (string) $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>@endforeach
                        </select>
                        @if($suppliers->isEmpty())<small class="text-muted">No hay proveedores. <a href="{{ route('suppliers.create') }}">Crea uno</a>.</small>@endif
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="entry_date" class="form-control no-uppercase" value="{{ old('entry_date', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Notas</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pago</label>
                        <div class="d-flex gap-3 align-items-center flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_type" id="pt-contado" value="contado" @checked(old('payment_type', 'contado') === 'contado')>
                                <label class="form-check-label" for="pt-contado">Contado</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_type" id="pt-credito" value="credito" @checked(old('payment_type') === 'credito')>
                                <label class="form-check-label" for="pt-credito">A crédito</label>
                            </div>
                            <div class="input-group input-group-sm {{ old('payment_type') === 'credito' ? '' : 'd-none' }}" id="credit-days-wrap" style="width: 170px;">
                                <span class="input-group-text">Plazo</span>
                                <input type="number" name="credit_days" class="form-control no-uppercase" min="0" max="365" value="{{ old('credit_days', 30) }}">
                                <span class="input-group-text">días</span>
                            </div>
                        </div>
                        <div class="form-text">A crédito, al recibir la orden queda la deuda en Cuentas por pagar.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-9">
                        <label class="form-label">Producto</label>
                        <select id="product-picker" class="form-select">
                            <option value="">Seleccionar producto...</option>
                            @foreach($products as $p)<option value="{{ $p->id }}" data-name="{{ $p->name }}" data-cost="{{ $p->cost }}">{{ $p->name }} (stock: {{ rtrim(rtrim(number_format($p->current_stock, 2), '0'), '.') }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary w-100" id="btn-add"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Producto</th><th style="width:110px" class="text-center">Cantidad</th><th style="width:130px" class="text-end">Costo unit.</th><th style="width:130px" class="text-end">Total</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="items-body">
                            <tr id="empty-row"><td colspan="5" class="text-center text-muted py-4">Agrega productos a la orden.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end fw-bold">Total</td><td class="text-end fw-bold" id="lbl-total">0.00</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> Crear orden</button>
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Plazo de crédito: visible solo si la orden es a crédito.
    document.querySelectorAll('[name="payment_type"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('credit-days-wrap').classList.toggle('d-none', r.value !== 'credito' || !r.checked);
        });
    });
(function () {
    var idx = 0;
    var picker = document.getElementById('product-picker');
    var body = document.getElementById('items-body');
    var emptyRow = document.getElementById('empty-row');

    function money(n) { return (Math.round(n * 100) / 100).toFixed(2); }

    function recalc() {
        var total = 0;
        body.querySelectorAll('tr.item-row').forEach(function (row) {
            var qty = parseFloat(row.querySelector('.i-qty').value) || 0;
            var cost = parseFloat(row.querySelector('.i-cost').value) || 0;
            var lt = qty * cost;
            row.querySelector('.i-total').textContent = money(lt);
            total += lt;
        });
        document.getElementById('lbl-total').textContent = money(total);
    }

    function addItem(id, name, cost) {
        if (emptyRow) emptyRow.style.display = 'none';
        var i = idx++;
        var tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td>' + name + '<input type="hidden" name="items[' + i + '][product_id]" value="' + id + '"></td>' +
            '<td><input type="number" min="0.01" step="1" class="form-control form-control-sm text-center no-uppercase i-qty" name="items[' + i + '][quantity]" value="1"></td>' +
            '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end no-uppercase i-cost" name="items[' + i + '][unit_cost]" value="' + money(cost) + '"></td>' +
            '<td class="text-end i-total">' + money(cost) + '</td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger i-remove"><i class="bi bi-x"></i></button></td>';
        body.appendChild(tr);
        tr.querySelectorAll('input').forEach(function (inp) { inp.addEventListener('input', recalc); });
        tr.querySelector('.i-remove').addEventListener('click', function () { tr.remove(); recalc(); });
        recalc();
    }

    document.getElementById('btn-add').addEventListener('click', function () {
        var opt = picker.options[picker.selectedIndex];
        if (!opt || !opt.value) return;
        addItem(opt.value, opt.getAttribute('data-name'), parseFloat(opt.getAttribute('data-cost')) || 0);
        picker.value = '';
    });

    document.getElementById('po-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr.item-row').length === 0) {
            e.preventDefault();
            alert('Agrega al menos un producto a la orden.');
        }
    });
})();
</script>
@endpush
