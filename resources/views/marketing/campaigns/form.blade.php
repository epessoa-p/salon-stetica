@extends('layouts.app')

@section('title', 'Nueva campaña')

@section('page')
@php
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => ucfirst(\Illuminate\Support\Carbon::create(null, $m, 1)->locale('es')->isoFormat('MMMM'))]);
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-broadcast"></i> Nueva campaña</h1>
            <p class="text-muted mb-0">1) Elige a quiénes · 2) escribe el mensaje · 3) crea la campaña y envíala uno a uno.</p>
        </div>
        <a href="{{ route('marketing.campaigns.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="GET" action="{{ route('marketing.campaigns.create') }}" id="seg-form">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-funnel"></i> 1. ¿A quiénes?</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">No vienen hace más de</label>
                    <div class="input-group input-group-sm"><input type="number" name="inactive_days" class="form-control no-uppercase" min="1" value="{{ $filters['inactive_days'] ?? '' }}" placeholder="—"><span class="input-group-text">días</span></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Se hicieron el servicio</label>
                    <div class="input-group input-group-sm">
                        <select name="service_id" class="form-select">
                            <option value="">Cualquiera</option>
                            @foreach($services as $s)<option value="{{ $s->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                        <span class="input-group-text">en</span>
                        <input type="number" name="service_days" class="form-control no-uppercase" min="1" style="max-width: 80px;" value="{{ $filters['service_days'] ?? 365 }}">
                        <span class="input-group-text">días</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Membresía</label>
                    <select name="membership" class="form-select form-select-sm">
                        <option value="">Da igual</option>
                        <option value="with" @selected(($filters['membership'] ?? '') === 'with')>Con membresía vigente</option>
                        <option value="without" @selected(($filters['membership'] ?? '') === 'without')>Sin membresía</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Cumplen años en</label>
                    <select name="birthday_month" class="form-select form-select-sm">
                        <option value="">Cualquier mes</option>
                        @foreach($months as $n => $m)<option value="{{ $n }}" @selected((string) ($filters['birthday_month'] ?? '') === (string) $n)>{{ $m }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Gastaron al menos</label>
                    <input type="number" name="min_spent" class="form-control form-control-sm no-uppercase" min="0" step="0.01" value="{{ $filters['min_spent'] ?? '' }}" placeholder="—">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <input type="hidden" name="name" value="{{ $name }}"><input type="hidden" name="body" value="{{ $body }}"><input type="hidden" name="coupon_id" value="{{ $couponId }}">
                    <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-arrow-repeat"></i> Ver cuántos son</button>
                </div>
            </div>
            <div class="alert alert-light border mt-3 mb-0 d-flex justify-content-between flex-wrap gap-2">
                <span><strong>{{ $segment }}.</strong></span>
                <span class="fs-5 fw-bold">{{ $count }} cliente(s)</span>
            </div>
            @if($sample->isNotEmpty())
                <div class="small text-muted mt-2">Por ejemplo: {{ $sample->pluck('full_name')->implode(', ') }}{{ $count > $sample->count() ? '…' : '' }}</div>
            @endif
        </div></div>
    </form>

    <form method="POST" action="{{ route('marketing.campaigns.store') }}">
        @csrf
        @foreach($filters as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <h6 class="fw-bold"><i class="bi bi-chat-text"></i> 2. El mensaje</h6>
                    <label class="form-label small" for="c-name">Nombre de la campaña <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="c-name" class="form-control mb-2" maxlength="120" required value="{{ old('name', $name) }}" placeholder="Ej. TE EXTRAÑAMOS OCTUBRE">
                    <label class="form-label small" for="c-template">Partir de una plantilla</label>
                    <select id="c-template" class="form-select form-select-sm mb-2">
                        <option value="">—</option>
                        @foreach($templates as $t)<option value="{{ $t->body }}">{{ $t->name }}</option>@endforeach
                    </select>
                    <label class="form-label small" for="c-body">Mensaje <span class="text-danger">*</span></label>
                    <textarea name="body" id="c-body" rows="5" class="form-control no-uppercase" maxlength="1000" required>{{ old('body', $body) }}</textarea>
                    <div class="form-text">Variables: {nombre}, {cliente}, {salon}, {cupon}, {descuento}.</div>
                    <label class="form-label small mt-2" for="c-coupon">Cupón ({cupon})</label>
                    <select name="coupon_id" id="c-coupon" class="form-select form-select-sm">
                        <option value="">Sin cupón</option>
                        @foreach($coupons as $c)<option value="{{ $c->id }}" data-code="{{ $c->code }}" data-value="{{ $c->valueLabel() }}" @selected((string) old('coupon_id', $couponId) === (string) $c->id)>{{ $c->code }} · {{ $c->valueLabel() }}</option>@endforeach
                    </select>
                    <button class="btn btn-primary mt-3" @disabled($count === 0)><i class="bi bi-send"></i> 3. Crear campaña para {{ $count }} cliente(s)</button>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm"><div class="card-body" style="background: #ece5dd; border-radius: inherit;">
                    <div class="small text-muted mb-2">Así le llega a «María»</div>
                    <div id="c-preview" style="background: #dcf8c6; border-radius: 10px 10px 10px 2px; padding: .6rem .75rem; white-space: pre-line;"></div>
                </div></div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.getElementById('c-body');
    const coupon = document.getElementById('c-coupon');
    const salon = @json(auth()->user()->getCurrentCompany()?->name);
    const preview = () => {
        const opt = coupon.options[coupon.selectedIndex];
        const vars = { nombre: 'María', cliente: 'María López', salon, cupon: opt?.dataset.code || '', descuento: opt?.dataset.value || '' };
        document.getElementById('c-preview').textContent = body.value.replace(/\{(\w+)\}/g, (m, k) => vars[k] ?? '');
    };
    document.getElementById('c-template').addEventListener('change', (e) => { if (e.target.value) { body.value = e.target.value; preview(); } });
    body.addEventListener('input', preview);
    coupon.addEventListener('change', preview);
    // Al recalcular el segmento se conserva lo escrito.
    document.getElementById('seg-form').addEventListener('submit', (e) => {
        const f = e.target;
        f.querySelector('[name="name"]').value = document.getElementById('c-name').value;
        f.querySelector('[name="body"]').value = body.value;
        f.querySelector('[name="coupon_id"]').value = coupon.value;
    });
    preview();
})();
</script>
@endpush
