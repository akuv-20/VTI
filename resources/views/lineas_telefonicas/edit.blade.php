@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h4 class="mb-3">Editar Línea Telefónica</h4>
            <form action="{{ route('lineas_telefonicas.update', $lineas_telefonica->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="linea" class="form-label">Número de Línea:</label>
                        <input type="text" name="linea" id="linea" class="form-control" value="{{ old('linea', $lineas_telefonica->linea) }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="estado" class="form-label">Estado:</label>
                        <select name="estado" id="estado" class="form-control" required>
                            <option value="Activo" {{ old('estado', $lineas_telefonica->estado) == 'Activo' ? 'selected' : '' }}>Activo</option>
                            <option value="Inactivo" {{ old('estado', $lineas_telefonica->estado) == 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="id_emisor" class="form-label">Emisor:</label>
                        <select name="id_emisor" id="id_emisor" class="form-control">
                            <option value="">-- Sin emisor --</option>
                            @foreach ($emisores as $emisor)
                                <option value="{{ $emisor->id }}" {{ old('id_emisor', $lineas_telefonica->id_emisor) == $emisor->id ? 'selected' : '' }}>
                                    {{ $emisor->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="id_usuario" class="form-label">Usuario:</label>
                        <select name="id_usuario" id="id_usuario" class="form-control">
                            <option value="">-- Sin usuario --</option>
                            @foreach ($usuarios as $usuario)
                                <option value="{{ $usuario->id }}" {{ old('id_usuario', $lineas_telefonica->id_usuario) == $usuario->id ? 'selected' : '' }}>
                                    {{ $usuario->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="id_empresa" class="form-label">Empresa:</label>
                        <select name="id_empresa" id="id_empresa" class="form-control">
                            <option value="">-- Sin empresa --</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}" {{ old('id_empresa', $lineas_telefonica->id_empresa) == $empresa->id ? 'selected' : '' }}>
                                    {{ $empresa->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="id_ubicacion" class="form-label">Ubicación:</label>
                        <select name="id_ubicacion" id="id_ubicacion" class="form-control">
                            <option value="">-- Sin ubicación --</option>
                            @foreach ($ubicaciones as $ubicacion)
                                <option value="{{ $ubicacion->id }}" {{ old('id_ubicacion', $lineas_telefonica->id_ubicacion) == $ubicacion->id ? 'selected' : '' }}>
                                    {{ $ubicacion->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Centro de Costo:</label>
                        <input type="text" id="ccosto_display" class="form-control"
                               value="{{ old('id_centro_costo') ? '' : ($lineas_telefonica->centroCosto?->ccosto ?? '') }}"
                               placeholder="Se completa al elegir Empresa + Ubicación" readonly>
                        <input type="hidden" name="id_centro_costo" id="id_centro_costo"
                               value="{{ old('id_centro_costo', $lineas_telefonica->id_centro_costo) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="id_aparato" class="form-label">Aparato:</label>
                    <select name="id_aparato" id="id_aparato" class="form-control">
                        <option value="">-- Sin aparato --</option>
                        @foreach ($aparatos as $aparato)
                            <option value="{{ $aparato->id }}" {{ old('id_aparato', $lineas_telefonica->id_aparato) == $aparato->id ? 'selected' : '' }}>
                                {{ $aparato->marca->nombre ?? '' }} - {{ $aparato->modelo }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="imei_equipo" class="form-label">IMEI Equipo:</label>
                        <input type="text" name="imei_equipo" id="imei_equipo" class="form-control" value="{{ old('imei_equipo', $lineas_telefonica->imei_equipo) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="imei_sim" class="form-label">IMEI SIM:</label>
                        <input type="text" name="imei_sim" id="imei_sim" class="form-control" value="{{ old('imei_sim', $lineas_telefonica->imei_sim) }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="fecha_entrega_sim" class="form-label">Fecha Entrega SIM:</label>
                        <input type="date" name="fecha_entrega_sim" id="fecha_entrega_sim" class="form-control" value="{{ old('fecha_entrega_sim', $lineas_telefonica->fecha_entrega_sim) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="fecha_renovacion_equipo" class="form-label">Fecha Renovación Equipo:</label>
                        <input type="date" name="fecha_renovacion_equipo" id="fecha_renovacion_equipo" class="form-control" value="{{ old('fecha_renovacion_equipo', $lineas_telefonica->fecha_renovacion_equipo) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="observacion" class="form-label">Observación:</label>
                    <textarea name="observacion" id="observacion" class="form-control" rows="3">{{ old('observacion', $lineas_telefonica->observacion) }}</textarea>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-success" type="submit">Guardar</button>
                    <a href="{{ route('lineas_telefonicas.index') }}" class="btn btn-danger">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function buscarCentroCosto() {
        const idEmpresa   = document.getElementById('id_empresa').value;
        const idUbicacion = document.getElementById('id_ubicacion').value;

        if (!idEmpresa || !idUbicacion) {
            document.getElementById('ccosto_display').value = '';
            document.getElementById('id_centro_costo').value = '';
            return;
        }

        fetch(`{{ route('centros_costo.buscar') }}?id_empresa=${idEmpresa}&id_ubicacion=${idUbicacion}`)
            .then(r => r.json())
            .then(data => {
                if (data) {
                    document.getElementById('ccosto_display').value = data.ccosto;
                    document.getElementById('id_centro_costo').value = data.id;
                } else {
                    document.getElementById('ccosto_display').value = 'Sin Centro de Costo asignado';
                    document.getElementById('id_centro_costo').value = '';
                }
            });
    }

    document.getElementById('id_empresa').addEventListener('change', buscarCentroCosto);
    document.getElementById('id_ubicacion').addEventListener('change', buscarCentroCosto);
</script>
@endsection
