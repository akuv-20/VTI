<!-- resources/views/familias/index.blade.php -->
@extends('layouts.app')

@section('content')
    
<center><a style="font-size: 18px" href="{{ route('empresas.create') }}" class="btn btn-primary mb-3">Registrar Nueva Empresa</a>

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
            @foreach ($empresas as $empresa)
                <tr>
                    <td>{{ $empresa->id }}</td>
                    <td>{{ $empresa->nombre }}</td>
                    <td>
                        <a href="{{ route('empresas.edit', $empresa->id) }}" class="btn btn-warning">Editar</a>
                        <form action="{{ route('empresas.destroy', $empresa->id) }}" method="POST" style="display:inline;">
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