<!-- resources/views/facturas/create.blade.php -->
@extends('layouts.app')




@section('content')
<center><h3>Registrar Nueva Factura</h3>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <form action="{{ route('facturas.store') }}" method="POST">
                @csrf
                <div>
                    <label for="id_servicio"></label>
                    <select class="form-select" name="id_servicio" id="id_servicio" required>
                        <option selected>Seleccionar Servicio</option>
                        @foreach ($servicios as $servicio)
                            <option value="{{ $servicio->id }}">{{ $servicio->servicio }} - {{ $servicio->concepto }} - {{ $servicio->empresa->nombre }}</option>
                        @endforeach
                    </select>
                </div>
        
                <div>
                    <label for="factura"></label>
                    <input placeholder="Número de Factura" class="form-control" type="text" name="factura" id="factura" required>
                </div>
        
                <div>
                    <label for="valor_neto"></label>
                    <input placeholder="Valor Neto" class="form-control" step="0.01" type="number" name="valor_neto" id="valor_neto" required oninput="calcularIVA()">
                </div>
        
                <div>
                    <label for="valor_iva"></label>
                    <input placeholder="Valor IVA" class="form-control" step="0.01" type="number" name="valor_iva" id="valor_iva" required readonly>
                </div>
        
                <div>
                    <label for="fecha_emision"></label>
                    <input placeholder="Fecha de Emisión" class="form-control" type="date" name="fecha_emision" id="fecha_emision" required>
                </div>

                <div>
                    <label for="descripcion"></label>
                    <input placeholder="Descripcion" class="form-control" type="text" name="descripcion" id="descripcion">
                </div>

                <div>
                    <br>
                    <button class="btn btn-success form-control" type="submit">Guardar</button>
                    <br>
                    <br>
                    <a href="{{ route('facturas.index') }}" class="btn btn-danger form-control mb-3">Cancelar</a>
                </div>
            </form>

            <script>
                function calcularIVA() {
                    // Obtener el valor del campo "valor_neto"
                    const valorNeto = document.getElementById('valor_neto').value;
        
                    // Calcular el valor del IVA (multiplicar por 1.19)
                    const valorIVA = valorNeto * 1.19;
        
                    // Asignar el resultado al campo "valor_iva"
                    document.getElementById('valor_iva').value = valorIVA.toFixed(2); // Redondear a 2 decimales
                }

            </script>

        </div>
    </div>
</div>



    
@endsection