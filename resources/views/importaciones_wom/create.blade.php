@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-file-earmark-plus me-2"></i>Nueva Importación WOM</h4>
        <a href="{{ route('importaciones_wom.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <form method="POST" action="{{ route('importaciones_wom.store') }}" id="formWom">
    @csrf

    {{-- ── Cabecera ─────────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.06em">
                <i class="bi bi-receipt me-1"></i>Datos de la factura
            </h6>
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label form-label-sm fw-semibold">Nro. Factura <span class="text-danger">*</span></label>
                    <input type="text" name="factura" class="form-control form-control-sm"
                           value="{{ old('factura') }}" required placeholder="Ej: 123456">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Mes <span class="text-danger">*</span></label>
                    <select name="periodo_mes" class="form-select form-select-sm" required>
                        @foreach($meses as $n => $nombre)
                            @if($n > 0)
                            <option value="{{ $n }}" {{ old('periodo_mes', $mesActual) == $n ? 'selected' : '' }}>
                                {{ $nombre }}
                            </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Año <span class="text-danger">*</span></label>
                    <select name="periodo_anio" class="form-select form-select-sm" required>
                        @for($y = $anioActual + 1; $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ old('periodo_anio', $anioActual) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label form-label-sm fw-semibold">Fecha Emisión</label>
                    <input type="date" name="fecha_emision" class="form-control form-control-sm"
                           value="{{ old('fecha_emision') }}">
                </div>
                <div class="col-12">
                    <label class="form-label form-label-sm fw-semibold">Observación</label>
                    <textarea name="observacion" class="form-control form-control-sm" rows="2"
                              placeholder="Opcional">{{ old('observacion') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Buscador de líneas ───────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="fw-bold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.06em">
                <i class="bi bi-search me-1"></i>Agregar líneas WOM
            </h6>
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <div class="d-flex gap-2" style="max-width:500px;flex:1">
                    <input type="text" id="buscarLinea" class="form-control form-control-sm"
                           placeholder="Buscar por número, usuario, empresa, ubicación…"
                           autocomplete="off">
                    <span class="input-group-text bg-transparent border-0 text-muted" style="font-size:.8rem" id="spinnerBuscar" hidden>
                        <i class="bi bi-arrow-repeat spin"></i>
                    </span>
                </div>
                <button type="button" class="btn btn-sm" id="btnPlantilla"
                        style="background:#6f42c1;color:#fff;white-space:nowrap"
                        onclick="cargarPlantilla()">
                    <i class="bi bi-layout-text-window me-1"></i>Cargar plantilla
                </button>
                <a href="{{ route('importaciones_wom.plantilla') }}" class="btn btn-sm btn-outline-secondary"
                   title="Editar plantilla" target="_blank">
                    <i class="bi bi-pencil-fill"></i>
                </a>
            </div>

            {{-- Resultados AJAX --}}
            <div id="resultadosBuscar" class="mb-3"></div>

            {{-- Tabla de líneas seleccionadas --}}
            <div id="wrapperSeleccionadas" hidden>
                <h6 class="fw-semibold mb-2" style="font-size:.82rem">
                    Líneas agregadas <span class="badge" style="background:#6f42c1" id="badgeCount">0</span>
                </h6>
                <div class="vti-table-wrapper">
                    <table class="vti-table" style="font-size:.82rem">
                        <thead>
                            <tr>
                                <th>Línea</th>
                                <th>Usuario</th>
                                <th>Empresa</th>
                                <th>Centro Costo</th>
                                <th>Ubicación</th>
                                <th class="text-end">Monto (neto)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbodySeleccionadas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Inputs ocultos se generan dinámicamente --}}
    <div id="hiddenInputs"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success" id="btnGuardar" disabled>
            <i class="bi bi-check-lg me-1"></i>Guardar importación
        </button>
        <a href="{{ route('importaciones_wom.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <span class="text-muted align-self-center small" id="labelTotal" hidden></span>
    </div>

    </form>
</div>
@endsection

@push('styles')
<style>
    .spin { animation: spin .6s linear infinite; display:inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .resultado-card {
        display:flex; align-items:center; gap:.75rem;
        padding:.55rem .8rem; border:1px solid #e2e8f0; border-radius:8px;
        margin-bottom:.4rem; background:#fff; cursor:pointer;
        transition:border-color .12s,background .12s;
    }
    .resultado-card:hover { border-color:#6f42c1; background:#faf5ff; }
    .resultado-card.ya-agregada { opacity:.5; cursor:default; pointer-events:none; }
    .resultado-card .linea-num { font-weight:700; font-size:.9rem; color:#6f42c1; min-width:100px; }
    .resultado-card .linea-info { font-size:.8rem; color:#64748b; flex:1; }
    .monto-input { width:130px; text-align:right; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const buscarUrl     = @json(route('importaciones_wom.buscar_lineas'));
    const plantillaUrl  = @json(route('importaciones_wom.plantilla_lineas'));

    const inputBuscar    = document.getElementById('buscarLinea');
    const resultadosDiv  = document.getElementById('resultadosBuscar');
    const tbodySel       = document.getElementById('tbodySeleccionadas');
    const wrapperSel     = document.getElementById('wrapperSeleccionadas');
    const hiddenInputs   = document.getElementById('hiddenInputs');
    const btnGuardar     = document.getElementById('btnGuardar');
    const badgeCount     = document.getElementById('badgeCount');
    const labelTotal     = document.getElementById('labelTotal');
    const spinner        = document.getElementById('spinnerBuscar');

    const seleccionadas = new Map(); // id → {datos, monto}
    let debounceTimer;

    // ── Cargar plantilla ─────────────────────────────────────────────────────
    window.cargarPlantilla = async function () {
        const btn = document.getElementById('btnPlantilla');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i>Cargando…';

        try {
            const res   = await fetch(plantillaUrl);
            const datos = await res.json();

            if (!datos.length) {
                alert('La plantilla está vacía. Agrégale líneas primero.');
                return;
            }

            let agregadas = 0;
            datos.forEach(l => {
                if (!seleccionadas.has(l.id)) {
                    seleccionadas.set(l.id, { linea: l, monto: l.monto || 0 });
                    agregadas++;
                }
            });

            renderTabla();
            if (agregadas < datos.length) {
                btn.innerHTML = `<i class="bi bi-layout-text-window me-1"></i>Plantilla cargada (${agregadas} nuevas)`;
            } else {
                btn.innerHTML = `<i class="bi bi-check-lg me-1"></i>Plantilla cargada (${agregadas})`;
            }
        } finally {
            btn.disabled = false;
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-layout-text-window me-1"></i>Cargar plantilla';
            }, 3000);
        }
    };

    // ── Buscar ──────────────────────────────────────────────────────────────
    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { resultadosDiv.innerHTML = ''; return; }
        debounceTimer = setTimeout(() => fetchLineas(q), 320);
    });

    async function fetchLineas(q) {
        spinner.hidden = false;
        try {
            const res = await fetch(buscarUrl + '?q=' + encodeURIComponent(q));
            const data = await res.json();
            renderResultados(data);
        } finally {
            spinner.hidden = true;
        }
    }

    function renderResultados(lineas) {
        if (!lineas.length) {
            resultadosDiv.innerHTML = '<p class="text-muted small mb-0">Sin resultados.</p>';
            return;
        }
        resultadosDiv.innerHTML = lineas.map(l => {
            const yaAgregada = seleccionadas.has(l.id);
            return `<div class="resultado-card ${yaAgregada ? 'ya-agregada' : ''}" data-id="${l.id}"
                         onclick="agregarLinea(${JSON.stringify(l).replace(/"/g,'&quot;')})">
                <span class="linea-num"><i class="bi bi-phone me-1"></i>${l.linea}</span>
                <span class="linea-info">
                    <strong>${l.usuario}</strong> &middot; ${l.empresa} &middot; ${l.cc} &middot; ${l.ubicacion}
                </span>
                ${yaAgregada
                    ? '<span class="badge bg-secondary ms-auto">Agregada</span>'
                    : '<span class="badge ms-auto" style="background:#6f42c1">+ Agregar</span>'}
            </div>`;
        }).join('');
    }

    // ── Agregar línea ────────────────────────────────────────────────────────
    window.agregarLinea = function (linea) {
        if (seleccionadas.has(linea.id)) return;
        seleccionadas.set(linea.id, { linea, monto: 0 });
        renderTabla();
        // Limpiar buscador y devolver foco
        inputBuscar.value = '';
        resultadosDiv.innerHTML = '';
        inputBuscar.focus();
    };

    // ── Quitar línea ─────────────────────────────────────────────────────────
    window.quitarLinea = function (id) {
        seleccionadas.delete(id);
        renderTabla();
        // re-render resultados para desmarcar
        const cards = resultadosDiv.querySelectorAll(`[data-id="${id}"]`);
        cards.forEach(c => {
            c.classList.remove('ya-agregada');
            c.querySelector('.badge').textContent = '+ Agregar';
            c.querySelector('.badge').style.background = '#6f42c1';
            c.querySelector('.badge').className = 'badge ms-auto';
            c.style.cssText = '';
        });
    };

    // ── Actualizar monto ─────────────────────────────────────────────────────
    window.actualizarMonto = function (id, val) {
        if (seleccionadas.has(id)) {
            const entry = seleccionadas.get(id);
            entry.monto = parseFloat(val.replace(/\./g,'').replace(',','.')) || 0;
        }
        actualizarTotal();
    };

    // ── Render tabla seleccionadas ────────────────────────────────────────────
    function renderTabla() {
        tbodySel.innerHTML = '';
        hiddenInputs.innerHTML = '';

        let i = 0;
        seleccionadas.forEach(({ linea, monto }, id) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong style="color:#6f42c1">${linea.linea}</strong></td>
                <td>${linea.usuario}</td>
                <td style="font-size:.79rem">${linea.empresa}</td>
                <td style="font-size:.79rem">${linea.cc}</td>
                <td style="font-size:.79rem">${linea.ubicacion}</td>
                <td class="text-end">
                    <input type="text" class="form-control form-control-sm monto-input"
                           value="${monto > 0 ? monto.toLocaleString('es-CL') : ''}"
                           placeholder="0"
                           oninput="actualizarMonto(${id}, this.value)"
                           onblur="formatMonto(this)">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                            onclick="quitarLinea(${id})">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>`;
            tbodySel.appendChild(tr);

            // Hidden inputs para el form
            hiddenInputs.insertAdjacentHTML('beforeend',
                `<input type="hidden" name="lineas[${i}][id]" value="${id}">` +
                `<input type="hidden" name="lineas[${i}][monto]" value="${monto}" data-monto-id="${id}">`
            );
            i++;
        });

        const count = seleccionadas.size;
        wrapperSel.hidden = count === 0;
        btnGuardar.disabled = count === 0;
        badgeCount.textContent = count;
        actualizarTotal();
    }

    // ── Total ─────────────────────────────────────────────────────────────────
    function actualizarTotal() {
        let total = 0;
        seleccionadas.forEach(e => total += e.monto);
        if (total > 0) {
            labelTotal.hidden = false;
            labelTotal.textContent = 'Total: $ ' + Math.round(total).toLocaleString('es-CL');
        } else {
            labelTotal.hidden = true;
        }
        // Actualizar hidden inputs de monto
        seleccionadas.forEach(({ monto }, id) => {
            const inp = hiddenInputs.querySelector(`[data-monto-id="${id}"]`);
            if (inp) inp.value = monto;
        });
    }

    // ── Formato visual del monto ──────────────────────────────────────────────
    window.formatMonto = function (el) {
        const raw = parseFloat(el.value.replace(/\./g,'').replace(',','.'));
        if (!isNaN(raw) && raw > 0) {
            el.value = Math.round(raw).toLocaleString('es-CL');
        }
    };
})();
</script>
@endpush
