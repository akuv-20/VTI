@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="mb-3">Editar Centro de Costo</h4>
            <form action="{{ route('centros_costo.update', $centros_costo->id) }}" method="POST">
                @csrf @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Empresa:</label>
                    <select name="id_empresa" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach ($empresas as $empresa)
                            <option value="{{ $empresa->id }}" {{ $centros_costo->id_empresa == $empresa->id ? 'selected' : '' }}>
                                {{ $empresa->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ubicación:</label>
                    <select name="id_ubicacion" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach ($ubicaciones as $ubicacion)
                            <option value="{{ $ubicacion->id }}" {{ $centros_costo->id_ubicacion == $ubicacion->id ? 'selected' : '' }}>
                                {{ $ubicacion->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Código B:</label>
                        <input type="text" name="codigo_b" class="form-control" value="{{ $centros_costo->codigo_b }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Código C:</label>
                        <input type="text" name="codigo_c" class="form-control" value="{{ $centros_costo->codigo_c }}" required>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-success" type="submit">Guardar</button>
                    <a href="{{ route('centros_costo.index') }}" class="btn btn-danger">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
