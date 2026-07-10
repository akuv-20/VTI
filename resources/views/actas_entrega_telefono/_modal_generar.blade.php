{{--
    Modal reutilizable "Generar Acta" con buscador de línea + formulario.
    Variables esperadas:
      $tipo        => 'entrega' | 'devolucion'
      $titulo      => texto del encabezado
      $buscarUrl   => URL del endpoint de búsqueda de líneas
      $storeBase   => URL store con placeholder __ID__ para la línea
      $btnClass    => clase del botón submit (ej: 'btn-success')
      $textClass   => clase de color del ícono (ej: 'text-success')
      $icono       => clase de ícono bootstrap
      $condDefault => 'Nuevo' | 'Usado'
      $accLabel    => etiqueta de la sección de accesorios
--}}
<div class="modal fade" id="modalGenerarActa" tabindex="-1" aria-labelledby="modalGenerarActaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formGenerarActa" action="" target="_blank">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGenerarActaLabel">
                        <i class="{{ $icono }} me-2 {{ $textClass }}"></i>{{ $titulo }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Paso 1: Buscador de línea --}}
                    <div class="mb-3">
                        <label for="buscarLineaActa" class="form-label fw-semibold">
                            <i class="bi bi-search me-1"></i>Buscar línea por número o nombre de usuario
                        </label>
                        <input type="text" class="form-control" id="buscarLineaActa"
                               placeholder="Escribe el número o nombre…" autocomplete="off">
                        <div id="resultadosLineaActa" class="list-group mt-1"
                             style="max-height:240px;overflow-y:auto"></div>
                    </div>

                    {{-- Paso 2: Línea seleccionada + formulario (oculto hasta seleccionar) --}}
                    <div id="seccionFormActa" style="display:none">

                        {{-- Resumen de la línea seleccionada --}}
                        <div class="alert alert-light border mb-4" style="font-size:.85rem">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="row g-2 flex-grow-1">
                                    <div class="col-6">
                                        <span class="text-muted">N° Línea:</span>
                                        <strong id="resLinea" class="font-monospace"></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Empleado:</span>
                                        <strong id="resUsuario"></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Zona:</span>
                                        <strong id="resZona"></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Equipo:</span>
                                        <strong id="resEquipo"></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Compañía:</span>
                                        <strong id="resEmisor"></strong>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                                        id="btnCambiarLinea">
                                    <i class="bi bi-arrow-repeat me-1"></i>Cambiar
                                </button>
                            </div>
                        </div>

                        {{-- Tipo de acta: Equipo+SIM o Solo SIM --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Tipo de acta</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_acta" id="tipoEquipoSim"
                                       value="equipo_sim" checked>
                                <label class="btn btn-outline-primary" for="tipoEquipoSim">
                                    <i class="bi bi-phone me-1"></i>Equipo + SIM
                                </label>
                                <input type="radio" class="btn-check" name="tipo_acta" id="tipoSoloSim"
                                       value="solo_sim">
                                <label class="btn btn-outline-primary" for="tipoSoloSim">
                                    <i class="bi bi-sim me-1"></i>Solo SIM
                                </label>
                            </div>
                            <div class="form-text" id="tipoActaHint">
                                Incluye equipo físico, condición, accesorios y documentación.
                            </div>
                        </div>

                        {{-- Bloque de equipo físico (oculto en modo Solo SIM) --}}
                        <div id="bloqueEquipo">

                        {{-- Condición --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Condición del equipo</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="condicion" id="actaCondNuevo"
                                           value="Nuevo" {{ $condDefault === 'Nuevo' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="actaCondNuevo">Nuevo</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="condicion" id="actaCondUsado"
                                           value="Usado" {{ $condDefault === 'Usado' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="actaCondUsado">Usado</label>
                                </div>
                            </div>
                        </div>

                        {{-- Accesorios --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">{{ $accLabel }}</label>
                            <table class="table table-sm table-bordered" style="font-size:.88rem">
                                <thead class="table-light">
                                    <tr>
                                        <th>Accesorio</th>
                                        <th class="text-center" style="width:90px">SI</th>
                                        <th class="text-center" style="width:90px">NO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach([
                                        'cargador_usb'   => 'Cargador (Cable USB C)',
                                        'cargador_auto'  => 'Cargador de automóvil',
                                        'manos_libres'   => 'Manos libres (auricular)',
                                        'cd_informacion' => 'Cd de información',
                                    ] as $key => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-center">
                                            <input type="radio" name="accesorios[{{ $key }}]"
                                                   value="SI" class="form-check-input">
                                        </td>
                                        <td class="text-center">
                                            <input type="radio" name="accesorios[{{ $key }}]"
                                                   value="NO" class="form-check-input" checked>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Documentación --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Documentación</label>
                            <table class="table table-sm table-bordered" style="font-size:.88rem">
                                <thead class="table-light">
                                    <tr>
                                        <th>Documento</th>
                                        <th class="text-center" style="width:90px">SI</th>
                                        <th class="text-center" style="width:90px">NO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach([
                                        'manual_propietario' => 'Manual del Propietario',
                                        'procedimiento_uso'  => 'Procedimiento uso de teléfono móvil',
                                    ] as $key => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-center">
                                            <input type="radio" name="documentacion[{{ $key }}]"
                                                   value="SI" class="form-check-input">
                                        </td>
                                        <td class="text-center">
                                            <input type="radio" name="documentacion[{{ $key }}]"
                                                   value="NO" class="form-check-input" checked>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        </div>{{-- /#bloqueEquipo --}}

                        {{-- Observación --}}
                        <div>
                            <label for="observacionActaGen" class="form-label fw-semibold">Observación</label>
                            <textarea class="form-control" id="observacionActaGen" name="observacion"
                                      rows="2" maxlength="500" placeholder="(opcional)"></textarea>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn {{ $btnClass }}" id="btnSubmitActa" disabled>
                        <i class="bi bi-printer-fill me-1"></i>Generar e Imprimir Acta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const buscarUrl  = @json($buscarUrl);
    const storeBase  = @json($storeBase); // contiene __ID__

    const input      = document.getElementById('buscarLineaActa');
    const resultados = document.getElementById('resultadosLineaActa');
    const seccion    = document.getElementById('seccionFormActa');
    const form       = document.getElementById('formGenerarActa');
    const btnSubmit  = document.getElementById('btnSubmitActa');
    const btnCambiar = document.getElementById('btnCambiarLinea');
    const modalEl    = document.getElementById('modalGenerarActa');
    const btnAbrir   = document.getElementById('btnGenerarActa');

    let fallbackBackdrop = null;
    let debounce;

    // ── Abrir/cerrar modal (con fallback sin Bootstrap JS) ──
    function abrirModal() {
        try {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } catch (e) {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(fallbackBackdrop);
        }
        setTimeout(() => input.focus(), 200);
    }
    function cerrarModalFallback() {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        document.body.classList.remove('modal-open');
        fallbackBackdrop?.remove();
        fallbackBackdrop = null;
    }
    btnAbrir?.addEventListener('click', abrirModal);
    modalEl?.addEventListener('click', function (e) {
        if (!fallbackBackdrop) return;
        if (e.target.closest('[data-bs-dismiss="modal"]') || e.target === modalEl) {
            cerrarModalFallback();
        }
    });

    // ── Búsqueda con debounce ──
    input?.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { resultados.innerHTML = ''; return; }
        debounce = setTimeout(() => buscar(q), 320);
    });

    // Evitar que Enter en el buscador envíe el formulario (action vacío)
    input?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') e.preventDefault();
    });

    // Bloquear el submit si aún no se ha seleccionado una línea
    form?.addEventListener('submit', function (e) {
        const action = form.getAttribute('action') || '';
        if (action === '' || action.includes('__ID__')) {
            e.preventDefault();
        }
    });

    async function buscar(q) {
        resultados.innerHTML = '<div class="list-group-item text-muted small">Buscando…</div>';
        try {
            const resp = await fetch(`${buscarUrl}?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (!data.length) {
                resultados.innerHTML = '<div class="list-group-item text-muted small">Sin resultados.</div>';
                return;
            }
            resultados.innerHTML = data.map(l => `
                <button type="button" class="list-group-item list-group-item-action"
                        data-id="${l.id}" data-linea="${escapeHtml(l.linea)}"
                        data-usuario="${escapeHtml(l.usuario)}" data-empresa="${escapeHtml(l.empresa)}"
                        data-ubicacion="${escapeHtml(l.ubicacion)}" data-emisor="${escapeHtml(l.emisor)}"
                        data-equipo="${escapeHtml(l.equipo)}">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold font-monospace">${escapeHtml(l.linea)}</span>
                        <span class="text-muted small">${escapeHtml(l.emisor)}</span>
                    </div>
                    <div class="small text-muted">${escapeHtml(l.usuario)} · ${escapeHtml(l.empresa)} ${l.ubicacion ? '· ' + escapeHtml(l.ubicacion) : ''}</div>
                </button>
            `).join('');
        } catch (e) {
            resultados.innerHTML = '<div class="list-group-item text-danger small">Error al buscar.</div>';
        }
    }

    // Rellena un elemento por id solo si existe (tolerante a nulos)
    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    // ── Selección de línea ──
    resultados?.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-id]');
        if (!btn) return;
        e.preventDefault();

        // Camino crítico: fijar destino, mostrar formulario, habilitar submit
        form.setAttribute('action', storeBase.replace('__ID__', btn.dataset.id));
        input.closest('.mb-3').style.display = 'none';
        seccion.style.display = 'block';
        btnSubmit.disabled = false;

        // Resumen (cosmético)
        const zona = [btn.dataset.empresa, btn.dataset.ubicacion].filter(Boolean).join(' — ');
        setText('resLinea',   btn.dataset.linea);
        setText('resUsuario', btn.dataset.usuario || '(sin asignar)');
        setText('resZona',    zona || '—');
        setText('resEquipo',  btn.dataset.equipo || '—');
        setText('resEmisor',  btn.dataset.emisor || '—');
    });

    // ── Cambiar línea ──
    btnCambiar?.addEventListener('click', function () {
        seccion.style.display = 'none';
        input.closest('.mb-3').style.display = 'block';
        input.value = '';
        resultados.innerHTML = '';
        form.setAttribute('action', '');
        btnSubmit.disabled = true;
        setTimeout(() => input.focus(), 100);
    });

    // ── Tipo de acta: Equipo+SIM / Solo SIM ──
    const bloqueEquipo = document.getElementById('bloqueEquipo');
    const hint         = document.getElementById('tipoActaHint');
    const filaEquipo   = document.getElementById('resEquipo')?.closest('.col-6');

    function aplicarTipoActa() {
        const soloSim = document.getElementById('tipoSoloSim')?.checked;
        if (bloqueEquipo) bloqueEquipo.style.display = soloSim ? 'none' : 'block';
        if (filaEquipo)   filaEquipo.style.display   = soloSim ? 'none' : '';
        if (hint) {
            hint.textContent = soloSim
                ? 'Solo la SIM: número, empleado, compañía y serial de la SIM. Sin equipo físico.'
                : 'Incluye equipo físico, condición, accesorios y documentación.';
        }
    }
    document.querySelectorAll('input[name="tipo_acta"]').forEach(r =>
        r.addEventListener('change', aplicarTipoActa));
    aplicarTipoActa();

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[m]));
    }
})();
</script>
@endpush
