@extends('layouts.app')

@section('title', 'Sesión de caja')

@section('page')
@php
    $expected = $session->expectedBalance();
    $ingresos = (float) $session->movements->where('type', 'ingreso')->sum('amount');
    $egresos = (float) $session->movements->where('type', 'egreso')->sum('amount');
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-cash-stack"></i> {{ $session->register?->name }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $session->status === 'open' ? 'bg-success' : 'bg-secondary' }}">{{ $session->status === 'open' ? 'Abierta' : 'Cerrada' }}</span>
                · Abierta {{ optional($session->opened_at)->format('d/m/Y H:i') }}
                @if($session->closed_at) · Cerrada {{ $session->closed_at->format('d/m/Y H:i') }} @endif
            </p>
        </div>
        <a href="{{ route('cash-sessions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Apertura</div><div class="fw-bold fs-5">{{ number_format($session->opening_amount, 2) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Ingresos</div><div class="fw-bold fs-5 text-success">+{{ number_format($ingresos, 2) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Egresos</div><div class="fw-bold fs-5 text-danger">-{{ number_format($egresos, 2) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Saldo esperado</div><div class="fw-bold fs-5">{{ number_format($expected, 2) }}</div>
        </div></div></div>
    </div>

    @if($session->status === 'closed')
        <div class="alert {{ abs($session->difference) < 0.01 ? 'alert-success' : 'alert-warning' }}">
            <strong>Arqueo:</strong> Contado {{ number_format($session->closing_amount, 2) }} ·
            Esperado {{ number_format($session->expected_amount, 2) }} ·
            Diferencia <strong>{{ number_format($session->difference, 2) }}</strong>
            {{ abs($session->difference) < 0.01 ? '(cuadra)' : ($session->difference < 0 ? '(faltante)' : '(sobrante)') }}
        </div>
    @endif

    <div class="row g-4">
        {{-- Movimientos --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="p-3"><h6 class="fw-bold mb-0"><i class="bi bi-arrow-left-right"></i> Movimientos</h6></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Concepto</th><th>Método</th><th class="text-end pe-3">Monto</th></tr></thead>
                            <tbody>
                                @forelse($session->movements as $m)
                                    <tr>
                                        <td class="ps-3">{{ $m->concept }}<br><small class="text-muted">{{ $m->created_at->format('d/m H:i') }}</small></td>
                                        <td>{{ ucfirst($m->payment_method) }}</td>
                                        <td class="text-end pe-3 fw-semibold {{ $m->type === 'ingreso' ? 'text-success' : 'text-danger' }}">
                                            {{ $m->type === 'ingreso' ? '+' : '-' }}{{ number_format($m->amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center py-4 text-muted">Sin movimientos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones (solo si abierta) --}}
        <div class="col-lg-5">
            @if($session->status === 'open')
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle"></i> Registrar movimiento</h6>
                        <form action="{{ route('cash-sessions.movements', $session) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Tipo</label>
                                <select name="type" class="form-select">
                                    <option value="ingreso">Ingreso</option>
                                    <option value="egreso">Egreso</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Monto</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control no-uppercase" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Concepto</label>
                                <input type="text" name="concept" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Método</label>
                                <select name="payment_method" class="form-select">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="qr">QR</option>
                                </select>
                            </div>
                            <button class="btn btn-outline-primary w-100" type="submit">Agregar movimiento</button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-lock"></i> Cerrar caja (arqueo)</h6>
                        <form action="{{ route('cash-sessions.close', $session) }}" method="POST" onsubmit="return confirm('¿Cerrar la caja? Esta acción no se puede deshacer.')">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Monto contado (efectivo real)</label>
                                <input type="number" step="0.01" min="0" name="closing_amount" class="form-control no-uppercase" value="{{ number_format($expected, 2, '.', '') }}" required>
                                <small class="text-muted">Esperado: {{ number_format($expected, 2) }}</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Notas de cierre</label>
                                <textarea name="notes" rows="2" class="form-control"></textarea>
                            </div>
                            <button class="btn btn-danger w-100" type="submit"><i class="bi bi-lock"></i> Cerrar caja</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
