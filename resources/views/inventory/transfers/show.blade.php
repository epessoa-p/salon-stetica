@extends('layouts.app')

@section('title', 'Traspaso ' . $transfer->transfer_number)

@section('page')
<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-left-right"></i> Traspaso {{ $transfer->transfer_number }}</h1>
            <p class="text-muted mb-0">
                {{ $transfer->transferred_at->format('d/m/Y H:i') }} · {{ $transfer->creator?->name }}
                · <span class="badge {{ $transfer->isVoided() ? 'bg-secondary' : 'bg-success' }}">{{ $transfer->isVoided() ? 'Anulado' : 'Completado' }}</span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            @if(!$transfer->isVoided() && (auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('stock.manage', auth()->user()->getCurrentCompany())))
                <form action="{{ route('stock-transfers.void', $transfer) }}" method="POST" onsubmit="return confirm('¿Anular el traspaso? Los productos vuelven al almacén de origen.')">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Anular</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <div><div class="small text-muted">Desde</div><div class="fw-bold">{{ $transfer->from?->label() }}</div></div>
            <i class="bi bi-arrow-right fs-4 text-muted"></i>
            <div><div class="small text-muted">Hacia</div><div class="fw-bold">{{ $transfer->to?->label() }}</div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Producto</th><th class="text-end pe-3">Cantidad</th></tr></thead>
                <tbody>
                    @foreach($transfer->items as $item)
                        <tr>
                            <td class="ps-3">{{ $item->product?->name ?? 'Producto eliminado' }} <span class="small text-muted">{{ $item->product?->sku }}</span></td>
                            <td class="text-end pe-3 fw-semibold">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->product?->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($transfer->notes)
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Notas</div>{{ $transfer->notes }}</div></div>
    @endif
</div>
@endsection
