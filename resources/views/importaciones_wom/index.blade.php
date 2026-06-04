@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-file-earmark-spreadsheet me-2"></i>Importaciones WOM</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('importaciones_wom.plantilla') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-layout-text-window me-1"></i>Plantilla
            </a>
            <a href="{{ route('importaciones_wom.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nueva importación
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Período</th>
                    <th>Nro. Factura</th>
                    <th>Fecha Emisión</th>
                    <th class="text-center">Líneas</th>
                    <th>Observación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($importaciones as $imp)
                <tr>
                    <td class="text-muted" style="font-size:.8rem">{{ $imp->id }}</td>
                    <td><span class="fw-semibold">{{ $imp->periodo_label }}</span></td>
                    <td>{{ $imp->factura }}</td>
                    <td>{{ $imp->fecha_emision ? $imp->fecha_emision->format('d/m/Y') : '—' }}</td>
                    <td class="text-center">
                        <span class="badge" style="background:#6f42c1">{{ $imp->total_lineas }}</span>
                    </td>
                    <td class="text-muted" style="font-size:.83rem">{{ $imp->observacion ?? '—' }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('importaciones_wom.show', $imp) }}" class="vti-btn-view" title="Ver resumen">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <a href="#" onclick="window.open('{{ route('importaciones_wom.imprimir', $imp) }}','_blank','width=1100,height=700,scrollbars=yes,resizable=yes');return false;"
                               class="btn btn-sm" style="background:#6f42c1;color:#fff;padding:4px 8px" title="Imprimir">
                                <i class="bi bi-printer-fill"></i>
                            </a>
                            <form action="{{ route('importaciones_wom.destroy', $imp) }}" method="POST"
                                  data-confirm="importación WOM {{ $imp->periodo_label }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="7">No hay importaciones WOM registradas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $importaciones->total() }} importación(es)</span>
        {{ $importaciones->links() }}
    </div>

</div>
@endsection
