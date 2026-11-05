@extends('layouts.app')

@section('title', 'Nuevo ' . $singular)

@section('page')
<div class="container-fluid" style="max-width: 640px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi {{ $icon }}"></i> Nuevo {{ $singular }}</h1>
            <p class="text-muted mb-0">Registra un {{ $singular }} del salon.</p>
        </div>
        <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route($storeRoute) }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Monto <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control no-uppercase" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="movement_date" class="form-control no-uppercase" value="{{ old('movement_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Concepto <span class="text-danger">*</span></label>
                        <input type="text" name="concept" class="form-control" value="{{ old('concept') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria</label>
                        <select name="category" id="m-category" class="form-select">
                            <option value="">Sin categoria</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Metodo de pago <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            @foreach(\App\Models\PaymentMethod::optionsFor() as $code => $label)
                                <option value="{{ $code }}" {{ old('payment_method') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="who-supplier">
                        <label class="form-label">Proveedor</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">Sin especificar</option>
                            @foreach($suppliers as $sup)<option value="{{ $sup->id }}" @selected((string) old('supplier_id') === (string) $sup->id)>{{ $sup->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="who-personal">
                        <label class="form-label">Personal</label>
                        <select name="personal_id" class="form-select">
                            <option value="">Sin especificar</option>
                            @foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('personal_id') === (string) $p->id)>{{ $p->full_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="reference" class="form-control no-uppercase" value="{{ old('reference') }}">
                    </div>
                    <div class="col-12">
                        @if($type === 'egreso')
                            @include('finance.partials.payment_source', ['sources' => $sources, 'amountInput' => 'input[name=amount]', 'selectedAccount' => $selectedAccount])
                        @else
                            @include('finance.partials.payment_source', ['sources' => $sources, 'label' => 'Entra a', 'noneLabel' => 'Solo registrar', 'selectedAccount' => $selectedAccount])
                        @endif
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> Registrar {{ $singular }}</button>
                    <a href="{{ route($indexRoute) }}" class="btn btn-light border">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Según la categoría se puede indicar a quién se pagó.
    (function () {
        const category = document.getElementById('m-category');
        const toggle = () => {
            document.getElementById('who-supplier').classList.toggle('d-none', category.value !== 'Pago a proveedores' || @json($type !== 'egreso'));
            document.getElementById('who-personal').classList.toggle('d-none', category.value !== 'Sueldos' || @json($type !== 'egreso'));
        };
        category.addEventListener('change', toggle);
        toggle();
    })();
</script>
@endsection
