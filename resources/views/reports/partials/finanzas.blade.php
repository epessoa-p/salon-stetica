{{-- Reporte de finanzas. Recibe $finance (FinanceReport::build), $setting, $from, $to, $financeFirstDate. --}}
@php
    use Illuminate\Support\Carbon;
    $money = fn ($n) => $setting->money($n);
    $tab = $finance['tab'];
    $link = fn (array $over = []) => route('reports.index', array_merge([
        'type' => 'finanzas', 'tab' => $tab, 'from' => $from, 'to' => $to,
        'scope' => $finance['scope'] ?? request('scope'), 'dir' => $finance['direction'] ?? request('dir'),
    ], $over));
    $presets = ['hoy' => 'Hoy', 'semana' => 'Esta semana', 'semana_ant' => 'Semana ant.', 'mes' => 'Este mes', 'mes_ant' => 'Mes ant.', 'todo' => 'Todo'];
    $dmy = fn ($d) => Carbon::parse($d)->format('d/m/Y');
    $today = today()->toDateString();
    $allTime = $from === $financeFirstDate && $to === $today;
    $range = ($allTime ? 'Desde el inicio' : ($from === $to ? $dmy($from) : 'Desde ' . $dmy($from))) . ($from === $to && !$allTime ? '' : ' — ' . ($to === $today ? 'hoy' : $dmy($to)));
@endphp

<div class="d-flex flex-wrap gap-2 mb-2">
    @foreach($presets as $key => $label)
        @php
            [$a, $b] = \App\Services\FinanceReport::presetRange($key, $company_id);
            $isOn = $a->toDateString() === $from && $b->toDateString() === $to;
        @endphp
        <a class="btn btn-sm {{ $isOn ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $link(['from' => $a->toDateString(), 'to' => $b->toDateString()]) }}">{{ $label }}</a>
    @endforeach
</div>
<p class="small text-muted mb-3"><i class="bi bi-calendar3"></i> {{ $range }}</p>

<ul class="nav nav-pills mb-3">
    @foreach(['resultados' => ['Resultados', 'bi-clipboard-data'], 'movimientos' => ['Movimientos', 'bi-arrow-left-right'], 'cierres' => ['Cierres', 'bi-lock']] as $key => [$label, $icon])
        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ $link(['tab' => $key]) }}"><i class="bi {{ $icon }}"></i> {{ $label }}</a></li>
    @endforeach
</ul>

@if($tab === 'resultados')
    @foreach(['income' => ['Ingresos', 'text-success', 'Total ingresos'], 'expense' => ['Egresos', 'text-danger', 'Total egresos']] as $key => [$title, $tone, $totalLabel])
        @php $side = $finance[$key]; @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="fw-bold {{ $tone }}">{{ $title }}</h5>
                @forelse($side['rows'] as $row)
                    <div class="d-flex justify-content-between py-1"><span>{{ $row['label'] }}</span><span>{{ $money($row['amount']) }}</span></div>
                @empty
                    <p class="text-muted mb-0">Sin movimientos en el período.</p>
                @endforelse
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold"><span>{{ $totalLabel }}</span><span class="{{ $tone }}">{{ $money($side['total']) }}</span></div>
                <div class="small text-muted mt-1">
                    Caja {{ $money($side['caja']) }} · Tesorería {{ $money($side['tesoreria']) }}
                    @if($side['otro'] > 0) · Sin origen {{ $money($side['otro']) }} @endif
                </div>
            </div>
        </div>
    @endforeach

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="text-muted">Resultado acumulado</div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="fs-5 fw-bold">{{ $finance['profit'] >= 0 ? 'Utilidad' : 'Pérdida' }}</span>
                <span class="fs-3 fw-bold {{ $finance['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($finance['profit']) }}</span>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between"><span class="fw-bold">Aportes de capital</span><span class="fw-bold">{{ $money($finance['capital']['added']) }}</span></div>
            @if($finance['capital']['taken'] > 0)
                <div class="d-flex justify-content-between"><span>Retiros</span><span>{{ $money($finance['capital']['taken']) }}</span></div>
            @endif
            <div class="small text-muted mt-1">Dinero que puso el dueño. No es ganancia: no entra en la utilidad.</div>
        </div>
    </div>

    @if($finance['sales_without_cash']['count'] > 0)
        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i>
            {{ $finance['sales_without_cash']['count'] }} venta(s) por {{ $money($finance['sales_without_cash']['amount']) }} se registraron sin caja abierta: no pasaron por caja y no están en estos ingresos.
        </div>
    @endif
    <p class="small text-muted"><i class="bi bi-info-circle"></i> {{ $finance['note'] }}</p>

