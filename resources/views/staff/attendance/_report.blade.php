{{-- Reporte de asistencia de una persona. Requiere $report y $canEdit (opcional). --}}
@php
    $t = $report['totals'];
    $canEdit = $canEdit ?? false;
    $weekdays = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
@endphp
<div class="row g-2 mb-3">
    @foreach([
        ['Días trabajados', $t['worked_days'], 'bi-calendar-check', 'text-success'],
        ['Horas trabajadas', \App\Models\Attendance::duration($t['minutes']), 'bi-hourglass-split', 'text-primary'],
        ['Tardanzas', $t['late_days'] . ($t['late_min'] ? ' · ' . \App\Models\Attendance::duration($t['late_min']) : ''), 'bi-alarm', 'text-warning'],
        ['Ausencias', $t['absences'], 'bi-person-x', 'text-danger'],
    ] as [$label, $value, $icon, $color])
        <div class="col-6 col-md-3">
            <div class="border rounded-3 p-2 h-100">
                <div class="small text-muted"><i class="bi {{ $icon }} {{ $color }}"></i> {{ $label }}</div>
                <div class="fw-bold fs-5">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Marcas</th>
                <th class="text-end">Horas</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_reverse($report['days'], true) as $date => $d)
                @php
                    $day = \Illuminate\Support\Carbon::parse($date);
                    // Días futuros sin nada que mostrar no aportan.
                    if ($day->isFuture() && !$day->isToday() && $d['records']->isEmpty()) continue;
                @endphp
                <tr class="{{ $d['state'] === 'absent' ? 'table-danger' : ($day->isToday() ? 'table-active' : '') }}">
                    <td class="text-nowrap"><span class="text-muted">{{ $weekdays[$day->dayOfWeekIso] }}</span> {{ $day->format('d/m') }}</td>
                    <td class="small text-muted text-nowrap">
                        {{ $d['blocks'] ? collect($d['blocks'])->map(fn ($b) => $b['start'] . '–' . $b['end'])->implode(' · ') : '—' }}
                    </td>
                    <td class="small">
                        @forelse($d['records'] as $r)
                            <div class="text-nowrap">
                                <i class="bi bi-box-arrow-in-right text-success"></i> {{ $r->check_in->format('H:i') }}
                                <i class="bi bi-box-arrow-right text-danger ms-1"></i> {{ $r->check_out?->format('H:i') ?? '…' }}
                                @if($r->in_source === 'staff')<span class="badge bg-light text-muted border" title="Marcada por recepción">recepción</span>@endif
                                @if($r->edited_by)<span class="badge bg-info-subtle text-info-emphasis" title="{{ $r->edit_reason }}">corregida</span>@endif
                                @if($canEdit)
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="modal" data-bs-target="#att-edit"
                                            data-url="{{ route('attendance.update', $r) }}"
                                            data-in="{{ $r->check_in->format('Y-m-d\TH:i') }}" data-out="{{ $r->check_out?->format('Y-m-d\TH:i') }}"
                                            title="Corregir"><i class="bi bi-pencil-square"></i></button>
                                @endif
                            </div>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td class="text-end small">{{ $d['worked'] ? \App\Models\Attendance::duration($d['worked']) : '—' }}</td>
                    <td class="text-center"><span class="badge {{ $d['badge'] }}">{{ $d['label'] }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($canEdit)
    @once
        <div class="modal fade" id="att-edit" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" id="att-edit-form">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Corregir marca</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small">Entrada</label>
                                <input type="datetime-local" name="check_in" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Salida</label>
                                <input type="datetime-local" name="check_out" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Motivo <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" maxlength="255" required placeholder="Ej. olvidó marcar la salida">
                            </div>
                        </div>
                        <div class="form-text">La corrección queda registrada con tu usuario y el motivo. La tardanza y las horas se recalculan.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary">Guardar corrección</button>
                    </div>
                </form>
            </div>
        </div>
        @push('scripts')
        <script>
            document.getElementById('att-edit').addEventListener('show.bs.modal', function (e) {
                var b = e.relatedTarget, f = document.getElementById('att-edit-form');
                f.action = b.dataset.url;
                f.check_in.value = b.dataset.in;
                f.check_out.value = b.dataset.out || '';
                f.reason.value = '';
            });
        </script>
        @endpush
    @endonce
@endif
