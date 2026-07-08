@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-box-arrow-in-left me-2"></i>Actas de Devolución — Teléfonos
        </h4>
        <button type="button" class="btn btn-warning btn-sm" id="btnGenerarActa">
            <i class="bi bi-plus-circle-fill me-1"></i>Generar Acta de Devolución
        </button>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            @if($actas->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x" style="font-size:2rem"></i>
                    <div class="mt-2">No hay actas registradas.</div>
                </div>
            @else
                <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                    <table class="vti-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha Devolución</th>
                                <th>N° Teléfono</th>
                                <th>Empleado</th>
                                <th>Zona</th>
                                <th>Compañía</th>
                                <th>Equipo</th>
                                <th>Recibido por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($actas as $acta)
                            <tr>
                                <td class="text-muted" style="font-size:.78rem">{{ $acta->id }}</td>
                                <td>{{ $acta->fecha_emision->format('d/m/Y') }}</td>
                                <td class="fw-semibold font-monospace">{{ $acta->numero_telefono }}</td>
                                <td>{{ $acta->nombre_receptor ?? '—' }}</td>
                                <td>{{ $acta->zona ?? '—' }}</td>
                                <td>{{ $acta->compania ?? '—' }}</td>
                                <td>
                                    @if($acta->marca || $acta->modelo)
                                        {{ $acta->marca }} {{ $acta->modelo }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="font-size:.82rem">{{ $acta->impreso_por ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('actas_devolucion_telefono.imprimir', $acta) }}"
                                           class="btn btn-outline-primary btn-sm" target="_blank" title="Reimprimir">
                                            <i class="bi bi-printer-fill"></i>
                                        </a>
                                        @if($acta->bloqueadaParaEdicion())
                                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                                                title="Bloqueada: emitida hace más de 2 días">
                                            <i class="bi bi-lock-fill"></i>
                                        </button>
                                        @else
                                        <a href="{{ route('actas_devolucion_telefono.edit', $acta) }}"
                                           class="btn btn-outline-warning btn-sm" title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        @endif
                                        @can('admin')
                                        <form method="POST"
                                              action="{{ route('actas_devolucion_telefono.destroy', $acta) }}"
                                              onsubmit="return confirm('¿Eliminar esta acta?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">
                    {{ $actas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

@include('actas_entrega_telefono._modal_generar', [
    'tipo'        => 'devolucion',
    'titulo'      => 'Generar Acta de Devolución',
    'buscarUrl'   => route('actas_devolucion_telefono.buscar_lineas'),
    'storeBase'   => route('actas_devolucion_telefono.store', ['linea' => '__ID__']),
    'btnClass'    => 'btn-warning',
    'textClass'   => 'text-warning',
    'icono'       => 'bi bi-box-arrow-in-left',
    'condDefault' => 'Usado',
    'accLabel'    => 'Accesorios devueltos',
])
@endsection
