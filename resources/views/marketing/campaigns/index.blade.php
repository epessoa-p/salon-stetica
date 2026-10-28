@extends('layouts.app')

@section('title', 'Campañas')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-broadcast"></i> Campañas</h1>
            <p class="text-muted mb-0">Elige un grupo de clientes, escribe el mensaje y envíalo uno a uno por WhatsApp.</p>
        </div>
        @if(empty($noCompany) && $can('marketing.edit'))
            <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva campaña</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las campañas se usan dentro de una empresa.</div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Campaña</th><th>Creada</th><th>Cupón</th><th style="min-width: 200px;">Avance</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($campaigns as $c)
                            @php $pct = $c->recipients_count ? round(100 * $c->sent_count / $c->recipients_count) : 0; @endphp
                            <tr>
                                <td class="ps-3"><a href="{{ route('marketing.campaigns.show', $c) }}" class="fw-semibold text-decoration-none">{{ $c->name }}</a></td>
                                <td class="small">{{ $c->created_at->format('d/m/Y') }}</td>
                                <td>{!! $c->coupon ? '<code>' . e($c->coupon->code) . '</code>' : '—' !!}</td>
                                <td>
                                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
                                    <div class="small text-muted">{{ $c->sent_count }} de {{ $c->recipients_count }} enviados</div>
                                </td>
                                <td><span class="badge {{ $c->status === 'done' ? 'bg-secondary' : 'bg-primary' }}">{{ $c->status === 'done' ? 'Cerrada' : 'En curso' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin campañas. Por ejemplo: «Te extrañamos» para clientes que no vienen hace 60 días.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $campaigns->links() }}</div>
    @endif
</div>
@endsection
