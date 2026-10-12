@extends('layouts.app')

@section('title', $rule->exists ? 'Editar regla' : 'Nueva regla')

@section('page')
<div class="container-fluid" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-sliders"></i> {{ $rule->exists ? 'Editar regla' : 'Nueva regla' }}</h1>
            <p class="text-muted mb-0">Los cambios se aplican a las ventas que se cobren desde ahora; lo ya devengado no cambia.</p>
        </div>
        <a href="{{ route('commission-rules.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ $rule->exists ? route('commission-rules.update', $rule) : route('commission-rules.store') }}" method="POST">
                @csrf
                @if($rule->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">A quién</label>
                        <select name="personal_id" class="form-select">
                            <option value="">Todo el equipo</option>
                            @foreach($specialists as $sp)
                                <option value="{{ $sp->id }}" @selected(old('personal_id', $rule->personal_id) == $sp->id)>{{ $sp->full_name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Una regla de un profesional siempre gana a una del equipo.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sobre qué <span class="text-danger">*</span></label>
                        <select name="applies_to" id="applies_to" class="form-select" required>
                            @foreach(\App\Models\CommissionRule::APPLIES_TO as $code => [$label])
                                @if($code !== 'products' || $sellsProducts || $rule->applies_to === 'products')
                                    <option value="{{ $code }}" @selected(old('applies_to', $rule->applies_to) === $code)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 target target-service">
                        <label class="form-label">Servicio <span class="text-danger">*</span></label>
                        <select name="service_id" class="form-select">
                            <option value="">Seleccionar...</option>
                            @foreach($services as $s)
                                <option value="{{ $s->id }}" @selected(old('service_id', $rule->service_id) == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 target target-category">
                        <label class="form-label">Categoría <span class="text-danger">*</span></label>
                        <select name="service_category_id" class="form-select">
                            <option value="">Seleccionar...</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('service_category_id', $rule->service_category_id) == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <div class="col-md-6">
                        <label class="form-label">Cómo se calcula <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select" required>
                            @foreach(\App\Models\CommissionRule::TYPES as $code => $label)
                                <option value="{{ $code }}" @selected(old('type', $rule->type) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="value" id="value" class="form-control no-uppercase" step="0.01" min="0"
                                   value="{{ old('value', $rule->value) }}" required>
                            <span class="input-group-text" id="value-suffix">%</span>
                        </div>
                        <small class="text-muted" id="value-help"></small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Vigente desde</label>
                        <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $rule->valid_from?->toDateString()) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vigente hasta</label>
                        <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $rule->valid_until?->toDateString()) }}">
                        <small class="text-muted">Vacío = sin límite. Sirve para programar un cambio de porcentaje.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Para recordar el porqué de la regla">{{ old('notes', $rule->notes) }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $rule->active))>
                            <label class="form-check-label" for="active">Activa</label>
                        </div>
                        <small class="text-muted">Una regla inactiva se conserva pero no se aplica.</small>
                    </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('commission-rules.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ $rule->exists ? 'Guardar cambios' : 'Crear regla' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var appliesTo = document.getElementById('applies_to');
    var type = document.getElementById('type');
    var value = document.getElementById('value');
    var suffix = document.getElementById('value-suffix');
    var help = document.getElementById('value-help');
    var currency = @json(\App\Models\CompanySetting::current()->currency_symbol);

    function refreshTarget() {
        document.querySelectorAll('.target').forEach(function (el) {
            el.style.display = el.classList.contains('target-' + appliesTo.value) ? '' : 'none';
        });
    }

    function refreshType() {
        var percent = type.value === 'percent';
        suffix.textContent = percent ? '%' : currency;
        value.max = percent ? 100 : '';
        help.textContent = percent
            ? 'Porcentaje sobre el total de la línea (máximo 100).'
            : 'Importe por unidad vendida: 3 unidades pagan 3 veces este valor.';
    }

    appliesTo.addEventListener('change', refreshTarget);
    type.addEventListener('change', refreshType);
    refreshTarget();
    refreshType();
})();
</script>
@endpush
