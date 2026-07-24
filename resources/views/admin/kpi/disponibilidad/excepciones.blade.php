@extends('layouts.app')

@php use App\Services\KpiDisponibilidad; @endphp

@section('content')
<style>
    .kpix-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
    .kpix-card h6 { font-size:.82rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }
    .kpix-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .kpix-table th, .kpix-table td { padding:.5rem .6rem; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:top; }
    .kpix-table th { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; font-weight:700; }
    .kpix-obj { font-family:ui-monospace,monospace; font-size:.74rem; color:#64748b; }
    .kpix-just { color:#475569; font-size:.78rem; max-width:320px; }
    .kpix-cat { display:inline-block; background:#eef2ff; color:#4338ca; border-radius:5px; padding:1px 7px; font-size:.7rem; font-weight:600; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-shield-check me-2" style="color:#16a34a"></i>Excepciones justificadas · KPI Disponibilidad</h4>
        <a href="{{ route('admin.kpi.disponibilidad.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-activity me-1"></i>Dashboard
        </a>
    </div>

    <div class="alert alert-info py-2" style="font-size:.82rem">
        <i class="bi bi-info-circle-fill me-1"></i>
        Una excepción marca una ventana de tiempo (con fecha y hora) cuya caída <strong>no debe penalizar tu KPI</strong>
        —por ejemplo un corte eléctrico de la compañía. La caída dentro de la ventana se <strong>descuenta</strong> del cálculo
        y queda justificada. Al guardar, los meses afectados se recalculan automáticamente.
    </div>

    {{-- ── Alta ───────────────────────────────────────────────────────────── --}}
    <div class="kpix-card">
        <h6><i class="bi bi-plus-square me-1"></i>Registrar excepción</h6>
        @if($servicios->isEmpty())
            <div class="text-muted" style="font-size:.85rem">Primero define servicios críticos para poder asociar excepciones.</div>
        @else
        <form method="POST" action="{{ route('admin.kpi.disponibilidad.excepciones.store') }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label" style="font-size:.75rem">Host <span class="text-danger">*</span></label>
                    <select name="host_name" class="form-select form-select-sm" required>
                        <option value="">— Selecciona —</option>
                        @foreach($servicios->pluck('host_name')->unique()->sort() as $h)
                            <option value="{{ $h }}" @selected(old('host_name') === $h)>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-size:.75rem">Servicio <span class="text-muted">(vacío = host completo)</span></label>
                    <input type="text" name="service_description" class="form-control form-control-sm" value="{{ old('service_description') }}" placeholder="CPU load">
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-size:.75rem">Categoría</label>
                    <input type="text" name="categoria" class="form-control form-control-sm" value="{{ old('categoria') }}" list="categoriasExc" placeholder="Corte eléctrico">
                    <datalist id="categoriasExc">
                        <option value="Corte eléctrico">
                        <option value="Falla ISP / enlace">
                        <option value="Mantención de terceros">
                        <option value="Falla proveedor cloud">
                        <option value="Corte de energía externo">
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:.75rem">Desde <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="desde" class="form-control form-control-sm" value="{{ old('desde') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:.75rem">Hasta <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="hasta" class="form-control form-control-sm" value="{{ old('hasta') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" style="font-size:.75rem">Justificación <span class="text-danger">*</span></label>
                    <input type="text" name="justificacion" class="form-control form-control-sm" value="{{ old('justificacion') }}" required placeholder="Corte de luz compañía eléctrica, ticket #12345">
                </div>
            </div>
            <button type="submit" class="btn btn-success btn-sm mt-3">
                <i class="bi bi-plus-lg me-1"></i>Registrar y recalcular
            </button>
        </form>
        @endif
    </div>

    {{-- ── Listado ─────────────────────────────────────────────────────────── --}}
    <div class="kpix-card">
        <h6><i class="bi bi-list-check me-1"></i>Excepciones registradas ({{ $excepciones->count() }})</h6>
        @if($excepciones->isEmpty())
            <div class="text-muted text-center py-4" style="font-size:.85rem">Aún no hay excepciones.</div>
        @else
        <div style="overflow-x:auto">
        <table class="kpix-table">
            <thead>
                <tr>
                    <th>Objeto</th>
                    <th>Período</th>
                    <th>Duración</th>
                    <th>Categoría</th>
                    <th>Justificación</th>
                    <th style="width:70px">Estado</th>
                    <th style="width:110px;text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($excepciones as $x)
                <tr class="{{ $x->activa ? '' : 'text-muted' }}">
                    <td class="kpix-obj">{{ $x->objeto }}</td>
                    <td style="font-size:.78rem;white-space:nowrap">
                        {{ $x->desde->format('d/m/Y H:i') }}<br>
                        <span class="text-muted">→ {{ $x->hasta->format('d/m/Y H:i') }}</span>
                    </td>
                    <td style="white-space:nowrap">{{ $x->duracion_horas }} h</td>
                    <td>@if($x->categoria)<span class="kpix-cat">{{ $x->categoria }}</span>@else <span class="text-muted">—</span>@endif</td>
                    <td class="kpix-just">{{ $x->justificacion }}</td>
                    <td>
                        @if($x->activa)
                            <span class="badge bg-success" style="font-size:.65rem">Activa</span>
                        @else
                            <span class="badge bg-secondary" style="font-size:.65rem">Inactiva</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 btn-edit-exc" data-id="{{ $x->id }}" title="Editar"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('admin.kpi.disponibilidad.excepciones.toggle', $x) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" title="{{ $x->activa ? 'Desactivar' : 'Activar' }}">
                                <i class="bi {{ $x->activa ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.kpi.disponibilidad.excepciones.destroy', $x) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar esta excepción? Los meses afectados se recalcularán sin el descuento.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <tr id="edit-exc-{{ $x->id }}" style="display:none;background:#f8fafc">
                    <td colspan="7">
                        <form method="POST" action="{{ route('admin.kpi.disponibilidad.excepciones.update', $x) }}" class="row g-2 align-items-end">
                            @csrf @method('PUT')
                            <input type="hidden" name="host_name" value="{{ $x->host_name }}">
                            <input type="hidden" name="service_description" value="{{ $x->service_description }}">
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:.72rem">Desde</label>
                                <input type="datetime-local" name="desde" class="form-control form-control-sm" value="{{ $x->desde->format('Y-m-d\TH:i') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:.72rem">Hasta</label>
                                <input type="datetime-local" name="hasta" class="form-control form-control-sm" value="{{ $x->hasta->format('Y-m-d\TH:i') }}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size:.72rem">Categoría</label>
                                <input type="text" name="categoria" class="form-control form-control-sm" value="{{ $x->categoria }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:.72rem">Justificación</label>
                                <input type="text" name="justificacion" class="form-control form-control-sm" value="{{ $x->justificacion }}" required>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i></button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.btn-edit-exc').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = document.getElementById('edit-exc-' + btn.dataset.id);
        row.style.display = row.style.display === 'none' ? '' : 'none';
    });
});
</script>
@endpush
@endsection
