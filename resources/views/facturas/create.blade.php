@extends('layouts.app')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Nueva Factura</h4>
        <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">

    <form action="{{ route('facturas.store') }}" method="POST">
        @csrf
        <input type="hidden" name="tipo" id="hiddenTipo" value="{{ old('tipo', 'Mensual') }}">

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                {{-- 1. Tipo ─────────────────────────────────────────────────────── --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">Tipo de Factura</label>
                    <div class="d-flex gap-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="_tipo" id="tipoMensual"
                                   value="Mensual" {{ old('tipo','Mensual') !== 'Esporádica' ? 'checked' : '' }}>
                            <label class="form-check-label" for="tipoMensual">
                                <i class="bi bi-calendar-check me-1 text-primary"></i> Mensual
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="_tipo" id="tipoEsporadica"
                                   value="Esporádica" {{ old('tipo') === 'Esporádica' ? 'checked' : '' }}>
                            <label class="form-check-label" for="tipoEsporadica">
                                <i class="bi bi-receipt me-1 text-warning"></i> Esporádica
                            </label>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                {{-- 2a. Mensual: Servicio ─────────────────────────────────────── --}}
                <div id="seccionMensual" class="mb-3">
                    <label class="form-label fw-semibold">Servicio <span class="text-danger">*</span></label>
                    <select name="id_servicio" id="id_servicio"
                            class="form-select @error('id_servicio') is-invalid @enderror">
                        <option value="">— Buscar servicio —</option>
                        @foreach($servicios as $s)
                            <option value="{{ $s->id }}"
                                    {{ old('id_servicio', request('id_servicio')) == $s->id ? 'selected' : '' }}>
                                {{ $s->codigo_servicio ? "[{$s->codigo_servicio}] " : '' }}{{ $s->servicio }} — {{ $s->concepto }} — {{ $s->empresa->nombre }} / {{ $s->compania->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <div id="err_servicio" class="invalid-feedback @error('id_servicio') d-block @enderror">
                        @error('id_servicio'){{ $message }}@enderror
                    </div>
                </div>

                {{-- 2b. Esporádica: Proveedor + Cuenta Contable ───────────────── --}}
                <div id="seccionEsporadica" class="d-none mb-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Proveedor <span class="text-danger">*</span></label>
                            <select name="proveedor" id="sel_proveedor"
                                    class="form-select @error('proveedor') is-invalid @enderror">
                                <option value="">— Buscar compañía —</option>
                                @foreach($companias as $c)
                                    <option value="{{ $c->nombre }}"
                                            {{ old('proveedor') === $c->nombre ? 'selected' : '' }}>
                                        {{ $c->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <div id="err_proveedor" class="invalid-feedback @error('proveedor') d-block @enderror">
                                @error('proveedor'){{ $message }}@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cuenta Contable <span class="text-danger">*</span></label>
                            <select name="id_cuenta_contable" id="id_cuenta_contable"
                                    class="form-select @error('id_cuenta_contable') is-invalid @enderror">
                                <option value="">— Seleccionar —</option>
                                @foreach($cuentasContables as $cc)
                                    <option value="{{ $cc->id }}"
                                            {{ old('id_cuenta_contable') == $cc->id ? 'selected' : '' }}>
                                        {{ $cc->numero_cuenta }} — {{ $cc->nombre_cuenta }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_cuenta_contable')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                {{-- 3. Identificación ─────────────────────────────────────────── --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">N° Factura <span class="text-danger">*</span></label>
                        <input type="text" name="factura"
                               class="form-control @error('factura') is-invalid @enderror"
                               value="{{ old('factura') }}" placeholder="Ej: 123456" autofocus>
                        @error('factura')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">N° OC</label>
                        <input type="text" name="oc" class="form-control"
                               value="{{ old('oc') }}" placeholder="Opcional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_emision"
                               class="form-control @error('fecha_emision') is-invalid @enderror"
                               value="{{ old('fecha_emision') }}">
                        @error('fecha_emision')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- 4. Montos ─────────────────────────────────────────────────── --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Valor Neto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" name="valor_neto" id="valor_neto" inputmode="numeric"
                                   class="form-control @error('valor_neto') is-invalid @enderror"
                                   value="{{ old('valor_neto') ? number_format((int) preg_replace('/\D/', '', old('valor_neto')), 0, ',', '.') : '' }}"
                                   placeholder="0" autocomplete="off">
                            @error('valor_neto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted">IVA (19%)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" name="valor_iva" id="valor_iva" class="form-control bg-light" readonly
                                   value="{{ old('valor_iva') ? number_format((int) preg_replace('/\D/', '', old('valor_iva')), 0, ',', '.') : '' }}"
                                   placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted">Total c/IVA</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" id="totalDisplay"
                                   class="form-control bg-light fw-bold text-primary" readonly placeholder="0">
                        </div>
                    </div>
                </div>

                {{-- 5. Descripción ────────────────────────────────────────────── --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">Descripción <span class="text-muted fw-normal small">— opcional</span></label>
                    <textarea name="descripcion" class="form-control" rows="2"
                              placeholder="Observación o detalle adicional">{{ old('descripcion') }}</textarea>
                </div>

                {{-- Acciones ─────────────────────────────────────────────────── --}}
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-floppy-fill me-1"></i> Guardar Factura
                    </button>
                </div>

            </div>
        </div>
    </form>

    </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    // ── Tom Select ────────────────────────────────────────────────────────
    var tsServicio  = new TomSelect('#id_servicio',   { placeholder: '— Buscar servicio —',  allowEmptyOption: true, maxOptions: null });
    var tsProveedor = new TomSelect('#sel_proveedor', { placeholder: '— Buscar compañía —',  allowEmptyOption: true, maxOptions: null });

    // ── Toggle tipo ───────────────────────────────────────────────────────
    var secMensual = document.getElementById('seccionMensual');
    var secEsporad = document.getElementById('seccionEsporadica');
    var hiddenTipo = document.getElementById('hiddenTipo');

    function aplicarTipo() {
        var val = document.querySelector('input[name="_tipo"]:checked').value;
        hiddenTipo.value = val;
        secMensual.classList.toggle('d-none', val !== 'Mensual');
        secEsporad.classList.toggle('d-none', val !== 'Esporádica');
    }
    document.querySelectorAll('input[name="_tipo"]').forEach(function (r) {
        r.addEventListener('change', aplicarTipo);
    });
    aplicarTipo();

    // ── Montos con formato miles ───────────────────────────────────────────
    var netoInput = document.getElementById('valor_neto');
    var ivaInput  = document.getElementById('valor_iva');
    var totalEl   = document.getElementById('totalDisplay');

    function formatCLP(n) { return Math.round(n).toLocaleString('es-CL'); }
    function parseCLP(s)  { return parseInt(String(s).replace(/\D/g, ''), 10) || 0; }

    function recalcular() {
        var neto = parseCLP(netoInput.value);
        var iva  = Math.round(neto * 0.19);
        ivaInput.value = formatCLP(iva);
        totalEl.value  = formatCLP(neto + iva);
    }
    netoInput.addEventListener('input', function () {
        var num = parseCLP(this.value);
        this.value = num > 0 ? formatCLP(num) : '';
        this.classList.remove('is-invalid');
        recalcular();
    });

    // ── Navegación con Enter ──────────────────────────────────────────────
    function getCampos() {
        var tipo = hiddenTipo.value;
        if (tipo === 'Mensual') {
            return [
                { el: document.getElementById('id_servicio'),          ts: tsServicio  },
                { el: document.querySelector('[name="factura"]'),       ts: null        },
                { el: document.querySelector('[name="oc"]'),            ts: null        },
                { el: document.querySelector('[name="fecha_emision"]'), ts: null        },
                { el: document.getElementById('valor_neto'),            ts: null        },
                { el: document.querySelector('[name="descripcion"]'),   ts: null        },
            ];
        } else {
            return [
                { el: document.getElementById('sel_proveedor'),         ts: tsProveedor },
                { el: document.getElementById('id_cuenta_contable'),    ts: null        },
                { el: document.querySelector('[name="factura"]'),       ts: null        },
                { el: document.querySelector('[name="oc"]'),            ts: null        },
                { el: document.querySelector('[name="fecha_emision"]'), ts: null        },
                { el: document.getElementById('valor_neto'),            ts: null        },
                { el: document.querySelector('[name="descripcion"]'),   ts: null        },
            ];
        }
    }

    function enfocarCampo(campo) {
        if (!campo || !campo.el) return;
        if (campo.ts) { campo.ts.focus(); }
        else { campo.el.focus(); }
    }

    // Busca el índice del campo activo en la secuencia
    function indiceCampoActivo(campos) {
        var activo = document.activeElement;
        for (var i = 0; i < campos.length; i++) {
            var campo = campos[i];
            var el    = campo.el;
            if (!el) continue;
            if (el === activo || el.contains(activo)) { return i; }
            // Tom Select: verificar contra el wrapper real de la instancia
            if (campo.ts && campo.ts.wrapper && campo.ts.wrapper.contains(activo)) { return i; }
        }
        return -1;
    }

    function navegarAlSiguiente() {
        var campos = getCampos();
        var idx    = indiceCampoActivo(campos);
        var siguiente = campos[idx + 1];
        if (siguiente) {
            enfocarCampo(siguiente);
        } else {
            // Último campo: mover foco al botón guardar
            document.querySelector('[type="submit"]').focus();
        }
    }

    // También se usa al validar para ir al primer campo vacío requerido
    function navegarAlPrimerVacio() {
        var campos = getCampos();
        for (var i = 0; i < campos.length; i++) {
            if (campos[i].el && !campos[i].el.value) {
                enfocarCampo(campos[i]);
                return;
            }
        }
    }

    document.querySelector('form').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (document.activeElement.tagName === 'TEXTAREA') return;
        e.preventDefault();
        navegarAlSiguiente();
    });

    // ── Validación al enviar ──────────────────────────────────────────────
    function setError(inputEl, errDivId, msg) {
        inputEl.classList.add('is-invalid');
        var d = document.getElementById(errDivId);
        if (d) { d.textContent = msg; d.classList.add('d-block'); }
    }
    function clearError(inputEl, errDivId) {
        inputEl.classList.remove('is-invalid');
        var d = document.getElementById(errDivId);
        if (d) { d.textContent = ''; d.classList.remove('d-block'); }
    }

    document.querySelector('form').addEventListener('submit', function (e) {
        var ok = true;
        var tipo = hiddenTipo.value;

        if (tipo === 'Mensual') {
            var svc = document.getElementById('id_servicio');
            if (!svc.value) { setError(svc, 'err_servicio', 'Selecciona un servicio.'); ok = false; }
            else { clearError(svc, 'err_servicio'); }
        } else {
            var prov = document.querySelector('[name="proveedor"]');
            var cc   = document.getElementById('id_cuenta_contable');
            if (!prov.value) { setError(prov, 'err_proveedor', 'Selecciona un proveedor.'); ok = false; }
            else { clearError(prov, 'err_proveedor'); }
            if (!cc.value)   { cc.classList.add('is-invalid'); ok = false; }
            else              { cc.classList.remove('is-invalid'); }
        }

        var factura = document.querySelector('[name="factura"]');
        if (!factura.value.trim()) { factura.classList.add('is-invalid'); ok = false; }
        else { factura.classList.remove('is-invalid'); }

        var fecha = document.querySelector('[name="fecha_emision"]');
        if (!fecha.value) { fecha.classList.add('is-invalid'); ok = false; }
        else { fecha.classList.remove('is-invalid'); }

        if (parseCLP(netoInput.value) <= 0) { netoInput.classList.add('is-invalid'); ok = false; }
        else { netoInput.classList.remove('is-invalid'); }

        if (!ok) {
            e.preventDefault();
            navegarAlPrimerVacio();
            return;
        }

        netoInput.value = parseCLP(netoInput.value);
        ivaInput.value  = parseCLP(ivaInput.value);
    });

    document.querySelectorAll('.form-control, .form-select').forEach(function (el) {
        ['input','change'].forEach(function(ev) {
            el.addEventListener(ev, function () { this.classList.remove('is-invalid'); });
        });
    });

    recalcular();
})();
</script>
@endpush
