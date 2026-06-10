@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-file-earmark-text-fill me-2"></i>Actas de Entrega — Teléfonos
        </h4>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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
                                <th>Fecha Emisión</th>
                                <th>N° Teléfono</th>
                                <th>Receptor</th>
                                <th>Zona</th>
                                <th>Compañía</th>
                                <th>Equipo</th>
                                <th>Impreso por</th>
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
                                        <a href="{{ route('actas_entrega_telefono.imprimir', $acta) }}"
                                           class="btn btn-outline-primary btn-sm" target="_blank">
                                            <i class="bi bi-printer-fill"></i>
                                        </a>
                                        @can('admin')
                                        <form method="POST"
                                              action="{{ route('actas_entrega_telefono.destroy', $acta) }}"
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
@endsection
