@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header" style="max-width:900px;margin-left:auto;margin-right:auto">
        <h4>
            <i class="bi bi-telephone-fill me-2"></i>
            Línea <strong>{{ $lineas_telefonica->linea }}</strong>
            @if($lineas_telefonica->estado === 'Activo')
                <span class="badge bg-success ms-2" style="font-size:.65rem;vertical-align:middle">Activo</span>
            @else
                <span class="badge bg-danger ms-2" style="font-size:.65rem;vertical-align:middle">Inactivo</span>
            @endif
        </h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm" id="btnAbrirActaEntrega">
                <i class="bi bi-file-earmark-text-fill me-1"></i>Imprimir Acta de Entrega
            </button>
            <a href="{{ route('lineas_telefonicas.edit', $lineas_telefonica->id) }}"
               class="btn btn-warning btn-sm">
                <i class="bi bi-pencil-fill me-1"></i>Editar
            </a>
            <a href="{{ route('lineas_telefonicas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    <div class="row g-4" style="max-width:900px;margin-left:auto;margin-right:auto">

        {{-- ── Datos generales ──────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header fw-bold border-0" style="background:#f8fafc">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Datos de la línea
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Número</div>
                            <div class="fw-bold">{{ $lineas_telefonica->linea }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Emisor</div>
                            <div>{{ $lineas_telefonica->emisor->nombre ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Estado</div>
                            <div>
                                @if($lineas_telefonica->estado === 'Activo')
                                    <span class="badge bg-success">Activo</span>
                                @else
                                    <span class="badge bg-danger">Inactivo</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Usuario actual</div>
                            <div>{{ $lineas_telefonica->usuario->nombre ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Empresa</div>
                            <div>{{ $lineas_telefonica->empresa->nombre ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Ubicación</div>
                            <div>{{ $lineas_telefonica->ubicacion->nombre ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Centro de Costo</div>
                            <div>{{ $lineas_telefonica->centroCosto?->ccosto ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Aparato</div>
                            <div>
                                @if($lineas_telefonica->aparato)
                                    {{ $lineas_telefonica->aparato->marca->nombre ?? '' }}
                                    {{ $lineas_telefonica->aparato->modelo }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        @if($lineas_telefonica->imei_equipo)
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">IMEI Equipo</div>
                            <div class="font-monospace" style="font-size:.85rem">{{ $lineas_telefonica->imei_equipo }}</div>
                        </div>
                        @endif
                        @if($lineas_telefonica->imei_sim)
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">IMEI SIM</div>
                            <div class="font-monospace" style="font-size:.85rem">{{ $lineas_telefonica->imei_sim }}</div>
                        </div>
                        @endif
                        @if($lineas_telefonica->fecha_entrega_sim)
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Entrega SIM</div>
                            <div>{{ \Carbon\Carbon::parse($lineas_telefonica->fecha_entrega_sim)->format('d/m/Y') }}</div>
                        </div>
                        @endif
                        @if($lineas_telefonica->fecha_renovacion_equipo)
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Renovación Equipo</div>
                            <div>{{ \Carbon\Carbon::parse($lineas_telefonica->fecha_renovacion_equipo)->format('d/m/Y') }}</div>
                        </div>
                        @endif
                        @if($lineas_telefonica->observacion)
                        <div class="col-12">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Observación</div>
                            <div>{{ $lineas_telefonica->observacion }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Historial de usuarios ─────────────────────────────────── --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header fw-bold border-0 d-flex align-items-center justify-content-between"
                     style="background:#f8fafc">
                    <span><i class="bi bi-clock-history me-2 text-primary"></i>Historial de usuarios</span>
                    @if($lineas_telefonica->historialUsuarios->count() > 0)
                        <span class="badge rounded-pill bg-primary" style="font-size:.72rem">
                            {{ $lineas_telefonica->historialUsuarios->count() }}
                            {{ $lineas_telefonica->historialUsuarios->count() === 1 ? 'cambio' : 'cambios' }}
                        </span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($lineas_telefonica->historialUsuarios->isEmpty())
                        <div class="text-center py-4 text-muted" style="font-size:.88rem">
                            <i class="bi bi-clock me-1"></i>Sin historial de cambios aún.
                        </div>
                    @else
                        <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                            <table class="vti-table">
                                <thead>
                                    <tr>
                                        <th style="width:180px">Fecha del cambio</th>
                                        <th>Usuario anterior</th>
                                        <th style="width:40px;text-align:center">
                                            <i class="bi bi-arrow-right"></i>
                                        </th>
                                        <th>Usuario nuevo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lineas_telefonica->historialUsuarios as $h)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">
                                                {{ $h->created_at->format('d/m/Y') }}
                                            </span>
                                            <span class="text-muted ms-1" style="font-size:.78rem">
                                                {{ $h->created_at->format('H:i') }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($h->usuarioAnterior)
                                                <span class="badge rounded-pill"
                                                      style="background:#fef3c7;color:#92400e;font-weight:600;font-size:.78rem">
                                                    {{ $h->usuarioAnterior->nombre }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin asignar</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-muted">
                                            <i class="bi bi-arrow-right"></i>
                                        </td>
                                        <td>
                                            @if($h->usuarioNuevo)
                                                <span class="badge rounded-pill"
                                                      style="background:#dcfce7;color:#166534;font-weight:600;font-size:.78rem">
                                                    {{ $h->usuarioNuevo->nombre }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin asignar</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Historial de IMEI ─────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header fw-bold border-0 d-flex align-items-center justify-content-between"
                     style="background:#f8fafc">
                    <span><i class="bi bi-cpu me-2 text-secondary"></i>Historial de IMEI / SIM</span>
                    @if($lineas_telefonica->historialImei->count() > 0)
                        <span class="badge rounded-pill bg-secondary" style="font-size:.72rem">
                            {{ $lineas_telefonica->historialImei->count() }}
                            {{ $lineas_telefonica->historialImei->count() === 1 ? 'cambio' : 'cambios' }}
                        </span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($lineas_telefonica->historialImei->isEmpty())
                        <div class="text-center py-4 text-muted" style="font-size:.88rem">
                            <i class="bi bi-clock me-1"></i>Sin historial de cambios aún.
                        </div>
                    @else
                        <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                            <table class="vti-table">
                                <thead>
                                    <tr>
                                        <th style="width:180px">Fecha del cambio</th>
                                        <th style="width:120px">Campo</th>
                                        <th>Valor anterior</th>
                                        <th style="width:40px;text-align:center">
                                            <i class="bi bi-arrow-right"></i>
                                        </th>
                                        <th>Valor nuevo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lineas_telefonica->historialImei as $h)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">
                                                {{ $h->created_at->format('d/m/Y') }}
                                            </span>
                                            <span class="text-muted ms-1" style="font-size:.78rem">
                                                {{ $h->created_at->format('H:i') }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border" style="font-size:.75rem">
                                                {{ $h->label }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($h->valor_anterior)
                                                <span class="font-monospace"
                                                      style="font-size:.82rem;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px">
                                                    {{ $h->valor_anterior }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin valor</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-muted">
                                            <i class="bi bi-arrow-right"></i>
                                        </td>
                                        <td>
                                            @if($h->valor_nuevo)
                                                <span class="font-monospace"
                                                      style="font-size:.82rem;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px">
                                                    {{ $h->valor_nuevo }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin valor</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Historial de Aparatos ────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header fw-bold border-0 d-flex align-items-center justify-content-between"
                     style="background:#f8fafc">
                    <span><i class="bi bi-phone me-2 text-primary"></i>Historial de Aparatos</span>
                    @if($lineas_telefonica->historialAparato->count() > 0)
                        <span class="badge rounded-pill bg-primary" style="font-size:.72rem">
                            {{ $lineas_telefonica->historialAparato->count() }}
                            {{ $lineas_telefonica->historialAparato->count() === 1 ? 'cambio' : 'cambios' }}
                        </span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($lineas_telefonica->historialAparato->isEmpty())
                        <div class="text-center py-4 text-muted" style="font-size:.88rem">
                            <i class="bi bi-clock me-1"></i>Sin historial de cambios aún.
                        </div>
                    @else
                        <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                            <table class="vti-table">
                                <thead>
                                    <tr>
                                        <th style="width:180px">Fecha del cambio</th>
                                        <th>Aparato anterior</th>
                                        <th style="width:40px;text-align:center"><i class="bi bi-arrow-right"></i></th>
                                        <th>Aparato nuevo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lineas_telefonica->historialAparato as $h)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $h->created_at->format('d/m/Y') }}</span>
                                            <span class="text-muted ms-1" style="font-size:.78rem">{{ $h->created_at->format('H:i') }}</span>
                                        </td>
                                        <td>
                                            @if($h->aparatoAnterior)
                                                <span class="badge rounded-pill"
                                                      style="background:#fef3c7;color:#92400e;font-weight:600;font-size:.78rem">
                                                    {{ $h->aparatoAnterior->marca->nombre ?? '' }} {{ $h->aparatoAnterior->modelo }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin asignar</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-muted"><i class="bi bi-arrow-right"></i></td>
                                        <td>
                                            @if($h->aparatoNuevo)
                                                <span class="badge rounded-pill"
                                                      style="background:#dcfce7;color:#166534;font-weight:600;font-size:.78rem">
                                                    {{ $h->aparatoNuevo->marca->nombre ?? '' }} {{ $h->aparatoNuevo->modelo }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin asignar</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Historial de Ubicaciones ──────────────────────────────────── --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header fw-bold border-0 d-flex align-items-center justify-content-between"
                     style="background:#f8fafc">
                    <span><i class="bi bi-geo-alt me-2 text-warning"></i>Historial de Ubicaciones</span>
                    @if($lineas_telefonica->historialUbicacion->count() > 0)
                        <span class="badge rounded-pill bg-warning text-dark" style="font-size:.72rem">
                            {{ $lineas_telefonica->historialUbicacion->count() }}
                            {{ $lineas_telefonica->historialUbicacion->count() === 1 ? 'cambio' : 'cambios' }}
                        </span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($lineas_telefonica->historialUbicacion->isEmpty())
                        <div class="text-center py-4 text-muted" style="font-size:.88rem">
                            <i class="bi bi-clock me-1"></i>Sin historial de cambios aún.
                        </div>
                    @else
                        <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                            <table class="vti-table">
                                <thead>
                                    <tr>
                                        <th style="width:180px">Fecha del cambio</th>
                                        <th>Ubicación anterior</th>
                                        <th style="width:40px;text-align:center"><i class="bi bi-arrow-right"></i></th>
                                        <th>Ubicación nueva</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lineas_telefonica->historialUbicacion as $h)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $h->created_at->format('d/m/Y') }}</span>
                                            <span class="text-muted ms-1" style="font-size:.78rem">{{ $h->created_at->format('H:i') }}</span>
                                        </td>
                                        <td>
                                            @if($h->ubicacionAnterior)
                                                <span class="badge rounded-pill"
                                                      style="background:#fef3c7;color:#92400e;font-weight:600;font-size:.78rem">
                                                    {{ $h->ubicacionAnterior->nombre }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin asignar</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-muted"><i class="bi bi-arrow-right"></i></td>
                                        <td>
                                            @if($h->ubicacionNueva)
                                                <span class="badge rounded-pill"
                                                      style="background:#dcfce7;color:#166534;font-weight:600;font-size:.78rem">
                                                    {{ $h->ubicacionNueva->nombre }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic" style="font-size:.82rem">Sin asignar</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ── Modal Acta de Entrega ──────────────────────────────────────────────── --}}
<div class="modal fade" id="modalActaEntrega" tabindex="-1" aria-labelledby="modalActaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('actas_entrega_telefono.store', $lineas_telefonica) }}"
                  target="_blank">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalActaLabel">
                        <i class="bi bi-file-earmark-text-fill me-2 text-success"></i>
                        Acta de Entrega — Línea {{ $lineas_telefonica->linea }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Resumen --}}
                    <div class="alert alert-light border mb-4" style="font-size:.85rem">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="text-muted">Empleado:</span>
                                <strong>{{ $lineas_telefonica->usuario->nombre ?? '(sin asignar)' }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Zona:</span>
                                <strong>
                                    {{ $lineas_telefonica->empresa->nombre ?? '' }}
                                    @if($lineas_telefonica->empresa && $lineas_telefonica->ubicacion) — @endif
                                    {{ $lineas_telefonica->ubicacion->nombre ?? '' }}
                                </strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Equipo:</span>
                                <strong>
                                    {{ $lineas_telefonica->aparato?->marca?->nombre }}
                                    {{ $lineas_telefonica->aparato?->modelo ?? '—' }}
                                </strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Compañía:</span>
                                <strong>{{ $lineas_telefonica->emisor->nombre ?? '—' }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Condición --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Condición del equipo</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="condicion" id="condNuevo"
                                       value="Nuevo" checked>
                                <label class="form-check-label" for="condNuevo">Nuevo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="condicion" id="condUsado"
                                       value="Usado">
                                <label class="form-check-label" for="condUsado">Usado</label>
                            </div>
                        </div>
                    </div>

                    {{-- Accesorios --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Accesorios</label>
                        <table class="table table-sm table-bordered" style="font-size:.88rem">
                            <thead class="table-light">
                                <tr>
                                    <th>Accesorio</th>
                                    <th class="text-center" style="width:90px">SI</th>
                                    <th class="text-center" style="width:90px">NO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    'cargador_usb'   => 'Cargador (Cable USB C)',
                                    'cargador_auto'  => 'Cargador de automóvil',
                                    'manos_libres'   => 'Manos libres (auricular)',
                                    'cd_informacion' => 'Cd de información',
                                ] as $key => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-center">
                                        <input type="radio" name="accesorios[{{ $key }}]"
                                               value="SI" class="form-check-input">
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" name="accesorios[{{ $key }}]"
                                               value="NO" class="form-check-input" checked>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Documentación --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Documentación</label>
                        <table class="table table-sm table-bordered" style="font-size:.88rem">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento</th>
                                    <th class="text-center" style="width:90px">SI</th>
                                    <th class="text-center" style="width:90px">NO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    'manual_propietario' => 'Manual del Propietario',
                                    'procedimiento_uso'  => 'Procedimiento uso de teléfono móvil',
                                ] as $key => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-center">
                                        <input type="radio" name="documentacion[{{ $key }}]"
                                               value="SI" class="form-check-input">
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" name="documentacion[{{ $key }}]"
                                               value="NO" class="form-check-input" checked>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Observación --}}
                    <div>
                        <label for="observacionActa" class="form-label fw-semibold">Observación</label>
                        <textarea class="form-control" id="observacionActa" name="observacion"
                                  rows="2" maxlength="500" placeholder="(opcional)"></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-printer-fill me-1"></i>Generar e Imprimir Acta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl  = document.getElementById('modalActaEntrega');
    const btnAbrir = document.getElementById('btnAbrirActaEntrega');
    let fallbackBackdrop = null;

    // ── Abrir modal: Bootstrap si está disponible, fallback manual si no ──
    function abrirModal() {
        try {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } catch (e) {
            // Fallback sin Bootstrap JS (producción)
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(fallbackBackdrop);
        }
    }

    function cerrarModalFallback() {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        document.body.classList.remove('modal-open');
        fallbackBackdrop?.remove();
        fallbackBackdrop = null;
    }

    btnAbrir?.addEventListener('click', abrirModal);

    // Cierre en modo fallback: botón X, Cancelar y clic fuera del diálogo
    modalEl?.addEventListener('click', function (e) {
        if (!fallbackBackdrop) return; // Bootstrap maneja su propio cierre
        if (e.target.closest('[data-bs-dismiss="modal"]') || e.target === modalEl) {
            cerrarModalFallback();
        }
    });
})();
</script>
@endpush
