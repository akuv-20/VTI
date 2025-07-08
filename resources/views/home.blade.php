@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            {{-- Formulario de Filtro por Mes y Año --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0">{{ __('Filtrar Facturas Pendientes') }}</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('home') }}" method="GET" class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label for="mes">Seleccionar Mes:</label>
                                <select name="mes" id="mes" class="form-select">
                                    @foreach ($mesesDisponibles as $num => $nombre)
                                        <option value="{{ $num }}" {{ $mesSeleccionado == $num ? 'selected' : '' }}>
                                            {{ $nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="anio">Seleccionar Año:</label>
                                <select name="anio" id="anio" class="form-select">
                                    @foreach ($añosDisponibles as $anioOpcion)
                                        <option value="{{ $anioOpcion }}" {{ $anioSeleccionado == $anioOpcion ? 'selected' : '' }}>
                                            {{ $anioOpcion }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Aplicar Filtro</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tarjeta para el Mes Seleccionado (antes "Mes Actual") --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0">{{ __('Estado de Facturas') }} - {{ \Carbon\Carbon::createFromDate($anioParaTablaActual, $mesParaTablaActual)->translatedFormat('F Y') }}</h3>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($serviciosConEstadoFacturaActual->isEmpty())
                        <div class="alert alert-info" role="alert">
                            No hay servicios registrados o no hay información de facturas para mostrar en este mes.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Compañía</th>
                                        <th>Servicio</th>
                                        <th>Concepto</th>
                                        <th>Fecha Facturación Esperada</th>
                                        <th>Estado Factura ({{ \Carbon\Carbon::createFromDate($anioParaTablaActual, $mesParaTablaActual)->translatedFormat('M') }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($serviciosConEstadoFacturaActual as $data)
                                        <tr>
                                            <td>{{ $data['servicio']->empresa->nombre ?? 'N/A' }}</td>
                                            <td>{{ $data['servicio']->compania->nombre ?? 'N/A' }}</td>
                                            <td>{{ $data['servicio']->servicio }}</td>
                                            <td>{{ $data['servicio']->concepto }}</td>
                                            <td>{{ $data['fecha_esperada_factura'] }}</td>
                                            <td>
                                                @if ($data['factura_pendiente'])
                                                    <span class="badge bg-danger">Pendiente</span>
                                                @else
                                                    <span class="badge bg-success">Facturada</span>
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

            {{-- Tarjeta para el Mes Anterior al Seleccionado --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">{{ __('Estado de Facturas') }} - {{ \Carbon\Carbon::createFromDate($anioParaTablaAnterior, $mesParaTablaAnterior)->translatedFormat('F Y') }}</h3>
                </div>

                <div class="card-body">
                    @if ($serviciosConEstadoFacturaAnterior->isEmpty())
                        <div class="alert alert-info" role="alert">
                            No hay servicios registrados o no hay información de facturas para mostrar en el mes anterior.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Compañía</th>
                                        <th>Servicio</th>
                                        <th>Concepto</th>
                                        <th>Fecha Facturación Esperada</th>
                                        <th>Estado Factura ({{ \Carbon\Carbon::createFromDate($anioParaTablaAnterior, $mesParaTablaAnterior)->translatedFormat('M') }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($serviciosConEstadoFacturaAnterior as $data)
                                        <tr>
                                            <td>{{ $data['servicio']->empresa->nombre ?? 'N/A' }}</td>
                                            <td>{{ $data['servicio']->compania->nombre ?? 'N/A' }}</td>
                                            <td>{{ $data['servicio']->servicio }}</td>
                                            <td>{{ $data['servicio']->concepto }}</td>
                                            <td>{{ $data['fecha_esperada_factura'] }}</td>
                                            <td>
                                                @if ($data['factura_pendiente'])
                                                    <span class="badge bg-danger">Pendiente</span>
                                                @else
                                                    <span class="badge bg-success">Facturada</span>
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