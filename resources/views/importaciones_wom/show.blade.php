@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-file-earmark-spreadsheet me-2"></i>
            Importación WOM — {{ $importacion->periodo_label }}
        </h4>
        <div class="d-flex gap-2">
            <a href="#" onclick="window.open('{{ route('importaciones_wom.imprimir', $importacion) }}','_blank','width=1100,height=700,scrollbars=yes,resizable=yes');return false;"
               class="btn btn-sm" style="background:#6f42c1;color:#fff">
                <i class="bi bi-printer-fill me-1"></i>Imprimir
            </a>
            <a href="{{ route('importaciones_wom.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    {{-- Info cabecera --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-sm-4 col-md-2">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Factura</div>
                    <div class="fw-bold">{{ $importacion->factura }}</div>
                </div>
                <div class="col-sm-4 col-md-2">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Período</div>
                    <div class="fw-bold">{{ $importacion->periodo_label }}</div>
                </div>
                @if($importacion->fecha_emision)
                <div class="col-sm-4 col-md-2">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Fecha Emisión</div>
                    <div class="fw-bold">{{ $importacion->fecha_emision->format('d/m/Y') }}</div>
                </div>
                @endif
                <div class="col-sm-4 col-md-2">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Líneas</div>
                    <div class="fw-bold">{{ $importacion->total_lineas }}</div>
                </div>
                <div class="col-sm-4 col-md-2">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Total neto</div>
                    <div class="fw-bold text-success">$ {{ number_format($totalGeneral, 0, ',', '.') }}</div>
                </div>
                @if($importacion->observacion)
                <div class="col-12">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Observación</div>
                    <div>{{ $importacion->observacion }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Resumen agrupado --}}
    @include('importaciones_wom._tabla_resumen', ['agrupado' => $agrupado, 'totalGeneral' => $totalGeneral])

</div>
@endsection
