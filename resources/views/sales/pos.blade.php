@extends('layouts.app')

@section('title', 'Punto de Venta')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-upc-scan"></i> Punto de Venta</h1>
            <p class="text-muted mb-0">Registra una venta de {{ $noCompany || !$sellsProducts ? 'servicios' : 'servicios y productos' }}.</p>
        </div>
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> Ventas</a>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> El punto de venta se usa dentro de una empresa.</div>
    @else
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @php
            // Vacío = la línea hereda el profesional principal de la venta.
            $specialistOptions = '<option value="">— Principal —</option>';
            foreach ($specialists as $sp) {
                $specialistOptions .= '<option value="'.$sp->id.'">'.e($sp->full_name).'</option>';
            }
        @endphp
        <form action="{{ route('sales.store') }}" method="POST" id="pos-form">
            @csrf
            <div class="row g-4">
                {{-- Ítems --}}
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-md-9">
                                    <label class="form-label">{{ $sellsProducts ? 'Servicio o producto' : 'Servicio' }}</label>
                                    <select id="item-picker" class="form-select">
                                        <option value="">Seleccionar...</option>
                                        <optgroup label="Servicios">
                                            @foreach($services as $s)
                                                <option value="{{ $s->id }}" data-kind="service" data-name="{{ $s->name }}" data-price="{{ $s->price }}">{{ $s->name }} ({{ number_format($s->price, 2) }})</option>
                                            @endforeach
                                        </optgroup>
                                        @if($sellsProducts && $products->count())
                                            <optgroup label="Productos">
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}" data-kind="product" data-name="{{ $p->name }}" data-price="{{ $p->price }}"
                                                            data-stock="{{ (float) $p->current_stock }}" @disabled((float) $p->current_stock <= 0)>
                                                        {{ $p->name }} ({{ number_format($p->price, 2) }}) · stock {{ rtrim(rtrim(number_format($p->current_stock, 2), '0'), '.') }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
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
                                            <th>Concepto</th>
                                            <th style="width:160px">Atendido por</th>
                                            <th style="width:90px" class="text-center">Cant.</th>
                                            <th style="width:110px" class="text-end">Precio</th>
                                            <th style="width:100px" class="text-end">Desc.</th>
                                            <th style="width:100px" class="text-end">Total</th>
                                            <th style="width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-body">
                                        <tr id="empty-row"><td colspan="7" class="text-center text-muted py-4">Agrega servicios{{ $sellsProducts ? ' o productos' : '' }} a la venta.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Las líneas con <em>— Principal —</em> se atribuyen al profesional principal de la venta.
                            </small>
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
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Nombre (si es ocasional)</label>
                                <input type="text" name="client_name" class="form-control" placeholder="Opcional" value="{{ old('client_name') }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Profesional principal</label>
                                <select name="personal_id" id="principal" class="form-select">
                                    <option value="">Sin asignar</option>
                                    @foreach($specialists as $sp)
                                        <option value="{{ $sp->id }}" @selected(old('personal_id') == $sp->id)>{{ $sp->full_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Recibe la propina y lo heredan las líneas sin profesional.</small>
                            </div>
                            @if($branches->count())
                                <div class="mb-2">
                                    <label class="form-label small">Sucursal</label>
                                    <select name="branch_id" class="form-select">
                                        <option value="">Sin sucursal</option>
                                        @foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>@endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-2">
                                <label class="form-label small">Caja (sesión abierta)</label>
                                <select name="cash_session_id" class="form-select">
                                    <option value="">No registrar en caja</option>
                                    @foreach($openSessions as $sess)
                                        <option value="{{ $sess->id }}" @selected(old('cash_session_id') == $sess->id)>{{ $sess->register?->name }}</option>
                                    @endforeach
                                </select>
                                @if($openSessions->isEmpty())<small class="text-muted">No hay cajas abiertas. <a href="{{ route('cash-sessions.create') }}">Abrir caja</a>.</small>@endif
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Método de pago <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-select" required>
                                    @foreach($paymentMethods as $code => $label)
                                        <option value="{{ $code }}" @selected(old('payment_method', $defaultPaymentMethod) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Notas</label>
                                <textarea name="notes" rows="1" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span id="lbl-subtotal">0.00</span></div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Descuento global</span>
                                <input type="number" step="0.01" min="0" name="global_discount" id="global-discount" class="form-control form-control-sm text-end no-uppercase" style="width:110px" value="{{ old('global_discount', 0) }}">
                            </div>
                            @if((float) $setting->tax_rate > 0)
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ $setting->tax_label }} ({{ rtrim(rtrim(number_format($setting->tax_rate, 2), '0'), '.') }}%){{ $setting->tax_included ? ' incl.' : '' }}</span>
                                    <span id="lbl-tax">0.00</span>
                                </div>
                            @endif
                            <hr>
                            <div class="d-flex justify-content-between mb-2"><span class="fw-bold fs-5">Total</span><span class="fw-bold fs-5"><small class="text-muted me-1">{{ $setting->currency_symbol }}</small><span id="lbl-total">0.00</span></span></div>

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted"><i class="bi bi-heart"></i> Propina</span>
                                <input type="number" step="0.01" min="0" name="tip" id="tip" class="form-control form-control-sm text-end no-uppercase" style="width:110px" value="{{ old('tip', 0) }}">
                            </div>
                            <small class="text-muted d-block mb-2">Va íntegra al profesional principal; no es venta del salón.</small>
                            <div class="d-flex justify-content-between mb-3 d-none" id="row-charge">
                                <span class="fw-semibold">A cobrar</span>
                                <span class="fw-semibold"><small class="text-muted me-1">{{ $setting->currency_symbol }}</small><span id="lbl-charge">0.00</span></span>
                            </div>

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
    var TAX_RATE = {{ (float) $setting->tax_rate }};
    var TAX_INCLUDED = {{ $setting->tax_included ? 'true' : 'false' }};
    var idx = 0;
    var picker = document.getElementById('item-picker');
    var body = document.getElementById('items-body');
    var emptyRow = document.getElementById('empty-row');
    var globalDiscount = document.getElementById('global-discount');
    var tipInput = document.getElementById('tip');
    var principal = document.getElementById('principal');

    function money(n) { return (Math.round(n * 100) / 100).toFixed(2); }
    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

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
        var gravado = Math.max(0, subtotal - lineDisc - gd);

        var tax = 0;
        if (TAX_RATE > 0) {
            tax = TAX_INCLUDED
                ? gravado - (gravado / (1 + TAX_RATE / 100))
                : gravado * TAX_RATE / 100;
        }
        var total = TAX_INCLUDED ? gravado : gravado + tax;
        var tip = parseFloat(tipInput.value) || 0;

        document.getElementById('lbl-subtotal').textContent = money(subtotal);
        var taxLabel = document.getElementById('lbl-tax');
        if (taxLabel) taxLabel.textContent = money(tax);
        document.getElementById('lbl-total').textContent = money(total);
        document.getElementById('lbl-charge').textContent = money(total + tip);
        document.getElementById('row-charge').classList.toggle('d-none', tip <= 0);
    }

    function addItem(kind, id, name, price, stock) {
        if (emptyRow) emptyRow.style.display = 'none';
        var i = idx++;
        var isProduct = kind === 'product';
        var idField = isProduct ? 'product_id' : 'service_id';
        var maxAttr = isProduct ? ' max="' + stock + '"' : '';
        var tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td>' + esc(name) +
                (isProduct ? ' <span class="badge bg-warning-subtle text-warning-emphasis">Producto</span>' : '') +
                '<input type="hidden" name="items[' + i + '][' + idField + ']" value="' + esc(id) + '">' +
                '<input type="hidden" name="items[' + i + '][description]" value="' + esc(name) + '"></td>' +
            '<td><select class="form-select form-select-sm" name="items[' + i + '][personal_id]">' + SPECIALIST_OPTIONS + '</select></td>' +
            '<td><input type="number" min="0.01" step="1"' + maxAttr + ' class="form-control form-control-sm text-center no-uppercase i-qty" name="items[' + i + '][quantity]" value="1"></td>' +
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
        addItem(
            opt.getAttribute('data-kind'),
            opt.value,
            opt.getAttribute('data-name'),
            parseFloat(opt.getAttribute('data-price')) || 0,
            parseFloat(opt.getAttribute('data-stock')) || 0
        );
        picker.value = '';
    });

    globalDiscount.addEventListener('input', recalc);
    tipInput.addEventListener('input', recalc);

    document.getElementById('pos-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr.item-row').length === 0) {
            e.preventDefault();
            alert('Agrega al menos un servicio o producto a la venta.');
            return;
        }
        if ((parseFloat(tipInput.value) || 0) > 0 && !principal.value) {
            e.preventDefault();
            alert('La propina es del profesional principal: selecciona quién atendió la venta.');
        }
    });
})();
</script>
@endpush
@endif
