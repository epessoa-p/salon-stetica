@extends('layouts.app')

@section('title', 'Editar cuenta')

@section('page')
<div class="container-fluid" style="max-width: 640px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-bank"></i> Editar cuenta</h1>
        <a href="{{ route('accounts.show', $account) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ route('accounts.update', $account) }}" method="POST" class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @csrf
            @method('PUT')
            @include('finance.accounts.form')
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" role="switch" name="active" value="1" id="acc-active" @checked(old('active', $account->active))>
                <label class="form-check-label" for="acc-active">Activa</label>
                <div class="form-text">Las inactivas no aparecen para pagar. Para desactivarla, su saldo debe estar en 0.</div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary px-4"><i class="bi bi-save"></i> Guardar</button>
                <a href="{{ route('accounts.show', $account) }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection
