@extends('layouts.app')

@section('content')
<center><h3>Editar Cuenta Contable</h3></center>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
    <form action="{{ route('cuentas_contables.update', $cuentas_contable->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="nombre_cuenta" class="form-label">Nombre:</label>
            <input type="text" name="nombre_cuenta" id="nombre_cuenta" class="form-control" value="{{ $cuentas_contable->nombre_cuenta }}" required>
        </div>
        <div class="mb-3"> {{-- ¡CAMBIO AQUI! --}}
            <label for="numero_cuenta" class="form-label">Número de Cuenta:</label> {{-- ¡CAMBIO AQUI! --}}
            <input type="text" name="numero_cuenta" id="numero_cuenta" class="form-control" value="{{ $cuentas_contable->numero_cuenta }}" required> {{-- ¡CAMBIO AQUI! --}}
        </div>
        <div>
            <br>
            <button class="btn btn-success form-control" type="submit">Guardar</button>
            <br>
            <br>
            <a href="{{ route('cuentas_contables.index') }}" class="btn btn-danger form-control mb-3">Cancelar</a>
        </div>
    </form>
@endsection