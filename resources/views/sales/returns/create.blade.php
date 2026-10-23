@extends('layouts.app')

@section('title', 'Devolución · venta ' . $sale->sale_number)

@section('page')
@php
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $oldItems = collect(old('items', []))->keyBy('sale_detail_id');
    $reasons = ['Producto defectuoso', 'Cliente insatisfecho con el servicio', 'Error en el cobro', 'Servicio no realizado', 'Cambio de opinión'];
    $hasClient = (bool) $sale->client_id;
    $method = old('refund_method', 'cash');
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-return-left"></i> Devolución de la venta {{ $sale->sale_number }}</h1>
            <p class="text-muted mb-0">{{ $sale->clientDisplay() }} · {{ optional($sale->sale_date)->format('d/m/Y H:i') }} · total {{ $setting->money($sale->total) }}
                @if((float) $sale->returned_total > 0) · ya devuelto {{ $setting->money($sale->returned_total) }}@endif
            </p>
        </div>
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a la venta</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('returns.store', $sale) }}" method="POST" id="ret-form">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-list-check text-primary"></i> ¿Qué devuelve?</h6>
                        <div class="small text-muted">Indica la cantidad de cada línea. El reintegro sugerido es lo que se pagó por ella; puedes bajarlo.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr><th class="ps-3">Línea</th><th class="text-center">Vendido</th><th class="text-center" style="width: 110px;">Devuelve</th><th class="text-end" style="width: 150px;">Reintegro</th><th class="text-center">Stock</th></tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $i => $l)
                                    @php
                                        $item = $l['item'];
                                        $old = $oldItems[$item->id] ?? null;
                                        $single = $item->isMembershipSale() || $item->isPackageSale();
                                    @endphp
                                    <tr class="ret-row {{ $l['returnable'] <= 0 ? 'opacity-50' : '' }}" data-unit="{{ $l['unit'] }}" data-max="{{ $l['max'] }}" data-returnable="{{ $l['returnable'] }}">
                                        <td class="ps-3">
                                            <input type="hidden" name="items[{{ $i }}][sale_detail_id]" value="{{ $item->id }}">
                                            <div class="fw-semibold">{{ $item->description ?: ($item->service?->name ?? $item->product?->name) }}</div>
                                            <div class="small text-muted">
                                                @if($item->isProduct())<span class="badge bg-warning-subtle text-warning-emphasis">Producto</span>@endif
                                                @if($item->combo)<span class="badge bg-info-subtle text-info-emphasis">Combo {{ $item->combo->name }}</span>@endif
                                                @if($item->isMembershipSale())<span class="badge bg-danger-subtle text-danger-emphasis">Membresía: se cancela</span>@endif
                                                @if($item->isPackageSale())<span class="badge bg-success-subtle text-success-emphasis">Paquete: se cancela</span>@endif
                                                @if($item->uses_session)<span class="badge bg-light text-dark border">Sesión prepagada: vuelve al saldo de sesiones</span>@endif
                                                {{ $setting->money($l['unit']) }} c/u
                                            </div>
                                        </td>
                                        <td class="text-center small">
                                            {{ $qty($item->quantity) }}
                                            @if((float) $item->returned_quantity > 0)<div class="text-muted">devuelto {{ $qty($item->returned_quantity) }}</div>@endif
                                        </td>
                                        <td class="text-center">
                                            @if($l['returnable'] > 0)
                                                <input type="number" name="items[{{ $i }}][quantity]" class="form-control form-control-sm text-center no-uppercase ret-qty"
                                                       min="0" max="{{ $l['returnable'] }}" step="{{ $single ? $l['returnable'] : 'any' }}" value="{{ $old['quantity'] ?? 0 }}">
                                            @else
                                                <span class="small text-muted">Devuelto</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($l['returnable'] > 0)
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                                                    <input type="number" name="items[{{ $i }}][amount]" class="form-control text-end no-uppercase ret-amount" min="0" step="0.01"
                                                           value="{{ $old['amount'] ?? '' }}" {{ $old && $old['amount'] !== null ? 'data-touched=1' : '' }}>
                                                </div>
                                                <div class="small text-muted ret-max"></div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($item->isProduct() && $l['returnable'] > 0)
                                                <input type="hidden" name="items[{{ $i }}][restock]" value="0">
                                                <input type="checkbox" class="form-check-input" name="items[{{ $i }}][restock]" value="1" title="Vuelve al stock (está en buen estado)"
                                                       @checked($old ? !empty($old['restock']) : true)>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-info-circle"></i> Al registrar la devolución:
                    los productos marcados vuelven al stock; las comisiones de lo devuelto se descuentan en la próxima liquidación del profesional;
                    @if($sale->loyalty_points_earned > 0 || $sale->loyalty_points_redeemed > 0) los puntos se ajustan en proporción; @endif
                    la propina no se devuelve. Una venta con devoluciones ya no se puede anular.
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-semibold">Total a reintegrar</div>
                        <div class="fs-3 fw-bold" id="ret-total">{{ $setting->money(0) }}</div>
                        <div class="small text-muted mb-3">Queda por devolver de la venta: {{ $setting->money($pending) }}</div>

                        <label class="form-label fw-semibold mb-1">¿Cómo se reintegra?</label>
                        <div class="d-grid gap-2 mb-2">
                            <input type="radio" class="btn-check" name="refund_method" id="rm-cash" value="cash" @checked($method === 'cash')>
                            <label class="btn btn-outline-primary text-start" for="rm-cash"><i class="bi bi-cash-coin"></i> Dinero
                                <span class="d-block small opacity-75">Sale de caja como egreso</span></label>
                            <input type="radio" class="btn-check" name="refund_method" id="rm-credit" value="credit" @checked($method === 'credit') @disabled(!$hasClient)>
                            <label class="btn btn-outline-primary text-start" for="rm-credit"><i class="bi bi-wallet2"></i> Saldo a favor
                                <span class="d-block small opacity-75">{{ $hasClient ? 'Lo usa en su próxima compra en el punto de venta' : 'Requiere una venta con cliente registrado' }}</span></label>
                        </div>
                        @if((float) $sale->credit_applied > 0)
                            <div class="small text-warning-emphasis mb-2"><i class="bi bi-exclamation-triangle"></i> Se pagó {{ $setting->money($sale->credit_applied) }} con saldo a favor: en dinero se devuelven hasta {{ $setting->money($cashRefundable) }}.</div>
                        @endif

                        <div id="cash-fields">
                            <label class="form-label small mb-1" for="ret-pay">Se devuelve en</label>
                            <select name="payment_method" id="ret-pay" class="form-select form-select-sm mb-2">
                                @foreach($paymentMethods as $code => $label)
                                    <option value="{{ $code }}" @selected(old('payment_method', $sale->payment_method) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="form-label small mb-1" for="ret-session">Caja</label>
                            <select name="cash_session_id" id="ret-session" class="form-select form-select-sm mb-1">
                                @if(!$setting->require_cash_session)<option value="">No registrar en caja</option>@endif
                                @foreach($openSessions as $s)
                                    <option value="{{ $s->id }}" @selected((string) old('cash_session_id', $openSessions->count() === 1 ? $s->id : '') === (string) $s->id)>{{ $s->register?->name ?? 'Caja' }}</option>
                                @endforeach
                            </select>
                            @if($openSessions->isEmpty())
                                <div class="small {{ $setting->require_cash_session ? 'text-danger' : 'text-muted' }}">No hay cajas abiertas. <a href="{{ route('cash-sessions.create') }}">Abrir caja</a></div>
                            @endif
                        </div>

                        <label class="form-label fw-semibold mt-3 mb-1" for="ret-reason">Motivo <span class="text-danger">*</span></label>
                        <input type="text" name="reason" id="ret-reason" class="form-control form-control-sm" maxlength="255" required value="{{ old('reason') }}">
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach($reasons as $r)<button type="button" class="btn btn-light border btn-sm py-0 small" data-reason="{{ $r }}">{{ $r }}</button>@endforeach
                        </div>
                        <label class="form-label small mt-2 mb-1" for="ret-notes">Notas</label>
                        <textarea name="notes" id="ret-notes" rows="2" class="form-control form-control-sm">{{ old('notes') }}</textarea>

                        <button class="btn btn-danger w-100 mt-3" id="ret-submit" disabled><i class="bi bi-arrow-return-left"></i> Registrar devolución</button>
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
    const rows = [...document.querySelectorAll('.ret-row')];

    function refresh() {
        let total = 0;
        rows.forEach((row) => {
            const q = row.querySelector('.ret-qty');
            const a = row.querySelector('.ret-amount');
            if (!q || !a) return;
            const qty = Math.min(parseFloat(q.value) || 0, parseFloat(row.dataset.returnable));
            const max = Math.round(parseFloat(row.dataset.unit) * qty * 100) / 100;
            if (!a.dataset.touched) a.value = qty > 0 ? max.toFixed(2) : '';
            a.max = max;
            a.disabled = qty <= 0;
            row.querySelector('.ret-max').textContent = qty > 0 ? 'máx. ' + money(max) : '';
            row.classList.toggle('table-warning', qty > 0);
            total += qty > 0 ? Math.min(parseFloat(a.value) || 0, max) : 0;
        });
        $('ret-total').textContent = money(total);
        $('ret-submit').disabled = total <= 0 && !rows.some((r) => (parseFloat(r.querySelector('.ret-qty')?.value) || 0) > 0);
        $('cash-fields').classList.toggle('d-none', document.querySelector('[name="refund_method"]:checked')?.value !== 'cash');
    }

    document.querySelectorAll('.ret-amount').forEach((a) => a.addEventListener('input', () => { a.dataset.touched = '1'; }));
    document.querySelectorAll('[data-reason]').forEach((b) => b.addEventListener('click', () => { $('ret-reason').value = b.dataset.reason; }));
    $('ret-form').addEventListener('input', refresh);
    $('ret-form').addEventListener('change', refresh);
    $('ret-form').addEventListener('submit', () => { $('ret-submit').disabled = true; });
    refresh();
})();
</script>
@endpush
