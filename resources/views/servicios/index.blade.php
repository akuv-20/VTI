@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Servicios</h4>
        <a href="{{ route('servicios.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Servicio
        </a>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Código</th>
                    <th>Servicio</th>
                    <th>Compañía</th>
                    <th>Familia</th>
                    <th>Facturación</th>
                    <th>Concepto</th>
                    <th>N° CC</th>
                    <th>Periódico</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($servicios as $servicio)
                <tr>
                    <td>{{ $servicio->empresa->nombre }}</td>
                    <td><span class="text-muted small">{{ $servicio->codigo_servicio }}</span></td>
                    <td>{{ $servicio->servicio }}</td>
                    <td>{{ $servicio->compania->nombre }}</td>
                    <td>{{ $servicio->familia->nombre }}</td>
                    <td><span class="small">{{ $servicio->fecha_facturacion }}</span></td>
                    <td>{{ $servicio->concepto }}</td>
                    <td>{{ $servicio->cuentaContable->numero_cuenta }}</td>
                    <td>
                        @if($servicio->es_periodico)
                            <span class="badge bg-success">Sí</span>
                        @else
                            <span class="badge bg-secondary">No</span>
                        @endif
                    </td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('servicios.edit', $servicio->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('servicios.destroy', $servicio->id) }}" method="POST"
                                  data-confirm="{{ $servicio->servicio }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="10">No hay servicios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
