{{--
    De dónde sale (o a dónde entra) el dinero: pestañas Caja abierta | Cuenta.
    Parámetros: sources (AccountService::sourceOptions), label, noneLabel (agrega
    una tercera opción sin origen), amountInput o amount (para avisar si el saldo
    no alcanza), selectedAccount.
--}}
@php
    $label = $label ?? 'Sale de';
    $noneLabel = $noneLabel ?? null;
    $selectedAccount = $selectedAccount ?? null;
    $sessions = $sources['sessions'];
    $accounts = $sources['accounts'];
    $canUseAccounts = $sources['canUseAccounts'];
    $sourceId = 'src-' . uniqid();
    // Por defecto la cuenta; si no hay, la caja. Un ingreso manual empieza en «externo».
    $current = old('source_type', ($preferNone ?? false) ? 'ninguno' : ($accounts->isNotEmpty() && $canUseAccounts ? 'cuenta' : ($sessions->isNotEmpty() ? 'caja' : ($noneLabel ? 'ninguno' : 'caja'))));
@endphp
<div class="payment-source mb-2" id="{{ $sourceId }}" data-amount="{{ $amount ?? '' }}" data-amount-input="{{ $amountInput ?? '' }}" data-warn-balance="{{ $label === 'Sale de' ? '1' : '0' }}">
    <label class="form-label small mb-1 d-block">{{ $label }} <span class="text-danger">*</span></label>
    <div class="btn-group w-100 mb-2" role="group">
        @if($canUseAccounts)
            <input type="radio" class="btn-check" name="source_type" id="{{ $sourceId }}-cuenta" value="cuenta" autocomplete="off" @checked($current === 'cuenta')>
            <label class="btn btn-outline-primary btn-sm" for="{{ $sourceId }}-cuenta"><i class="bi bi-wallet2"></i> Cuenta</label>
        @endif
        <input type="radio" class="btn-check" name="source_type" id="{{ $sourceId }}-caja" value="caja" autocomplete="off" @checked($current === 'caja')>
        <label class="btn btn-outline-primary btn-sm" for="{{ $sourceId }}-caja"><i class="bi bi-safe"></i> Caja abierta</label>
        @if($noneLabel)
            <input type="radio" class="btn-check" name="source_type" id="{{ $sourceId }}-ninguno" value="ninguno" autocomplete="off" @checked($current === 'ninguno')>
            <label class="btn btn-outline-primary btn-sm" for="{{ $sourceId }}-ninguno">{{ $noneLabel }}</label>
        @endif
    </div>

    <div data-panel="caja">
        @if($sessions->isEmpty())
            <div class="small text-muted">No hay cajas abiertas. <a href="{{ route('cash-sessions.create') }}">Abre un turno</a>{{ $canUseAccounts ? ' o elige una cuenta' : '' }}.</div>
        @else
            <select name="cash_session_id" class="form-select form-select-sm" aria-label="Caja abierta">
                @foreach($sessions as $s)
                    <option value="{{ $s->id }}" @selected((string) old('cash_session_id') === (string) $s->id)>{{ $s->register?->name ?? 'Caja' }} · esperado {{ number_format($s->expectedBalance(), 2) }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if($canUseAccounts)
        <div data-panel="cuenta">
            @if($accounts->isEmpty())
                <div class="small text-muted">Aún no hay cuentas.@if(auth()->user()->hasPermissionInCompany('accounts.create', auth()->user()->getCurrentCompany())) <a href="{{ route('accounts.create') }}">Crea una</a>.@endif</div>
            @else
                <select name="financial_account_id" class="form-select form-select-sm" aria-label="Cuenta">
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" data-balance="{{ (float) $a->saldo }}" @selected((string) old('financial_account_id', $selectedAccount) === (string) $a->id)>{{ $a->name }} · saldo {{ number_format((float) $a->saldo, 2) }}</option>
                    @endforeach
                </select>
                <div class="small text-danger mt-1 d-none" data-balance-warning></div>
            @endif
        </div>
    @endif
</div>

@once
<script>
    // Muestra el panel de la pestaña elegida; lo oculto no se envía.
    function initPaymentSource(box) {
        const amountInput = box.dataset.amountInput ? document.querySelector(box.dataset.amountInput) : null;
        const amount = () => parseFloat(amountInput ? amountInput.value : box.dataset.amount) || 0;
        const warn = () => {
            const select = box.querySelector('[name=financial_account_id]');
            const warning = box.querySelector('[data-balance-warning]');
            if (!select || !warning || box.dataset.warnBalance !== '1') return;
            const balance = parseFloat(select.selectedOptions[0]?.dataset.balance) || 0;
            const short = box.querySelector('[name=source_type]:checked')?.value === 'cuenta' && amount() > balance + 0.001;
            warning.classList.toggle('d-none', !short);
            warning.textContent = short ? 'El saldo de esta cuenta no alcanza: tiene ' + balance.toFixed(2) + '.' : '';
        };
        const sync = () => {
            const type = box.querySelector('[name=source_type]:checked')?.value;
            box.querySelectorAll('[data-panel]').forEach((panel) => {
                const on = panel.dataset.panel === type;
                panel.classList.toggle('d-none', !on);
                panel.querySelectorAll('select').forEach((el) => { el.disabled = !on; el.required = on; });
            });
            warn();
        };
        box.querySelectorAll('[name=source_type]').forEach((radio) => radio.addEventListener('change', sync));
        box.querySelector('[name=financial_account_id]')?.addEventListener('change', warn);
        amountInput?.addEventListener('input', warn);
        sync();
    }
</script>
@endonce
<script>initPaymentSource(document.getElementById(@json($sourceId)));</script>
