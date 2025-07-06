@extends('layouts.app')

@section('content')

<center><a style="font-size: 18px" href="{{ route('cuentas_contables.create') }}" class="btn btn-primary mb-3">Registrar Nueva Cuenta Contable</a>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Número de Cuenta</th> {{-- ¡CAMBIO AQUI! --}}
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cuentasContables as $cuentaContable)
                <tr>
                    <td>{{ $cuentaContable->id }}</td>
                    <td>{{ $cuentaContable->nombre_cuenta }}</td>
                    <td>{{ $cuentaContable->numero_cuenta }}</td> {{-- ¡CAMBIO AQUI! --}}
                    <td>
                        <a href="{{ route('cuentas_contables.edit', $cuentaContable->id) }}" class="btn btn-warning">Editar</a>
                        <form action="{{ route('cuentas_contables.destroy', $cuentaContable->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar esta Cuenta Contable?')">Eliminar</button>
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