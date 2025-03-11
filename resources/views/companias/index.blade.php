<!-- resources/views/familias/index.blade.php -->
@extends('layouts.app')

@section('content')
    
<center><a style="font-size: 18px" href="{{ route('companias.create') }}" class="btn btn-primary mb-3">Registrar Nueva Compañia</a>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($companias as $compania)
                <tr>
                    <td>{{ $compania->id }}</td>
                    <td>{{ $compania->nombre }}</td>
                    <td>
                        <a href="{{ route('companias.edit', $compania->id) }}" class="btn btn-warning">Editar</a>
                        <form action="{{ route('companias.destroy', $compania->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar esta Compañia?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
            </div>
            </div>
        </div>
@endsection