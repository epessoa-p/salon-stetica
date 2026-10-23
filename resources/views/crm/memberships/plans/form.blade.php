@extends('layouts.app')

@section('title', $plan->exists ? 'Editar plan' : 'Nuevo plan de membresía')

@section('page')
@php
    $currency = \App\Models\CompanySetting::current()->currency_symbol;
    $sessionRows = old('sessions', $plan->sessions->map(fn ($s) => ['service_id' => $s->service_id, 'quantity' => $s->quantity])->all());
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-award"></i> {{ $plan->exists ? 'Editar plan' : 'Nuevo plan de membresía' }}</h1>
            <p class="text-muted mb-0">{{ $plan->exists ? 'Los cambios valen para las próximas ventas: lo ya vendido conserva sus condiciones.' : 'Define precio, vigencia y beneficios. Se vende desde el punto de venta.' }}</p>
        </div>
        <a href="{{ route('memberships.plans.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $plan->exists ? route('memberships.plans.update', $plan) : route('memberships.plans.store') }}" method="POST">
        @csrf
        @if($plan->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-card-text text-primary"></i> Plan</h6></div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="f-name" class="form-control" maxlength="120" required value="{{ old('name', $plan->name) }}" placeholder="Ej. MENSUAL UÑAS">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currency }}</span>
                                    <input type="number" name="price" id="f-price" class="form-control no-uppercase" min="0" step="0.5" required value="{{ old('price', $plan->price ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vigencia <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="duration_days" id="f-days" class="form-control no-uppercase" min="1" max="3650" required value="{{ old('duration_days', $plan->duration_days) }}">
                                    <span class="input-group-text">días</span>
                                </div>
                                <div class="d-flex gap-1 flex-wrap mt-1">
                                    @foreach(\App\Models\MembershipPlan::DURATIONS as $days => $label)
                                        <button type="button" class="btn btn-sm btn-light border py-0" data-days="{{ $days }}">{{ explode(' ', $label)[0] }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea name="description" rows="2" class="form-control" maxlength="1000">{{ old('description', $plan->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-percent text-danger"></i> Descuento</h6></div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Porcentaje</label>
                                <div class="input-group">
                                    <input type="number" name="discount_percent" id="f-pct" class="form-control no-uppercase" min="0" max="100" step="0.5" value="{{ old('discount_percent', $plan->discount_percent ?? 0) }}">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">0 = sin descuento.</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Se aplica a</label>
                                <select name="discount_scope" id="f-scope" class="form-select">
                                    @foreach(\App\Models\MembershipPlan::SCOPES as $code => $label)
                                        <option value="{{ $code }}" @selected(old('discount_scope', $plan->discount_scope) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12" id="f-category-wrap">
                                <label class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select name="service_category_id" id="f-category" class="form-select">
                                    <option value="">Seleccionar...</option>
                                    @foreach($categories as $c)
                                        <option value="{{ $c->id }}" @selected(old('service_category_id', $plan->service_category_id) == $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-check2-square text-success"></i> Sesiones incluidas</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-session"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                    <div class="card-body pt-2">
                        <p class="small text-muted mb-2">Opcional. Se consumen en el punto de venta durante la vigencia y la línea se cobra en 0.</p>
                        <div id="sessions"></div>
                        <div class="text-muted small" id="sessions-empty">Sin sesiones incluidas.</div>
                    </div>
                </div>
            </div>

            {{-- Vista previa --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-semibold mb-2">Así lo verá la cajera</div>
                        <div class="p-3 rounded-3" style="background: color-mix(in srgb, #ec4899 7%, #fff); border: 1px solid color-mix(in srgb, #ec4899 25%, #fff);">
                            <div class="small" style="color: #db2777;"><i class="bi bi-award"></i> Membresía</div>
                            <div class="fw-bold" id="pv-name">—</div>
                            <div class="small text-muted" id="pv-days"></div>
                            <div class="fs-4 fw-bold mt-1" id="pv-price"></div>
                            <ul class="small mb-0 mt-2 ps-3" id="pv-benefits"></ul>
                        </div>
                        <p class="small text-muted mt-3 mb-0" id="pv-unit"></p>

                        <hr>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="f-active" @checked(old('active', $plan->active))>
                            <label class="form-check-label" for="f-active">A la venta en el punto de venta</label>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1">Orden en el catálogo</label>
                            <input type="number" name="sort_order" class="form-control form-control-sm no-uppercase" min="0" max="999" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" style="max-width: 100px;">
                        </div>
                        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-lg"></i> {{ $plan->exists ? 'Guardar cambios' : 'Crear plan' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="session-row">
    <div class="d-flex gap-2 mb-2 align-items-center session-row">
        <select class="form-select form-select-sm" data-name="service_id">
            <option value="">Servicio...</option>
            @foreach($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <input type="number" class="form-control form-control-sm no-uppercase" data-name="quantity" min="1" max="999" value="1" style="width: 90px;" title="Sesiones">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove><i class="bi bi-x"></i></button>
    </div>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const currency = @json($currency);
    const scopes = @json(\App\Models\MembershipPlan::SCOPES);
    const rows = @json(array_values($sessionRows));
    const box = document.getElementById('sessions');
    const tpl = document.getElementById('session-row');
    const $ = (id) => document.getElementById(id);
    let idx = 0;

    function addRow(data = {}) {
        const node = tpl.content.firstElementChild.cloneNode(true);
        const i = idx++;
        node.querySelectorAll('[data-name]').forEach((el) => {
            el.name = `sessions[${i}][${el.dataset.name}]`;
            if (data[el.dataset.name] !== undefined) el.value = data[el.dataset.name];
        });
        box.appendChild(node);
        preview();
    }

    function preview() {
        const pct = parseFloat($('f-pct').value) || 0;
        const scope = $('f-scope').value;
        const cat = $('f-category');
        const scopeText = scope === 'category'
            ? 'la categoría ' + (cat.value ? cat.options[cat.selectedIndex].text : '…')
            : scopes[scope].toLowerCase();
        const price = parseFloat($('f-price').value) || 0;

        $('f-category-wrap').classList.toggle('d-none', scope !== 'category');
        $('pv-name').textContent = $('f-name').value || 'Nombre del plan';
        $('pv-days').textContent = ($('f-days').value || 0) + ' días de vigencia';
        $('pv-price').textContent = currency + ' ' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const items = [];
        if (pct > 0) items.push(`${pct} % de descuento en ${scopeText}`);
        let total = 0;
        box.querySelectorAll('.session-row').forEach((row) => {
            const sel = row.querySelector('select');
            const qty = parseInt(row.querySelector('input').value, 10) || 0;
            if (sel.value && qty > 0) { items.push(`${qty} × ${sel.options[sel.selectedIndex].text}`); total += qty; }
        });
        $('pv-benefits').innerHTML = items.length
            ? items.map((t) => `<li>${t.replace(/</g, '&lt;')}</li>`).join('')
            : '<li class="text-danger">Sin beneficios: agrega un descuento o sesiones.</li>';

        $('sessions-empty').classList.toggle('d-none', box.children.length > 0);
        $('pv-unit').textContent = total > 0
            ? `Cada sesión vale ${currency} ${(price / total).toFixed(2)} (precio ÷ ${total} sesiones): es la base de la comisión del profesional que la realiza.`
            : '';
    }

    rows.forEach((r) => addRow(r));
    $('add-session').addEventListener('click', () => addRow());
    box.addEventListener('click', (e) => { if (e.target.closest('[data-remove]')) { e.target.closest('.session-row').remove(); preview(); } });
    document.querySelectorAll('[data-days]').forEach((b) => b.addEventListener('click', () => { $('f-days').value = b.dataset.days; preview(); }));
    document.querySelector('form').addEventListener('input', preview);
    document.querySelector('form').addEventListener('change', preview);
    preview();
})();
</script>
@endpush
