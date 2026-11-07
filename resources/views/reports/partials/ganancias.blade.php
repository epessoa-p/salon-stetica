{{-- Reporte de ganancias. Recibe $profit (ProfitReport::build), $setting, $from, $to. --}}
@php
    $money = fn ($n) => $setting->money($n);
    $marginClass = fn ($m) => $m >= 0 ? 'text-success' : 'text-danger';
    $scope = $profit['scope'];
    $presets = ['hoy' => 'Hoy', 'semana' => 'Esta semana', 'semana_ant' => 'Semana ant.', 'mes' => 'Este mes', 'mes_ant' => 'Mes ant.'];
    $s = $profit['summary'];
    $rankings = array_filter(['Productos' => $profit['product_rank'], 'Servicios' => $profit['service_rank']]);
@endphp

<div class="d-flex flex-wrap gap-2 mb-2">
    @foreach($presets as $key => $label)
        @php [$a, $b] = \App\Services\ProfitReport::presetRange($key); $isOn = $a->toDateString() === $from && $b->toDateString() === $to; @endphp
        <a class="btn btn-sm {{ $isOn ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reports.index', ['type' => 'ganancias', 'scope' => $scope, 'from' => $a->toDateString(), 'to' => $b->toDateString()]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="btn-group mb-3" role="group" aria-label="Qué incluir">
    @foreach(['ambos' => ['Ambos', 'bi-intersect'], 'productos' => ['Productos', 'bi-cart'], 'servicios' => ['Servicios', 'bi-scissors']] as $key => [$label, $icon])
        <a class="btn btn-sm {{ $scope === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('reports.index', ['type' => 'ganancias', 'scope' => $key, 'from' => $from, 'to' => $to]) }}"><i class="bi {{ $icon }}"></i> {{ $label }}</a>
    @endforeach
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <div class="text-muted">Ganancia</div>
                <div class="display-6 fw-bold {{ $marginClass($s['profit']) }}">{{ $money($s['profit']) }}</div>
            </div>
            <span class="badge rounded-pill bg-success-subtle text-success-emphasis fs-6">Margen {{ number_format($s['margin'], 1) }}%</span>
        </div>
        <hr>
        <div class="row">
            <div class="col-6"><div class="small text-muted">Ingresos</div><div class="fs-5 fw-bold">{{ $money($s['revenue']) }}</div></div>
            <div class="col-6"><div class="small text-muted">Costo</div><div class="fs-5 fw-bold">{{ $money($s['cost']) }}</div></div>
        </div>
    </div>
</div>

<p class="small text-muted mb-3"><i class="bi bi-info-circle"></i> {{ $profit['note'] }}</p>

<div class="row g-3 mb-3">
    @foreach(['productos' => ['Productos', 'bi-cart', $profit['products'], 'Costo de productos'], 'servicios' => ['Servicios', 'bi-scissors', $profit['services'], null]] as $key => [$title, $icon, $b, $costLabel])
        @continue($scope !== 'ambos' && $scope !== $key)
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <h6 class="fw-bold mb-0"><i class="bi {{ $icon }} text-primary"></i> {{ $title }}</h6>
                        <span class="small text-muted">{{ $b['count'] }} ventas</span>
                    </div>
                    <div class="d-flex justify-content-between py-1"><span>Ingresos</span><span>{{ $money($b['revenue']) }}</span></div>
                    @if($costLabel)<div class="d-flex justify-content-between py-1 text-muted"><span>{{ $costLabel }}</span><span>− {{ $money($b['cost']) }}</span></div>@endif
                    <div class="d-flex justify-content-between py-1 text-muted"><span>Comisiones</span><span>− {{ $money($b['commissions']) }}</span></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold"><span>Ganancia</span><span class="{{ $marginClass($b['profit']) }}">{{ $money($b['profit']) }} <small class="fw-normal">({{ number_format($b['margin'], 1) }}%)</small></span></div>
                </div>
            </div>
        </div>
    @endforeach
    @if($profit['net'])
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-piggy-bank text-primary"></i> Después de gastos</h6>
                    <div class="d-flex justify-content-between py-1"><span>Ganancia</span><span>{{ $money($s['profit']) }}</span></div>
                    <div class="d-flex justify-content-between py-1 text-muted"><span>Gastos operativos</span><span>− {{ $money($profit['net']['expenses']) }}</span></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold"><span>Ganancia neta</span><span class="{{ $marginClass($profit['net']['profit']) }}">{{ $money($profit['net']['profit']) }}</span></div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-calendar3 text-primary"></i> Por día</h6>
                @forelse($profit['days'] as $d)
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><span class="fw-semibold">{{ \Illuminate\Support\Carbon::parse($d['date'])->locale('es')->isoFormat('ddd DD/MM') }}</span> <small class="text-muted ms-2">Vendido {{ $money($d['sold']) }}</small></span>
                        <span class="fw-bold {{ $marginClass($d['profit']) }}">{{ $money($d['profit']) }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Sin ventas en el período.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        @foreach($rankings as $title => $rank)
            @php $paneId = 'rank-' . \Illuminate\Support\Str::slug($title); @endphp
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-trophy text-warning"></i> {{ $title }}</h6>
                    <ul class="nav nav-pills nav-fill mb-2" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active py-1" data-bs-toggle="pill" data-bs-target="#{{ $paneId }}-top" type="button" role="tab">Más ganancia</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#{{ $paneId }}-low" type="button" role="tab">Margen bajo</button></li>
                    </ul>
                    <div class="tab-content">
                        @foreach(['top', 'low'] as $key)
                            <div class="tab-pane fade {{ $key === 'top' ? 'show active' : '' }}" id="{{ $paneId }}-{{ $key }}" role="tabpanel">
                                @forelse($rank[$key] as $r)
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <div>
                                            <div class="fw-semibold">{{ $r['name'] }}</div>
                                            <small class="text-muted">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }} u. · vendido {{ $money($r['sold']) }}</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold">{{ $money($r['profit']) }}</div>
                                            <small class="{{ $marginClass($r['margin']) }}">{{ number_format($r['margin'], 1) }}%</small>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">Sin datos en el período.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-receipt text-primary"></i> Ventas</h6>
                    <span class="small text-muted">{{ $profit['sales_count'] }} registros{{ $profit['sales_count'] > count($profit['sales']) ? ' · últimas ' . count($profit['sales']) : '' }}</span>
                </div>
                @forelse($profit['sales'] as $r)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $r['number'] }}</div>
                            <small class="text-muted">{{ \Illuminate\Support\Carbon::parse($r['at'])->format('d/m H:i') }} · {{ $r['client'] }}</small>
                            <div><small class="text-muted">Vendido {{ $money($r['sold']) }} · costo {{ $money($r['cost']) }}</small></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold {{ $marginClass($r['profit']) }}">{{ $money($r['profit']) }}</div>
                            <small class="{{ $marginClass($r['margin']) }}">{{ number_format($r['margin'], 1) }}%</small>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Sin ventas en el período.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
