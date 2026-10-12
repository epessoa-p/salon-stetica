@extends('layouts.app')

@section('title', 'Nuevo método de pago')

@section('page')
<div class="container-fluid" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-credit-card-2-front"></i> Nuevo método de pago</h1>
            <p class="text-muted mb-0">Define cómo se cobra en el punto de venta y en la caja.</p>
        </div>
        <a href="{{ route('payment-methods.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('payment-methods.store') }}" method="POST">
                @include('organization.payment_methods._form')
            </form>
        </div>
    </div>
</div>
@endsection
