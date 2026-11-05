@extends('layouts.app')

@section('title', 'Nueva cuenta')

@section('page')
<div class="container-fluid" style="max-width: 640px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-bank"></i> Nueva cuenta</h1>
        <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ route('accounts.store') }}" method="POST" class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @csrf
            @include('finance.accounts.form')

            <hr class="my-4">
            <h6 class="fw-bold mb-1">Aporte inicial <span class="text-muted fw-normal">(opcional)</span></h6>
            <p class="small text-muted">El dinero con el que empieza la cuenta. Queda como su primer movimiento.</p>
            <div class="input-group mb-3" style="max-width: 240px;">
                <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                <input type="number" name="initial_amount" id="acc-initial" class="form-control text-end no-uppercase" min="0" step="0.01" value="{{ old('initial_amount') }}" placeholder="600.00">
            </div>
            @include('finance.partials.payment_source', ['sources' => $sources, 'label' => 'Viene de', 'noneLabel' => 'Externo (dueño o socio)', 'amountInput' => '#acc-initial'])

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary px-4"><i class="bi bi-save"></i> Crear cuenta</button>
                <a href="{{ route('accounts.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection
