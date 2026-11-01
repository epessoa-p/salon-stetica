@extends('layouts.app')

@section('title', 'Traspasos')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-left-right"></i> Traspasos</h1>
            <p class="text-muted mb-0">Mueve productos de un almacén (sucursal) a otro.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-primary"><i class="bi bi-boxes"></i> Existencias</a>
            @if($warehouseCount > 1)
                <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo traspaso</a>
            @endif
        </div>
    </div>

    @if($warehouseCount < 2)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i>
            Para traspasar productos se necesitan al menos dos almacenes: crea otra sucursal en Configuración › Sucursales (cada una trae su almacén).
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Número</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th class="text-center">Productos</th>
                            <th class="text-center">Estado</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $t)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $t->transfer_number }}</td>
                                <td>{{ $t->transferred_at->format('d/m/Y H:i') }}<div class="small text-muted">{{ $t->creator?->name }}</div></td>
                                <td>{{ $t->from?->label() }}</td>
                                <td>{{ $t->to?->label() }}</td>
                                <td class="text-center">{{ $t->items_count }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $t->isVoided() ? 'bg-secondary' : 'bg-success' }}">{{ $t->isVoided() ? 'Anulado' : 'Completado' }}</span>
                                </td>
                                <td class="text-end pe-3"><a href="{{ route('stock-transfers.show', $t) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">Aún no hay traspasos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $transfers->links() }}</div>
    </div>
</div>
@endsection
