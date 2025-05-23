<!-- resources/views/facturas/index.blade.php -->
@extends('layouts.app')

@section('content')
    {{-- <h1>Lista de Facturas</h1> --}}
    
    <div class="container text-center">
        <div class="row">
          <div class="col">
            
          </div>
          <div class="col">
            <a style="font-size: 18px" href="{{ route('facturas.create') }}" class="btn btn-primary mb-3">Registrar Nueva Factura</a>
          </div>
          <div class="col">
            {{-- <a href="{{ route('servicios.index') }}" class="btn btn-success mb-3">Ir a Servicios</a> --}}
          </div>
        </div>
      </div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <table id="facturas-table" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Número de Factura</th>
                            <th>Concepto</th>
                            <th>Fecha Emisión</th>
                            <th>Servicio</th>
                            <th>Empresa</th>
                            <th>Valor Neto</th>
                            <th>Valor IVA</th>
                            <th>Fecha Emisión</th>
                            <th>Descripcion</th>
                            <th>Acciónes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($facturas as $factura)
                            <tr>
                                <td>{{ $factura->factura }}</td>
                                <td>{{ $factura->servicio->concepto }}</td>
                                <td>{{ $factura->fecha_emision }}</td>
                                <td>{{ $factura->servicio->servicio }}</td>
                                <td>{{ $factura->servicio->empresa->nombre }}</td>
                                <td>${{ number_format($factura->valor_neto, 2) }}</td>
                                <td>${{ number_format($factura->valor_iva, 2) }}</td>
                                <td>{{ $factura->fecha_emision }}</td>
                                <td>{{ $factura->descripcion }}</td>
                                <td>
                                    <a href="{{ route('facturas.edit', $factura->id) }}" class="btn btn-warning">Editar</a>
                                    <form action="{{ route('facturas.destroy', $factura->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar esta factura?')">Eliminar</button>
                                    </form>
                                    
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <script>
                    $(document).ready(function () {
                        $('#facturas-table').DataTable({
                            language: {
                                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' // Traducción al español
                            },
                            columnDefs: [
                                { orderable: false, targets: 5 } // Desactivar ordenamiento en la columna "Acciones"
                            ]
                        });
                    });
                </script>
            </div>
        </div>
    </div>
    
@endsection

@section('scripts')

@endsection