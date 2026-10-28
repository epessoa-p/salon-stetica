@extends('layouts.app')

@section('title', 'Recibo ' . $number)

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $first = $payments->first();
    $total = (float) $payments->sum('amount');
@endphp
<div class="container-fluid no-print" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-receipt"></i> Recibo {{ $number }}</h1>
            <p class="text-muted mb-0">
                @if($voided)<span class="badge bg-secondary">Anulado</span> · @endif
                {{ $first->paid_at->format('d/m/Y H:i') }}{{ $first->creator ? ' · ' . $first->creator->name : '' }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($client)<a href="{{ route('receivables.client', $client) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Estado de cuenta</a>@endif
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            @if(!$voided && $can('receivables.edit'))
                <form action="{{ route('receivables.receipt.void', $number) }}" method="POST" onsubmit="return confirm('¿Anular el recibo {{ $number }}? La deuda vuelve a quedar pendiente y se quita el ingreso de caja.')">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Anular</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <div><div class="text-muted small">Cliente</div><div class="fw-semibold">{{ $client?->full_name }}</div></div>
                <div class="text-end"><div class="text-muted small">Cobrado</div><div class="fs-3 fw-bold">{{ $setting->money($total) }}</div>
                    <div class="small">{{ \App\Models\PaymentMethod::label($first->payment_method) }}{{ $first->session ? ' · caja ' . $first->session->register?->name : '' }}</div></div>
            </div>
            <table class="table table-sm mb-2">
                <thead class="table-light"><tr><th>Aplicado a</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr><td>Venta {{ $p->receivable?->sale?->sale_number }}</td><td class="text-end">{{ number_format($p->amount, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <div class="small text-muted">Saldo pendiente del cliente después de este cobro: <strong>{{ $setting->money($balance) }}</strong></div>
            @if($first->notes)<div class="small mt-1">Nota: {{ $first->notes }}</div>@endif
        </div>
    </div>
</div>

{{-- Recibo de impresión (80 mm) --}}
<div class="ticket">
    <div class="t-center">
        <div class="t-strong t-big">{{ $setting->invoice_business_name ?: $company?->name }}</div>
        @if($setting->invoice_phone ?: $company?->phone)<div>Tel. {{ $setting->invoice_phone ?: $company?->phone }}</div>@endif
    </div>
    <div class="t-sep"></div>
    <div class="t-center t-strong">RECIBO DE COBRO {{ $number }}{{ $voided ? ' (ANULADO)' : '' }}</div>
    <div>Fecha: {{ $first->paid_at->format('d/m/Y H:i') }}</div>
    <div>Cliente: {{ $client?->full_name }}</div>
    <div class="t-sep"></div>
    @foreach($payments as $p)
        <div class="t-row"><span>Venta {{ $p->receivable?->sale?->sale_number }}</span><span>{{ number_format($p->amount, 2) }}</span></div>
    @endforeach
    <div class="t-sep"></div>
    <div class="t-row t-strong t-big"><span>COBRADO</span><span>{{ $setting->money($total) }}</span></div>
    <div>Pago: {{ \App\Models\PaymentMethod::label($first->payment_method) }}</div>
    <div>Saldo pendiente: {{ $setting->money($balance) }}</div>
    <div class="t-sep"></div>
    <div class="t-center">{{ $setting->invoice_footer ?: '¡Gracias!' }}</div>
</div>
@endsection

@push('styles')
<style>
    .ticket { display: none; }
    @media print {
        @page { size: 80mm auto; margin: 4mm; }
        body, .app-shell { background: #fff !important; }
        .app-sidebar, .app-topbar, .no-print, .offcanvas, .alert { display: none !important; }
        .app-main { padding: 0 !important; }
        .ticket { display: block; width: 72mm; font-family: 'Courier New', monospace; font-size: 11px; line-height: 1.35; color: #000; }
        .t-center { text-align: center; }
        .t-strong { font-weight: 700; }
        .t-big { font-size: 13px; }
        .t-row { display: flex; justify-content: space-between; gap: 6px; }
        .t-sep { border-top: 1px dashed #000; margin: 5px 0; }
    }
</style>
@endpush
