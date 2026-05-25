@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Ubicaciones</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('ubicaciones.index') }}">
                <div class="vti-search">
                    <input type="text" name="buscar" class="form-control form-control-sm" style="width:240px"
                           placeholder="Buscar por nombre…" value="{{ $buscar ?? '' }}">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    @if($buscar)
                        <a href="{{ route('ubicaciones.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <a href="{{ route('ubicaciones.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nueva Ubicación
            </a>
        </div>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ubicaciones as $ubicacion)
                <tr>
                    <td class="text-muted">{{ $ubicacion->id }}</td>
                    <td>{{ $ubicacion->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('ubicaciones.edit', $ubicacion) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('ubicaciones.destroy', $ubicacion) }}" method="POST"
                                  data-confirm="{{ $ubicacion->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay ubicaciones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $ubicaciones->total() }} resultado(s)</span>
        {{ $ubicaciones->links() }}
    </div>

</div>
@endsection
