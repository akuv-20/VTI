@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-person-fill-gear me-2"></i>Editar Usuario: {{ $usuario->name }}</h4>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form action="{{ route('admin.usuarios.update', $usuario) }}" method="POST" data-loader>
        @csrf @method('PUT')
        @include('admin.usuarios._form', ['asignados' => $asignados])

        <div class="mt-3">
            <button type="submit" class="btn btn-warning" id="btn-submit">
                <i class="bi bi-check-lg"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>
@endsection
