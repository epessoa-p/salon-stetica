@extends('layouts.app')

@section('title', $combo->exists ? 'Editar combo' : 'Nuevo combo')

@section('page')
@php
    $currency = \App\Models\CompanySetting::current()->currency_symbol;
    $rows = old('items', $combo->items->map(fn ($i) => [
        'ref' => ($i->product_id ? 'product:' . $i->product_id : 'service:' . $i->service_id),
        'quantity' => (float) $i->quantity,
    ])->all());
@endphp
<div class="container-fluid" style="max-width: 960px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-collection"></i> {{ $combo->exists ? 'Editar combo' : 'Nuevo combo' }}</h1>
            <p class="text-muted mb-0">Se cobra como una línea en el punto de venta; el sistema lo desarma en sus componentes para el stock, las comisiones y los reportes.</p>
        </div>
        <a href="{{ route('combos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $combo->exists ? route('combos.update', $combo) : route('combos.store') }}" method="POST" id="combo-form">
        @csrf
        @if($combo->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="f-name">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="f-name" class="form-control" maxlength="120" required value="{{ old('name', $combo->name) }}" placeholder="Ej. NOVIA">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="f-price">Precio del combo <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currency }}</span>
                                    <input type="number" name="price" id="f-price" class="form-control no-uppercase" min="0" step="0.5" required value="{{ old('price', $combo->price ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="f-desc">Descripción</label>
                                <textarea name="description" id="f-desc" rows="2" class="form-control" maxlength="1000">{{ old('description', $combo->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-list-check text-primary"></i> Incluye</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-row"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                    <div class="card-body pt-2">
                        <div id="rows"></div>
                        <div class="text-muted small" id="rows-empty">Agrega los servicios y productos del combo.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-semibold mb-2">Resumen</div>
                        <div class="d-flex justify-content-between small"><span>Por separado</span><span id="pv-list">—</span></div>
                        <div class="d-flex justify-content-between fw-bold fs-5"><span>Combo</span><span id="pv-price">—</span></div>
                        <div class="small mt-1" id="pv-saving"></div>

                        <div class="small text-muted text-uppercase fw-semibold mt-3 mb-1">Así se reparte el precio</div>
                        <table class="table table-sm small mb-0"><tbody id="pv-split"></tbody></table>
                        <p class="small text-muted mt-2 mb-0">El precio se reparte según el precio de lista de cada componente. Es la base de la comisión de cada profesional.</p>

                        <hr>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="f-active" @checked(old('active', $combo->active))>
                            <label class="form-check-label" for="f-active">A la venta en el punto de venta</label>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1" for="f-sort">Orden en el catálogo</label>
                            <input type="number" name="sort_order" id="f-sort" class="form-control form-control-sm no-uppercase" min="0" max="999" value="{{ old('sort_order', $combo->sort_order ?? 0) }}" style="max-width: 100px;">
                        </div>
                        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-lg"></i> {{ $combo->exists ? 'Guardar cambios' : 'Crear combo' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="row-tpl">
    <div class="d-flex gap-2 mb-2 align-items-center combo-row">
        <select class="form-select form-select-sm" data-name="ref">
            <option value="">Servicio o producto...</option>
            @foreach($options->groupBy('group') as $group => $opts)
                <optgroup label="{{ $group }}">
                    @foreach($opts as $o)<option value="{{ $o['ref'] }}">{{ $o['name'] }}</option>@endforeach
                </optgroup>
            @endforeach
        </select>
        <input type="number" class="form-control form-control-sm no-uppercase" data-name="quantity" min="0.01" max="999" step="1" value="1" style="width: 90px;" title="Cantidad">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Quitar"><i class="bi bi-x"></i></button>
    </div>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const currency = @json($currency);
    const prices = @json($options->pluck('price', 'ref'));
    const names = @json($options->pluck('name', 'ref'));
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
        const rows = [...box.querySelectorAll('.combo-row')]
            .map((r) => ({ ref: r.querySelector('select').value, qty: parseFloat(r.querySelector('input').value) || 0 }))
            .filter((r) => r.ref && r.qty > 0);
        const price = parseFloat($('f-price').value) || 0;
        const list = rows.reduce((s, r) => s + (prices[r.ref] || 0) * r.qty, 0);

        $('rows-empty').classList.toggle('d-none', box.children.length > 0);
        $('pv-list').textContent = money(list);
        $('pv-price').textContent = money(price);
        $('pv-saving').innerHTML = list > price && list > 0
            ? `<span class="text-success">El cliente ahorra ${money(list - price)} (${Math.round(100 * (list - price) / list)} %)</span>`
            : (list > 0 && price > list ? '<span class="text-warning">El combo cuesta más que por separado.</span>' : '');

        const weights = rows.map((r) => (prices[r.ref] || 0) * r.qty);
        const total = weights.reduce((a, b) => a + b, 0) || rows.reduce((s, r) => s + r.qty, 0) || 1;
        $('pv-split').innerHTML = rows.map((r, k) => {
            const w = weights.reduce((a, b) => a + b, 0) ? weights[k] : r.qty;
            return `<tr><td>${r.qty !== 1 ? r.qty + ' × ' : ''}${esc(names[r.ref] || '')}</td><td class="text-end">${money(price * w / total)}</td></tr>`;
        }).join('') || '<tr><td class="text-muted">—</td></tr>';
    }

    (@json(array_values($rows))).forEach((r) => addRow(r));
    if (!box.children.length) { addRow(); addRow(); }
    $('add-row').addEventListener('click', () => addRow());
    box.addEventListener('click', (e) => { if (e.target.closest('[data-remove]')) { e.target.closest('.combo-row').remove(); preview(); } });
    $('combo-form').addEventListener('input', preview);
    $('combo-form').addEventListener('change', preview);
    preview();
})();
</script>
@endpush
