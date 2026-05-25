@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="mb-3">Editar Aparato</h4>
            <form action="{{ route('aparatos.update', $aparato) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="id_marca" class="form-label">Marca:</label>
                    <select name="id_marca" id="id_marca" class="form-control" required>
                        <option value="">-- Seleccione una marca --</option>
                        @foreach ($marcas as $marca)
                            <option value="{{ $marca->id }}" {{ old('id_marca', $aparato->id_marca) == $marca->id ? 'selected' : '' }}>
                                {{ $marca->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="modelo" class="form-label">Modelo:</label>
                    <input type="text" name="modelo" id="modelo" class="form-control" value="{{ old('modelo', $aparato->modelo) }}" required>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" type="submit">Guardar</button>
                    <a href="{{ route('aparatos.index') }}" class="btn btn-danger">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
