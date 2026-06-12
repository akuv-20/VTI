@extends('layouts.app')

@push('styles')
<style>
    .informe-table { font-size: 0.85rem; }
    .row-empresa td { background-color: #198754 !important; color: #fff; font-weight: 700; font-size: 0.9rem; }
    .row-ccosto  td { background-color: #d1e7dd !important; color: #0a3622; font-weight: 600; }
    .row-usuario td { background-color: #fff; }
    .row-subtotal td{ background-color: #f0f9f4 !important; font-weight: 600; color: #0a3622; border-top: 1px solid #a3cfbb; }
    .row-total   td { background-color: #0a3622 !important; color: #fff; font-weight: 700; font-size: 0.95rem; }
    .col-monto { text-align: right; width: 110px; }
    .col-ccosto { width: 110px; }

    @media print {
        @page { size: A4 portrait; margin: 0.8cm 1cm; }

        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

        /* Ocultar chrome del layout */
        .no-print,
        .vti-sidebar,
        header.vti-topbar { display: none !important; }

        /* El contenido principal ocupa todo el ancho sin margen del sidebar */
        #app.vti-main {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        main.vti-main-inner {
            padding: 0 !important;
        }
        .container-fluid { padding: 0 !important; margin: 0 !important; }

        .print-header { display: block !important; margin-bottom: 6px; font-size: 0.78rem; }

        .informe-table { font-size: 0.65rem !important; }
        .informe-table td, .informe-table th { padding: 1px 4px !important; line-height: 1.3; }

        /* La fila de empresa no queda sola al final de página */
        .row-empresa   { break-after: avoid; }
        /* Cada bloque CC (cabecera + líneas) no se corta */
        .grupo-cc      { break-inside: avoid; }
        .row-total     { break-inside: avoid; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- Filtros --}}
    <div class="mb-3 no-print">
        <div class="vti-page-header">
            <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Informe Telefonía</h4>
            @if($datos !== null)
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
            @endif
        </div>
        <form method="GET" action="{{ route('informes.telefonia') }}" id="formInforme">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-auto">
                    <label class="form-label small mb-1 text-muted">Servicio</label>
                    <select name="servicio" id="sel_servicio" class="form-select form-select-sm" required>
                        <option value="">— Seleccionar —</option>
                        <optgroup label="Movistar">
                            <option value="Movistar_Movil" {{ $servicio === 'Movistar_Movil' ? 'selected' : '' }}>Movistar Móvil</option>
                            <option value="Movistar_BAM"   {{ $servicio === 'Movistar_BAM'   ? 'selected' : '' }}>Movistar BAM</option>
                        </optgroup>
                        <optgroup label="Entel">
                            <option value="Entel_Movil"    {{ $servicio === 'Entel_Movil'    ? 'selected' : '' }}>Entel Móvil</option>
                            <option value="Entel_BAM"      {{ $servicio === 'Entel_BAM'      ? 'selected' : '' }}>Entel BAM</option>
                        </optgroup>
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-md-auto">
                    <label class="form-label small mb-1 text-muted">Año</label>
                    <select name="anio" id="sel_anio" class="form-select form-select-sm" required>
                        <option value="">Año</option>
                        @foreach($periodos->pluck('anio')->unique()->sort()->reverse() as $y)
                            <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-md-auto">
                    <label class="form-label small mb-1 text-muted">Mes</label>
                    <select name="mes" id="sel_mes" class="form-select form-select-sm" required>
                        <option value="">Mes</option>
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ $meses[$m] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-bar-chart-fill me-1"></i>Ver informe
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if($datos === null)
        <div class="alert alert-info no-print">Selecciona un servicio y período para generar el informe.</div>

    @elseif(empty($datos))
        <div class="alert alert-warning no-print">No hay datos para el período y servicio seleccionado.</div>

    @else
        @php
            $servicioLabel = match($servicio) {
                'Movistar_Movil' => 'Movistar Móvil',
                'Movistar_BAM'   => 'Movistar BAM',
                'Entel_Movil'    => 'Entel Móvil',
                'Entel_BAM'      => 'Entel BAM',
                default          => $servicio,
            };
        @endphp

        {{-- Encabezado visible solo en impresión --}}
        <div class="print-header" style="display:none;">
            <strong>Informe Telefonía — {{ $servicioLabel }}{{ $folio ? ' — Factura ' . $folio : '' }} — {{ $meses[$mes] }} {{ $anio }}</strong>
        </div>

        {{-- Encabezado pantalla --}}
        <div class="mb-3 no-print">
            <span style="font-size:1.15rem;font-weight:700;color:#1e293b">
                {{ $servicioLabel }}
                @if($folio)
                    <span style="color:#2563eb"> — Factura <span style="font-size:1.25rem">{{ $folio }}</span></span>
                @endif
            </span>
            <span class="text-muted ms-2" style="font-size:.95rem;font-weight:600">{{ $meses[$mes] }} {{ $anio }}</span>
        </div>

        <table class="table table-bordered table-sm informe-table mb-0">
            {{-- Un tbody por empresa para fila de encabezado --}}
            @foreach($datos as $empresa => $ccostos)
                <tbody>
                    <tr class="row-empresa">
                        <td colspan="4">{{ $empresa }}</td>
                    </tr>
                </tbody>
                @foreach($ccostos as $ccosto => $info)
                    <tbody class="grupo-cc">
                        <tr class="row-ccosto">
                            <td>{{ $ccosto }}</td>
                            <td>{{ $info['ubicacion'] }}</td>
                            <td></td>
                            <td class="col-monto"></td>
                        </tr>
                        @foreach($info['lineas'] as $usuario => $monto)
                            <tr class="row-usuario">
                                <td></td>
                                <td></td>
                                <td class="ps-4">{{ $usuario }}</td>
                                <td class="col-monto">{{ number_format($monto, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            @endforeach

            <tbody>
                <tr class="row-total">
                    <td colspan="3" class="text-end pe-3">TOTAL GENERAL</td>
                    <td class="col-monto">{{ number_format($totalGeneral, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

<script>
const ultimoPorServicio = @json($ultimoPorServicio);

document.getElementById('sel_servicio').addEventListener('change', function () {
    const ultimo = ultimoPorServicio[this.value];
    if (!ultimo) return;

    const selAnio = document.getElementById('sel_anio');
    const selMes  = document.getElementById('sel_mes');

    // Seleccionar año
    for (let opt of selAnio.options) {
        if (parseInt(opt.value) === ultimo.anio) { opt.selected = true; break; }
    }
    // Seleccionar mes
    for (let opt of selMes.options) {
        if (parseInt(opt.value) === ultimo.mes) { opt.selected = true; break; }
    }

    // Enviar formulario automáticamente
    document.getElementById('formInforme').submit();
});
</script>
@endsection