@elseif($tab === 'movimientos')
    @php
        $scope = $finance['scope'];
        $direction = $finance['direction'];
        $summary = $finance['summary'];
        $days = collect($finance['items'])->groupBy('date');
        $hints = [
            'caja' => 'Solo movimientos de caja: es lo que cuadra con los cierres.',
            'tesoreria' => 'Solo movimientos de las cuentas, también las transferencias.',
            'todo' => 'Caja y cuentas juntas, sin las transferencias entre ellas.',
        ];
    @endphp
    <div class="btn-group mb-2" role="group" aria-label="Origen">
        @foreach(['caja' => 'Caja', 'tesoreria' => 'Tesorería', 'todo' => 'Todo'] as $key => $label)
            <a class="btn btn-sm {{ $scope === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $link(['scope' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <p class="small text-muted">{{ $hints[$scope] }}</p>

    <div class="row g-3 mb-3">
        @foreach([['Ingresos', $summary['income'], 'text-success'], ['Egresos', $summary['expense'], 'text-danger'], ['Balance', $summary['balance'], $summary['balance'] >= 0 ? 'text-success' : 'text-danger']] as [$label, $value, $tone])
            <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3">
                <div class="small text-muted">{{ $label }}</div>
                <div class="fs-5 fw-bold {{ $tone }}">{{ $money($value) }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div class="d-flex gap-2 mb-3">
        @foreach(['todos' => 'Todos', 'ingresos' => 'Ingresos', 'egresos' => 'Egresos'] as $key => $label)
            <a class="btn btn-sm {{ $direction === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $link(['dir' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="fw-bold">Por categoría</h6>
            @forelse($finance['categories'] as $c)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small"><span>{{ $c['label'] }}</span><span class="fw-semibold">{{ $money($c['amount']) }}</span></div>
                    <div class="progress" style="height: 6px;"><div class="progress-bar {{ $c['direction'] === 'in' ? 'bg-success' : 'bg-danger' }}" style="width: {{ $c['share'] * 100 }}%"></div></div>
                </div>
            @empty
                <p class="text-muted mb-0">Sin movimientos en el período.</p>
            @endforelse
        </div>
    </div>

    <h6 class="fw-bold">Movimientos ({{ $finance['count'] }})</h6>
    @if($finance['count'] > $finance['limit'])
        <p class="small text-muted">Se muestran los {{ $finance['limit'] }} más recientes.</p>
    @endif
    @foreach($days as $day => $items)
        <div class="small fw-semibold text-muted mt-3 mb-1">{{ ucfirst(Carbon::parse($day)->locale('es')->isoFormat('dddd D [de] MMMM')) }}</div>
        <div class="card border-0 shadow-sm"><ul class="list-group list-group-flush">
            @foreach($items as $m)
                @php $in = $m['direction'] === 'in'; @endphp
                <li class="list-group-item d-flex align-items-center gap-2">
                    <i class="bi {{ $in ? 'bi-arrow-up-circle-fill text-success' : 'bi-arrow-down-circle-fill text-danger' }} fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold {{ $m['cancelled'] ? 'text-decoration-line-through' : '' }}">{{ $m['concept'] }}</div>
                        <div class="small text-muted">{{ collect([$m['category'], $m['time'], $m['origin'], $m['method']])->filter()->implode(' · ') }}@if($m['cancelled']) · Anulado @endif</div>
                    </div>
                    <span class="fw-bold {{ $in ? 'text-success' : 'text-danger' }}">{{ ($in ? '+' : '−') . ' ' . $money($m['amount']) }}</span>
                </li>
            @endforeach
        </ul></div>
    @endforeach

@else
    @if(empty($finance['sessions']))
        <div class="alert alert-light border">No hay turnos de caja en el período.</div>
    @endif
    @if($finance['count'] > $finance['limit'])
        <p class="small text-muted">Se muestran los {{ $finance['limit'] }} más recientes de {{ $finance['count'] }}.</p>
    @endif
    @php $at = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y H:i') : ''; @endphp
    <div class="row g-3">
        @foreach($finance['sessions'] as $s)
            @php $diff = $s['difference']; @endphp
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-bold">{{ collect([$s['register'], $s['branch']])->filter()->implode(' · ') }}</span>
                        <span class="badge rounded-pill {{ $s['status'] === 'open' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $s['status'] === 'open' ? 'Abierta' : 'Cerrada' }}</span>
                    </div>
                    <div class="small text-muted">Abrió {{ $s['opened_by'] ?? '—' }} · {{ $at($s['opened_at']) }}</div>
                    @if($s['closed_at'])
                        <div class="small text-muted">Cerró {{ $s['closed_by'] ?? '—' }} · {{ $at($s['closed_at']) }}</div>
                    @endif
                    <hr class="my-2">
                    <div class="d-flex justify-content-between"><span>Apertura</span><span>{{ $money($s['opening']) }}</span></div>
                    <div class="d-flex justify-content-between"><span>Ingresos efectivo</span><span class="text-success">{{ $money($s['cash_in']) }}</span></div>
                    <div class="d-flex justify-content-between"><span>Egresos efectivo</span><span class="text-danger">{{ $money($s['cash_out']) }}</span></div>
                    @if(abs($s['others_net']) >= 0.005)
                        <div class="d-flex justify-content-between"><span>Otros medios</span><span>{{ $money($s['others_net']) }}</span></div>
                    @endif
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold"><span>Esperado en caja</span><span>{{ $money($s['expected']) }}</span></div>
                    @if($s['counted'] !== null)
                        <div class="d-flex justify-content-between"><span>Contado</span><span>{{ $money($s['counted']) }}</span></div>
                        <div class="d-flex justify-content-between"><span>Diferencia</span>
                            <span class="fw-bold {{ abs((float) $diff) < 0.005 ? 'text-success' : 'text-danger' }}">
                                {{ abs((float) $diff) < 0.005 ? 'Exacto' : ($diff > 0 ? 'Sobrante ' : 'Faltante ') . $money(abs($diff)) }}
                            </span>
                        </div>
                    @endif
                    @if(!empty($s['others']))
                        <div class="small text-muted mt-2">Otros medios: {{ collect($s['others'])->map(fn ($o) => $o['method'] . ' ' . $money($o['amount']))->implode(' · ') }}</div>
                    @endif
                </div></div>
            </div>
        @endforeach
    </div>
@endif
