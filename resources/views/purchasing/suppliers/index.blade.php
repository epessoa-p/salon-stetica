@extends('layouts.app')

@section('title', 'Proveedores')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-truck"></i> Proveedores</h1>
            <p class="text-muted mb-0">Proveedores de productos e insumos.</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Proveedor</a>
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 380px;">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control no-uppercase" placeholder="Buscar proveedor...">
            @if($search)<a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>@endif
            <button class="btn btn-outline-primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Proveedor</th>
                            <th>Contacto</th>
                            <th>Teléfono</th>
                            @if(auth()->user()->is_super_admin)<th>Empresa</th>@endif
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $supplier)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $supplier->name }}</div>
                                    <small class="text-muted">{{ $supplier->email ?: ($supplier->tax_id ? 'NIT: '.$supplier->tax_id : 'Sin email') }}</small>
                                </td>
                                <td>{{ $supplier->contact_name ?: '—' }}</td>
                                <td>{{ $supplier->phone ?: '—' }}</td>
                                @if(auth()->user()->is_super_admin)<td>{{ $supplier->company?->name ?? '-' }}</td>@endif
                                <td class="text-center"><span class="badge {{ $supplier->active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar proveedor {{ addslashes($supplier->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->is_super_admin ? 6 : 5 }}" class="text-center py-5 text-muted">
                                {{ $search ? 'No se encontraron proveedores.' : 'No hay proveedores registrados.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $suppliers->links() }}</div>
    </div>
</div>
@endsection
