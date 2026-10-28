{{-- Pestaña "Fotos" de la ficha del cliente: antes / después --}}
@php
    $pUser = auth()->user();
    $pCo = $pUser->getCurrentCompany();
    $pCan = fn ($p) => $pUser->is_super_admin || ($pCo && $pUser->hasPermissionInCompany($p, $pCo));
@endphp
@if($pCan('photos.create'))
    <form action="{{ route('clients.photos.store', $client) }}" method="POST" enctype="multipart/form-data" class="border rounded-3 p-3 mb-3 bg-light">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Fotos (hasta 10)</label>
                <input type="file" name="photos[]" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" multiple required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tipo</label>
                <select name="kind" class="form-select form-select-sm">
                    @foreach(\App\Models\ClientPhoto::KINDS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Fecha</label>
                <input type="date" name="taken_on" class="form-control form-control-sm" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Servicio</label>
                <select name="service_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($photoServices as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i> Subir</button></div>
            <div class="col-12">
                <input type="text" name="notes" class="form-control form-control-sm" maxlength="255" placeholder="Nota (opcional): técnica, color, producto…">
            </div>
        </div>
        <div class="form-text">Las fotos son privadas: solo las ve el personal del salón con permiso. Se reducen a 1600 px al subirlas.</div>
    </form>
@endif

@forelse($photos->groupBy(fn ($p) => $p->taken_on->toDateString()) as $date => $group)
    <div class="fw-semibold small text-muted mt-2 mb-1">{{ ucfirst(\Illuminate\Support\Carbon::parse($date)->locale('es')->isoFormat('dddd D [de] MMMM YYYY')) }}</div>
    <div class="row g-2 mb-2">
        @foreach($group->sortBy(fn ($p) => array_search($p->kind, ['before', 'after', 'other'])) as $photo)
            <div class="col-6 col-md-3 col-xl-2">
                <div class="photo-card">
                    <a href="{{ route('photos.image', $photo) }}" target="_blank" rel="noopener">
                        <img src="{{ route('photos.image', [$photo, 'thumb']) }}" alt="{{ $photo->kindLabel() }}" loading="lazy">
                    </a>
                    <span class="badge photo-kind {{ $photo->kind === 'before' ? 'bg-secondary' : ($photo->kind === 'after' ? 'bg-success' : 'bg-light text-dark') }}">{{ $photo->kindLabel() }}</span>
                    <div class="small mt-1">
                        {{ $photo->service?->name }}
                        @if($photo->notes)<div class="text-muted">{{ $photo->notes }}</div>@endif
                    </div>
                    @if($pCan('photos.delete'))
                        <form action="{{ route('photos.destroy', $photo) }}" method="POST" onsubmit="return confirm('¿Eliminar la foto?')" class="photo-del">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-light" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@empty
    <p class="text-muted small mb-0">Sin fotos todavía.</p>
@endforelse

@push('styles')
<style>
    .photo-card { position: relative; }
    .photo-card img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; background: #f3f4f6; }
    .photo-kind { position: absolute; top: .4rem; left: .4rem; }
    .photo-del { position: absolute; top: .3rem; right: .3rem; opacity: .85; }
</style>
@endpush
