@extends('layouts.app')

@section('title', 'Paquetes de clientes')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
    $filters = ['active' => 'Activos', 'expired' => 'Vencidos', 'canceled' => 'Cancelados', 'all' => 'Todos'];
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-collection"></i> Combos y Paquetes</h1>
            <p class="text-muted mb-0">Paquetes vendidos y las sesiones que le quedan a cada cliente.</p>
        </div>
    </div>

    @include('catalog._bundles_tabs')

    <form class="d-flex gap-2 flex-wrap mb-3" method="GET">
        <div class="btn-group btn-group-sm" role="group">
            @foreach($filters as $key => $label)
                <a href="{{ route('packages.sold', ['filter' => $key, 'q' => $term ?: null]) }}" class="btn {{ $filter === $key ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input type="search" name="q" value="{{ $term }}" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Buscar cliente o teléfono…">
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th>Paquete</th>
                        <th>Sesiones</th>
                        <th>Vigencia</th>
                        <th class="text-end">Pagado</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sold as $p)
                        @php $s = $summaries[$p->id]; @endphp
                        <tr>
                            <td>
                                @if($p->client)
                                    <a href="{{ route('clients.show', $p->client) }}" class="fw-semibold text-decoration-none">{{ $p->client->full_name }}</a>
                                    <div class="small text-muted">{{ $p->client->phone }}</div>
                                @else
                                    <span class="text-muted">Cliente eliminado</span>
                                @endif
                            </td>
                            <td>
                                {{ $p->package_name }}
                                @if($p->sale)<div class="small"><a href="{{ route('sales.show', $p->sale) }}" class="text-muted">Venta {{ $p->sale->sale_number }}</a></div>@endif
                            </td>
                            <td class="small">
                                @foreach($s['items'] as $i)
                                    <div>{{ $i['service'] }}: <strong>{{ $i['remaining'] }}</strong>/{{ $i['included'] }}</div>
                                @endforeach
                            </td>
                            <td class="small text-nowrap">{{ $p->starts_on->format('d/m/Y') }} · {{ $p->expiryLabel() }}</td>
                            <td class="text-end">{{ $setting->money($p->price_paid) }}</td>
                            <td><span class="badge {{ $s['state_badge'] }}">{{ $s['state_label'] }}</span></td>
                            <td class="text-end">
                                @if($p->status === 'active' && $can('packages.edit'))
                                    <form action="{{ route('packages.cancel', $p) }}" method="POST" class="d-inline"
                                          onsubmit="const r = prompt('Motivo de la cancelación del paquete:'); if (!r) return false; this.reason.value = r;">
                                        @csrf
                                        <input type="hidden" name="reason">
                                        <button class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay paquetes {{ $filter === 'all' ? '' : mb_strtolower($filters[$filter] ?? '') }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $sold->links() }}</div>
</div>
@endsection
