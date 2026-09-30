@extends('layouts.app')

@section('title', 'Comisiones')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-percent"></i> Comisiones</h1>
            <p class="text-muted mb-0">Comisiones generadas por los servicios vendidos.</p>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Las comisiones se gestionan dentro de una empresa.</div>
    @else
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Especialista</label>
                        <select name="personal_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($specialists as $sp)
                                <option value="{{ $sp->id }}" {{ (string) $filters['personal_id'] === (string) $sp->id ? 'selected' : '' }}>{{ $sp->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach(\App\Models\Commission::STATUSES as $k => $meta)
                                <option value="{{ $k }}" {{ $filters['status'] === $k ? 'selected' : '' }}>{{ $meta[0] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="{{ route('commissions.index') }}" class="btn btn-light border">Limpiar</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm"><div class="card-body d-flex justify-content-between align-items-center">
                    <span class="text-muted">Pendiente de pago</span>
                    <span class="fs-4 fw-bold text-warning">{{ number_format($totales['pendiente'], 2) }}</span>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm"><div class="card-body d-flex justify-content-between align-items-center">
                    <span class="text-muted">Ya pagado</span>
                    <span class="fs-4 fw-bold text-success">{{ number_format($totales['pagada'], 2) }}</span>
                </div></div>
            </div>
        </div>

        @if($porEspecialista->count())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-0">
                    <div class="p-3"><h6 class="fw-bold mb-0"><i class="bi bi-people"></i> Resumen por especialista</h6></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Especialista</th><th class="text-end">Pendiente</th><th class="text-end">Pagado</th><th class="text-end pe-3">Accion</th></tr></thead>
                            <tbody>
                                @foreach($porEspecialista as $personalId => $filas)
                                    @php
                                        $nombre = $filas->first()->specialist?->full_name ?? 'N/D';
                                        $pend = (float) $filas->where('status', 'pendiente')->sum('monto');
                                        $pag  = (float) $filas->where('status', 'pagada')->sum('monto');
                                    @endphp
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $nombre }}</td>
                                        <td class="text-end text-warning fw-semibold">{{ number_format($pend, 2) }}</td>
                                        <td class="text-end text-success">{{ number_format($pag, 2) }}</td>
                                        <td class="text-end pe-3">
                                            @if($pend > 0)
                                                <form action="{{ route('commissions.pay-bulk') }}" method="POST" class="d-inline" onsubmit="return confirm('Marcar como pagadas todas las comisiones pendientes?')">
                                                    @csrf
                                                    <input type="hidden" name="personal_id" value="{{ $personalId }}">
                                                    <input type="hidden" name="from" value="{{ $filters['from'] }}">
                                                    <input type="hidden" name="to" value="{{ $filters['to'] }}">
                                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-all"></i> Pagar todo</button>
                                                </form>
                                            @else
                                                <span class="text-muted small">Sin pendientes</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th class="ps-3">Fecha</th><th>Especialista</th><th>Servicio</th><th>Venta</th><th class="text-center">%</th><th class="text-end">Comision</th><th class="text-center">Estado</th><th class="text-end pe-3"></th></tr>
                        </thead>
                        <tbody>
                            @forelse($commissions as $c)
                                <tr>
                                    <td class="ps-3">{{ $c->created_at->format('d/m/Y') }}</td>
                                    <td>{{ $c->specialist?->full_name ?? 'N/D' }}</td>
                                    <td>{{ $c->saleItem?->service?->name ?? $c->saleItem?->description ?? '-' }}</td>
                                    <td>{{ $c->sale?->sale_number ?? '-' }}</td>
                                    <td class="text-center">{{ rtrim(rtrim(number_format($c->rate, 2), '0'), '.') }}%</td>
                                    <td class="text-end fw-semibold">{{ number_format($c->amount, 2) }}</td>
                                    <td class="text-center"><span class="badge {{ $c->statusBadge() }}">{{ $c->statusLabel() }}</span></td>
                                    <td class="text-end pe-3">
                                        @if(!$c->isPaid())
                                            <form action="{{ route('commissions.pay', $c) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" title="Marcar pagada"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-5 text-muted">No hay comisiones en el periodo. Se generan al vender un servicio con especialista asignado y porcentaje de comision.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $commissions->links() }}</div>
        </div>
    @endif
</div>
@endsection
