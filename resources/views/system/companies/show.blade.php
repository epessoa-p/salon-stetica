@extends('layouts.app')

@section('page')
<h1><i class="bi bi-building"></i> {{ $company->name }}</h1>

{{-- Credenciales del administrador: solo justo después de crear la empresa (no se guardan en claro). --}}
@if($cred = session('credentials'))
    <div class="card border-0 shadow-sm mt-3" style="border-left: 4px solid var(--accent, #A85585) !important">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-key"></i> Credenciales del administrador</h5>
                    <small class="text-muted">Entrégaselas a tu cliente. <strong>Esta es la única vez que se muestra la contraseña</strong>: si se pierde, se cambia desde Usuarios.</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="copy-credentials"><i class="bi bi-clipboard"></i> Copiar todo</button>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-sm-6 col-lg-3"><div class="small text-muted">Usuario</div><div class="fw-bold font-monospace">{{ $cred['username'] }}</div></div>
                <div class="col-sm-6 col-lg-3"><div class="small text-muted">Correo</div><div class="fw-bold font-monospace">{{ $cred['email'] }}</div></div>
                <div class="col-sm-6 col-lg-3"><div class="small text-muted">Contraseña{{ $cred['generated'] ? ' (generada)' : '' }}</div><div class="fw-bold font-monospace fs-5">{{ $cred['password'] }}</div></div>
                <div class="col-sm-6 col-lg-3"><div class="small text-muted">Ingreso</div><div class="fw-bold font-monospace text-break">{{ url('/login') }}</div></div>
            </div>
            <div class="small text-muted mt-3">
                <i class="bi bi-check2-circle text-success"></i> Se creó: {{ $cred['branch'] }} con su almacén · {{ $cred['caja'] }} · ficha de personal de {{ $cred['name'] }} (cargo Administrador, horario lun–sáb 09:00–19:00) · métodos de pago base.
                Puede iniciar sesión con el usuario o con el correo.
            </div>
            <textarea id="credentials-text" class="d-none no-uppercase">Glamora · {{ $cred['company'] }}
Ingreso: {{ url('/login') }}
Usuario: {{ $cred['username'] }}
Correo: {{ $cred['email'] }}
Contraseña: {{ $cred['password'] }}</textarea>
        </div>
    </div>
    @push('scripts')
    <script>
        document.getElementById('copy-credentials')?.addEventListener('click', function () {
            var text = document.getElementById('credentials-text').value, btn = this;
            var done = function () { btn.innerHTML = '<i class="bi bi-check2"></i> Copiado'; };
            if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
            else { var t = document.getElementById('credentials-text'); t.classList.remove('d-none'); t.select(); document.execCommand('copy'); t.classList.add('d-none'); done(); }
        });
    </script>
    @endpush
@endif

{{-- Estructura: cada sucursal con su almacén, cajas y personal --}}
@php($branches = \App\Models\Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $company->id)->with('warehouse')->withCount(['cajas', 'personals'])->orderBy('id')->get())
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-diagram-3"></i> Sucursales</h6></div>
    <div class="card-body pt-2">
        @forelse($branches as $branch)
            <div class="d-flex justify-content-between flex-wrap gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div><strong>{{ $branch->name }}</strong> <span class="text-muted small">· {{ $branch->warehouse?->name ?? 'Sin almacén' }}</span></div>
                <div class="small text-muted">{{ $branch->cajas_count }} caja(s) · {{ $branch->personals_count }} persona(s)</div>
            </div>
        @empty
            <p class="text-muted mb-0">Sin sucursales: ejecuta actualizar_sucursales.sql o créala en Configuración › Sucursales.</p>
        @endforelse
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información</h5>
            </div>
            <div class="card-body">
                <p><strong>RUC:</strong> {{ $company->ruc ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $company->email ?? '-' }}</p>
                <p><strong>Teléfono:</strong> {{ $company->phone ?? '-' }}</p>
                <p><strong>Dirección:</strong> {{ $company->address ?? '-' }}</p>
                <p><strong>Estado:</strong> <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">{{ $company->active ? 'Activo' : 'Inactivo' }}</span></p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('companies.edit', $company) }}" class="btn btn-warning w-100 mb-2">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <form action="{{ route('companies.destroy', $company) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('¿Estás seguro?')">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Usuarios de la Empresa</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->pivot->role_id ? \App\Models\Role::find($user->pivot->role_id)->name : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No hay usuarios en esta empresa</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<a href="{{ route('companies.index') }}" class="btn btn-secondary mt-4">
    <i class="bi bi-arrow-left"></i> Volver
</a>
@endsection
