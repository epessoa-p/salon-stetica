@extends('layouts.app')

@section('title', 'Recordatorios')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-bell"></i> Recordatorios</h1>
            <p class="text-muted mb-0">Avisa a cada cliente de su cita por WhatsApp con un clic: el mensaje se arma solo con la plantilla.</p>
        </div>
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Los recordatorios se usan dentro de una empresa.</div>
    @else
        <form class="d-flex gap-2 align-items-end flex-wrap mb-3" method="GET">
            <div>
                <label class="form-label small mb-1">Día de las citas</label>
                <div class="d-flex gap-1">
                    <a href="{{ route('reminders.index', ['date' => today()->toDateString(), 'template_id' => $templateId]) }}" class="btn btn-sm {{ $day->isToday() ? 'btn-dark' : 'btn-outline-secondary' }}">Hoy</a>
                    <a href="{{ route('reminders.index', ['date' => today()->addDay()->toDateString(), 'template_id' => $templateId]) }}" class="btn btn-sm {{ $day->isTomorrow() ? 'btn-dark' : 'btn-outline-secondary' }}">Mañana</a>
                    <input type="date" name="date" value="{{ $day->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()">
                </div>
            </div>
            <div>
                <label class="form-label small mb-1">Plantilla</label>
                <select name="template_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($templates as $t)<option value="{{ $t->id }}" @selected($templateId === $t->id)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">Mostrar</label>
                <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" @selected($filter === 'all')>Todas</option>
                    <option value="pending" @selected($filter === 'pending')>Sin avisar</option>
                </select>
            </div>
            @if($co && $co->planAllows('marketing') && $can('marketing.edit'))
                <a href="{{ route('marketing.templates.index') }}" class="btn btn-sm btn-link">Editar plantillas</a>
            @endif
        </form>

        @php $notified = $appointments->filter(fn ($a) => $logs->has($a->id))->count(); @endphp
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">{{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }} · {{ $appointments->count() }} cita(s)</h6>
            <span class="small text-muted"><span id="notified-count">{{ $notified }}</span> avisada(s)</span>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Hora</th><th>Cliente</th><th>Servicio</th><th>Estado</th><th>Aviso</th><th class="text-end pe-3"></th></tr></thead>
                    <tbody>
                        @forelse($appointments as $a)
                            @php $last = $logs->get($a->id)?->first(); @endphp
                            <tr data-row="{{ $a->id }}">
                                <td class="ps-3 fw-semibold">{{ $a->starts_at->format('H:i') }}</td>
                                <td>{{ $a->client?->full_name ?? 'Sin cliente' }}<div class="small text-muted">{{ $a->client?->phone ?: 'Sin teléfono' }}</div></td>
                                <td class="small">{{ $a->service?->name ?? '—' }}<div class="text-muted">{{ $a->specialist?->full_name }}</div></td>
                                <td><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
                                <td class="small" data-sent>
                                    @if($last)<span class="text-success"><i class="bi bi-check2-all"></i> {{ $last->sent_at->format('d/m H:i') }}</span><div class="text-muted">{{ $last->sender?->name }}</div>
                                    @else<span class="text-muted">Sin avisar</span>@endif
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($a->client?->phone)
                                        <a href="{{ route('messages.send', ['client' => $a->client_id, 'type' => 'reminder', 'appointment' => $a->id, 'template' => $templateId]) }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-success js-wa"><i class="bi bi-whatsapp"></i> {{ $last ? 'Reenviar' : 'Avisar' }}</a>
                                    @endif
                                    @if($a->status === 'pendiente' && $can('appointments.edit'))
                                        <form action="{{ route('appointments.status', $a) }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="status" value="confirmada">
                                            <button class="btn btn-sm btn-outline-primary" title="Marcar confirmada"><i class="bi bi-check-lg"></i> Confirmó</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay citas pendientes o confirmadas ese día.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Al abrir WhatsApp, la fila queda marcada como avisada sin recargar.
    document.querySelectorAll('.js-wa').forEach(function (a) {
        a.addEventListener('click', function () {
            var row = a.closest('tr');
            var cell = row.querySelector('[data-sent]');
            if (cell && !cell.dataset.done) {
                if (/Sin avisar/.test(cell.textContent)) {
                    var c = document.getElementById('notified-count');
                    if (c) c.textContent = parseInt(c.textContent, 10) + 1;
                }
                cell.innerHTML = '<span class="text-success"><i class="bi bi-check2-all"></i> Recién avisado</span>';
                cell.dataset.done = '1';
                a.innerHTML = '<i class="bi bi-whatsapp"></i> Reenviar';
            }
        });
    });
</script>
@endpush
