@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    {{-- Encabezado --}}
    <div class="vti-page-header">
        <h4>Facturas Pendientes</h4>
        <form action="{{ route('facturas.pendientes') }}" method="GET">
            <div class="d-flex gap-2 align-items-center">
                <select name="mes" class="form-select form-select-sm" style="width:130px">
                    @foreach($mesesDisponibles as $num => $nombre)
                        <option value="{{ $num }}" {{ $mesSeleccionado == $num ? 'selected' : '' }}>
                            {{ ucfirst($nombre) }}
                        </option>
                    @endforeach
                </select>
                <select name="anio" class="form-select form-select-sm" style="width:90px">
                    @foreach($añosDisponibles as $anioOpcion)
                        <option value="{{ $anioOpcion }}" {{ $anioSeleccionado == $anioOpcion ? 'selected' : '' }}>
                            {{ $anioOpcion }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel-fill"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    @php
        $pendientesActual  = $serviciosActual->where('factura_pendiente', true)->count();
        $pendientesAnterior = $serviciosAnterior->where('factura_pendiente', true)->count();
    @endphp

    {{-- Mes seleccionado --}}
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.06em">
            {{ ucfirst(\Carbon\Carbon::createFromDate($anioActual, $mesActual)->translatedFormat('F Y')) }}
        </h6>
        @if($pendientesActual > 0)
            <span class="badge bg-danger">{{ $pendientesActual }} pendiente(s)</span>
        @else
            <span class="badge bg-success">Al día</span>
        @endif
    </div>

    <div class="vti-table-wrapper mb-4">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Compañía</th>
                    <th>Servicio</th>
                    <th>Concepto</th>
                    <th>Cuenta Contable</th>
                    <th>Fecha Esperada</th>
                    <th class="text-end">Neto</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($serviciosActual as $data)
                <tr style="background:{{ $data['factura_pendiente'] ? 'rgba(239,68,68,.08)' : 'rgba(34,197,94,.08)' }} !important;">
                    <td>{{ $data['servicio']->empresa->nombre ?? 'N/A' }}</td>
                    <td>{{ $data['servicio']->compania->nombre ?? 'N/A' }}</td>
                    <td>{{ $data['servicio']->servicio }}</td>
                    <td>{{ $data['servicio']->concepto }}</td>
                    <td>{{ $data['servicio']->cuentacontable->numero_cuenta }}</td>
                    <td>{{ $data['fecha_esperada_factura'] }}</td>
                    <td class="text-end fw-semibold">
                        @if(!$data['factura_pendiente'])
                            $ {{ number_format($data['factura']->valor_neto, 0, ',', '.') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($data['factura_pendiente'])
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger">Pendiente</span>
                                <a href="{{ route('facturas.create', ['id_servicio' => $data['servicio']->id]) }}"
                                   class="vti-btn-view" title="Registrar factura"
                                   style="background:#dcfce7;color:#166534;">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success">Facturada</span>
                                <a href="{{ route('facturas.edit', $data['factura']->id) }}"
                                   class="vti-btn-edit" title="Editar factura">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="8">No hay servicios periódicos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mes anterior --}}
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.06em">
            {{ ucfirst(\Carbon\Carbon::createFromDate($anioAnterior, $mesAnterior)->translatedFormat('F Y')) }}
        </h6>
        @if($pendientesAnterior > 0)
            <span class="badge bg-warning text-dark">{{ $pendientesAnterior }} pendiente(s)</span>
        @else
            <span class="badge bg-success">Al día</span>
        @endif
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Compañía</th>
                    <th>Servicio</th>
                    <th>Concepto</th>
                    <th>Cuenta Contable</th>
                    <th>Fecha Esperada</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($serviciosAnterior as $data)
                <tr style="background:{{ $data['factura_pendiente'] ? 'rgba(239,68,68,.08)' : 'rgba(34,197,94,.08)' }} !important;">
                    <td>{{ $data['servicio']->empresa->nombre ?? 'N/A' }}</td>
                    <td>{{ $data['servicio']->compania->nombre ?? 'N/A' }}</td>
                    <td>{{ $data['servicio']->servicio }}</td>
                    <td>{{ $data['servicio']->concepto }}</td>
                    <td>{{ $data['servicio']->cuentacontable->numero_cuenta }}</td>
                    <td>{{ $data['fecha_esperada_factura'] }}</td>
                    <td>
                        @if($data['factura_pendiente'])
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger">Pendiente</span>
                                <a href="{{ route('facturas.create', ['id_servicio' => $data['servicio']->id]) }}"
                                   class="vti-btn-view" title="Registrar factura"
                                   style="background:#dcfce7;color:#166534;">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success">Facturada</span>
                                <a href="{{ route('facturas.edit', $data['factura']->id) }}"
                                   class="vti-btn-edit" title="Editar factura">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="7">No hay servicios periódicos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
