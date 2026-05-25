@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="mb-3">Editar Usuario Telefónico</h4>
            <form action="{{ route('usuarios_telefonicos.update', $usuarios_telefonico) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $usuarios_telefonico->nombre) }}" required>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" type="submit">Guardar</button>
                    <a href="{{ route('usuarios_telefonicos.index') }}" class="btn btn-danger">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
