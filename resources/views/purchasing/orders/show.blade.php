@extends('layouts.app')

@section('title', 'Orden ' . $order->entry_number)

@section('page')
<div class="container-fluid" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-file-earmark-text"></i> Orden {{ $order->entry_number }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $order->statusBadge() }}">{{ $order->statusLabel() }}</span>
                · {{ optional($order->entry_date)->format('d/m/Y') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            @if($order->status === 'pendiente')
                @if($paysOnReceipt)
                    <button type="button" class="btn btn-success text-nowrap" data-bs-toggle="modal" data-bs-target="#receive-modal"><i class="bi bi-box-arrow-in-down"></i> Recibir y pagar</button>
                @else
                <form action="{{ route('purchase-orders.receive', $order) }}" method="POST" class="d-flex gap-2" onsubmit="return confirm('¿Recibir la orden? Se sumará el stock de cada producto en el almacén elegido.')">
                    @csrf
                    @if($warehouses->count() > 1)
                        <select name="warehouse_id" class="form-select" title="Almacén donde entra la mercadería">
                            @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected($w->id === $defaultWarehouse)>{{ $w->label() }}</option>@endforeach
                        </select>
                    @endif
                    <button class="btn btn-success text-nowrap"><i class="bi bi-box-arrow-in-down"></i> Recibir</button>
                </form>
                @endif
                <form action="{{ route('purchase-orders.void', $order) }}" method="POST" onsubmit="return confirm('¿Anular la orden?')">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Anular</button>
                </form>
            @endif
            @php
                $poUser = auth()->user();
                $poCo = $poUser->getCurrentCompany();
            @endphp
            @if($order->status === 'recibida' && ($poUser->is_super_admin || ($poCo && $poUser->hasPermissionInCompany('purchase-returns.create', $poCo))))
                <a href="{{ route('purchase-returns.create', $order) }}" class="btn btn-outline-warning"><i class="bi bi-arrow-return-left"></i> Devolver al proveedor</a>
            @endif
        </div>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Proveedor</div><div class="fw-semibold">{{ $order->supplierDisplay() }}</div>
        </div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Total</div><div class="fw-semibold fs-5">{{ number_format($order->total, 2) }}</div>
            <div class="small">
                @if($order->isOnCredit())
                    <i class="bi bi-clock-history"></i> A crédito · {{ $order->credit_days }} días
                    @if($order->payable)
                        · <a href="{{ route('payables.show', $order->payable) }}">saldo {{ number_format($order->payable->balance(), 2) }}</a>
                        <span class="badge {{ $order->payable->stateBadge() }}">{{ $order->payable->stateLabel() }}</span>
                    @elseif($order->status === 'pendiente')
                        · la deuda se registra al recibirla
                    @endif
                @else
                    <i class="bi bi-cash"></i> Contado
                @endif
            </div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Producto</th><th class="text-center">Cantidad</th><th class="text-end">Costo unit.</th><th class="text-end pe-3">Total</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td class="ps-3">{{ $item->product?->name ?? 'Producto #'.$item->product_id }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="text-end pe-3">{{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end fw-bold fs-5">Total</td><td class="text-end pe-3 fw-bold fs-5">{{ number_format($order->total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($order->notes)<div class="mt-3 text-muted"><i class="bi bi-sticky"></i> {{ $order->notes }}</div>@endif
</div>
@if($payment)
    <div class="alert alert-light border small"><i class="bi bi-cash-stack"></i> Pagada al recibir desde <strong>{{ $payment->account?->name ?? ($payment->session?->register?->name ? 'caja ' . $payment->session->register->name : '—') }}</strong> · {{ \App\Models\PaymentMethod::label($payment->payment_method) }}</div>
@endif
@if($order->status === 'pendiente' && $paysOnReceipt)
<div class="modal fade" id="receive-modal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('purchase-orders.receive', $order) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> Recibir y pagar {{ $order->entry_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Compra al contado por <strong>{{ number_format($order->total, 2) }}</strong>. Se suma el stock y se registra el gasto «Pago a proveedores».</p>
                @if($warehouses->count() > 1)
                    <label class="form-label small mb-1">Almacén donde entra la mercadería</label>
                    <select name="warehouse_id" class="form-select form-select-sm mb-2">
                        @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected($w->id === $defaultWarehouse)>{{ $w->label() }}</option>@endforeach
                    </select>
                @endif
                <label class="form-label small mb-1">Forma de pago</label>
                <select name="payment_method" class="form-select form-select-sm mb-2">
                    @foreach($paymentMethods as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                </select>
                @include('finance.partials.payment_source', ['sources' => $sources, 'amount' => (float) $order->total])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success"><i class="bi bi-check-lg"></i> Recibir y pagar</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
