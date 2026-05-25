@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Marcas</h4>
        <a href="{{ route('marcas.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nueva Marca
        </a>
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
                @forelse($marcas as $marca)
                <tr>
                    <td class="text-muted">{{ $marca->id }}</td>
                    <td>{{ $marca->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('marcas.edit', $marca->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('marcas.destroy', $marca->id) }}" method="POST"
                                  data-confirm="{{ $marca->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay marcas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
