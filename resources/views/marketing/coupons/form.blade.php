@extends('layouts.app')

@section('title', $coupon->exists ? 'Editar cupón' : 'Nuevo cupón')

@section('page')
@php $currency = \App\Models\CompanySetting::current()->currency_symbol; @endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-ticket-perforated"></i> {{ $coupon->exists ? 'Editar cupón' : 'Nuevo cupón' }}</h1>
        <a href="{{ route('marketing.coupons.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $coupon->exists ? route('marketing.coupons.update', $coupon) : route('marketing.coupons.store') }}" method="POST">
        @csrf
        @if($coupon->exists) @method('PUT') @endif
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="c-code">Código <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="c-code" class="form-control fw-bold" maxlength="40" required value="{{ old('code', $coupon->code) }}" style="letter-spacing: .05em;">
                    <div class="form-text">Lo que el cliente dice o muestra. Sin espacios.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="c-name">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="c-name" class="form-control" maxlength="120" required value="{{ old('name', $coupon->name) }}" placeholder="Ej. CUMPLEAÑOS 15 %">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de descuento</label>
                    <select name="discount_type" id="c-type" class="form-select">
                        <option value="percent" @selected(old('discount_type', $coupon->discount_type) === 'percent')>Porcentaje (%)</option>
                        <option value="amount" @selected(old('discount_type', $coupon->discount_type) === 'amount')>Monto fijo ({{ $currency }})</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="c-value">Valor <span class="text-danger">*</span></label>
                    <input type="number" name="value" id="c-value" class="form-control no-uppercase" min="0.01" step="0.01" required value="{{ old('value', $coupon->value) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Aplica a</label>
                    <select name="applies_to" class="form-select">
                        @foreach(\App\Models\Coupon::APPLIES as $k => $label)<option value="{{ $k }}" @selected(old('applies_to', $coupon->applies_to) === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Compra mínima</label>
                    <input type="number" name="min_purchase" class="form-control no-uppercase" min="0" step="0.01" value="{{ old('min_purchase', $coupon->min_purchase) }}" placeholder="Sin mínimo">
                </div>
                <div class="col-md-4" id="c-max-wrap">
                    <label class="form-label">Descuento máximo</label>
                    <input type="number" name="max_discount" class="form-control no-uppercase" min="0" step="0.01" value="{{ old('max_discount', $coupon->max_discount) }}" placeholder="Sin tope">
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-4">
                    <label class="form-label">Desde</label>
                    <input type="date" name="starts_on" class="form-control" value="{{ old('starts_on', $coupon->starts_on?->toDateString()) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="ends_on" class="form-control" value="{{ old('ends_on', $coupon->ends_on?->toDateString()) }}">
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-4">
                    <label class="form-label">Usos en total</label>
                    <input type="number" name="max_uses" class="form-control no-uppercase" min="1" value="{{ old('max_uses', $coupon->max_uses) }}" placeholder="Ilimitado">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Usos por cliente</label>
                    <input type="number" name="max_uses_per_client" class="form-control no-uppercase" min="1" value="{{ old('max_uses_per_client', $coupon->max_uses_per_client) }}" placeholder="Ilimitado">
                    <div class="form-text">Si se limita, hay que elegir al cliente al cobrar.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Solo para el cliente N.º</label>
                    <input type="number" name="client_id" class="form-control no-uppercase" min="1" value="{{ old('client_id', $coupon->client_id) }}" placeholder="Cualquiera">
                    @if($coupon->client)<div class="form-text">{{ $coupon->client->full_name }}</div>@endif
                </div>
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <input type="text" name="notes" class="form-control" maxlength="255" value="{{ old('notes', $coupon->notes) }}">
                </div>
            </div>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" name="active" value="1" id="c-active" @checked(old('active', $coupon->active))>
                <label class="form-check-label" for="c-active">Activo</label>
            </div>
            <button class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> Guardar cupón</button>
        </div></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var type = document.getElementById('c-type');
        var toggle = function () { document.getElementById('c-max-wrap').classList.toggle('d-none', type.value !== 'percent'); };
        type.addEventListener('change', toggle); toggle();
        document.getElementById('c-code').addEventListener('input', function (e) { e.target.value = e.target.value.toUpperCase().replace(/\s+/g, ''); });
    })();
</script>
@endpush
