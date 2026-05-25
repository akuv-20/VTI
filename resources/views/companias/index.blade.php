@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Compañías</h4>
        <a href="{{ route('companias.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nueva Compañía
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
                @forelse($companias as $compania)
                <tr>
                    <td class="text-muted">{{ $compania->id }}</td>
                    <td>{{ $compania->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('companias.edit', $compania->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('companias.destroy', $compania->id) }}" method="POST"
                                  data-confirm="{{ $compania->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay compañías registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
