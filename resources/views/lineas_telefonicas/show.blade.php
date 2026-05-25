@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
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
            <a href="{{ route('lineas_telefonicas.edit', $lineas_telefonica->id) }}"
               class="btn btn-warning btn-sm">
                <i class="bi bi-pencil-fill me-1"></i>Editar
            </a>
            <a href="{{ route('lineas_telefonicas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    <div class="row g-4" style="max-width:900px">

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

    </div>
</div>
@endsection
