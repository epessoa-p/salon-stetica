@extends('layouts.app')

@section('title', 'Citas')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-calendar-check"></i> Citas</h1>
            <p class="text-muted mb-0">Lista de citas del salón.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('appointments.calendar') }}" class="btn btn-outline-primary"><i class="bi bi-calendar-week"></i> Calendario</a>
            <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva Cita</a>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i> La agenda se gestiona dentro de una empresa. Selecciona o ingresa a una empresa para ver sus citas.
        </div>
    @else
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Buscar cliente</label>
                        <input type="text" name="q" value="{{ $filters['search'] ?? '' }}" class="form-control no-uppercase" placeholder="Nombre del cliente">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach(\App\Models\Appointment::STATUSES as $key => $meta)
                                <option value="{{ $key }}" {{ ($filters['status'] ?? '') === $key ? 'selected' : '' }}>{{ $meta[0] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Fecha</label>
                        <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="{{ route('appointments.index') }}" class="btn btn-light border">Limpiar</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha y hora</th>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Especialista</th>
                                <th class="text-end">Precio</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($appointments as $appt)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold">{{ $appt->starts_at->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $appt->starts_at->format('H:i') }}@if($appt->ends_at) - {{ $appt->ends_at->format('H:i') }}@endif</small>
                                    </td>
                                    <td>{{ $appt->client?->full_name ?? '—' }}</td>
                                    <td>{{ $appt->service?->name ?? '—' }}</td>
                                    <td>{{ $appt->specialist?->full_name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($appt->price, 2) }}</td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="badge {{ $appt->statusBadge() }} border-0 dropdown-toggle" data-bs-toggle="dropdown" type="button">{{ $appt->statusLabel() }}</button>
                                            <ul class="dropdown-menu shadow border-0">
                                                @foreach(\App\Models\Appointment::STATUSES as $key => $meta)
                                                    <li>
                                                        <form action="{{ route('appointments.status', $appt) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="status" value="{{ $key }}">
                                                            <button type="submit" class="dropdown-item {{ $appt->status === $key ? 'active' : '' }}">{{ $meta[0] }}</button>
                                                        </form>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('appointments.edit', $appt) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('appointments.destroy', $appt) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta cita?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-5 text-muted">No hay citas que coincidan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $appointments->links() }}</div>
        </div>
    @endif
</div>
@endsection
