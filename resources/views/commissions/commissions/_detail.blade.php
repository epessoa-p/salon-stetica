{{-- Detalle de un profesional y formulario de liquidación --}}
@php
    $canPay = auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('commissions.pay', auth()->user()->getCurrentCompany());
    $toPay = round($pendingTotal + $pendingTips, 2);
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-person-badge"></i> {{ $personal->full_name }} · detalle</h6>
            </div>
            <div class="card-body p-0">
                @if($detail->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Sin comisiones en este periodo.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Fecha</th>
                                    <th>Venta</th>
                                    <th>Concepto</th>
                                    <th class="text-end">Base</th>
                                    <th class="text-end">Tasa</th>
                                    <th class="text-end">Importe</th>
                                    <th class="pe-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($detail as $c)
                                    <tr class="{{ $c->isReversal() ? 'table-danger' : '' }}">
                                        <td class="ps-3 text-nowrap">{{ $c->accrued_at?->format('d/m/Y') }}</td>
                                        <td>
                                            @if($c->sale)
                                                <a href="{{ route('sales.show', $c->sale) }}" class="text-decoration-none"><code>{{ $c->sale->sale_number }}</code></a>
                                            @else — @endif
                                        </td>
                                        <td class="small">{{ $c->concept() }}</td>
                                        <td class="text-end">{{ number_format((float) $c->base, 2) }}</td>
                                        <td class="text-end small">{{ $c->rateLabel() }}</td>
                                        <td class="text-end fw-semibold">{{ number_format((float) $c->amount, 2) }}</td>
                                        <td class="pe-3">
                                            @if($c->settlement)
                                                <a href="{{ route('commission-settlements.show', $c->settlement) }}" class="badge bg-success text-decoration-none">{{ $c->settlement->number }}</a>
                                            @else
                                                <span class="badge {{ $c->statusBadge() }}">{{ $c->statusLabel() }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if($tipSales->isNotEmpty())
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-heart"></i> Propinas (aparte de las comisiones)</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach($tipSales as $s)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $s->sale_date?->format('d/m/Y') }}</td>
                                    <td><a href="{{ route('sales.show', $s) }}" class="text-decoration-none"><code>{{ $s->sale_number }}</code></a></td>
                                    <td class="text-end fw-semibold">{{ number_format((float) $s->tip, 2) }}</td>
                                    <td class="pe-3">
                                        @if($s->tipSettlement)
                                            <span class="badge bg-success">{{ $s->tipSettlement->number }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Liquidar --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-cash-coin text-success"></i> Liquidar</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Comisiones pendientes</span><span>{{ number_format($pendingTotal, 2) }}</span></div>
                <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Propinas pendientes</span><span>{{ number_format($pendingTips, 2) }}</span></div>
                <hr class="my-2">

                @if(!$canPay)
                    <p class="text-muted small mb-0"><i class="bi bi-lock"></i> No tienes permiso para liquidar comisiones.</p>
                @elseif($pendingTotal == 0 && $pendingTips == 0)
                    <p class="text-muted small mb-0">No hay nada pendiente de liquidar en este periodo.</p>
                @else
                    <form action="{{ route('commission-settlements.store') }}" method="POST"
                          onsubmit="return confirm('¿Registrar el pago a {{ $personal->full_name }}?')">
                        @csrf
                        <input type="hidden" name="personal_id" value="{{ $personal->id }}">
                        <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                        <input type="hidden" name="to" value="{{ $to->toDateString() }}">

                        <p class="small text-muted mb-2">
                            Periodo {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}.
                            Cambia las fechas arriba para liquidar otro rango.
                        </p>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="include_tips" value="1" id="include_tips" @checked($pendingTips > 0)
                                   data-tips="{{ $pendingTips }}" data-commissions="{{ $pendingTotal }}">
                            <label class="form-check-label small" for="include_tips">Incluir propinas en este pago</label>
                        </div>

                        @if($usesCash)
                            <div class="mb-2">
                                <label class="form-label small">Sale de la caja <span class="text-danger">*</span></label>
                                <select name="cash_session_id" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar turno abierto...</option>
                                    @foreach($openSessions as $sess)
                                        <option value="{{ $sess->id }}">{{ $sess->register?->name }}</option>
                                    @endforeach
                                </select>
                                @if($openSessions->isEmpty())
                                    <small class="text-danger">No hay turnos abiertos. <a href="{{ route('cash-sessions.create') }}">Abre uno</a> antes de pagar.</small>
                                @endif
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label small">Notas</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm"></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">A pagar</span>
                            <span class="fw-bold fs-5">{{ $setting->currency_symbol }} <span id="lbl-to-pay">{{ number_format($toPay, 2) }}</span></span>
                        </div>
                        <button class="btn btn-success w-100" @disabled($usesCash && $openSessions->isEmpty())>
                            <i class="bi bi-check2-circle"></i> Registrar pago
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var box = document.getElementById('include_tips');
    var label = document.getElementById('lbl-to-pay');
    if (!box || !label) return;
    function refresh() {
        var total = parseFloat(box.dataset.commissions) + (box.checked ? parseFloat(box.dataset.tips) : 0);
        label.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    box.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
