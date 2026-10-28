@extends('layouts.app')

@section('title', 'Venta ' . $sale->sale_number)

@section('page')
@php
    $setting = \App\Models\CompanySetting::current($sale->company_id);
    $company = $sale->company;
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $lineName = fn ($item) => match (true) {
        $item->isMembershipSale() => $item->description ?: 'Membresía ' . $item->membershipPlan?->name,
        $item->isPackageSale()    => $item->description ?: 'Paquete ' . $item->servicePackage?->name,
        default                   => $item->service?->name ?? $item->product?->name ?? $item->description,
    };
    $user = auth()->user();
    $can = fn ($p) => $user->is_super_admin || ($company && $user->hasPermissionInCompany($p, $company));
    $returnState = $sale->returnState();
@endphp
<div class="container-fluid no-print" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-receipt"></i> Venta {{ $sale->sale_number }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $sale->status === 'completada' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($sale->status) }}</span>
                @if($returnState !== 'none')
                    <span class="badge bg-warning text-dark">{{ $returnState === 'full' ? 'Devuelta' : 'Devolución parcial' }}</span>
                @endif
                · {{ optional($sale->sale_date)->format('d/m/Y H:i') }}
                @if($sale->quote) · desde la cotización <a href="{{ route('quotes.show', $sale->quote) }}">{{ $sale->quote->quote_number }}</a>@endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Ventas</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir ticket</button>
            @if($canReturn && $can('returns.create'))
                <a href="{{ route('returns.create', $sale) }}" class="btn btn-outline-warning"><i class="bi bi-arrow-return-left"></i> Devolución</a>
            @endif
            @if($sale->status === 'completada' && $returnState === 'none' && $can('sales.void'))
                <form action="{{ route('sales.void', $sale) }}" method="POST" onsubmit="return confirm('¿Anular esta venta? Se revertirá su ingreso de caja si la sesión sigue abierta, se repondrá el stock, se ajustarán las comisiones y los puntos, y se cancelarán las membresías vendidas en ella.')">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Anular</button>
                </form>
            @endif
            <a href="{{ route('sales.pos') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nueva venta</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Cliente</div><div class="fw-semibold">{{ $sale->clientDisplay() }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Profesional principal</div><div class="fw-semibold">{{ $sale->specialist?->full_name ?? '—' }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Método de pago</div><div class="fw-semibold">{{ \App\Models\PaymentMethod::label($sale->payment_method) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Caja</div><div class="fw-semibold">{{ $sale->session?->register?->name ?? '—' }}</div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Concepto</th><th>Atendió</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Desc.</th><th class="text-end pe-3">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($sale->items as $item)
                            @php $benefit = (float) $item->membership_discount + (float) $item->package_discount + (float) $item->promotion_discount; @endphp
                            <tr>
                                <td class="ps-3">
                                    {{ $lineName($item) }}
                                    @if($item->isProduct())<span class="badge bg-warning-subtle text-warning-emphasis">Producto</span>@endif
                                    @if($item->isMembershipSale())<span class="badge bg-danger-subtle text-danger-emphasis">Membresía</span>@endif
                                    @if($item->isPackageSale())<span class="badge bg-success-subtle text-success-emphasis">Paquete</span>@endif
                                    @if($item->combo)<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-collection"></i> {{ $item->combo->name }}</span>@endif
                                    @if((float) $item->promotion_discount > 0)<span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-percent"></i> {{ $item->promotion?->name }}</span>@endif
                                    @if($item->uses_session && $item->clientPackage)
                                        <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-ticket-perforated"></i> Sesión de {{ $item->clientPackage->package_name }}</span>
                                    @elseif($item->uses_session)
                                        <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-award"></i> Sesión incluida</span>
                                    @elseif($benefit > 0)
                                        <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-award"></i> −{{ $qty($item->clientMembership?->discount_percent) }} % membresía</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $item->specialist?->full_name ?? '—' }}</td>
                                <td class="text-center">
                                    {{ $qty($item->quantity) }}
                                    @if((float) $item->returned_quantity > 0)<div class="small text-warning-emphasis">devuelto {{ $qty($item->returned_quantity) }}</div>@endif
                                </td>
                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $item->discount + $benefit, 2) }}</td>
                                <td class="text-end pe-3">{{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="5" class="text-end text-muted">Subtotal</td><td class="text-end pe-3">{{ number_format($sale->subtotal, 2) }}</td></tr>
                        @if((float) $sale->discount > 0)
                            <tr><td colspan="5" class="text-end text-muted">Descuentos</td><td class="text-end pe-3">−{{ number_format($sale->discount, 2) }}</td></tr>
                        @endif
                        @if((float) $sale->membership_discount > 0)
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-award"></i> Beneficio de membresía</td><td class="text-end pe-3">−{{ number_format($sale->membership_discount, 2) }}</td></tr>
                        @endif
                        @if((float) $sale->promotion_discount > 0)
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-percent"></i> Promociones</td><td class="text-end pe-3">−{{ number_format($sale->promotion_discount, 2) }}</td></tr>
                        @endif
                        @if((float) $sale->coupon_discount > 0)
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-ticket"></i> Cupón {{ $sale->coupon?->code }}</td><td class="text-end pe-3">−{{ number_format($sale->coupon_discount, 2) }}</td></tr>
                        @endif
                        @if((float) $sale->package_discount > 0)
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-ticket-perforated"></i> Sesiones de paquete (prepagadas)</td><td class="text-end pe-3">−{{ number_format($sale->package_discount, 2) }}</td></tr>
                        @endif
                        @if((float) $sale->loyalty_discount > 0)
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-stars"></i> Canje de {{ $sale->loyalty_points_redeemed }} puntos</td><td class="text-end pe-3">−{{ number_format($sale->loyalty_discount, 2) }}</td></tr>
                        @endif
                        @if((float) $sale->tax > 0)
                            <tr><td colspan="5" class="text-end text-muted">{{ $setting->tax_label }} ({{ $qty($setting->tax_rate) }}%){{ $setting->tax_included ? ' incluido' : '' }}</td><td class="text-end pe-3">{{ number_format($sale->tax, 2) }}</td></tr>
                        @endif
                        <tr><td colspan="5" class="text-end fw-bold fs-5">Total</td><td class="text-end pe-3 fw-bold fs-5">{{ $setting->money($sale->total) }}</td></tr>
                        @if((float) $sale->credit_applied > 0)
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-wallet2"></i> Pagado con saldo a favor</td><td class="text-end pe-3">{{ number_format($sale->credit_applied, 2) }}</td></tr>
                        @endif
                        @if($sale->on_credit && $sale->receivable)
                            @php $rcv = $sale->receivable; @endphp
                            <tr><td colspan="5" class="text-end text-muted"><i class="bi bi-journal-text"></i> Venta a crédito · quedó pendiente (vence {{ $rcv->due_date->format('d/m/Y') }})</td><td class="text-end pe-3">{{ number_format($rcv->amount, 2) }}</td></tr>
                            <tr><td colspan="5" class="text-end">Saldo por cobrar <span class="badge {{ $rcv->stateBadge() }}">{{ $rcv->stateLabel() }}</span>
                                @if($sale->client)<a href="{{ route('receivables.client', $sale->client) }}" class="small ms-1">estado de cuenta</a>@endif</td><td class="text-end pe-3 fw-semibold">{{ number_format($rcv->balance(), 2) }}</td></tr>
                        @endif
                        @if((float) $sale->returned_total > 0)
                            <tr><td colspan="5" class="text-end text-warning-emphasis"><i class="bi bi-arrow-return-left"></i> Devuelto</td><td class="text-end pe-3 text-warning-emphasis">−{{ number_format($sale->returned_total, 2) }}</td></tr>
                            <tr><td colspan="5" class="text-end fw-semibold">Neto</td><td class="text-end pe-3 fw-semibold">{{ $setting->money($sale->netTotal()) }}</td></tr>
                        @endif
                        @if((float) $sale->tip > 0)
                            <tr>
                                <td colspan="5" class="text-end text-muted">
                                    <i class="bi bi-heart"></i> Propina para {{ $sale->specialist?->full_name ?? 'el profesional' }}
                                    @if($sale->tipSettlement)<span class="badge bg-success">Pagada · {{ $sale->tipSettlement->number }}</span>@else<span class="badge bg-warning text-dark">Pendiente</span>@endif
                                </td>
                                <td class="text-end pe-3">{{ number_format($sale->tip, 2) }}</td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($sale->loyalty_points_earned > 0)
        <div class="alert alert-light border mt-3 mb-0 small">
            <i class="bi bi-stars text-warning"></i> El cliente ganó <strong>{{ $sale->loyalty_points_earned }} puntos</strong> con esta compra{{ $sale->isVoided() ? ' (revertidos al anular)' : '' }}.
        </div>
    @endif

    @if($sale->returns->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-arrow-return-left text-warning"></i> Devoluciones</h6></div>
            <ul class="list-group list-group-flush">
                @foreach($sale->returns as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('returns.show', $r) }}" class="fw-semibold">{{ $r->return_number }}</a>
                            <span class="small text-muted">· {{ $r->return_date->format('d/m/Y H:i') }} · {{ $r->reason }}</span>
                        </div>
                        <span><i class="bi {{ $r->refundIcon() }}"></i> {{ $r->refundLabel() }} · <strong>{{ $setting->money($r->total) }}</strong></span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($sale->notes)
        <div class="mt-3 text-muted"><i class="bi bi-sticky"></i> {{ $sale->notes }}</div>
    @endif
</div>

{{-- ================= Ticket de impresión (80 mm) ================= --}}
<div class="ticket">
    <div class="t-center">
        <div class="t-strong t-big">{{ $setting->invoice_business_name ?: $company?->name }}</div>
        @if($setting->invoice_nit ?: $company?->ruc)<div>NIT/RUC: {{ $setting->invoice_nit ?: $company?->ruc }}</div>@endif
        @if($setting->invoice_address ?: $company?->address)<div>{{ $setting->invoice_address ?: $company?->address }}</div>@endif
        @if($setting->invoice_phone ?: $company?->phone)<div>Tel. {{ $setting->invoice_phone ?: $company?->phone }}</div>@endif
    </div>
    <div class="t-sep"></div>
    <div>Venta: <span class="t-strong">{{ $sale->sale_number }}</span>{{ $sale->isVoided() ? ' (ANULADA)' : '' }}</div>
    <div>Fecha: {{ optional($sale->sale_date)->format('d/m/Y H:i') }}</div>
    <div>Cliente: {{ $sale->clientDisplay() }}</div>
    @if($sale->specialist)<div>Atendió: {{ $sale->specialist->full_name }}</div>@endif
    <div class="t-sep"></div>
    @foreach($sale->items as $item)
        <div>{{ $lineName($item) }}</div>
        <div class="t-row">
            <span>{{ $qty($item->quantity) }} × {{ number_format($item->unit_price, 2) }}{{ $item->uses_session ? ' (sesión incluida)' : '' }}</span>
            <span>{{ number_format($item->total, 2) }}</span>
        </div>
    @endforeach
    <div class="t-sep"></div>
    <div class="t-row"><span>Subtotal</span><span>{{ number_format($sale->subtotal, 2) }}</span></div>
    @if((float) $sale->discount > 0)<div class="t-row"><span>Descuentos</span><span>−{{ number_format($sale->discount, 2) }}</span></div>@endif
    @if((float) $sale->membership_discount > 0)<div class="t-row"><span>Membresía</span><span>−{{ number_format($sale->membership_discount, 2) }}</span></div>@endif
    @if((float) $sale->promotion_discount > 0)<div class="t-row"><span>Promociones</span><span>−{{ number_format($sale->promotion_discount, 2) }}</span></div>@endif
    @if((float) $sale->coupon_discount > 0)<div class="t-row"><span>Cupón {{ $sale->coupon?->code }}</span><span>−{{ number_format($sale->coupon_discount, 2) }}</span></div>@endif
    @if((float) $sale->package_discount > 0)<div class="t-row"><span>Paquete</span><span>−{{ number_format($sale->package_discount, 2) }}</span></div>@endif
    @if((float) $sale->loyalty_discount > 0)<div class="t-row"><span>Puntos ({{ $sale->loyalty_points_redeemed }})</span><span>−{{ number_format($sale->loyalty_discount, 2) }}</span></div>@endif
    @if((float) $sale->tax > 0)<div class="t-row"><span>{{ $setting->tax_label }}{{ $setting->tax_included ? ' incl.' : '' }}</span><span>{{ number_format($sale->tax, 2) }}</span></div>@endif
    <div class="t-row t-strong t-big"><span>TOTAL</span><span>{{ $setting->money($sale->total) }}</span></div>
    @if((float) $sale->tip > 0)<div class="t-row"><span>Propina</span><span>{{ number_format($sale->tip, 2) }}</span></div>@endif
    @if((float) $sale->credit_applied > 0)<div class="t-row"><span>Saldo a favor</span><span>−{{ number_format($sale->credit_applied, 2) }}</span></div>@endif
    <div>Pago: {{ \App\Models\PaymentMethod::label($sale->payment_method) }}</div>
    @if($sale->on_credit && $sale->receivable)<div class="t-row"><span>A CRÉDITO · saldo</span><span>{{ number_format($sale->receivable->balance(), 2) }}</span></div><div>Vence: {{ $sale->receivable->due_date->format('d/m/Y') }}</div>@endif
    @if($sale->loyalty_points_earned > 0 && !$sale->isVoided())<div>Puntos ganados: {{ $sale->loyalty_points_earned }}</div>@endif
    <div class="t-sep"></div>
    <div class="t-center">{{ $setting->invoice_footer ?: '¡Gracias por tu visita!' }}</div>
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
        .ticket {
            display: block; width: 72mm; font-family: 'Courier New', monospace;
            font-size: 11px; line-height: 1.35; color: #000;
        }
        .t-center { text-align: center; }
        .t-strong { font-weight: 700; }
        .t-big { font-size: 13px; }
        .t-row { display: flex; justify-content: space-between; gap: 6px; }
        .t-sep { border-top: 1px dashed #000; margin: 5px 0; }
    }
</style>
@endpush
