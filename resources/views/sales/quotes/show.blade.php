@extends('layouts.app')

@section('title', 'Cotización ' . $quote->quote_number)

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <div>
            <h1 class="mb-1"><i class="bi bi-file-earmark-text"></i> Cotización {{ $quote->quote_number }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $quote->stateBadge() }}">{{ $quote->stateLabel() }}</span>
                · creada el {{ $quote->created_at->format('d/m/Y H:i') }}{{ $quote->creator ? ' por ' . $quote->creator->name : '' }}
                @if($quote->sale) · <a href="{{ route('sales.show', $quote->sale) }}">venta {{ $quote->sale->sale_number }}</a>@endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('quotes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cotizaciones</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir / PDF</button>
            @if($quote->canConvert())
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-outline-success" title="{{ $quote->client?->phone ? 'Enviar al ' . $quote->client->phone : 'Elige el contacto en WhatsApp' }}">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                @if($can('quotes.delete'))
                    <form action="{{ route('quotes.void', $quote) }}" method="POST" onsubmit="return confirm('¿Anular la cotización {{ $quote->quote_number }}?')">
                        @csrf
                        <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Anular</button>
                    </form>
                @endif
                @if($can('sales.create'))
                    <a href="{{ route('sales.pos', ['quote' => $quote->id]) }}" class="btn btn-success"><i class="bi bi-cart-check"></i> Convertir en venta</a>
                @endif
            @endif
        </div>
    </div>

    @if($quote->state() === 'vencida')
        <div class="alert alert-warning no-print"><i class="bi bi-clock-history"></i> Venció el {{ $quote->valid_until->format('d/m/Y') }}. Si la conviertes en venta se cargarán los precios actuales.</div>
    @endif

    {{-- Documento (también es lo que se imprime) --}}
    <div class="card border-0 shadow-sm doc">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <div class="fs-5 fw-bold">{{ $setting->invoice_business_name ?: $company?->name }}</div>
                    <div class="small text-muted">
                        @if($setting->invoice_nit ?: $company?->ruc)NIT/RUC: {{ $setting->invoice_nit ?: $company?->ruc }}<br>@endif
                        @if($setting->invoice_address ?: $company?->address){{ $setting->invoice_address ?: $company?->address }}<br>@endif
                        @if($setting->invoice_phone ?: $company?->phone)Tel. {{ $setting->invoice_phone ?: $company?->phone }}@endif
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-uppercase small text-muted fw-semibold">Cotización</div>
                    <div class="fs-5 fw-bold">{{ $quote->quote_number }}</div>
                    <div class="small">Fecha: {{ $quote->created_at->format('d/m/Y') }}</div>
                    <div class="small">Válida hasta: <strong>{{ $quote->valid_until->format('d/m/Y') }}</strong></div>
                </div>
            </div>

            <div class="mb-3">
                <div class="small text-muted">Para</div>
                <div class="fw-semibold">{{ $quote->clientDisplay() }}</div>
                @if($quote->client?->phone)<div class="small">{{ $quote->client->phone }}</div>@endif
                @if($quote->specialist)<div class="small text-muted">Atiende: {{ $quote->specialist->full_name }}</div>@endif
            </div>

            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Concepto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Desc.</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                    @foreach($quote->items as $item)
                        <tr>
                            <td>
                                {{ $item->description }}
                                @if($item->kind !== 'service')<span class="badge bg-light text-dark border">{{ $item->kindLabel() }}</span>@endif
                            </td>
                            <td class="text-center">{{ $qty($item->quantity) }}</td>
                            <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end">{{ (float) $item->discount > 0 ? number_format($item->discount, 2) : '—' }}</td>
                            <td class="text-end">{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="text-end text-muted">Subtotal</td><td class="text-end">{{ number_format($quote->subtotal, 2) }}</td></tr>
                    @if((float) $quote->discount + (float) $quote->global_discount > 0)
                        <tr><td colspan="4" class="text-end text-muted">Descuentos</td><td class="text-end">−{{ number_format((float) $quote->discount + (float) $quote->global_discount, 2) }}</td></tr>
                    @endif
                    @if((float) $quote->tax > 0)
                        <tr><td colspan="4" class="text-end text-muted">{{ $setting->tax_label }} ({{ $qty($setting->tax_rate) }}%){{ $setting->tax_included ? ' incluido' : '' }}</td><td class="text-end">{{ number_format($quote->tax, 2) }}</td></tr>
                    @endif
                    <tr><td colspan="4" class="text-end fw-bold fs-5">Total</td><td class="text-end fw-bold fs-5">{{ $setting->money($quote->total) }}</td></tr>
                </tfoot>
            </table>

            @if($quote->notes)
                <div class="mt-3 small"><strong>Notas:</strong> {{ $quote->notes }}</div>
            @endif
            <p class="small text-muted mt-4 mb-0">Precios válidos hasta el {{ $quote->valid_until->format('d/m/Y') }}. Esta cotización no reserva horarios ni productos.</p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        @page { size: A4; margin: 14mm; }
        body, .app-shell { background: #fff !important; }
        .app-sidebar, .app-topbar, .no-print, .offcanvas, .alert { display: none !important; }
        .app-main { padding: 0 !important; }
        .doc { box-shadow: none !important; }
    }
</style>
@endpush
