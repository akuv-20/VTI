@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Emisores</h4>
        <a href="{{ route('emisores.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Emisor
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
                @forelse($emisores as $emisor)
                <tr>
                    <td class="text-muted">{{ $emisor->id }}</td>
                    <td>{{ $emisor->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('emisores.edit', $emisor) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('emisores.destroy', $emisor) }}" method="POST"
                                  data-confirm="{{ $emisor->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay emisores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
