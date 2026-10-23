@extends('layouts.app')

@section('title', 'Devolución a proveedor · ' . $order->entry_number)

@section('page')
@php
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $old = collect(old('items', []))->keyBy('entry_detail_id');
    $method = old('refund_method', 'credit');
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-return-left"></i> Devolver al proveedor</h1>
            <p class="text-muted mb-0">Orden {{ $order->entry_number }} · {{ $order->supplierDisplay() }} · recibida el {{ optional($order->entry_date)->format('d/m/Y') }}</p>
        </div>
        <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a la orden</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('purchase-returns.store', $order) }}" method="POST" id="pr-form">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Producto</th><th class="text-center">Recibido</th><th class="text-center">En stock</th><th class="text-center" style="width: 110px;">Devuelve</th><th class="text-end pe-3">Costo</th></tr></thead>
                            <tbody>
                                @foreach($lines as $i => $l)
                                    @php $item = $l['item']; $max = min($l['returnable'], $l['stock']); @endphp
                                    <tr class="pr-row" data-cost="{{ (float) $item->unit_cost }}">
                                        <td class="ps-3">
                                            <input type="hidden" name="items[{{ $i }}][entry_detail_id]" value="{{ $item->id }}">
                                            <div class="fw-semibold">{{ $item->product?->name ?? 'Producto #' . $item->product_id }}</div>
                                            <div class="small text-muted">{{ $setting->money($item->unit_cost) }} c/u
                                                @if($l['returned'] > 0) · ya devuelto {{ $qty($l['returned']) }}@endif</div>
                                        </td>
                                        <td class="text-center">{{ $qty($item->quantity) }}</td>
                                        <td class="text-center {{ $l['stock'] < $l['returnable'] ? 'text-warning-emphasis' : '' }}">{{ $qty($l['stock']) }}</td>
                                        <td class="text-center">
                                            @if($max > 0)
                                                <input type="number" name="items[{{ $i }}][quantity]" class="form-control form-control-sm text-center no-uppercase pr-qty"
                                                       min="0" max="{{ $max }}" step="any" value="{{ $old[$item->id]['quantity'] ?? 0 }}">
                                            @else
                                                <span class="small text-muted">{{ $l['returnable'] <= 0 ? 'Devuelto' : 'Sin stock' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 pr-total">—</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Se puede devolver hasta lo recibido menos lo ya devuelto, y nunca más de lo que hay en stock.</p>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-semibold">Total al costo</div>
                        <div class="fs-3 fw-bold mb-3" id="pr-sum">{{ $setting->money(0) }}</div>

                        <label class="form-label fw-semibold mb-1">El proveedor…</label>
                        <div class="d-grid gap-2 mb-2">
                            @foreach(\App\Models\PurchaseReturn::REFUND_METHODS as $code => $label)
                                <input type="radio" class="btn-check" name="refund_method" id="prm-{{ $code }}" value="{{ $code }}" @checked($method === $code)>
                                <label class="btn btn-outline-primary text-start" for="prm-{{ $code }}">{{ $label }}
                                    <span class="d-block small opacity-75">{{ $code === 'cash' ? 'Se registra como ingreso «Devolución de proveedor»' : 'Queda el registro para descontar en la próxima compra' }}</span></label>
                            @endforeach
                        </div>

                        <div id="pr-cash" class="{{ $method === 'cash' ? '' : 'd-none' }}">
                            <label class="form-label small mb-1" for="pr-pay">Forma de pago</label>
                            <select name="payment_method" id="pr-pay" class="form-select form-select-sm mb-2">
                                @foreach($paymentMethods as $code => $label)<option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $label }}</option>@endforeach
                            </select>
                            <label class="form-label small mb-1" for="pr-session">Caja</label>
                            <select name="cash_session_id" id="pr-session" class="form-select form-select-sm">
                                <option value="">Sin caja (solo ingreso)</option>
                                @foreach($openSessions as $s)<option value="{{ $s->id }}" @selected((string) old('cash_session_id') === (string) $s->id)>{{ $s->register?->name ?? 'Caja' }}</option>@endforeach
                            </select>
                        </div>

                        <label class="form-label fw-semibold mt-3 mb-1" for="pr-reason">Motivo <span class="text-danger">*</span></label>
                        <input type="text" name="reason" id="pr-reason" class="form-control form-control-sm" maxlength="255" required value="{{ old('reason') }}">
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach(['Producto dañado', 'Producto vencido', 'Error en el pedido', 'No era lo solicitado'] as $r)
                                <button type="button" class="btn btn-light border btn-sm py-0 small" data-reason="{{ $r }}">{{ $r }}</button>
                            @endforeach
                        </div>
                        <label class="form-label small mt-2 mb-1" for="pr-notes">Notas</label>
                        <textarea name="notes" id="pr-notes" rows="2" class="form-control form-control-sm">{{ old('notes') }}</textarea>

                        <button class="btn btn-warning w-100 mt-3" id="pr-submit" disabled><i class="bi bi-arrow-return-left"></i> Registrar devolución</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const currency = @json($setting->currency_symbol);
    const money = (n) => currency + ' ' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const $ = (id) => document.getElementById(id);

    function refresh() {
        let sum = 0;
        document.querySelectorAll('.pr-row').forEach((row) => {
            const q = row.querySelector('.pr-qty');
            const qty = q ? Math.min(parseFloat(q.value) || 0, parseFloat(q.max)) : 0;
            const total = qty * parseFloat(row.dataset.cost);
            row.querySelector('.pr-total').textContent = qty > 0 ? money(total) : '—';
            row.classList.toggle('table-warning', qty > 0);
            sum += total;
        });
        $('pr-sum').textContent = money(sum);
        $('pr-submit').disabled = sum <= 0;
        $('pr-cash').classList.toggle('d-none', document.querySelector('[name="refund_method"]:checked')?.value !== 'cash');
    }

    document.querySelectorAll('[data-reason]').forEach((b) => b.addEventListener('click', () => { $('pr-reason').value = b.dataset.reason; }));
    $('pr-form').addEventListener('input', refresh);
    $('pr-form').addEventListener('change', refresh);
    $('pr-form').addEventListener('submit', () => { $('pr-submit').disabled = true; });
    refresh();
})();
</script>
@endpush
