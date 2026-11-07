{{-- «Comisiones por pagar»: de cualquier fecha, no depende de los filtros. Recibe $owed y $setting. --}}
@php
    $owedUser = auth()->user();
    $canPay = $owedUser->hasPermissionInCompany('commissions.pay', $owedUser->getCurrentCompany());
    $owedTotal = collect($owed)->sum('total');
    $owedCount = collect($owed)->sum('count');
@endphp
<div class="card border-0 shadow-sm mb-3 {{ $owed ? 'border-start border-warning border-4' : '' }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="bi bi-hourglass-split text-warning"></i> Comisiones por pagar</h6>
            <span class="fs-5 fw-bold">{{ $setting->money($owedTotal) }}</span>
        </div>
        <div class="small text-muted mb-2">
            {{ $owed ? $owedCount . ' ' . ($owedCount === 1 ? 'comisión' : 'comisiones') . ' sin pagar, de cualquier fecha.' : 'Nada pendiente: todas las comisiones están pagadas.' }}
        </div>
        @foreach($owed as $row)
            <div class="d-flex justify-content-between align-items-center py-2 border-top">
                <div>
                    <div class="fw-semibold">{{ $row['name'] }}</div>
                    <div class="small text-muted">{{ $row['count'] }} {{ $row['count'] === 1 ? 'comisión' : 'comisiones' }} · desde el {{ \Illuminate\Support\Carbon::parse($row['since'])->format('d/m/Y') }}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-warning-emphasis">{{ $setting->money($row['total']) }}</span>
                    @if($canPay)
                        <a href="{{ route('commissions.index', ['personal_id' => $row['personal_id'], 'from' => $row['since'], 'to' => today()->toDateString()]) }}" class="btn btn-sm btn-warning">Pagar</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
