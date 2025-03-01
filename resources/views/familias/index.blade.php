<!-- resources/views/familias/index.blade.php -->
@extends('layouts.app')

@section('content')
    
<center><a style="font-size: 18px" href="{{ route('familias.create') }}" class="btn btn-primary mb-3">Registrar Nueva Familia</a>

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
            @foreach ($familias as $familia)
                <tr>
                    <td>{{ $familia->id }}</td>
                    <td>{{ $familia->nombre }}</td>
                    <td>
                        <a href="{{ route('familias.edit', $familia->id) }}" class="btn btn-warning">Editar</a>
                        <form action="{{ route('familias.destroy', $familia->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar esta familia?')">Eliminar</button>
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