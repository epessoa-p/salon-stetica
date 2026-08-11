@extends('layouts.app')

@section('title', 'Arqueos de caja')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-clipboard-check"></i> Caja — Sesiones y arqueos</h1>
            <p class="text-muted mb-0">Apertura, movimientos y cierre de caja.</p>
        </div>
        <a href="{{ route('cash-sessions.create') }}" class="btn btn-primary"><i class="bi bi-unlock"></i> Abrir caja</a>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> La caja se gestiona dentro de una empresa.</div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Caja</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th class="text-end">Monto apertura</th>
                                <th class="text-end">Diferencia</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $s)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $s->register?->name ?? '—' }}</td>
                                    <td>{{ optional($s->opened_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ optional($s->closed_at)->format('d/m/Y H:i') ?: '—' }}</td>
                                    <td class="text-end">{{ number_format($s->opening_amount, 2) }}</td>
                                    <td class="text-end {{ $s->difference < 0 ? 'text-danger' : ($s->difference > 0 ? 'text-success' : '') }}">
                                        {{ $s->status === 'closed' ? number_format($s->difference, 2) : '—' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $s->status === 'open' ? 'bg-success' : 'bg-secondary' }}">{{ $s->status === 'open' ? 'Abierta' : 'Cerrada' }}</span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('cash-sessions.show', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-5 text-muted">No hay sesiones de caja registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $sessions->links() }}</div>
        </div>
    @endif
</div>
@endsection
