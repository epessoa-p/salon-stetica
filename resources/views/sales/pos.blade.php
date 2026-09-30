@extends('layouts.app')

@section('title', 'Punto de Venta')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-upc-scan"></i> Punto de Venta</h1>
            <p class="text-muted mb-0">Registra una venta de servicios.</p>
        </div>
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> Ventas</a>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> El punto de venta se usa dentro de una empresa.</div>
    @else
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @php $specialistOptions = '<option value="">Sin asignar</option>'; foreach ($specialists as $sp) { $specialistOptions .= '<option value="'.$sp->id.'">'.e($sp->full_name).'</option>'; } @endphp
        <form action="{{ route('sales.store') }}" method="POST" id="pos-form">
            @csrf
            <div class="row g-4">
                {{-- Ítems --}}
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-md-9">
                                    <label class="form-label">Servicio</label>
                                    <select id="service-picker" class="form-select">
                                        <option value="">Seleccionar servicio...</option>
                                        @foreach($services as $s)
                                            <option value="{{ $s->id }}" data-name="{{ $s->name }}" data-price="{{ $s->price }}">{{ $s->name }} ({{ number_format($s->price, 2) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-primary w-100" id="btn-add-item"><i class="bi bi-plus-lg"></i> Agregar</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Servicio</th>
                                            <th style="width:160px">Atendido por</th>
                                            <th style="width:90px" class="text-center">Cant.</th>
                                            <th style="width:120px" class="text-end">Precio</th>
                                            <th style="width:120px" class="text-end">Desc.</th>
                                            <th style="width:120px" class="text-end">Total</th>
                                            <th style="width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-body">
                                        <tr id="empty-row"><td colspan="7" class="text-center text-muted py-4">Agrega servicios a la venta.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Datos y cobro --}}
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Cliente y pago</h6>
                            <div class="mb-2">
                                <label class="form-label small">Cliente</label>
                                <select name="client_id" class="form-select">
                                    <option value="">Cliente ocasional</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Nombre (si es ocasional)</label>
                                <input type="text" name="client_name" class="form-control" placeholder="Opcional">
                            </div>
                            @if($branches->count())
                                <div class="mb-2">
                                    <label class="form-label small">Sucursal</label>
                                    <select name="branch_id" class="form-select">
                                        <option value="">Sin sucursal</option>
                                        @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-2">
                                <label class="form-label small">Caja (sesión abierta)</label>
                                <select name="cash_session_id" class="form-select">
                                    <option value="">No registrar en caja</option>
                                    @foreach($openSessions as $sess)
                                        <option value="{{ $sess->id }}">{{ $sess->register?->name }}</option>
                                    @endforeach
                                </select>
                                @if($openSessions->isEmpty())<small class="text-muted">No hay cajas abiertas. <a href="{{ route('cash-sessions.create') }}">Abrir caja</a>.</small>@endif
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Método de pago <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="qr">QR</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Notas</label>
                                <textarea name="notes" rows="1" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span id="lbl-subtotal">0.00</span></div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Descuento global</span>
                                <input type="number" step="0.01" min="0" name="global_discount" id="global-discount" class="form-control form-control-sm text-end no-uppercase" style="width:110px" value="0">
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3"><span class="fw-bold fs-5">Total</span><span class="fw-bold fs-5" id="lbl-total">0.00</span></div>
                            <button class="btn btn-success w-100 btn-lg" type="submit"><i class="bi bi-cash-coin"></i> Cobrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@if(!$noCompany)
@push('scripts')
<script>
(function () {
    var SPECIALIST_OPTIONS = @json($specialistOptions);
    var idx = 0;
    var picker = document.getElementById('service-picker');
    var body = document.getElementById('items-body');
    var emptyRow = document.getElementById('empty-row');
    var globalDiscount = document.getElementById('global-discount');

    function money(n) { return (Math.round(n * 100) / 100).toFixed(2); }

    function recalc() {
        var subtotal = 0, lineDisc = 0;
        body.querySelectorAll('tr.item-row').forEach(function (row) {
            var qty = parseFloat(row.querySelector('.i-qty').value) || 0;
            var price = parseFloat(row.querySelector('.i-price').value) || 0;
            var disc = parseFloat(row.querySelector('.i-disc').value) || 0;
            var total = Math.max(0, qty * price - disc);
            row.querySelector('.i-total').textContent = money(total);
            subtotal += qty * price;
            lineDisc += disc;
        });
        var gd = parseFloat(globalDiscount.value) || 0;
        var total = Math.max(0, subtotal - lineDisc - gd);
        document.getElementById('lbl-subtotal').textContent = money(subtotal);
        document.getElementById('lbl-total').textContent = money(total);
    }

    function addItem(id, name, price) {
        if (emptyRow) emptyRow.style.display = 'none';
        var i = idx++;
        var tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td>' + name +
                '<input type="hidden" name="items[' + i + '][service_id]" value="' + (id || '') + '">' +
                '<input type="hidden" name="items[' + i + '][description]" value="' + name.replace(/"/g, '&quot;') + '"></td>' +
            '<td><select class="form-select form-select-sm" name="items[' + i + '][personal_id]">' + SPECIALIST_OPTIONS + '</select></td>' +
            '<td><input type="number" min="0.01" step="1" class="form-control form-control-sm text-center no-uppercase i-qty" name="items[' + i + '][quantity]" value="1"></td>' +
            '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end no-uppercase i-price" name="items[' + i + '][unit_price]" value="' + money(price) + '"></td>' +
            '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end no-uppercase i-disc" name="items[' + i + '][discount]" value="0"></td>' +
            '<td class="text-end i-total">' + money(price) + '</td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger i-remove"><i class="bi bi-x"></i></button></td>';
        body.appendChild(tr);
        tr.querySelectorAll('input').forEach(function (inp) { inp.addEventListener('input', recalc); });
        tr.querySelector('.i-remove').addEventListener('click', function () { tr.remove(); recalc(); });
        recalc();
    }

    document.getElementById('btn-add-item').addEventListener('click', function () {
        var opt = picker.options[picker.selectedIndex];
        if (!opt || !opt.value) return;
        addItem(opt.value, opt.getAttribute('data-name'), parseFloat(opt.getAttribute('data-price')) || 0);
        picker.value = '';
    });

    globalDiscount.addEventListener('input', recalc);

    document.getElementById('pos-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr.item-row').length === 0) {
            e.preventDefault();
            alert('Agrega al menos un servicio a la venta.');
        }
    });
})();
</script>
@endpush
@endif
