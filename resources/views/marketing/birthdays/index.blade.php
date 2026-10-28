@extends('layouts.app')

@section('title', 'Cumpleaños')

@section('page')
@php
    $ranges = ['today' => 'Hoy', 'week' => 'Próximos 7 días', 'month' => 'Este mes'];
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-cake2"></i> Cumpleaños</h1>
            <p class="text-muted mb-0">Salúdalos por WhatsApp con un clic y, si quieres, regálales un cupón.</p>
        </div>
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Se usa dentro de una empresa.</div>
    @else
        <form class="d-flex gap-2 align-items-end flex-wrap mb-3" method="GET">
            <div class="btn-group btn-group-sm">
                @foreach($ranges as $k => $label)
                    <a href="{{ route('marketing.birthdays', ['range' => $k, 'template_id' => $templateId, 'coupon_id' => $couponId]) }}" class="btn {{ $range === $k ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
            <input type="hidden" name="range" value="{{ $range }}">
            <div>
                <label class="form-label small mb-1">Plantilla</label>
                <select name="template_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($templates as $t)<option value="{{ $t->id }}" @selected($templateId === $t->id)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">Cupón de regalo ({cupon})</label>
                <select name="coupon_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Sin cupón</option>
                    @foreach($coupons as $c)<option value="{{ $c->id }}" @selected((string) $couponId === (string) $c->id)>{{ $c->code }} · {{ $c->valueLabel() }}</option>@endforeach
                </select>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <ul class="list-group list-group-flush">
                @forelse($clients as $c)
                    @php
                        $isToday = $c->birthdate->format('m-d') === today()->format('m-d');
                        $age = $c->birthdate->year > 1900 ? today()->year - $c->birthdate->year : null;
                        $sentLog = $sent->get($c->id);
                    @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 {{ $isToday ? 'bg-warning-subtle' : '' }}">
                        <div>
                            <a href="{{ route('clients.show', $c) }}" class="fw-semibold">{{ $c->full_name }}</a>
                            @if($isToday)<span class="badge bg-warning text-dark">¡Hoy! 🎂</span>@endif
                            <div class="small text-muted">
                                {{ ucfirst($c->birthdate->copy()->year(today()->year)->locale('es')->isoFormat('dddd D [de] MMMM')) }}{{ $age ? ' · cumple ' . $age : '' }}
                                · {{ $c->phone ?: 'sin teléfono' }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small {{ $sentLog ? 'text-success' : 'text-muted' }}" data-sent>{!! $sentLog ? '<i class="bi bi-check2-all"></i> Saludado el ' . $sentLog->sent_at->format('d/m') : '' !!}</span>
                            @if($c->phone)
                                <a href="{{ route('messages.send', ['client' => $c->id, 'type' => 'birthday', 'template' => $templateId, 'coupon' => $couponId]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success js-wa">
                                    <i class="bi bi-whatsapp"></i> Saludar
                                </a>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-4">Nadie cumple años en este periodo. Recuerda cargar la fecha de nacimiento en la ficha del cliente.</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.js-wa').forEach(function (a) {
        a.addEventListener('click', function () {
            var s = a.closest('li').querySelector('[data-sent]');
            if (s) { s.className = 'small text-success'; s.innerHTML = '<i class="bi bi-check2-all"></i> Recién saludado'; }
        });
    });
</script>
@endpush
