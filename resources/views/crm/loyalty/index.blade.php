@extends('layouts.app')

@section('title', 'Fidelización')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-stars"></i> Fidelización</h1>
            <p class="text-muted mb-0">Programa de puntos: los clientes acumulan al comprar y canjean como descuento en el punto de venta.</p>
        </div>
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> La fidelización se configura dentro de una empresa.</div>
    @else
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @if(!$setting->loyalty_enabled)
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="bi bi-pause-circle-fill"></i>
                <div><strong>El programa está desactivado.</strong> Las ventas no acumulan puntos y no se pueden canjear{{ $canEdit ? '. Actívalo en la configuración de abajo.' : '.' }}</div>
            </div>
        @endif

        {{-- Indicadores --}}
        <div class="row g-3 mb-3">
            @foreach([
                ['Puntos en circulación', number_format($kpis['circulation']), 'bi-stars', 'text-warning', 'Saldo total de todos los clientes'],
                ['Pasivo estimado', $setting->money($kpis['liability']), 'bi-cash-stack', 'text-danger', 'Lo que valen esos puntos si se canjean'],
                ['Ganados este mes', number_format($kpis['earned']), 'bi-graph-up-arrow', 'text-success', 'Por compras de clientes'],
                ['Canjeados este mes', number_format($kpis['redeemed']), 'bi-bag-check', 'text-primary', 'Usados como descuento'],
            ] as [$label, $value, $icon, $color, $hint])
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small"><i class="bi {{ $icon }} {{ $color }}"></i> {{ $label }}</div>
                            <div class="fs-4 fw-bold">{{ $value }}</div>
                            <div class="small text-muted">{{ $hint }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            {{-- Movimientos --}}
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0">
                            Movimientos{{ $client ? ' de ' . $client->full_name : '' }}
                            @if($client)
                                <a href="{{ route('loyalty.index') }}" class="small fw-normal ms-1">ver todos</a>
                            @endif
                        </h6>
                        <form method="GET" class="d-flex gap-2">
                            <select name="client_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 220px;">
                                <option value="">Todos los clientes</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected($client && $client->id === $c->id)>{{ $c->full_name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        @if($movements->isEmpty())
                            <p class="text-muted text-center py-4 mb-0">Todavía no hay movimientos de puntos.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Fecha</th>
                                            @unless($client)<th>Cliente</th>@endunless
                                            <th>Tipo</th>
                                            <th>Detalle</th>
                                            <th class="text-end pe-3">Puntos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($movements as $mv)
                                            <tr>
                                                <td class="ps-3 small text-nowrap">{{ $mv->created_at?->format('d/m/Y H:i') }}</td>
                                                @unless($client)
                                                    <td><a href="{{ route('loyalty.index', ['client_id' => $mv->client_id]) }}" class="text-decoration-none">{{ $mv->client?->full_name }}</a></td>
                                                @endunless
                                                <td><span class="badge {{ $mv->typeBadge() }}">{{ $mv->typeLabel() }}</span></td>
                                                <td class="small">
                                                    @if($mv->sale)
                                                        <a href="{{ route('sales.show', $mv->sale) }}" class="text-decoration-none"><code>{{ $mv->sale->sale_number }}</code></a>
                                                    @endif
                                                    {{ $mv->sale && str_contains((string) $mv->notes, $mv->sale->sale_number) ? '' : $mv->notes }}
                                                    @if($mv->type === 'adjust' && $mv->author)<span class="text-muted">· {{ $mv->author->name }}</span>@endif
                                                </td>
                                                <td class="text-end pe-3 fw-semibold {{ $mv->points >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $mv->points >= 0 ? '+' : '−' }}{{ number_format(abs($mv->points)) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mt-3">{{ $movements->links() }}</div>
            </div>

            <div class="col-xl-4">
                {{-- Ajuste manual --}}
                @if($canAdjust)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-plus-slash-minus text-info"></i> Ajuste manual</h6></div>
                        <div class="card-body pt-2">
                            <form action="{{ route('loyalty.adjust') }}" method="POST">
                                @csrf
                                <select name="client_id" class="form-select form-select-sm mb-2" required>
                                    <option value="">Cliente...</option>
                                    @foreach($clients as $c)
                                        <option value="{{ $c->id }}" @selected((old('client_id') ?? $client?->id) == $c->id)>{{ $c->full_name }}</option>
                                    @endforeach
                                </select>
                                <div class="d-flex gap-2 mb-2">
                                    <select name="direction" class="form-select form-select-sm" style="max-width: 110px;">
                                        <option value="add">Sumar</option>
                                        <option value="sub" @selected(old('direction') === 'sub')>Restar</option>
                                    </select>
                                    <input type="number" name="points" class="form-control form-control-sm no-uppercase" min="1" required placeholder="Puntos" value="{{ old('points') }}">
                                </div>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" maxlength="255" required placeholder="Motivo (queda en el historial)" value="{{ old('reason') }}">
                                <button class="btn btn-sm btn-outline-primary w-100">Registrar ajuste</button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Mejores saldos --}}
                @if($topClients->isNotEmpty())
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-trophy text-warning"></i> Mayores saldos</h6></div>
                        <ul class="list-group list-group-flush">
                            @foreach($topClients as $row)
                                <li class="list-group-item d-flex justify-content-between small">
                                    <a href="{{ route('loyalty.index', ['client_id' => $row->client_id]) }}" class="text-decoration-none">{{ $row->client?->full_name }}</a>
                                    <span class="fw-semibold">{{ number_format($row->balance) }} pts</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Configuración --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-gear text-secondary"></i> Configuración</h6></div>
                    <div class="card-body pt-2">
                        <form action="{{ route('loyalty.settings') }}" method="POST" id="loyalty-form">
                            @csrf @method('PUT')
                            <fieldset @disabled(!$canEdit)>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="loyalty_enabled" value="1" id="l-enabled" @checked(old('loyalty_enabled', $setting->loyalty_enabled))>
                                    <label class="form-check-label fw-semibold" for="l-enabled">Programa de puntos activo</label>
                                </div>

                                <label class="form-label small mb-1">Se gana 1 punto por cada</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                                    <input type="number" name="loyalty_amount_per_point" id="l-per" class="form-control no-uppercase" min="0.01" step="0.01" value="{{ old('loyalty_amount_per_point', (float) $setting->loyalty_amount_per_point) }}">
                                    <span class="input-group-text">gastados</span>
                                </div>

                                <label class="form-label small mb-1">Al canjear, 1 punto vale</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                                    <input type="number" name="loyalty_point_value" id="l-value" class="form-control no-uppercase" min="0.0001" step="0.01" value="{{ old('loyalty_point_value', (float) $setting->loyalty_point_value) }}">
                                </div>

                                <label class="form-label small mb-1">Canje mínimo</label>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="number" name="loyalty_min_redeem" id="l-min" class="form-control no-uppercase" min="1" step="1" value="{{ old('loyalty_min_redeem', $setting->loyalty_min_redeem) }}">
                                    <span class="input-group-text">puntos</span>
                                </div>

                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="loyalty_expires" value="1" id="l-expires" @checked(old('loyalty_expires', $setting->loyalty_expiry_months !== null))>
                                    <label class="form-check-label small" for="l-expires">Los puntos vencen por inactividad</label>
                                </div>
                                <div class="input-group input-group-sm mb-2" id="l-months-wrap">
                                    <span class="input-group-text">tras</span>
                                    <input type="number" name="loyalty_expiry_months" id="l-months" class="form-control no-uppercase" min="1" max="120" value="{{ old('loyalty_expiry_months', $setting->loyalty_expiry_months ?? 12) }}">
                                    <span class="input-group-text">meses sin comprar ni canjear</span>
                                </div>

                                <div class="alert alert-light border small mb-3" id="l-example"></div>
                                @if($canEdit)
                                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-check-lg"></i> Guardar configuración</button>
                                @endif
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@if(empty($noCompany))
@push('scripts')
<script>
(function () {
    const cur = @json($setting->currency_symbol);
    const $ = (id) => document.getElementById(id);
    const f = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Ejemplo en vivo de la equivalencia, para que el dueño vea qué está regalando.
    function example() {
        const per = parseFloat($('l-per').value) || 0;
        const value = parseFloat($('l-value').value) || 0;
        const min = parseInt($('l-min').value, 10) || 0;
        const spend = 500;
        const pts = per > 0 ? Math.floor(spend / per) : 0;
        const back = per > 0 ? (value / per) * 100 : 0;

        $('l-months-wrap').classList.toggle('d-none', !$('l-expires').checked);
        $('l-example').innerHTML =
            `<i class="bi bi-lightbulb text-warning"></i> Una compra de <strong>${cur} ${f(spend)}</strong> gana <strong>${pts} puntos</strong>, ` +
            `que valen <strong>${cur} ${f(pts * value)}</strong> al canjearlos. Devuelves el <strong>${back.toFixed(1)} %</strong> de lo gastado. ` +
            `Para canjear se necesitan ${min} puntos (= ${cur} ${f(min * value)}).`;
    }

    $('loyalty-form').addEventListener('input', example);
    $('loyalty-form').addEventListener('change', example);
    example();
})();
</script>
@endpush
@endif
