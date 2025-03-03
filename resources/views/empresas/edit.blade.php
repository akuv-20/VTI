<!-- resources/views/familias/edit.blade.php -->
@extends('layouts.app')

@section('content')
<center><h3>Editar Empresa</h3></center>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
    <form action="{{ route('empresas.update', $empresa->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre:</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{ $empresa->nombre }}" required>
        </div>
        <div>
            <br>
            <button class="btn btn-success form-control" type="submit">Guardar</button>
            <br>
            <br>
            <a href="{{ route('empresas.index') }}" class="btn btn-danger form-control mb-3">Cancelar</a>
        </div>
    </form>
@endsection