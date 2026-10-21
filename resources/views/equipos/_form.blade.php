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
                data-usuario="{{ $l->id_usuario }}"
                data-usuario-nombre="{{ $l->usuario->nombre ?? '' }}"
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
        <label for="mac_wifi" class="form-label">MAC WiFi:</label>
        <input type="text" name="mac_wifi" id="mac_wifi" class="form-control @error('mac_wifi') is-invalid @enderror"
               maxlength="40" value="{{ old('mac_wifi', $e->mac_wifi ?? '') }}" placeholder="AA:BB:CC:DD:EE:FF">
        <div class="form-text">Se cruza con el DHCP para mostrar la IP reservada. Se guarda como <code>aa-bb-cc-dd-ee-ff</code>.</div>
        @error('mac_wifi')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
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
        <div class="form-text" id="usuarioHint" style="display:none">
            <i class="bi bi-link-45deg"></i> Se toma de la línea asociada. Quita la línea para editarlo.
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

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    const lineaSel   = document.getElementById('id_linea');
    const usuarioSel = document.getElementById('id_usuario');
    const hint       = document.getElementById('usuarioHint');
    if (!lineaSel || !usuarioSel) return;

    // Buscador en el desplegable de línea
    let ts = null;
    if (window.TomSelect) {
        try {
            ts = new TomSelect(lineaSel, {
                placeholder: '— Buscar línea por número o usuario —',
                allowEmptyOption: true,
                maxOptions: null,
            });
        } catch (e) { /* si falla, queda el select normal */ }
    }

    // Usuario de la línea seleccionada (desde el <option> original)
    function usuarioDeLinea() {
        const opt = lineaSel.options[lineaSel.selectedIndex];
        return opt ? (opt.dataset.usuario || '') : '';
    }

    function sincronizar() {
        const hayLinea = !!lineaSel.value;
        const uid = usuarioDeLinea();

        if (hayLinea && uid) {
            // La línea manda: fija el usuario y bloquea el campo
            usuarioSel.value = uid;
            usuarioSel.setAttribute('disabled', 'disabled');
            if (hint) hint.style.display = '';
        } else {
            // Sin línea (o línea sin usuario): usuario editable
            usuarioSel.removeAttribute('disabled');
            if (hint) hint.style.display = 'none';
        }
    }

    lineaSel.addEventListener('change', sincronizar);
    sincronizar(); // estado inicial (al editar un equipo con línea)

    // Un <select> deshabilitado no se envía: reactívalo justo antes del submit
    const form = lineaSel.closest('form');
    form?.addEventListener('submit', () => usuarioSel.removeAttribute('disabled'));
})();
</script>
@endpush
