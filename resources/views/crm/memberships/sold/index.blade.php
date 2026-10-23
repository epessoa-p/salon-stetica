@extends('layouts.app')

@section('title', 'Membresías de clientes')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $canEdit = $user->is_super_admin || ($co && $user->hasPermissionInCompany('memberships.edit', $co));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-award"></i> Membresías</h1>
            <p class="text-muted mb-0">Quién tiene membresía, hasta cuándo y cuántas sesiones le quedan.</p>
        </div>
        @if(empty($noCompany))
            <a href="{{ route('sales.pos') }}" class="btn btn-primary"><i class="bi bi-upc-scan"></i> Vender membresía</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las membresías se consultan dentro de una empresa.</div>
    @else
        @include('crm.memberships._tabs')

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="btn-group btn-group-sm flex-wrap">
                @foreach(\App\Http\Controllers\Crm\ClientMembershipController::FILTERS as $key => $label)
                    <a href="{{ route('memberships.index', ['filter' => $key, 'q' => $search ?: null]) }}"
                       class="btn {{ $filter === $key ? 'btn-dark' : 'btn-outline-secondary' }}">
                        {{ $label }}
                        @isset($counts[$key])<span class="badge {{ $key === 'expiring' && $counts[$key] ? 'bg-warning text-dark' : 'bg-light text-dark' }} ms-1">{{ $counts[$key] }}</span>@endisset
                    </a>
                @endforeach
            </div>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar cliente…">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                @if($memberships->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">No hay membresías en esta vista.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Cliente</th>
                                    <th>Plan</th>
                                    <th>Vigencia</th>
                                    <th class="text-center">Sesiones</th>
                                    <th class="text-center">Estado</th>
                                    <th class="pe-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($memberships as $m)
                                    <tr>
                                        <td class="ps-3">
                                            @if($m->client)
                                                <a href="{{ route('clients.show', $m->client) }}" class="fw-semibold text-decoration-none">{{ $m->client->full_name }}</a>
                                                <div class="small text-muted">{{ $m->client->phone }}</div>
                                            @else — @endif
                                        </td>
                                        <td>
                                            {{ $m->plan_name }}
                                            @if((float) $m->discount_percent > 0)
                                                <div class="small text-muted">{{ rtrim(rtrim(number_format((float) $m->discount_percent, 2), '0'), '.') }} % en {{ mb_strtolower($m->scopeLabel()) }}</div>
                                            @endif
                                        </td>
                                        <td class="small text-nowrap">
                                            {{ $m->starts_on->format('d/m/Y') }} – {{ $m->ends_on->format('d/m/Y') }}
                                            @if($m->state() === 'active')
                                                <div class="{{ $m->daysLeft() <= 7 ? 'text-warning-emphasis fw-semibold' : 'text-muted' }}">
                                                    {{ $m->daysLeft() === 0 ? 'Vence hoy' : 'Quedan ' . $m->daysLeft() . ' días' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($m->sessions->sum('included') > 0)
                                                <strong>{{ $remaining[$m->id] ?? 0 }}</strong><span class="text-muted">/{{ $m->sessions->sum('included') }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center"><span class="badge {{ $m->stateBadge() }}">{{ $m->stateLabel() }}</span></td>
                                        <td class="pe-3 text-end text-nowrap">
                                            @if($m->sale_id)
                                                <a href="{{ route('sales.show', $m->sale_id) }}" class="btn btn-sm btn-outline-secondary" title="Ver venta"><i class="bi bi-receipt"></i></a>
                                            @endif
                                            @if($canEdit && $m->status !== 'canceled' && $m->state() !== 'expired')
                                                <form action="{{ route('memberships.cancel', $m) }}" method="POST" class="d-inline"
                                                      onsubmit="const r = prompt('Motivo de la cancelación (no se reembolsa: para devolver el dinero, anula la venta):'); if (r === null) return false; this.reason.value = r;">
                                                    @csrf
                                                    <input type="hidden" name="reason">
                                                    <button class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-3">{{ $memberships->links() }}</div>
    @endif
</div>
@endsection
