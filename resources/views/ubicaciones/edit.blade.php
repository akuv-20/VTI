@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="mb-3">Editar Ubicación</h4>
            <form action="{{ route('ubicaciones.update', $ubicacion) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $ubicacion->nombre) }}" required>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" type="submit">Guardar</button>
                    <a href="{{ route('ubicaciones.index') }}" class="btn btn-danger">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
