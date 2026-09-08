@php $e = $equipo ?? null; @endphp

<div class="row">
    <div class="col-md-8 mb-3">
        <label for="id_aparato" class="form-label">Modelo (Marca · Modelo):</label>
        <div class="input-group">
            <select name="id_aparato" id="id_aparato" class="form-select">
                <option value="">-- Sin especificar --</option>
                @foreach ($aparatos as $aparato)
                    <option value="{{ $aparato->id }}"
                        {{ (string) old('id_aparato', $e->id_aparato ?? '') === (string) $aparato->id ? 'selected' : '' }}>
                        {{ $aparato->marca->nombre ?? '' }} · {{ $aparato->modelo }}
                    </option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-crear-rapido" data-tipo="aparato" data-target="id_aparato" title="Crear modelo nuevo">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label for="propiedad" class="form-label">Propiedad:</label>
        <select name="propiedad" id="propiedad" class="form-select" required>
            @foreach ($propiedades as $op)
                <option value="{{ $op }}" {{ old('propiedad', $e->propiedad ?? 'Empresa') === $op ? 'selected' : '' }}>{{ $op }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="mb-3">
    <label for="id_linea" class="form-label">Línea asociada <span class="text-muted fw-normal">(opcional — déjalo vacío si el equipo no tiene línea)</span>:</label>
    <select name="id_linea" id="id_linea" class="form-select">
        <option value="">-- Sin línea (solo inventario) --</option>
        @foreach ($lineasDisponibles as $l)
            <option value="{{ $l->id }}"
                {{ (string) old('id_linea', $lineaActualId ?? '') === (string) $l->id ? 'selected' : '' }}>
                {{ $l->linea }} — {{ $l->usuario->nombre ?? '(sin usuario)' }} · {{ $l->emisor->nombre ?? 's/emisor' }}
            </option>
        @endforeach
    </select>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="imei" class="form-label">IMEI:</label>
        <input type="text" name="imei" id="imei" class="form-control" maxlength="50"
               value="{{ old('imei', $e->imei ?? '') }}" placeholder="Identificación del equipo">
    </div>
    <div class="col-md-6 mb-3">
        <label for="estado" class="form-label">Estado:</label>
        <select name="estado" id="estado" class="form-select" required>
            @foreach ($estados as $op)
                <option value="{{ $op }}" {{ old('estado', $e->estado ?? 'En uso') === $op ? 'selected' : '' }}>{{ $op }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="id_usuario" class="form-label">Usuario:</label>
        <div class="input-group">
            <select name="id_usuario" id="id_usuario" class="form-select">
                <option value="">-- Sin usuario --</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}"
                        {{ (string) old('id_usuario', $e->id_usuario ?? '') === (string) $usuario->id ? 'selected' : '' }}>
                        {{ $usuario->nombre }}
                    </option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-crear-rapido" data-tipo="usuario" data-target="id_usuario" title="Crear usuario">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="id_ubicacion" class="form-label">Ubicación:</label>
        <div class="input-group">
            <select name="id_ubicacion" id="id_ubicacion" class="form-select">
                <option value="">-- Sin ubicación --</option>
                @foreach ($ubicaciones as $ubicacion)
                    <option value="{{ $ubicacion->id }}"
                        {{ (string) old('id_ubicacion', $e->id_ubicacion ?? '') === (string) $ubicacion->id ? 'selected' : '' }}>
                        {{ $ubicacion->nombre }}
                    </option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-crear-rapido" data-tipo="ubicacion" data-target="id_ubicacion" title="Crear ubicación">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="observacion" class="form-label">Observación:</label>
    <textarea name="observacion" id="observacion" class="form-control" rows="2">{{ old('observacion', $e->observacion ?? '') }}</textarea>
</div>
