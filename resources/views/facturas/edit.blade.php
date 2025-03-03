<!-- resources/views/facturas/edit.blade.php -->
@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
    <form action="{{ route('facturas.update', $factura->id) }}" method="POST">
        @csrf
        @method('PUT') <!-- Importante para indicar que es una actualización -->

        <div>
            <label for="id_servicio">Seleccionar Servicio:</label>
            <select class="form-select" name="id_servicio" id="id_servicio" required>
                @foreach ($servicios as $servicio)
                    <option value="{{ $servicio->id }}" {{ $factura->id_servicio == $servicio->id ? 'selected' : '' }}>
                        {{ $servicio->servicio }} - {{ $servicio->concepto }} - {{ $servicio->empresa->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="factura">Número de Factura:</label>
            <input class="form-control" type="text" name="factura" id="factura" value="{{ $factura->factura }}" required>
        </div>

        <div>
            <label for="valor_neto">Valor Neto:</label>
            <input class="form-control" type="number" name="valor_neto" id="valor_neto" step="0.01" value="{{ $factura->valor_neto }}" required oninput="calcularIVA()">
        </div>

        <div>
            <label for="valor_iva">Valor IVA:</label>
            <input class="form-control" type="number" name="valor_iva" id="valor_iva" step="0.01" value="{{ $factura->valor_iva }}" readonly>
        </div>

        <div>
            <label for="fecha_emision">Fecha de Emisión:</label>
            <input class="form-control" type="date" name="fecha_emision" id="fecha_emision" value="{{ $factura->fecha_emision }}" required>
        </div>
        <div>
            <label for="descripcion">Descripcion</label>
            <input class="form-control" type="text" name="descripcion" id="descripcion" value="{{ $factura->descripcion }}">
        </div>
        <div>
            <br>
            <button class="btn btn-success form-control" type="submit">Guardar</button>
            <br>
            <br>
            <a href="{{ route('facturas.index') }}" class="btn btn-danger form-control mb-3">Cancelar</a>
        </div>
    </form>
            </div>
            </div>
        </div>

    <!-- Script para calcular el IVA automáticamente -->
    <script>
        function calcularIVA() {
            const valorNeto = parseFloat(document.getElementById('valor_neto').value);
            if (isNaN(valorNeto)) {
                document.getElementById('valor_iva').value = '';
                return;
            }
            const valorIVA = valorNeto * 1.19;
            document.getElementById('valor_iva').value = valorIVA.toFixed(2);
        }
    </script>
@endsection