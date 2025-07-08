<!-- resources/views/servicios/index.blade.php -->
@extends('layouts.app')

@section('content')

    
    <div class="container-fluid text-center">
        <div class="row">
            <div class="col">
            </div>
            <div class="col">
            <center><a style="font-size: 18px" href="{{ route('servicios.create') }}" class="btn btn-primary mb-3">Registrar Nuevo Servicio</a>
            </div>
            <div class="col">
            {{-- <a href="{{ route('facturas.index') }}" class="btn btn-success mb-3">Ir a Facturas</a> --}}
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-12">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Empresa</th>
                <th>Codigo de Servicio</th>
                <th>Servicio</th>
                <th>Compañía</th>
                <th>Familia</th>
                <th>Fecha Facturación</th>
                <th>Concepto</th>
                <th>ID C.C.</th>
                <th>Nombre C.C.</th>
                <th>¿Es Periódico?</th> 
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($servicios as $servicio)
                <tr>
                    <td>{{ $servicio->empresa->nombre}}</td>
                    <td>{{ $servicio->codigo_servicio }}</td>
                    <td>{{ $servicio->servicio }}</td>
                    <td>{{ $servicio->compania->nombre }}</td>
                    <td>{{ $servicio->familia->nombre }}</td>
                    <td>{{ $servicio->fecha_facturacion }}</td>
                    <td>{{ $servicio->concepto }}</td>
                    <td>{{ $servicio->cuentaContable->numero_cuenta }}</td>
                    <td>{{ $servicio->cuentaContable->nombre_cuenta }}</td>
                    <td>{{ $servicio->es_periodico ? 'Sí' : 'No' }}</td>
                    <td>
                        <a href="{{ route('servicios.edit', $servicio->id) }}" class="btn btn-warning">Editar</a>
                        <form action="{{ route('servicios.destroy', $servicio->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar este servicio?')">Eliminar</button>
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