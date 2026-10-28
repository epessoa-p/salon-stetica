@extends('layouts.app')

@section('title', 'Cupones')

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
            <h1 class="mb-1"><i class="bi bi-ticket-perforated"></i> Cupones</h1>
            <p class="text-muted mb-0">Códigos de descuento que el cliente presenta y se escriben en el punto de venta.</p>
        </div>
        @if(empty($noCompany) && $can('coupons.create'))
            <a href="{{ route('marketing.coupons.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo cupón</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Los cupones se configuran dentro de una empresa.</div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Código</th><th>Descuento</th><th>Vigencia</th><th class="text-center">Usos</th><th class="text-end">Descontado</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($coupons as $c)
                            @php $st = $stats->get($c->id); $uses = (int) ($st->uses ?? 0); $state = $c->state($uses); @endphp
                            <tr>
                                <td class="ps-3">
                                    <code class="fs-6 fw-bold">{{ $c->code }}</code>
                                    <div class="small text-muted">{{ $c->name }}{{ $c->client ? ' · solo ' . $c->client->full_name : '' }}</div>
                                </td>
                                <td>{{ $c->valueLabel($setting->currency_symbol) }}<div class="small text-muted">{{ \App\Models\Coupon::APPLIES[$c->applies_to] ?? '' }}{{ $c->min_purchase ? ' · mín. ' . number_format($c->min_purchase, 2) : '' }}</div></td>
                                <td class="small">{{ $c->starts_on?->format('d/m/Y') ?? 'Desde ya' }} – {{ $c->ends_on?->format('d/m/Y') ?? 'sin fin' }}</td>
                                <td class="text-center">{{ $uses }}{{ $c->max_uses ? ' / ' . $c->max_uses : '' }}</td>
                                <td class="text-end">{{ number_format((float) ($st->discount ?? 0), 2) }}<div class="small text-muted">en ventas por {{ number_format((float) ($st->sales ?? 0), 2) }}</div></td>
                                <td><span class="badge {{ \App\Models\Coupon::STATES[$state][1] }}">{{ \App\Models\Coupon::STATES[$state][0] }}</span></td>
                                <td class="text-end text-nowrap">
                                    @if($can('coupons.edit'))<a href="{{ route('marketing.coupons.edit', $c) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endif
                                    @if($can('coupons.delete'))
                                        <form action="{{ route('marketing.coupons.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el cupón {{ $c->code }}?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay cupones. Crea uno, por ejemplo <code>CUMPLE15</code> con 15 % de descuento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $coupons->links() }}</div>
    @endif
</div>
@endsection
