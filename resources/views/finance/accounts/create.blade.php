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

            <div class="mt-3" style="max-width: 280px;">
                <label class="form-label">Saldo inicial</label>
                <div class="input-group">
                    <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                    <input type="number" name="initial_amount" class="form-control text-end no-uppercase" min="0" step="0.01" value="{{ old('initial_amount') }}" placeholder="600.00">
                </div>
                <div class="form-text">Lo que hay hoy en la cuenta. Entra como primer movimiento.</div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary px-4"><i class="bi bi-save"></i> Crear cuenta</button>
                <a href="{{ route('accounts.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection
