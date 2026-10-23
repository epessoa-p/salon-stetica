@extends('layouts.app')

@section('title', 'Devoluciones a proveedor')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-return-left"></i> Devoluciones a proveedor</h1>
            <p class="text-muted mb-0">Mercadería devuelta sobre órdenes de compra recibidas. Descuenta el stock al costo de compra.</p>
        </div>
        @if(empty($noCompany) && $can('purchase-returns.create') && $orders->isNotEmpty())
            <form class="d-flex gap-2" onsubmit="if (this.order.value) window.location = this.order.value; return false;">
                <select name="order" class="form-select form-select-sm" style="width: 260px;" required>
                    <option value="">Elegir orden recibida…</option>
                    @foreach($orders as $o)
                        <option value="{{ route('purchase-returns.create', $o) }}">{{ $o->entry_number }} · {{ $o->supplierDisplay() }} · {{ optional($o->entry_date)->format('d/m/Y') }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus-lg"></i> Nueva devolución</button>
            </form>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las devoluciones se registran dentro de una empresa.</div>
    @else
        <form class="d-flex gap-2 align-items-end flex-wrap mb-3" method="GET">
            <div><label class="form-label small mb-1">Desde</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
            <div><label class="form-label small mb-1">Hasta</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
            <div class="ms-auto small text-muted">{{ $count }} devolución(es) · {{ $setting->money($total) }}</div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Número</th><th>Fecha</th><th>Proveedor</th><th>Orden</th><th>Motivo</th><th>Reintegro</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse($returns as $r)
                            <tr>
                                <td><a href="{{ route('purchase-returns.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->return_number }}</a></td>
                                <td class="small">{{ $r->return_date->format('d/m/Y') }}</td>
                                <td>{{ $r->provider?->name ?? $r->order?->supplierDisplay() ?? '—' }}</td>
                                <td>@if($r->order)<a href="{{ route('purchase-orders.show', $r->order) }}">{{ $r->order->entry_number }}</a>@endif</td>
                                <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason, 40) }}</td>
                                <td class="small">{{ $r->refundLabel() }}</td>
                                <td class="text-end fw-semibold">{{ $setting->money($r->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay devoluciones a proveedor en el periodo. Se registran desde una orden recibida, con <strong>Devolver al proveedor</strong>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $returns->links() }}</div>
    @endif
</div>
@endsection
