@extends('layouts.app')

@section('title', 'Movimientos de inventario')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-list-ul"></i> Movimientos de inventario</h1>
            <p class="text-muted mb-0">Historial de entradas, salidas y ajustes.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary"><i class="bi bi-boxes"></i> Existencias</a>
            <a href="{{ route('stock.movement.create') }}" class="btn btn-primary"><i class="bi bi-arrow-down-up"></i> Nuevo movimiento</a>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> El inventario se gestiona dentro de una empresa.</div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Producto</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-end">Cantidad</th>
                                <th class="pe-3">Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $m)
                                @php
                                    $badge = ['entrada' => 'bg-success', 'salida' => 'bg-danger', 'ajuste' => 'bg-warning text-dark'][$m->type] ?? 'bg-secondary';
                                @endphp
                                <tr>
                                    <td class="ps-3">{{ optional($m->movement_date)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $m->product?->name ?? '—' }}</td>
                                    <td class="text-center"><span class="badge {{ $badge }}">{{ \App\Models\InventoryMovement::TYPES[$m->type] ?? $m->type }}</span></td>
                                    <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format($m->quantity, 2), '0'), '.') }}</td>
                                    <td class="pe-3 text-muted">{{ $m->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">Sin movimientos registrados.</td></tr>
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
