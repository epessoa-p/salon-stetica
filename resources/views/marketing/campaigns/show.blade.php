@extends('layouts.app')

@section('title', 'Campaña · ' . $campaign->name)

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $pct = $total ? round(100 * $sent / $total) : 0;
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-broadcast"></i> {{ $campaign->name }}</h1>
            <p class="text-muted mb-0">{{ $segment }} · creada el {{ $campaign->created_at->format('d/m/Y') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('marketing.campaigns.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Campañas</a>
            @if($can('marketing.edit'))
                <form action="{{ route('marketing.campaigns.complete', $campaign) }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-dark">{{ $campaign->status === 'done' ? 'Reabrir' : 'Cerrar campaña' }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Avance</div>
            <div class="fs-3 fw-bold"><span id="sent-count">{{ $sent }}</span> / {{ $total }}</div>
            <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" id="sent-bar" style="width: {{ $pct }}%"></div></div>
            @if($campaign->coupon)<div class="small mt-2">Cupón <code>{{ $campaign->coupon->code }}</code> · {{ $campaign->coupon->valueLabel() }}</div>@endif
        </div></div></div>
        <div class="col-md-7"><div class="card border-0 shadow-sm h-100"><div class="card-body" style="background: #ece5dd; border-radius: inherit;">
            <div class="small text-muted mb-1">Mensaje (ejemplo)</div>
            <div style="background: #dcf8c6; border-radius: 10px 10px 10px 2px; padding: .6rem .75rem; white-space: pre-line;">{{ $preview }}</div>
        </div></div></div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="btn-group btn-group-sm">
            <a href="{{ route('marketing.campaigns.show', $campaign) }}" class="btn {{ $pending ? 'btn-outline-secondary' : 'btn-dark' }}">Todos</a>
            <a href="{{ route('marketing.campaigns.show', [$campaign, 'filter' => 'pending']) }}" class="btn {{ $pending ? 'btn-dark' : 'btn-outline-secondary' }}">Pendientes</a>
        </div>
        <span class="small text-muted">Toca «Enviar»: se abre WhatsApp con el mensaje listo.</span>
    </div>

    <div class="card border-0 shadow-sm">
        <ul class="list-group list-group-flush">
            @forelse($recipients as $r)
                <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <div class="fw-semibold">{{ $r->client?->full_name ?? 'Cliente eliminado' }}</div>
                        <div class="small text-muted">{{ $r->client?->phone }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small {{ $r->sent_at ? 'text-success' : 'text-muted' }}" data-sent>{!! $r->sent_at ? '<i class="bi bi-check2-all"></i> ' . $r->sent_at->format('d/m H:i') : '' !!}</span>
                        @if($r->client?->phone && $can('marketing.send') && $campaign->status !== 'done')
                            <a href="{{ route('messages.send', ['client' => $r->client_id, 'type' => 'campaign', 'campaign' => $campaign->id]) }}" target="_blank" rel="noopener"
                               class="btn btn-sm {{ $r->sent_at ? 'btn-outline-success' : 'btn-success' }} js-wa" data-was-sent="{{ $r->sent_at ? 1 : 0 }}">
                                <i class="bi bi-whatsapp"></i> {{ $r->sent_at ? 'Reenviar' : 'Enviar' }}
                            </a>
                        @endif
                    </div>
                </li>
            @empty
                <li class="list-group-item text-center text-muted py-4">{{ $pending ? '¡Listo! Ya se envió a todos.' : 'Sin destinatarios.' }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-3">{{ $recipients->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const total = {{ $total }};
        document.querySelectorAll('.js-wa').forEach((a) => a.addEventListener('click', () => {
            const s = a.closest('li').querySelector('[data-sent]');
            s.className = 'small text-success';
            s.innerHTML = '<i class="bi bi-check2-all"></i> Recién enviado';
            if (a.dataset.wasSent === '0') {
                a.dataset.wasSent = '1';
                const c = document.getElementById('sent-count');
                c.textContent = parseInt(c.textContent, 10) + 1;
                document.getElementById('sent-bar').style.width = Math.round(100 * parseInt(c.textContent, 10) / total) + '%';
                a.className = 'btn btn-sm btn-outline-success js-wa';
                a.innerHTML = '<i class="bi bi-whatsapp"></i> Reenviar';
            }
        }));
    })();
</script>
@endpush
