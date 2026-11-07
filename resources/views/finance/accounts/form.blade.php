{{-- Datos de la cuenta, compartidos por crear y editar. --}}
<div class="row g-3">
    <div class="col-md-7">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $account->name) }}" placeholder="Ej.: Caja chica" required maxlength="120">
    </div>
    <div class="col-md-5">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        <select name="type" id="acc-type" class="form-select">
            @foreach($types as $code => $typeLabel)
                <option value="{{ $code }}" @selected(old('type', $account->type) === $code)>{{ $typeLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-7" data-bank>
        <label class="form-label">Banco o billetera (opcional)</label>
        <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $account->bank_name) }}" maxlength="120">
    </div>
    <div class="col-md-5" data-bank>
        <label class="form-label">N.º de cuenta (opcional)</label>
        <input type="text" name="account_number" class="form-control no-uppercase" value="{{ old('account_number', $account->account_number) }}" maxlength="60">
    </div>
    <div class="col-12">
        <label class="form-label">Notas</label>
        <input type="text" name="notes" class="form-control" value="{{ old('notes', $account->notes) }}" maxlength="255">
    </div>
</div>
<script>
    // Banco y número aplican a bancos y billeteras.
    (function () {
        const type = document.getElementById('acc-type');
        const toggle = () => document.querySelectorAll('[data-bank]').forEach((el) => el.classList.toggle('d-none', !['banco', 'billetera'].includes(type.value)));
        type.addEventListener('change', toggle);
        toggle();
    })();
</script>
