<div class="modal fade" id="modalMovistar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formMovistar" action="" >
                @csrf
                <input type="hidden" name="carrier" value="movistar">
                <div class="modal-header text-white" style="background:#0099CC">
                    <h5 class="modal-title"><i class="bi bi-airplane me-2"></i>Agendar Roaming Movistar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Buscador --}}
                    <div class="mb-3" id="movBuscador">
                        <label class="form-label fw-semibold"><i class="bi bi-search me-1"></i>Buscar línea Movistar (usuario o número)</label>
                        <input type="text" class="form-control" id="buscarMov" placeholder="Escribe el número o nombre…" autocomplete="off">
                        <div id="resMov" class="list-group mt-1" style="max-height:240px;overflow-y:auto"></div>
                    </div>

                    {{-- Formulario --}}
                    <div id="movForm" style="display:none">
                        <div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center" style="font-size:.85rem">
                            <div>
                                <span class="text-muted">Línea:</span> <strong id="movResLinea" class="font-monospace"></strong>
                                <span class="text-muted ms-2">·</span> <strong id="movResUsuario"></strong>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="movCambiar"><i class="bi bi-arrow-repeat me-1"></i>Cambiar</button>
                        </div>

                        {{-- Bloqueo por recurrente --}}
                        <div class="alert alert-danger d-none" id="movBloqueo">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Esta línea tiene un <strong>roaming recurrente activo</strong>. Desactívalo en la pestaña "Recurrentes" antes de agendar.
                        </div>

                        <div id="movCampos">
                            {{-- Tipo --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipo</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="tipo" id="movTipoPasaporte" value="pasaporte" checked>
                                    <label class="btn btn-outline-primary" for="movTipoPasaporte"><i class="bi bi-airplane me-1"></i>Pasaporte</label>
                                    <input type="radio" class="btn-check" name="tipo" id="movTipoRecurrente" value="recurrente">
                                    <label class="btn btn-outline-primary" for="movTipoRecurrente"><i class="bi bi-arrow-repeat me-1"></i>Recurrente (30d)</label>
                                </div>
                            </div>

                            <div class="row g-3">
                                {{-- Pasaporte días --}}
                                <div class="col-md-4" id="movDiasWrap">
                                    <label class="form-label fw-semibold">Pasaporte</label>
                                    <select class="form-select" name="pasaporte_dias" id="movDias">
                                        <option value="1">1 día</option>
                                        <option value="3">3 días</option>
                                        <option value="7" selected>7 días</option>
                                        <option value="15">15 días</option>
                                        <option value="21">21 días</option>
                                    </select>
                                </div>
                                {{-- Inicio --}}
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Inicio</label>
                                    <input type="datetime-local" class="form-control" name="fecha_inicio" id="movInicio"
                                           value="{{ now()->format('Y-m-d\TH:i') }}" required>
                                </div>
                                {{-- Término (preview) --}}
                                <div class="col-md-4" id="movTerminoWrap">
                                    <label class="form-label fw-semibold">Término (calculado)</label>
                                    <input type="text" class="form-control bg-light" id="movTermino" readonly>
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Destino</label>
                                    <input type="text" class="form-control" name="destino" maxlength="255" placeholder="Ej: Perú, Europa…">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">ID Solicitud</label>
                                    <input type="text" class="form-control" name="id_solicitud" maxlength="255" placeholder="(opcional)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Observación</label>
                                    <textarea class="form-control" name="observacion" rows="1" maxlength="500" placeholder="(opcional)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white" id="movSubmit" style="background:#0099CC" disabled>
                        <i class="bi bi-check-circle me-1"></i>Agendar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const storeBase = @json(route('roamings.store', ['linea' => '__ID__']));
    const form   = document.getElementById('formMovistar');
    const buscador = document.getElementById('movBuscador');
    const formSec  = document.getElementById('movForm');
    const bloqueo  = document.getElementById('movBloqueo');
    const campos   = document.getElementById('movCampos');
    const submit   = document.getElementById('movSubmit');
    const diasWrap = document.getElementById('movDiasWrap');
    const terminoWrap = document.getElementById('movTerminoWrap');
    const dias     = document.getElementById('movDias');
    const inicio   = document.getElementById('movInicio');
    const termino  = document.getElementById('movTermino');

    document.getElementById('btnAgendarMovistar')?.addEventListener('click', () => {
        resetMov();
        abrirModal(document.getElementById('modalMovistar'));
        setTimeout(() => document.getElementById('buscarMov').focus(), 200);
    });

    initBuscadorLinea({
        inputId: 'buscarMov', resultadosId: 'resMov', carrier: 'movistar',
        onSelect: (d) => {
            // Camino crítico primero (usa referencias capturadas al init, que sí existen)
            form.setAttribute('action', storeBase.replace('__ID__', d.id));
            if (buscador) buscador.style.display = 'none';
            if (formSec)  formSec.style.display  = 'block';

            const bloqueado = d.recurrente === '1';
            if (bloqueo) bloqueo.classList.toggle('d-none', !bloqueado);
            if (campos)  campos.style.display = bloqueado ? 'none' : 'block';
            if (submit)  submit.disabled = bloqueado;

            // Resumen (cosmético, tolerante a null)
            const rl = document.getElementById('movResLinea');
            const ru = document.getElementById('movResUsuario');
            if (rl) rl.textContent = d.linea;
            if (ru) ru.textContent = d.usuario;
            calcTermino();
        }
    });

    document.getElementById('movCambiar')?.addEventListener('click', resetMov);

    function resetMov() {
        form.setAttribute('action', '');
        buscador.style.display = 'block';
        formSec.style.display = 'none';
        submit.disabled = true;
        document.getElementById('buscarMov').value = '';
        document.getElementById('resMov').innerHTML = '';
    }

    // Toggle pasaporte / recurrente
    function aplicarTipo() {
        const esPasaporte = document.getElementById('movTipoPasaporte').checked;
        diasWrap.style.display = esPasaporte ? '' : 'none';
        terminoWrap.style.display = esPasaporte ? '' : 'none';
        dias.disabled = !esPasaporte;
        calcTermino();
    }
    document.querySelectorAll('input[name="tipo"]').forEach(r => r.addEventListener('change', aplicarTipo));

    // Calcular término = inicio + N días
    function calcTermino() {
        if (!document.getElementById('movTipoPasaporte').checked || !inicio.value) { termino.value = ''; return; }
        const d = new Date(inicio.value);
        d.setDate(d.getDate() + parseInt(dias.value));
        termino.value = d.toLocaleString('es-CL', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }
    dias.addEventListener('change', calcTermino);
    inicio.addEventListener('change', calcTermino);
})();
</script>
@endpush
