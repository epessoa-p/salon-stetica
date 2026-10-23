@extends('layouts.app')

@section('title', 'Métodos de pago')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-credit-card-2-front"></i> Métodos de pago</h1>
            <p class="text-muted mb-0">Formas de cobro disponibles en el punto de venta y en la caja.</p>
        </div>
        <div class="d-flex gap-2">
            @if($methods->isEmpty() && $companyId)
                <form action="{{ route('payment-methods.seed-defaults') }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-secondary"><i class="bi bi-magic"></i> Cargar métodos base</button>
                </form>
            @endif
            <a href="{{ route('payment-methods.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo método</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($methods->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill"></i> Todavía no hay métodos configurados, por eso el sistema usa el catálogo base:
            <strong>{{ implode(', ', $fallback) }}</strong>. Crea los tuyos o carga los base para personalizarlos.
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px" class="text-center">Orden</th>
                                <th>Método</th>
                                <th>Código</th>
                                @if(auth()->user()->is_super_admin)<th>Empresa</th>@endif
                                <th class="text-center">Suma a caja</th>
                                <th class="text-center">Estado</th>
                                <th style="width:110px" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($methods as $m)
                                <tr>
                                    <td class="text-center text-muted">{{ $m->sort_order }}</td>
                                    <td>
                                        <i class="bi {{ $m->icon ?: 'bi-credit-card-2-front' }} me-1"></i>
                                        <span class="fw-semibold">{{ $m->name }}</span>
                                        @if($m->is_default)<span class="badge bg-primary-subtle text-primary ms-1">Predeterminado</span>@endif
                                    </td>
                                    <td><code>{{ $m->code }}</code></td>
                                    @if(auth()->user()->is_super_admin)<td class="text-muted small">{{ $m->company?->name ?: '-' }}</td>@endif
                                    <td class="text-center">
                                        @if($m->affects_cash)
                                            <i class="bi bi-check-circle-fill text-success" title="Entra al efectivo del arqueo"></i>
                                        @else
                                            <i class="bi bi-dash-circle text-muted" title="No entra al efectivo del arqueo"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $m->active ? 'bg-success' : 'bg-secondary' }}">{{ $m->active ? 'Activo' : 'Inactivo' }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('payment-methods.edit', $m) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('payment-methods.destroy', $m) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el método {{ $m->name }}?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-lightbulb"></i> <strong>Suma a caja</strong> indica si el cobro ingresa al efectivo que se cuenta al cerrar el arqueo
            (el efectivo sí, una transferencia normalmente no).
        </p>
    @endif
</div>
@endsection
