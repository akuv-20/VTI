@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    {{-- Encabezado --}}
    <div class="vti-page-header">
        <div>
            <h4>
                Importación Entel — Folio <strong>{{ $importacion->folio }}</strong>
                @if($importacion->tipo_servicio === 'Movil')
                    <span class="badge bg-primary ms-2">Móvil</span>
                @else
                    <span class="badge bg-warning text-dark ms-2">BAM</span>
                @endif
            </h4>
            <small class="text-muted">Período: {{ $importacion->periodo_cobro }}</small>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('importaciones_entel.recruzar', $importacion) }}" method="POST"
                  onsubmit="return confirm('¿Re-procesar el cruce con las líneas del sistema?')">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>Re-cruzar líneas
                </button>
            </form>
            <a href="{{ route('importaciones_entel.index') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    {{-- Resumen --}}
    <div class="d-flex gap-3 flex-wrap mb-4">
        <div class="card border-0 shadow-sm text-center px-4 py-3" style="min-width:130px">
            <div class="fs-3 fw-bold text-dark">{{ number_format($importacion->total_lineas) }}</div>
            <small class="text-muted">Total en importación</small>
        </div>
        <div class="card border-0 shadow-sm text-center px-4 py-3" style="min-width:130px;background:#f0fdf4">
            <div class="fs-3 fw-bold text-success">{{ number_format($enSistema) }}</div>
            <small class="text-muted">Cruzadas con sistema</small>
        </div>
        <div class="card border-0 shadow-sm text-center px-4 py-3" style="min-width:130px;background:#fef2f2">
            <div class="fs-3 fw-bold text-danger">{{ number_format($sinSistema) }}</div>
            <small class="text-muted">En importación, no en sistema</small>
        </div>
        <div class="card border-0 shadow-sm text-center px-4 py-3" style="min-width:130px;background:#fff7ed">
            <div class="fs-3 fw-bold text-warning">{{ number_format($lineasSinImportar->count()) }}</div>
            <small class="text-muted">En sistema, no en importación</small>
        </div>
        @if($lineasInactivasFacturadas->count() > 0)
        <div class="card border-0 shadow-sm text-center px-4 py-3" style="min-width:130px;background:#fff4ed">
            <div class="fs-3 fw-bold" style="color:#f97316">{{ number_format($lineasInactivasFacturadas->count()) }}</div>
            <small class="text-muted">Inactivas aún facturadas</small>
        </div>
        @endif
        <div class="card border-0 shadow-sm text-center px-4 py-3" style="min-width:130px;background:#eff6ff">
            <div class="fs-3 fw-bold text-primary">
                $ {{ number_format($importacion->detalles->sum('monto'), 0, ',', '.') }}
            </div>
            <small class="text-muted">Neto total</small>
        </div>
    </div>

    {{-- ① Inactivas en sistema pero sí facturadas --}}
    @if($lineasInactivasFacturadas->count() > 0)
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.06em">
            ⚠ Inactivas en sistema pero se siguen facturando
        </h6>
        <span class="badge bg-orange text-dark" style="background:#f97316!important">{{ $lineasInactivasFacturadas->count() }}</span>
    </div>
    <div class="vti-table-wrapper mb-4">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>N° Servicio</th>
                    <th>Línea (sistema)</th>
                    <th>Usuario</th>
                    <th>Empresa</th>
                    <th class="text-end">Monto</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineasInactivasFacturadas as $detalle)
                <tr style="background:rgba(249,115,22,.1) !important">
                    <td><strong>{{ $detalle->numero_servicio }}</strong></td>
                    <td>{{ $detalle->lineaTelefonica->linea }}</td>
                    <td>{{ $detalle->lineaTelefonica->usuario?->nombre ?? '—' }}</td>
                    <td>{{ $detalle->lineaTelefonica->empresa?->nombre ?? '—' }}</td>
                    <td class="text-end fw-semibold">$ {{ number_format($detalle->monto, 0, ',', '.') }}</td>
                    <td>
                        <a href="{{ route('lineas_telefonicas.edit', $detalle->lineaTelefonica->id) }}"
                           class="vti-btn-edit" title="Editar línea">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ② Líneas de importación sin cruzar --}}
    @if($sinSistema > 0)
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.06em">
            En importación pero NO registradas en el sistema
        </h6>
        <span class="badge bg-danger">{{ $sinSistema }}</span>
    </div>
    <div class="vti-table-wrapper mb-4">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>N° Servicio</th>
                    <th>Plan Tarifario</th>
                    <th class="text-end">Monto Neto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($importacion->detalles->whereNull('id_linea_telefonica') as $detalle)
                <tr style="background:rgba(239,68,68,.07) !important">
                    <td><strong>{{ $detalle->numero_servicio }}</strong></td>
                    <td><small class="text-muted">{{ $detalle->plan_tarifario ?? '—' }}</small></td>
                    <td class="text-end fw-semibold">$ {{ number_format($detalle->monto, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ② Líneas del sistema no encontradas en importación --}}
    @if($lineasSinImportar->count() > 0)
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.06em">
            En sistema pero NO aparecen en esta importación
        </h6>
        <span class="badge bg-warning text-dark">{{ $lineasSinImportar->count() }}</span>
    </div>
    <div class="vti-table-wrapper mb-4">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Línea</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Empresa</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineasSinImportar as $linea)
                @php $inactiva = $linea->estado === 'Inactivo'; @endphp
                <tr style="background:{{ $inactiva ? 'rgba(100,116,139,.06)' : 'rgba(234,179,8,.07)' }} !important">
                    <td><strong>{{ $linea->linea }}</strong></td>
                    <td>
                        @if($inactiva)
                            <span class="badge bg-secondary">Inactiva</span>
                        @else
                            <span class="badge bg-success">Activa</span>
                        @endif
                    </td>
                    <td>{{ $linea->usuario?->nombre ?? '—' }}</td>
                    <td>{{ $linea->empresa?->nombre ?? '—' }}</td>
                    <td>
                        <a href="{{ route('lineas_telefonicas.edit', $linea->id) }}"
                           class="vti-btn-edit" title="Editar línea">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ③ Todas las líneas de la importación --}}
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.06em">
            Detalle completo de la importación
        </h6>
        <span class="badge bg-secondary">{{ $importacion->total_lineas }}</span>
    </div>
    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>N° Servicio</th>
                    <th>Plan Tarifario</th>
                    <th class="text-end">Monto Neto</th>
                    <th>Estado cruce</th>
                    <th>Usuario (sistema)</th>
                    <th>Empresa (sistema)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($importacion->detalles as $detalle)
                <tr style="background:{{ $detalle->id_linea_telefonica ? 'rgba(34,197,94,.06)' : 'rgba(239,68,68,.07)' }} !important">
                    <td><strong>{{ $detalle->numero_servicio }}</strong></td>
                    <td><small class="text-muted">{{ $detalle->plan_tarifario ?? '—' }}</small></td>
                    <td class="text-end fw-semibold">$ {{ number_format($detalle->monto, 0, ',', '.') }}</td>
                    <td>
                        @if($detalle->id_linea_telefonica)
                            <span class="badge bg-success">✓ Cruzada</span>
                        @else
                            <span class="badge bg-danger">✗ Sin registrar</span>
                        @endif
                    </td>
                    <td>{{ $detalle->lineaTelefonica?->usuario?->nombre ?? '—' }}</td>
                    <td>{{ $detalle->lineaTelefonica?->empresa?->nombre ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
