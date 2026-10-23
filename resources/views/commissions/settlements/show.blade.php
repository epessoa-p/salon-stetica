@extends('layouts.app')

@section('title', 'Liquidación ' . $settlement->number)

@section('page')
@php
    $company = auth()->user()->getCurrentCompany();
    $setting = \App\Models\CompanySetting::current($settlement->company_id);
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
        <div>
            <h1 class="mb-1"><i class="bi bi-journal-check"></i> Liquidación {{ $settlement->number }}</h1>
            <p class="text-muted mb-0">Comprobante de pago de comisiones.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('commission-settlements.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Historial</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        </div>
    </div>


    <div class="card border-0 shadow-sm receipt">
        <div class="card-body">
            {{-- Encabezado del comprobante --}}
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <div class="fw-bold fs-5">{{ $setting->invoice_business_name ?: $company?->name }}</div>
                    @if($setting->invoice_nit ?: $company?->ruc)<div class="small text-muted">NIT/RUC: {{ $setting->invoice_nit ?: $company?->ruc }}</div>@endif
                    @if($setting->invoice_address ?: $company?->address)<div class="small text-muted">{{ $setting->invoice_address ?: $company?->address }}</div>@endif
                </div>
                <div class="text-end">
                    <div class="text-muted small">Liquidación de comisiones</div>
                    <div class="fw-bold fs-4"><code>{{ $settlement->number }}</code></div>
                    <div class="small text-muted">Pagada el {{ $settlement->paid_at->format('d/m/Y H:i') }}</div>
                </div>
            </div>

            <div class="row g-3 mb-3 small">
                <div class="col-sm-4"><div class="text-muted">Profesional</div><div class="fw-semibold">{{ $settlement->specialist?->full_name }}</div></div>
                <div class="col-sm-4"><div class="text-muted">Periodo</div><div class="fw-semibold">{{ $settlement->period_start->format('d/m/Y') }} – {{ $settlement->period_end->format('d/m/Y') }}</div></div>
                <div class="col-sm-4"><div class="text-muted">Pagado por</div><div class="fw-semibold">{{ $settlement->payer?->name ?? '—' }}</div></div>
                <div class="col-sm-4"><div class="text-muted">Caja</div><div class="fw-semibold">{{ $settlement->session?->register?->name ?? 'Sin caja' }}</div></div>
                @if($settlement->notes)
                    <div class="col-sm-8"><div class="text-muted">Notas</div><div>{{ $settlement->notes }}</div></div>
                @endif
            </div>

            {{-- Comisiones --}}
            <h6 class="fw-bold mt-4 mb-2">Comisiones ({{ $settlement->commissions_count }})</h6>
            @if($settlement->commissions->isEmpty())
                <p class="text-muted small">Esta liquidación no incluye comisiones.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Venta</th>
                                <th>Concepto</th>
                                <th class="text-end">Base</th>
                                <th class="text-end">Tasa</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->commissions as $c)
                                <tr class="{{ $c->isReversal() ? 'table-danger' : '' }}">
                                    <td class="text-nowrap">{{ $c->accrued_at?->format('d/m/Y') }}</td>
                                    <td><code>{{ $c->sale?->sale_number ?? '—' }}</code></td>
                                    <td class="small">{{ $c->concept() }}</td>
                                    <td class="text-end">{{ number_format((float) $c->base, 2) }}</td>
                                    <td class="text-end small">{{ $c->rateLabel() }}</td>
                                    <td class="text-end">{{ number_format((float) $c->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Propinas --}}
            @if($settlement->tipSales->isNotEmpty())
                <h6 class="fw-bold mt-4 mb-2">Propinas</h6>
                <table class="table table-sm align-middle">
                    <tbody>
                        @foreach($settlement->tipSales as $s)
                            <tr>
                                <td class="text-nowrap">{{ $s->sale_date?->format('d/m/Y') }}</td>
                                <td><code>{{ $s->sale_number }}</code></td>
                                <td class="text-end">{{ number_format((float) $s->tip, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Totales: guardados al pagar, nunca recalculados --}}
            <div class="row justify-content-end mt-3">
                <div class="col-sm-6 col-md-5">
                    <div class="d-flex justify-content-between"><span class="text-muted">Comisiones</span><span>{{ number_format((float) $settlement->commissions_total, 2) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Propinas</span><span>{{ number_format((float) $settlement->tips_total, 2) }}</span></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold fs-5"><span>Total pagado</span><span>{{ $setting->money($settlement->total) }}</span></div>
                </div>
            </div>

            {{-- Firmas --}}
            <div class="row mt-5 pt-4 text-center small">
                <div class="col-6"><div class="border-top mx-4 pt-2">Recibí conforme<br><strong>{{ $settlement->specialist?->full_name }}</strong></div></div>
                <div class="col-6"><div class="border-top mx-4 pt-2">Entregué<br><strong>{{ $settlement->payer?->name }}</strong></div></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        .app-sidebar, .app-topbar, .no-print, .offcanvas { display: none !important; }
        .app-main { padding: 0 !important; }
        .receipt { box-shadow: none !important; }
        body, .app-shell { background: #fff !important; }
    }
</style>
@endpush
