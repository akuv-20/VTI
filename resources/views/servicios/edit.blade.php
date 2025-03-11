<!-- resources/views/servicios/edit.blade.php -->
@extends('layouts.app')

@section('content')
    <center><h3>Editar Servicio</h3></center>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
    <form action="{{ route('servicios.update', $servicio->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div>
            <label for="codigo_servicio">Codigo de Servicio:</label>
            <input type="text" name="codigo_servicio" id="codigo_servicio" class="form-control" value="{{ $servicio->codigo_servicio }}">
        </div>

        <div>
            <label for="id_familia">Seleccionar Familia</label>
            <select class="form-select" name="id_familia" id="id_familia" required>
                @foreach ($familias as $familia)
                    <option value="{{ $familia->id }}" {{ $servicio->id_familia == $familia->id ? 'selected' : '' }}> {{ $familia->nombre }} </option>
                @endforeach
            </select>
        </div>


        <div>
            <label for="id_empresa">Seleccionar Empresa</label>
            <select class="form-select" name="id_empresa" id="id_empresa" required>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}" {{ $servicio->id_empresa == $empresa->id ? 'selected' : '' }}>
                        {{ $empresa->nombre }}
                    </option>
                @endforeach
            </select>
        </div>


        <div>
            <label for="id_empresa">Seleccionar Compañia</label>
            <select class="form-select" name="id_compania" id="id_compania" required>
                @foreach ($companias as $compania)
                    <option value="{{ $compania->id }}" {{ $servicio->id_compania == $compania->id ? 'selected' : '' }}>
                        {{ $compania->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

    
        <div class="">
            <label for="servicio" class="form-label">Servicio:</label>
            <input type="text" name="servicio" id="servicio" class="form-control" value="{{ $servicio->servicio }}" required>
        </div>
        <div class="">
            <label for="fecha_facturacion" class="form-label">Fecha de Facturación:</label>
            <input type="text" name="fecha_facturacion" id="fecha_facturacion" class="form-control" value="{{ $servicio->fecha_facturacion }}" required>
        </div>
        <div class="">
            <label for="concepto" class="form-label">Concepto:</label>
            <textarea name="concepto" id="concepto" class="form-control" required>{{ $servicio->concepto }}</textarea>
        </div>
        <div>
            <br>
            <button class="btn btn-success form-control" type="submit">Guardar</button>
            <br>
            <br>
            <a href="{{ route('servicios.index') }}" class="btn btn-danger form-control mb-3">Cancelar</a>
        </div>
    </form>
            </div>
            </div>
        </div>
@endsection