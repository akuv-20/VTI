@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <div class="d-flex align-items-center gap-3">
            <h4>Importaciones Movistar</h4>
            <span class="text-muted small">
                <span class="badge bg-primary me-1">35626534</span>Líneas Móviles
            </span>
            <span class="text-muted small">
                <span class="badge bg-warning text-dark me-1">11502573</span>BAM
            </span>
        </div>
        <a href="{{ route('importaciones_movistar.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-upload"></i> Subir Archivo
        </a>
    </div>

    @if($importaciones->isEmpty())
        <div class="alert alert-info">No hay importaciones registradas.</div>
    @else
        <div class="vti-table-wrapper">
            <table class="vti-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Tipo</th>
                        <th>Período</th>
                        <th>Fecha Emisión</th>
                        <th>Período Cobro</th>
                        <th>Líneas</th>
                        <th>Neto</th>
                        <th>Total c/IVA</th>
                        <th>Archivo</th>
                        <th>Importado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($importaciones as $imp)
                    <tr>
                        <td><strong>{{ $imp->folio }}</strong></td>
                        <td>
                            @if($imp->tipo_servicio === 'Movil')
                                <span class="badge bg-primary">Móvil</span>
                            @else
                                <span class="badge bg-warning text-dark">BAM</span>
                            @endif
                        </td>
                        <td>{{ $imp->periodo_label }}</td>
                        <td>{{ \Carbon\Carbon::parse($imp->fecha_emision)->format('d/m/Y') }}</td>
                        <td><small class="text-muted">{{ $imp->periodo_cobro }}</small></td>
                        <td>{{ number_format($imp->total_lineas) }}</td>
                        <td class="fw-semibold">$ {{ number_format($imp->total_monto ?? 0, 0, ',', '.') }}</td>
                        <td class="fw-semibold">$ {{ number_format(($imp->total_monto ?? 0) * 1.19, 0, ',', '.') }}</td>
                        <td><small class="text-muted">{{ $imp->archivo_nombre }}</small></td>
                        <td><small>{{ $imp->created_at->format('d/m/Y H:i') }}</small></td>
                        <td>
                            <div class="vti-actions">
                                <a href="{{ route('importaciones_movistar.show', $imp) }}" class="vti-btn-view" title="Ver detalle">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <form action="{{ route('importaciones_movistar.destroy', $imp) }}" method="POST"
                                      data-confirm="importación folio {{ $imp->folio }}">
                                    @csrf @method('DELETE')
                                    <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="vti-footer">
            <span></span>
            {{ $importaciones->links() }}
        </div>
    @endif

</div>
@endsection
