@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Empresas</h4>
        <a href="{{ route('empresas.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nueva Empresa
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
                @forelse($empresas as $empresa)
                <tr>
                    <td class="text-muted">{{ $empresa->id }}</td>
                    <td>{{ $empresa->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('empresas.edit', $empresa->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('empresas.destroy', $empresa->id) }}" method="POST"
                                  data-confirm="{{ $empresa->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay empresas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
