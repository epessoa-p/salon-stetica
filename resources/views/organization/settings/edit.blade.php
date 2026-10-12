@extends('layouts.app')

@section('title', 'Preferencias')

@section('page')
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-sliders"></i> Preferencias</h1>
            <p class="text-muted mb-0">Moneda, impuestos, datos de facturación y formato de folios{{ $company ? ' de ' . $company->name : '' }}.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if(auth()->user()->is_super_admin)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('settings.edit') }}" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Empresa a configurar</label>
                        <select name="company_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Seleccionar empresa...</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" @selected($company && $company->id === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if(!$company)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i> Selecciona una empresa para ver y editar sus preferencias.
        </div>
    @else
    <form action="{{ route('settings.update') }}" method="POST">
        @csrf @method('PUT')
        <input type="hidden" name="company_id" value="{{ $company->id }}">

        <div class="row g-4">
            {{-- Moneda e impuestos --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-cash-stack text-success"></i> Moneda e impuestos</h6></div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label small">Moneda</label>
                                <select name="currency" id="currency" class="form-select">
                                    @foreach($currencies as $code => $info)
                                        <option value="{{ $code }}" data-symbol="{{ $info[1] }}" @selected(old('currency', $setting->currency) === $code)>{{ $code }} — {{ $info[0] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Símbolo</label>
                                <input type="text" name="currency_symbol" id="currency_symbol" class="form-control no-uppercase" maxlength="10" value="{{ old('currency_symbol', $setting->currency_symbol) }}">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Nombre del impuesto</label>
                                <input type="text" name="tax_label" class="form-control" maxlength="30" value="{{ old('tax_label', $setting->tax_label) }}" placeholder="IVA">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Tasa (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control no-uppercase" value="{{ old('tax_rate', $setting->tax_rate) }}">
                                <small class="text-muted">0 = sin impuesto.</small>
                            </div>
                            <div class="col-md-3 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tax_included" value="1" id="tax_included" @checked(old('tax_included', $setting->tax_included))>
                                    <label class="form-check-label small" for="tax_included">Incluido en el precio</label>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0 small">
                            <i class="bi bi-info-circle"></i> Con <strong>incluido</strong>, el precio del servicio ya contiene el impuesto y la venta solo lo desglosa.
                            Sin él, el impuesto se suma al total.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Folios y operación --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-upc text-primary"></i> Folios y operación</h6></div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small">Prefijo de venta</label>
                                <input type="text" name="sale_prefix" id="sale_prefix" class="form-control" maxlength="10" value="{{ old('sale_prefix', $setting->sale_prefix) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Dígitos</label>
                                <input type="number" min="1" max="12" name="sale_padding" id="sale_padding" class="form-control no-uppercase" value="{{ old('sale_padding', $setting->sale_padding) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Ejemplo</label>
                                <div class="form-control bg-light" id="folio-preview">—</div>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small">Duración base de cita (min)</label>
                                <input type="number" min="5" max="240" step="5" name="appointment_slot_minutes" class="form-control no-uppercase" value="{{ old('appointment_slot_minutes', $setting->appointment_slot_minutes) }}">
                            </div>
                            <div class="col-md-5 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_cash_session" value="1" id="require_cash_session" @checked(old('require_cash_session', $setting->require_cash_session))>
                                    <label class="form-check-label small" for="require_cash_session">Exigir caja abierta para vender</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Facturación --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-receipt text-warning"></i> Datos de facturación</h6></div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Razón social</label>
                                <input type="text" name="invoice_business_name" class="form-control" maxlength="255" value="{{ old('invoice_business_name', $setting->invoice_business_name) }}" placeholder="{{ $company->name }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">NIT / RUC</label>
                                <input type="text" name="invoice_nit" class="form-control" maxlength="30" value="{{ old('invoice_nit', $setting->invoice_nit) }}" placeholder="{{ $company->ruc }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Teléfono</label>
                                <input type="text" name="invoice_phone" class="form-control" maxlength="40" value="{{ old('invoice_phone', $setting->invoice_phone) }}" placeholder="{{ $company->phone }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Dirección</label>
                                <input type="text" name="invoice_address" class="form-control" maxlength="255" value="{{ old('invoice_address', $setting->invoice_address) }}" placeholder="{{ $company->address }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Pie del comprobante</label>
                                <textarea name="invoice_footer" rows="2" class="form-control" maxlength="500" placeholder="¡Gracias por tu visita!">{{ old('invoice_footer', $setting->invoice_footer) }}</textarea>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Si dejas un campo vacío se usan los datos de la empresa.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Guardar preferencias</button>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var currency = document.getElementById('currency');
    var symbol = document.getElementById('currency_symbol');
    if (currency && symbol) {
        currency.addEventListener('change', function () {
            var s = this.options[this.selectedIndex].getAttribute('data-symbol');
            if (s) symbol.value = s;
        });
    }

    var prefix = document.getElementById('sale_prefix');
    var padding = document.getElementById('sale_padding');
    var preview = document.getElementById('folio-preview');

    function renderFolio() {
        var n = Math.min(12, Math.max(1, parseInt(padding.value, 10) || 1));
        preview.textContent = (prefix.value || '') + String(1).padStart(n, '0');
    }

    if (prefix && padding && preview) {
        prefix.addEventListener('input', renderFolio);
        padding.addEventListener('input', renderFolio);
        renderFolio();
    }
})();
</script>
@endpush
