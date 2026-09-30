@extends('layouts.app')

@section('title', $title)

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi {{ $icon }}"></i> {{ $title }}</h1>
            <p class="text-muted mb-0">Registro de {{ $type === 'ingreso' ? 'ingresos' : 'gastos' }} del salon.</p>
        </div>
        <a href="{{ route($createRoute) }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo {{ $singular }}</a>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Este modulo se gestiona dentro de una empresa.</div>
    @else
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Categoria</label>
                        <select name="category" class="form-select">
                            <option value="">Todas</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ ($filters['category'] ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="{{ route($indexRoute) }}" class="btn btn-light border">Limpiar</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted">Total del periodo</span>
                <span class="fs-4 fw-bold text-{{ $color }}">{{ $type === 'ingreso' ? '+' : '-' }}{{ number_format($total, 2) }}</span>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Concepto</th>
                                <th>Categoria</th>
                                <th>Metodo</th>
                                <th>Caja</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $m)
                                <tr>
                                    <td class="ps-3">{{ optional($m->movement_date)->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $m->concept }}</div>
                                        @if($m->reference)<small class="text-muted">Ref: {{ $m->reference }}</small>@endif
                                    </td>
                                    <td>{{ $m->category ?: '-' }}</td>
                                    <td>{{ ucfirst($m->payment_method) }}</td>
                                    <td>{{ $m->session?->register?->name ?? '-' }}</td>
                                    <td class="text-end fw-semibold text-{{ $color }}">{{ number_format($m->amount, 2) }}</td>
                                    <td class="text-end pe-3">
                                        <form action="{{ route($destroyRoute, $m) }}" method="POST" class="d-inline" onsubmit="return confirm('Eliminar este movimiento?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-5 text-muted">No hay movimientos en el periodo seleccionado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $movements->links() }}</div>
        </div>
    @endif
</div>
@endsection
