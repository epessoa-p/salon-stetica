@extends('layouts.app')

@section('title', 'Cliente')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-person-circle"></i> {{ $client->full_name }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $client->active ? 'bg-success' : 'bg-secondary' }}">{{ $client->active ? 'Activo' : 'Inactivo' }}</span>
                @if($client->id_number) · Doc: {{ $client->id_number }} @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-datos" type="button">Datos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historial" type="button">Historial</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-obs" type="button">Observaciones</button></li>
            </ul>

            <div class="tab-content p-4">
                {{-- Datos --}}
                <div class="tab-pane fade show active" id="tab-datos">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="text-muted small">Nombre completo</div><div class="fw-semibold">{{ $client->full_name }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Documento</div><div>{{ $client->id_number ?: '-' }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Teléfono</div><div>{{ $client->phone ?: '-' }}</div></div>
                        <div class="col-md-4"><div class="text-muted small">Email</div><div>{{ $client->email ?: '-' }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Nacimiento</div><div>{{ optional($client->birthdate)->format('d/m/Y') ?: '-' }}</div></div>
                        <div class="col-md-2"><div class="text-muted small">Género</div><div>{{ $client->gender ? ucfirst($client->gender) : '-' }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Cómo nos conoció</div><div>{{ $client->source ?: '-' }}</div></div>
                        <div class="col-12"><div class="text-muted small">Dirección</div><div>{{ $client->address ?: '-' }}</div></div>
                    </div>
                </div>

                {{-- Historial --}}
                <div class="tab-pane fade" id="tab-historial">
                    @if(!$showAppointments && !$showSales)
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-lock" style="font-size: 2.5rem;"></i>
                            <p class="mt-3 mb-0">No tienes permiso para ver el historial de citas ni de ventas de este cliente.</p>
                        </div>
                    @else
                        {{-- Indicadores --}}
                        <div class="row g-3 mb-4">
                            @if($showAppointments)
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small"><i class="bi bi-calendar-check text-primary"></i> Visitas atendidas</div>
                                        <div class="fs-4 fw-bold">{{ $stats['visitas'] }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($showSales)
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small"><i class="bi bi-cash-coin text-success"></i> Total gastado</div>
                                        <div class="fs-4 fw-bold">{{ $setting->money($stats['gastado']) }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small"><i class="bi bi-receipt text-warning"></i> Ticket promedio</div>
                                        <div class="fs-4 fw-bold">{{ $setting->money($stats['ticket']) }}</div>
                                    </div>
                                </div>
                            @endif
                            <div class="col-6 col-lg-3">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="text-muted small"><i class="bi bi-clock-history text-info"></i> Última visita</div>
                                    <div class="fs-5 fw-bold">{{ $stats['ultima'] ? \Illuminate\Support\Carbon::parse($stats['ultima'])->format('d/m/Y') : '—' }}</div>
                                </div>
                            </div>
                        </div>

                        @if($stats['favorito'] || $stats['canceladas'])
                            <p class="small text-muted">
                                @if($stats['favorito'])
                                    <span class="me-3"><i class="bi bi-star-fill text-warning"></i> Servicio preferido: <strong>{{ $stats['favorito'] }}</strong></span>
                                @endif
                                @if($stats['canceladas'])
                                    <span><i class="bi bi-x-circle text-danger"></i> Citas canceladas o no asistidas: <strong>{{ $stats['canceladas'] }}</strong></span>
                                @endif
                            </p>
                        @endif

                        {{-- Citas --}}
                        @if($showAppointments)
                            <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-calendar3"></i> Citas</h6>
                            @if($appointments->isEmpty())
                                <p class="text-muted small">Este cliente todavía no tiene citas registradas.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Servicio</th>
                                                <th>Atendió</th>
                                                <th class="text-end">Precio</th>
                                                <th class="text-center">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($appointments as $a)
                                                <tr>
                                                    <td class="text-nowrap">{{ optional($a->starts_at)->format('d/m/Y H:i') }}</td>
                                                    <td>{{ $a->service?->name ?: '-' }}</td>
                                                    <td class="text-muted small">{{ $a->specialist?->full_name ?: '-' }}</td>
                                                    <td class="text-end">{{ $a->price ? number_format($a->price, 2) : '-' }}</td>
                                                    <td class="text-center"><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif

                        {{-- Compras --}}
                        @if($showSales)
                            <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-bag-check"></i> Compras</h6>
                            @if($sales->isEmpty())
                                <p class="text-muted small mb-0">Este cliente todavía no tiene compras registradas.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Folio</th>
                                                <th>Detalle</th>
                                                <th>Pago</th>
                                                <th class="text-end">Total</th>
                                                <th style="width:40px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sales as $venta)
                                                <tr class="{{ $venta->status === 'anulada' ? 'text-decoration-line-through text-muted' : '' }}">
                                                    <td class="text-nowrap">{{ optional($venta->sale_date)->format('d/m/Y') }}</td>
                                                    <td><code>{{ $venta->sale_number }}</code></td>
                                                    <td class="small">{{ $venta->items->pluck('description')->filter()->take(3)->implode(', ') }}{{ $venta->items->count() > 3 ? '…' : '' }}</td>
                                                    <td class="small">{{ \App\Models\PaymentMethod::label($venta->payment_method, $venta->company_id) }}</td>
                                                    <td class="text-end fw-semibold">{{ number_format($venta->total, 2) }}</td>
                                                    <td class="text-end"><a href="{{ route('sales.show', $venta) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif
                    @endif
                </div>

                {{-- Observaciones --}}
                <div class="tab-pane fade" id="tab-obs">
                    @if($client->notes)
                        <p class="mb-0" style="white-space: pre-line;">{{ $client->notes }}</p>
                    @else
                        <p class="text-muted mb-0">Sin observaciones registradas.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
