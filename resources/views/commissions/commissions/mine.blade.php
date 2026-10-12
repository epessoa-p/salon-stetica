@extends('layouts.app')

@section('title', 'Mis comisiones')

@section('page')
@php $setting = \App\Models\CompanySetting::current(); @endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-wallet2"></i> Mis comisiones</h1>
            <p class="text-muted mb-0">Lo que ganaste, lo que se te debe y lo que ya se te pagó.</p>
        </div>
    </div>

    @if(!$personal)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i>
            Tu usuario no está vinculado a una ficha de personal en esta empresa, así que no tiene comisiones propias.
        </div>
    @else
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
                    </div>
                    <div class="col-md-4 d-grid">
                        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Ver periodo</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-3">
            @foreach([
                ['Ganado', $kpis['earned'], 'bi-graph-up-arrow', 'text-primary'],
                ['Pendiente de pago', $kpis['pending'], 'bi-hourglass-split', 'text-warning'],
                ['Liquidado', $kpis['settled'], 'bi-check2-circle', 'text-success'],
                ['Propinas', $kpis['tips'], 'bi-heart', 'text-danger'],
            ] as [$label, $value, $icon, $color])
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small"><i class="bi {{ $icon }} {{ $color }}"></i> {{ $label }}</div>
                            <div class="fs-5 fw-bold">{{ $setting->money($value) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Detalle · {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</h6></div>
            <div class="card-body p-0">
                @if($commissions->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Sin comisiones en este periodo.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Fecha</th>
                                    <th>Concepto</th>
                                    <th class="text-end">Base</th>
                                    <th class="text-end">Tasa</th>
                                    <th class="text-end">Importe</th>
                                    <th class="pe-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($commissions as $c)
                                    <tr class="{{ $c->isReversal() ? 'table-danger' : '' }}">
                                        <td class="ps-3 text-nowrap">{{ $c->accrued_at?->format('d/m/Y') }}</td>
                                        <td class="small">{{ $c->concept() }}</td>
                                        <td class="text-end">{{ number_format((float) $c->base, 2) }}</td>
                                        <td class="text-end small">{{ $c->rateLabel() }}</td>
                                        <td class="text-end fw-semibold">{{ number_format((float) $c->amount, 2) }}</td>
                                        <td class="pe-3"><span class="badge {{ $c->statusBadge() }}">{{ $c->statusLabel() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if($tipSales->isNotEmpty())
            <p class="text-muted small mt-3 mb-0">
                <i class="bi bi-heart"></i> Propinas del periodo: {{ $setting->money($kpis['tips']) }}
                ({{ $tipSales->whereNull('tip_settlement_id')->count() }} pendiente(s) de pago).
            </p>
        @endif
    @endif
</div>
@endsection
