<!-- resources/views/familias/create.blade.php -->
@extends('layouts.app')

@section('content')
    <center><h3>Registrar Nueva Compañía</h3>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
    <form action="{{ route('companias.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre:</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required>
        </div>
        <div>
            <br>
            <button class="btn btn-success form-control" type="submit">Guardar</button>
            <br>
            <br>
            <a href="{{ route('companias.index') }}" class="btn btn-danger form-control mb-3">Cancelar</a>
        </div>
    </form>
    
                </div>
                </div>
            </div>
@endsection