@extends('layouts.app')

@section('title', $package->exists ? 'Editar paquete' : 'Nuevo paquete')

@section('page')
@php
    $currency = \App\Models\CompanySetting::current()->currency_symbol;
    $rows = old('items', $package->items->map(fn ($i) => ['service_id' => $i->service_id, 'quantity' => $i->quantity])->all());
    $noExpiry = old('no_expiry', $package->exists && !$package->validity_days);
@endphp
<div class="container-fluid" style="max-width: 960px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-ticket-perforated"></i> {{ $package->exists ? 'Editar paquete' : 'Nuevo paquete' }}</h1>
            <p class="text-muted mb-0">{{ $package->exists ? 'Los cambios valen para las próximas ventas: lo ya vendido conserva sus condiciones.' : 'El cliente lo paga por adelantado y en cada visita se descuenta una sesión.' }}</p>
        </div>
        <a href="{{ route('packages.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $package->exists ? route('packages.update', $package) : route('packages.store') }}" method="POST" id="pkg-form">
        @csrf
        @if($package->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="f-name">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="f-name" class="form-control" maxlength="120" required value="{{ old('name', $package->name) }}" placeholder="Ej. 10 DEPILACIONES LÁSER">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="f-price">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currency }}</span>
                                    <input type="number" name="price" id="f-price" class="form-control no-uppercase" min="0" step="0.5" required value="{{ old('price', $package->price ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="f-days">Plazo para usarlo</label>
                                <div class="input-group">
                                    <input type="number" name="validity_days" id="f-days" class="form-control no-uppercase" min="1" max="3650" value="{{ old('validity_days', $package->validity_days) }}" @disabled($noExpiry)>
                                    <span class="input-group-text">días</span>
                                </div>
                                <div class="d-flex gap-1 flex-wrap mt-1">
                                    @foreach(\App\Models\ServicePackage::VALIDITIES as $days => $label)
                                        <button type="button" class="btn btn-sm btn-light border py-0" data-days="{{ $days }}">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check mt-md-3">
                                    <input class="form-check-input" type="checkbox" name="no_expiry" value="1" id="f-noexp" @checked($noExpiry)>
                                    <label class="form-check-label" for="f-noexp">No vence</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="f-desc">Descripción</label>
                                <textarea name="description" id="f-desc" rows="2" class="form-control" maxlength="1000">{{ old('description', $package->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-check2-square text-success"></i> Sesiones incluidas</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-row"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                    <div class="card-body pt-2">
                        <div id="rows"></div>
                        <div class="text-muted small" id="rows-empty">Agrega al menos un servicio.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-semibold mb-2">Resumen</div>
                        <div class="d-flex justify-content-between small"><span>Sesiones por separado</span><span id="pv-list">—</span></div>
                        <div class="d-flex justify-content-between fw-bold fs-5"><span>Paquete</span><span id="pv-price">—</span></div>
                        <div class="small mt-1" id="pv-saving"></div>

                        <div class="small text-muted text-uppercase fw-semibold mt-3 mb-1">Valor de cada sesión</div>
                        <table class="table table-sm small mb-0"><tbody id="pv-unit"></tbody></table>
                        <p class="small text-muted mt-2 mb-0">Al usarla, la línea se cobra en 0 y el profesional comisiona sobre este valor.</p>

                        <hr>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="f-active" @checked(old('active', $package->active))>
                            <label class="form-check-label" for="f-active">A la venta en el punto de venta</label>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1" for="f-sort">Orden en el catálogo</label>
                            <input type="number" name="sort_order" id="f-sort" class="form-control form-control-sm no-uppercase" min="0" max="999" value="{{ old('sort_order', $package->sort_order ?? 0) }}" style="max-width: 100px;">
                        </div>
                        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-lg"></i> {{ $package->exists ? 'Guardar cambios' : 'Crear paquete' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="row-tpl">
    <div class="d-flex gap-2 mb-2 align-items-center pkg-row">
        <select class="form-select form-select-sm" data-name="service_id">
            <option value="">Servicio...</option>
            @foreach($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <input type="number" class="form-control form-control-sm no-uppercase" data-name="quantity" min="1" max="999" value="1" style="width: 90px;" title="Sesiones">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Quitar"><i class="bi bi-x"></i></button>
    </div>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const currency = @json($currency);
    const prices = @json($services->pluck('price', 'id'));
    const names = @json($services->pluck('name', 'id'));
    const box = document.getElementById('rows');
    const tpl = document.getElementById('row-tpl');
    const $ = (id) => document.getElementById(id);
    const money = (n) => currency + ' ' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    let idx = 0;

    function addRow(data = {}) {
        const node = tpl.content.firstElementChild.cloneNode(true);
        const i = idx++;
        node.querySelectorAll('[data-name]').forEach((el) => {
            el.name = `items[${i}][${el.dataset.name}]`;
            if (data[el.dataset.name] !== undefined) el.value = data[el.dataset.name];
        });
        box.appendChild(node);
        preview();
    }

    function preview() {
        const rows = [...box.querySelectorAll('.pkg-row')]
            .map((r) => ({ id: r.querySelector('select').value, qty: parseInt(r.querySelector('input').value, 10) || 0 }))
            .filter((r) => r.id && r.qty > 0);
        const price = parseFloat($('f-price').value) || 0;
        const list = rows.reduce((s, r) => s + (Number(prices[r.id]) || 0) * r.qty, 0);
        const sessions = rows.reduce((s, r) => s + r.qty, 0);

        $('rows-empty').classList.toggle('d-none', box.children.length > 0);
        $('pv-list').textContent = money(list);
        $('pv-price').textContent = money(price);
        $('pv-saving').innerHTML = list > price && list > 0
            ? `<span class="text-success">El cliente ahorra ${money(list - price)} (${Math.round(100 * (list - price) / list)} %)</span>` : '';
        $('pv-unit').innerHTML = rows.map((r) => {
            const share = list > 0 ? price * (Number(prices[r.id]) || 0) * r.qty / list : price * r.qty / (sessions || 1);
            return `<tr><td>${esc(names[r.id] || '')}</td><td class="text-end">${r.qty} × ${money(share / r.qty)}</td></tr>`;
        }).join('') || '<tr><td class="text-muted">—</td></tr>';
    }

    (@json(array_values($rows))).forEach((r) => addRow(r));
    if (!box.children.length) addRow();
    $('add-row').addEventListener('click', () => addRow());
    box.addEventListener('click', (e) => { if (e.target.closest('[data-remove]')) { e.target.closest('.pkg-row').remove(); preview(); } });
    document.querySelectorAll('[data-days]').forEach((b) => b.addEventListener('click', () => {
        $('f-noexp').checked = false; $('f-days').disabled = false; $('f-days').value = b.dataset.days;
    }));
    $('f-noexp').addEventListener('change', (e) => { $('f-days').disabled = e.target.checked; });
    $('pkg-form').addEventListener('input', preview);
    $('pkg-form').addEventListener('change', preview);
    preview();
})();
</script>
@endpush
