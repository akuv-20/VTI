@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Familias</h4>
        <a href="{{ route('familias.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nueva Familia
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
                @forelse($familias as $familia)
                <tr>
                    <td class="text-muted">{{ $familia->id }}</td>
                    <td>{{ $familia->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('familias.edit', $familia->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('familias.destroy', $familia->id) }}" method="POST"
                                  data-confirm="{{ $familia->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay familias registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
