{{-- Reporte de inventario. Recibe $inventory (InventoryReport::build), $inventoryMovements, $setting, $from, $to. --}}
@php
    use Illuminate\Support\Carbon;
    $money = fn ($n) => $setting->money($n);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $s = $inventory['summary'];
    $restock = $inventory['restock'];
    $idle = $inventory['idle'];
    $below = $inventory['below_cost'];
    $selected = $inventory['warehouse_id'];
    $link = fn ($id) => route('reports.index', array_filter(['type' => 'inventario', 'from' => $from, 'to' => $to, 'warehouse' => $id]));
@endphp

<p class="small text-muted"><i class="bi bi-info-circle"></i> El inventario es el de hoy. Las fechas solo aplican a «Movimientos del período», al final.</p>

@if(count($inventory['warehouses']) > 1)
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm {{ !$selected ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $link(null) }}">Todos los almacenes</a>
        @foreach($inventory['warehouses'] as $w)
            <a class="btn btn-sm {{ $selected === $w['id'] ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $link($w['id']) }}">{{ $w['name'] }}</a>
        @endforeach
    </div>
@endif

<div class="row g-3 mb-3">
    @foreach([
        ['Productos', number_format($s['products']), 'bi-box', ''],
        ['Unidades', $qty($s['units']), 'bi-layers', ''],
        ['Valor a costo', $money($s['cost']), 'bi-receipt', ''],
        ['Valor a venta', $money($s['sale']), 'bi-tag', 'text-success'],
    ] as [$label, $value, $icon, $tone])
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="small text-muted text-uppercase"><i class="bi {{ $icon }}"></i> {{ $label }}</div>
            <div class="fs-5 fw-bold {{ $tone }}">{{ $value }}</div>
        </div></div></div>
    @endforeach
    <div class="col-12"><div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, var(--bs-primary), #CF7F92);"><div class="card-body py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="small text-uppercase"><i class="bi bi-graph-up-arrow"></i> Ganancia potencial</div>
            <div class="fs-4 fw-bold">{{ $money($s['profit']) }}</div>
        </div>
        <span class="badge rounded-pill bg-light text-dark fs-6">Margen {{ number_format($s['margin'], 1) }}%</span>
    </div></div></div>
</div>

@if($inventory['no_cost'] > 0)
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> {{ $inventory['no_cost'] }} producto(s) con existencias no tienen costo cargado: la ganancia potencial sale más alta de lo real.</div>
@endif

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h5 class="fw-bold">Por categoría</h5>
    @forelse($inventory['categories'] as $c)
        <div class="mb-3">
            <div class="d-flex justify-content-between"><span>{{ $c['name'] }}</span><span class="fw-bold">{{ $money($c['sale']) }}</span></div>
            <div class="small text-muted">{{ $c['products'] }} {{ $c['products'] === 1 ? 'producto' : 'productos' }} · {{ $qty($c['units']) }} unid. · costo {{ $money($c['cost']) }}</div>
            <div class="progress mt-1" style="height: 6px;"><div class="progress-bar" style="width: {{ $c['share'] * 100 }}%"></div></div>
        </div>
    @empty
        <p class="text-muted mb-0">No hay productos activos.</p>
    @endforelse
</div></div>

<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h5 class="fw-bold mb-1">Para reponer</h5>
        <div class="d-flex gap-2 mb-2">
            <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis">{{ $restock['out'] }} {{ $restock['out'] === 1 ? 'agotado' : 'agotados' }}</span>
            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">{{ $restock['low'] }} con stock bajo</span>
        </div>
        <div class="small text-muted mb-2">Llegar al mínimo cuesta <strong>{{ $money($restock['cost']) }}</strong>.@if($selected) Se calcula con el total de todos los almacenes, porque el mínimo es por producto.@endif</div>
        @forelse($restock['items'] as $p)
            <div class="d-flex justify-content-between border-top py-1">
                <div><span class="{{ $p['status'] === 'agotado' ? 'text-danger fw-semibold' : '' }}">{{ $p['name'] }}</span>
                    <div class="small text-muted">Stock {{ $qty($p['stock']) }} · mínimo {{ $qty($p['min']) }}</div></div>
                <div class="text-end small">@if($p['missing'] > 0) Faltan {{ $qty($p['missing']) }}<div class="text-muted">{{ $money($p['cost']) }}</div>@endif</div>
            </div>
        @empty
            <p class="text-muted mb-0">Nada por reponer.</p>
        @endforelse
        @if($restock['count'] > count($restock['items']))<div class="small text-muted mt-1">Y {{ $restock['count'] - count($restock['items']) }} más.</div>@endif
    </div></div></div>

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h5 class="fw-bold mb-1">Sin movimiento</h5>
        <div class="small text-muted mb-2">Sin salidas en {{ $idle['days'] }} días: <strong>{{ $money($idle['value']) }}</strong> detenidos en {{ $idle['count'] }} producto(s).</div>
        @forelse($idle['items'] as $p)
            <div class="d-flex justify-content-between border-top py-1">
                <div>{{ $p['name'] }}
                    <div class="small text-muted">{{ $qty($p['units']) }} unid. · {{ $p['last_exit'] ? 'última salida ' . Carbon::parse($p['last_exit'])->format('d/m/Y') : 'nunca ha salido' }}</div></div>
                <div class="text-end small fw-semibold">{{ $money($p['value']) }}</div>
            </div>
        @empty
            <p class="text-muted mb-0">Todo se está moviendo.</p>
        @endforelse
        @if($idle['count'] > count($idle['items']))<div class="small text-muted mt-1">Y {{ $idle['count'] - count($idle['items']) }} más.</div>@endif
    </div></div></div>

    @if($below['count'] > 0)
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h5 class="fw-bold mb-1">Precio por debajo del costo</h5>
            <div class="small text-muted mb-2">{{ $below['count'] }} producto(s) se venden sin ganar nada o perdiendo.</div>
            @foreach($below['items'] as $p)
                <div class="d-flex justify-content-between border-top py-1">
                    <span>{{ $p['name'] }}</span>
                    <span class="small">Costo {{ $money($p['cost']) }} · <span class="text-danger fw-semibold">Precio {{ $money($p['price']) }}</span></span>
                </div>
            @endforeach
        </div></div></div>
    @endif

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h5 class="fw-bold mb-1">Movimientos del período</h5>
        <table class="table table-sm mb-0">
            <thead><tr><th>Tipo</th><th class="text-end">Movimientos</th><th class="text-end">Cantidad</th></tr></thead>
            <tbody>
                @forelse($inventoryMovements as $m)
                    <tr><td>{{ $m['type'] }}</td><td class="text-end">{{ $m['count'] }}</td><td class="text-end">{{ $qty($m['quantity']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Sin movimientos en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
</div>
