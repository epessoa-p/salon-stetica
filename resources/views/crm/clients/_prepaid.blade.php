{{-- Pestaña "Paquetes y saldo" de la ficha del cliente --}}
<div class="row g-3">
    @if($showPackages)
        <div class="col-lg-7">
            <h6 class="fw-bold mb-2"><i class="bi bi-ticket-perforated text-success"></i> Paquetes de sesiones</h6>
            @forelse($clientPackages as $p)
                <div class="border rounded-3 p-3 mb-2 {{ $p['state'] === 'active' ? '' : 'opacity-75' }}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ $p['name'] }}</div>
                            <div class="small text-muted">
                                Comprado el {{ $p['model']->starts_on->format('d/m/Y') }} · {{ $p['expiry'] }} · {{ $setting->money($p['price_paid']) }}
                                @if($p['model']->sale) · <a href="{{ route('sales.show', $p['model']->sale_id) }}">venta</a>@endif
                            </div>
                        </div>
                        <span class="badge {{ $p['state_badge'] }}">{{ $p['state_label'] }}</span>
                    </div>
                    <div class="mt-2">
                        @foreach($p['items'] as $i)
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span>{{ $i['service'] }}</span>
                                <span><strong>{{ $i['remaining'] }}</strong> de {{ $i['included'] }} disponibles</span>
                            </div>
                            <div class="progress mb-2" style="height: 5px;">
                                <div class="progress-bar bg-success" style="width: {{ $i['included'] ? round(100 * $i['used'] / $i['included']) : 0 }}%"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-muted small">Sin paquetes. Se venden desde el punto de venta.</p>
            @endforelse
        </div>
    @endif

    @if($showCredit)
        <div class="col-lg-5">
            <h6 class="fw-bold mb-2"><i class="bi bi-wallet2 text-primary"></i> Saldo a favor</h6>
            <div class="fs-3 fw-bold {{ $creditBalance > 0 ? 'text-primary' : '' }}">{{ $setting->money($creditBalance) }}</div>
            <p class="small text-muted">Nace de devoluciones reintegradas como saldo y se usa como pago en el punto de venta.</p>
            @if($creditMovements->isNotEmpty())
                <ul class="list-group list-group-flush small">
                    @foreach($creditMovements as $m)
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <span class="badge {{ $m->typeBadge() }}">{{ $m->typeLabel() }}</span>
                                {{ $m->created_at->format('d/m/Y') }}
                                @if($m->saleReturn) · <a href="{{ route('returns.show', $m->saleReturn) }}">{{ $m->saleReturn->return_number }}</a>
                                @elseif($m->sale) · <a href="{{ route('sales.show', $m->sale) }}">{{ $m->sale->sale_number }}</a>@endif
                            </span>
                            <span class="fw-semibold {{ (float) $m->amount < 0 ? 'text-danger' : 'text-success' }}">{{ (float) $m->amount > 0 ? '+' : '' }}{{ number_format($m->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
