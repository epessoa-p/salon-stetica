{{-- Pestaña "Membresía y puntos" de la ficha del cliente --}}
<div class="row g-3">
    @if($showMemberships)
        <div class="col-lg-7">
            <h6 class="fw-bold mb-2"><i class="bi bi-award text-danger"></i> Membresía</h6>
            @if($activeMembership)
                @php $m = $activeMembership; @endphp
                <div class="p-3 rounded-3 mb-3" style="background: color-mix(in srgb, #ec4899 7%, #fff); border: 1px solid color-mix(in srgb, #ec4899 25%, #fff);">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-bold">{{ $m['plan'] }}</div>
                            <div class="small text-muted">{{ $m['starts_on'] }} – {{ $m['ends_on'] }}</div>
                        </div>
                        <span class="badge {{ $m['days_left'] <= 7 ? 'bg-warning text-dark' : 'bg-success' }}">
                            {{ $m['days_left'] === 0 ? 'Vence hoy' : 'Quedan ' . $m['days_left'] . ' días' }}
                        </span>
                    </div>
                    @if($m['discount'] > 0)
                        <div class="small mt-2"><i class="bi bi-percent"></i> {{ rtrim(rtrim(number_format($m['discount'], 2), '0'), '.') }} % de descuento en {{ mb_strtolower($m['scope']) }}</div>
                    @endif
                    @foreach($m['sessions'] as $s)
                        <div class="mt-2">
                            <div class="d-flex justify-content-between small">
                                <span>{{ $s['service'] }}</span>
                                <span><strong>{{ $s['remaining'] }}</strong> de {{ $s['included'] }} disponibles</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" style="width: {{ $s['included'] ? round($s['used'] / $s['included'] * 100) : 0 }}%; background: #ec4899;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="border rounded-3 p-3 mb-3 text-center text-muted small">
                    Sin membresía vigente.
                    <a href="{{ route('sales.pos') }}">Vender una desde el punto de venta</a>.
                </div>
            @endif

            @if($membershipHistory->count() > ($activeMembership ? 1 : 0))
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Plan</th><th>Vigencia</th><th class="text-end">Pagado</th><th class="text-center">Estado</th></tr></thead>
                        <tbody>
                            @foreach($membershipHistory as $h)
                                <tr>
                                    <td>{{ $h->plan_name }}</td>
                                    <td class="small text-nowrap">{{ $h->starts_on->format('d/m/Y') }} – {{ $h->ends_on->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ number_format($h->price_paid, 2) }}</td>
                                    <td class="text-center"><span class="badge {{ $h->stateBadge() }}">{{ $h->stateLabel() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if($showPoints)
        <div class="col-lg-5">
            <h6 class="fw-bold mb-2"><i class="bi bi-stars text-warning"></i> Puntos</h6>
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <div class="fs-3 fw-bold {{ $points['balance'] < 0 ? 'text-danger' : '' }}">{{ number_format($points['balance']) }}</div>
                        <div class="small text-muted">puntos ≈ {{ $setting->money($points['value']) }}</div>
                    </div>
                    <a href="{{ route('loyalty.index', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-secondary">Ver movimientos</a>
                </div>
                @unless($pointsEnabled)
                    <div class="small text-warning-emphasis mt-2"><i class="bi bi-pause-circle"></i> El programa de puntos está desactivado.</div>
                @endunless
            </div>

            @if($pointMovements->isNotEmpty())
                <ul class="list-group list-group-flush small">
                    @foreach($pointMovements as $mv)
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <span class="badge {{ $mv->typeBadge() }}">{{ $mv->typeLabel() }}</span>
                                <span class="text-muted">{{ $mv->created_at?->format('d/m/Y') }}</span>
                                @if($mv->sale)<code>{{ $mv->sale->sale_number }}</code>@endif
                            </span>
                            <span class="fw-semibold {{ $mv->points >= 0 ? 'text-success' : 'text-danger' }}">{{ $mv->points >= 0 ? '+' : '−' }}{{ number_format(abs($mv->points)) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
