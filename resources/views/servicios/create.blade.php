<!-- resources/views/servicios/create.blade.php -->
@extends('layouts.app')

@section('content')
    <center><h3>Registrar Nuevo Servicio</h3>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
    <form action="{{ route('servicios.store') }}" method="POST">
        @csrf
        <div>
            <label for="codigo_servicio" class="form-label"></label>
            <input placeholder="Codigo de Servicio (Opcional)" type="text" name="codigo_servicio" id="codigo_servicio" class="form-control">
        </div>

      
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text" id="id_familia">Seleccionar Familia</span>
            </div>
            <select class="form-select" name="id_familia" id="id_familia" aria-label="Default select example" required>
                <option selected></option>
                @foreach ($familias as $familia)
                    <option value="{{ $familia->id }}">{{ $familia->nombre }}</option>
                @endforeach
            </select>
        </div>
        <br>
        
        <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text" id="id_empresa">Seleccionar Empresa</span>
            </div>
            <select class="form-select" name="id_empresa" id="id_empresa" aria-label="Default select example" required>
                <option selected></option>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                @endforeach
            </select>
        </div>



        
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text" id="compania">Seleccionar Compañia</span>
            </div>
            <select class="form-select" name="compania" id="compania" aria-label="Default select example" required>
                <option selected></option>
                <option value="Entel">Entel</option>
                <option value="Movistar">Movistar</option>
                <option value="WOM">WOM</option>
                <option value="TIBOX">TIBOX</option>
                <option value="Starlink">Starlink</option>
                <option value="Accesnet">Accesnet</option>
                <option value="Intersur">Intersur</option>
                <option value="Pretorian">Pretorian</option>
            </select>
        </div>


       
        <br>
        <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text" id="servicio">Seleccionar Servicio</span>
            </div>
            <select class="form-select" name="servicio" id="servicio" aria-label="Default select example" required>
                <option selected></option>
                <option value="BAM">BAM</option>
                <option value="Telefonia Local">Telefonia Local</option>
                <option value="Telefonia Movil">Telefonia Movil</option>
                <option value="Compra de Switch Rapel">Compra de Switch Rapel</option>
            </select>
        </div>

        


        <br>
        <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text" id="fecha_facturacion">Seleccionar Fecha de Facturacion</span>
            </div>
            <select class="form-select" name="fecha_facturacion" id="fecha_facturacion" aria-label="Default select example" required>
                <option selected></option>
                <option value="1 de cada Mes">1 de cada Mes</option>
                <option value="15 de cada Mes">15 de cada Mes</option>
                <option value="30 de cada Mes">30 de cada Mes</option>
            </select>
        </div>

    

    


        <div class="">
            <label for="concepto" class="form-label"></label>
            <textarea placeholder="Concepto" name="concepto" id="concepto" class="form-control" required></textarea>
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