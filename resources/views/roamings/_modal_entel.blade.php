<div class="modal fade" id="modalEntel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formEntel" action="">
                @csrf
                <input type="hidden" name="carrier" value="entel">
                <input type="hidden" name="tipo" value="entel_uso">
                <div class="modal-header text-white" style="background:#002C7F">
                    <h5 class="modal-title"><i class="bi bi-broadcast me-2"></i>Activar Roaming Entel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Buscador --}}
                    <div class="mb-3" id="entBuscador">
                        <label class="form-label fw-semibold"><i class="bi bi-search me-1"></i>Buscar línea Entel (usuario o número)</label>
                        <input type="text" class="form-control" id="buscarEnt" placeholder="Escribe el número o nombre…" autocomplete="off">
                        <div id="resEnt" class="list-group mt-1" style="max-height:240px;overflow-y:auto"></div>
                    </div>

                    {{-- Formulario --}}
                    <div id="entForm" style="display:none">
                        <div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center" style="font-size:.85rem">
                            <div>
                                <span class="text-muted">Línea:</span> <strong id="entResLinea" class="font-monospace"></strong>
                                <span class="text-muted ms-2">·</span> <strong id="entResUsuario"></strong>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="entCambiar"><i class="bi bi-arrow-repeat me-1"></i>Cambiar</button>
                        </div>

                        <div class="alert alert-light border" style="font-size:.82rem">
                            <i class="bi bi-info-circle me-1"></i>El roaming Entel se paga por uso. Este registro deja constancia de la activación; ciérralo cuando ya no se use.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha de activación</label>
                                <input type="datetime-local" class="form-control" name="fecha_inicio"
                                       value="{{ now()->format('Y-m-d\TH:i') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Destino</label>
                                <input type="text" class="form-control" name="destino" maxlength="255" placeholder="(opcional)">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white" id="entSubmit" style="background:#002C7F" disabled>
                        <i class="bi bi-check-circle me-1"></i>Activar
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
    const form     = document.getElementById('formEntel');
    const buscador = document.getElementById('entBuscador');
    const formSec  = document.getElementById('entForm');
    const submit   = document.getElementById('entSubmit');

    document.getElementById('btnActivarEntel')?.addEventListener('click', () => {
        resetEnt();
        abrirModal(document.getElementById('modalEntel'));
        setTimeout(() => document.getElementById('buscarEnt').focus(), 200);
    });

    initBuscadorLinea({
        inputId: 'buscarEnt', resultadosId: 'resEnt', carrier: 'entel',
        onSelect: (d) => {
            form.setAttribute('action', storeBase.replace('__ID__', d.id));
            if (buscador) buscador.style.display = 'none';
            if (formSec)  formSec.style.display  = 'block';
            if (submit)   submit.disabled = false;
            const rl = document.getElementById('entResLinea');
            const ru = document.getElementById('entResUsuario');
            if (rl) rl.textContent = d.linea;
            if (ru) ru.textContent = d.usuario;
        }
    });

    document.getElementById('entCambiar')?.addEventListener('click', resetEnt);

    function resetEnt() {
        form.setAttribute('action', '');
        buscador.style.display = 'block';
        formSec.style.display = 'none';
        submit.disabled = true;
        document.getElementById('buscarEnt').value = '';
        document.getElementById('resEnt').innerHTML = '';
    }
})();
</script>
@endpush
