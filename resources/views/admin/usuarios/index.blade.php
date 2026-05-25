@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('admin.usuarios.index') }}">
                <div class="vti-search">
                    <input type="text" name="buscar" class="form-control form-control-sm" style="width:260px"
                           placeholder="Buscar por nombre o email…" value="{{ $buscar }}">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    @if($buscar)
                        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <a href="{{ route('admin.usuarios.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
            </a>
        </div>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th class="text-center">Admin</th>
                    <th class="text-center">Estado</th>
                    <th>Módulos asignados</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->name }}</td>
                    <td><span class="text-muted">{{ $usuario->email }}</span></td>
                    <td class="text-center">
                        @if($usuario->es_admin)
                            <span class="badge bg-danger">Admin</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($usuario->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td>
                        @if($usuario->es_admin)
                            <em class="text-muted small">Acceso total</em>
                        @else
                            @foreach($usuario->modulos->where('activo', true)->sortBy('orden') as $mod)
                                <span class="badge bg-primary me-1">{{ $mod->label }}</span>
                            @endforeach
                            @if($usuario->modulos->isEmpty())
                                <em class="text-muted small">Sin módulos</em>
                            @endif
                        @endif
                    </td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            @if($usuario->id !== auth()->id())
                            <form action="{{ route('admin.usuarios.destroy', $usuario) }}" method="POST"
                                  data-confirm="{{ $usuario->name }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="6">No hay usuarios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $usuarios->total() }} usuario(s)</span>
        {{ $usuarios->links() }}
    </div>

</div>
@endsection
