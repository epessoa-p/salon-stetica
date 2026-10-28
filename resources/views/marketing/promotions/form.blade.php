@extends('layouts.app')

@section('title', $promotion->exists ? 'Editar promoción' : 'Nueva promoción')

@section('page')
@php
    $currency = \App\Models\CompanySetting::current()->currency_symbol;
    $days = old('weekdays', $promotion->weekdayList());
@endphp
<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-percent"></i> {{ $promotion->exists ? 'Editar promoción' : 'Nueva promoción' }}</h1>
        <a href="{{ route('marketing.promotions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $promotion->exists ? route('marketing.promotions.update', $promotion) : route('marketing.promotions.store') }}" method="POST">
        @csrf
        @if($promotion->exists) @method('PUT') @endif
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="p-name">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="p-name" class="form-control" maxlength="120" required value="{{ old('name', $promotion->name) }}" placeholder="Ej. MARTES DE MANICURA">
                    <div class="form-text">Se muestra en la línea del punto de venta y en el ticket.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo</label>
                    <select name="discount_type" class="form-select">
                        <option value="percent" @selected(old('discount_type', $promotion->discount_type) === 'percent')>Porcentaje (%)</option>
                        <option value="amount" @selected(old('discount_type', $promotion->discount_type) === 'amount')>Monto por unidad ({{ $currency }})</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="p-value">Valor <span class="text-danger">*</span></label>
                    <input type="number" name="value" id="p-value" class="form-control no-uppercase" min="0.01" step="0.01" required value="{{ old('value', $promotion->value) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Aplica a</label>
                    <select name="applies_to" id="p-applies" class="form-select">
                        @foreach(\App\Models\Promotion::APPLIES as $k => $label)<option value="{{ $k }}" @selected(old('applies_to', $promotion->applies_to) === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6" id="p-service-wrap">
                    <label class="form-label">Servicio</label>
                    <select name="service_id" class="form-select">
                        <option value="">Elegir…</option>
                        @foreach($services as $s)<option value="{{ $s->id }}" @selected((string) old('service_id', $promotion->service_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6" id="p-category-wrap">
                    <label class="form-label">Categoría</label>
                    <select name="service_category_id" class="form-select">
                        <option value="">Elegir…</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}" @selected((string) old('service_category_id', $promotion->service_category_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label d-block">Días</label>
                    @foreach(\App\Models\Promotion::WEEKDAYS as $n => $label)
                        <input type="checkbox" class="btn-check" name="weekdays[]" value="{{ $n }}" id="wd-{{ $n }}" @checked(in_array($n, array_map('intval', (array) $days), true))>
                        <label class="btn btn-sm btn-outline-primary" for="wd-{{ $n }}">{{ $label }}</label>
                    @endforeach
                    <div class="form-text">Sin marcar = todos los días.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Desde</label>
                    <input type="date" name="starts_on" class="form-control" value="{{ old('starts_on', $promotion->starts_on?->toDateString()) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="ends_on" class="form-control" value="{{ old('ends_on', $promotion->ends_on?->toDateString()) }}">
                </div>
            </div>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" name="active" value="1" id="p-active" @checked(old('active', $promotion->active))>
                <label class="form-check-label" for="p-active">Activa</label>
            </div>
            <button class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> Guardar promoción</button>
        </div></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var a = document.getElementById('p-applies');
        var t = function () {
            document.getElementById('p-service-wrap').classList.toggle('d-none', a.value !== 'service');
            document.getElementById('p-category-wrap').classList.toggle('d-none', a.value !== 'category');
        };
        a.addEventListener('change', t); t();
    })();
</script>
@endpush
