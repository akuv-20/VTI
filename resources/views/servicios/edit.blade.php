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

        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="servicio">Nombre del servicio</span>
              </div>
            <input placeholder="Nombre del servicio" type="text" name="servicio" id="servicio" class="form-control" value="{{ $servicio->servicio }}" required>
        </div>

        {{-- <div class="">
            <label for="servicio" class="form-label">Nombre del servicio:</label>
            <input type="text" name="servicio" id="servicio" class="form-control" value="{{ $servicio->servicio }}" required>
        </div> --}}
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="codigo_servicio">Codigo del servicio</span>
              </div>
            <input type="text" name="codigo_servicio" id="codigo_servicio" class="form-control" value="{{ $servicio->codigo_servicio }}">
        </div>
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="id_familia">Seleccionar Familia</span>
              </div>
            <select class="form-select" name="id_familia" id="id_familia" required>
                @foreach ($familias as $familia)
                    <option value="{{ $familia->id }}" {{ $servicio->id_familia == $familia->id ? 'selected' : '' }}> {{ $familia->nombre }} </option>
                @endforeach
            </select>
        </div>

        <br>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="id_empresa">Seleccionar Empresa</span>
              </div>
            <select class="form-select" name="id_empresa" id="id_empresa" required>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}" {{ $servicio->id_empresa == $empresa->id ? 'selected' : '' }}>
                        {{ $empresa->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <br>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="id_compania">Seleccionar Compañia</span>
              </div>
            <select class="form-select" name="id_compania" id="id_compania" required>
                @foreach ($companias as $compania)
                    <option value="{{ $compania->id }}" {{ $servicio->id_compania == $compania->id ? 'selected' : '' }}>
                        {{ $compania->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

    
       
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="fecha_facturacion">Fecha de facturación</span>
              </div>
            <input type="text" name="fecha_facturacion" id="fecha_facturacion" class="form-control" value="{{ $servicio->fecha_facturacion }}" required>
        </div>
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="concepto">Concepto</span>
              </div>
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