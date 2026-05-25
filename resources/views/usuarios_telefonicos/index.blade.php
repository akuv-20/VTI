@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Usuarios Telefónicos</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('usuarios_telefonicos.index') }}">
                <div class="vti-search">
                    <input type="text" name="buscar" class="form-control form-control-sm" style="width:240px"
                           placeholder="Buscar por nombre…" value="{{ $buscar ?? '' }}">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    @if($buscar)
                        <a href="{{ route('usuarios_telefonicos.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <a href="{{ route('usuarios_telefonicos.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nuevo Usuario
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
                @forelse($usuarios as $usuario)
                <tr>
                    <td class="text-muted">{{ $usuario->id }}</td>
                    <td>{{ $usuario->nombre }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('usuarios_telefonicos.edit', $usuario->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('usuarios_telefonicos.destroy', $usuario->id) }}" method="POST"
                                  data-confirm="{{ $usuario->nombre }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="3">No hay usuarios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $usuarios->total() }} resultado(s)</span>
        {{ $usuarios->links() }}
    </div>

</div>
@endsection
