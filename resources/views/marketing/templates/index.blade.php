@extends('layouts.app')

@section('title', 'Plantillas')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $render = fn ($body) => app(\App\Services\MessageService::class)->render($body, $sample ?? []);
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-envelope-paper"></i> Plantillas de mensajes</h1>
            <p class="text-muted mb-0">Textos que se usan al enviar por WhatsApp: recordatorios, cumpleaños y campañas.</p>
        </div>
        @if(empty($noCompany) && $can('marketing.edit'))
            <a href="{{ route('marketing.templates.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva plantilla</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las plantillas se configuran dentro de una empresa.</div>
    @else
        @foreach(\App\Models\MessageTemplate::TYPES as $type => [$label, $icon])
            <h6 class="fw-bold mt-3"><i class="bi {{ $icon }}"></i> {{ $label }}</h6>
            <div class="row g-3">
                @forelse($templates->get($type, collect()) as $t)
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 {{ $t->active ? '' : 'opacity-50' }}">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold">{{ $t->name }}</div>
                                    <div>
                                        @if($t->is_default)<span class="badge bg-primary">Predeterminada</span>@endif
                                        @if(!$t->active)<span class="badge bg-secondary">Inactiva</span>@endif
                                    </div>
                                </div>
                                <div class="wa-bubble mt-2">{{ $render($t->body) }}</div>
                                @if($can('marketing.edit'))
                                    <div class="d-flex justify-content-end gap-1 mt-auto pt-2">
                                        <a href="{{ route('marketing.templates.edit', $t) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('marketing.templates.destroy', $t) }}" method="POST" onsubmit="return confirm('¿Eliminar la plantilla?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 small text-muted">Sin plantillas.</div>
                @endforelse
            </div>
        @endforeach
        <p class="small text-muted mt-3">La vista previa usa datos de ejemplo. Variables: @foreach(\App\Models\MessageTemplate::VARIABLES as $k => $d)<code>{{ '{' . $k . '}' }}</code> @endforeach</p>
    @endif
</div>
@endsection

@push('styles')
<style>
    .wa-bubble { background: #dcf8c6; border-radius: 10px 10px 10px 2px; padding: .6rem .75rem; font-size: .9rem; white-space: pre-line; color: #111; }
</style>
@endpush
