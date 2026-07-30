<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formEditar" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-fill me-2 text-warning"></i>Editar Roaming</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Info (no editable) --}}
                    <div class="alert alert-light border mb-3" style="font-size:.85rem">
                        <div class="row g-2">
                            <div class="col-6"><span class="text-muted">Línea:</span> <strong id="edtNumero" class="font-monospace"></strong></div>
                            <div class="col-6"><span class="text-muted">Usuario:</span> <strong id="edtUsuario"></strong></div>
                            <div class="col-6"><span class="text-muted">Operador:</span> <strong id="edtCarrier"></strong></div>
                            <div class="col-6"><span class="text-muted">Tipo:</span> <strong id="edtTipo"></strong></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        {{-- Pasaporte días (solo pasaporte) --}}
                        <div class="col-md-4" id="edtDiasWrap">
                            <label class="form-label fw-semibold">Pasaporte</label>
                            <select class="form-select" name="pasaporte_dias" id="edtDias">
                                <option value="1">1 día</option>
                                <option value="3">3 días</option>
                                <option value="7">7 días</option>
                                <option value="15">15 días</option>
                                <option value="21">21 días</option>
                            </select>
                        </div>
                        {{-- Inicio --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" id="edtInicioLabel">Inicio</label>
                            <input type="datetime-local" class="form-control" name="fecha_inicio" id="edtInicio" required>
                        </div>
                        {{-- Término (preview, solo pasaporte) --}}
                        <div class="col-md-4" id="edtTerminoWrap">
                            <label class="form-label fw-semibold">Término (calculado)</label>
                            <input type="text" class="form-control bg-light" id="edtTermino" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Destino</label>
                            <input type="text" class="form-control" name="destino" id="edtDestino" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ID Solicitud</label>
                            <input type="text" class="form-control" name="id_solicitud" id="edtSolicitud" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estado</label>
                            <select class="form-select" name="estado" id="edtEstado">
                                <option value="activo">Activo</option>
                                <option value="cerrado">Cerrado</option>
                                <option value="archivado">Archivado</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Observación</label>
                            <textarea class="form-control" name="observacion" id="edtObs" rows="1" maxlength="500"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle me-1"></i>Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const updateBase = @json(route('roamings.update', ['roaming' => '__ID__']));
    const form       = document.getElementById('formEditar');
    const diasWrap   = document.getElementById('edtDiasWrap');
    const terminoWrap= document.getElementById('edtTerminoWrap');
    const dias       = document.getElementById('edtDias');
    const inicio     = document.getElementById('edtInicio');
    const termino    = document.getElementById('edtTermino');
    const inicioLabel= document.getElementById('edtInicioLabel');

    function calcTermino() {
        if (!inicio.value || diasWrap.style.display === 'none') { termino.value = ''; return; }
        const d = new Date(inicio.value);
        d.setDate(d.getDate() + parseInt(dias.value || '0'));
        termino.value = d.toLocaleString('es-CL', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }
    dias.addEventListener('change', calcTermino);
    inicio.addEventListener('change', calcTermino);

    // Delegación: abrir editor desde cualquier botón .btn-editar-roaming
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-editar-roaming');
        if (!btn) return;
        const d = btn.dataset;

        form.setAttribute('action', updateBase.replace('__ID__', d.id));

        document.getElementById('edtNumero').textContent  = d.numero || '—';
        document.getElementById('edtUsuario').textContent = d.usuario || '—';
        document.getElementById('edtCarrier').textContent = d.carrier === 'entel' ? 'Entel' : 'Movistar';
        const tipoTxt = { pasaporte:'Pasaporte', recurrente:'Recurrente (30d)', entel_uso:'Entel · por uso' }[d.tipo] || d.tipo;
        document.getElementById('edtTipo').textContent = tipoTxt;

        const esPasaporte = d.tipo === 'pasaporte';
        diasWrap.style.display    = esPasaporte ? '' : 'none';
        terminoWrap.style.display = esPasaporte ? '' : 'none';
        dias.disabled = !esPasaporte;
        if (esPasaporte && d.dias) dias.value = d.dias;
        inicioLabel.textContent = d.tipo === 'entel_uso' ? 'Fecha de activación' : 'Inicio';

        inicio.value          = d.inicio || '';
        document.getElementById('edtDestino').value   = d.destino || '';
        document.getElementById('edtSolicitud').value = d.solicitud || '';
        document.getElementById('edtEstado').value    = d.estado || 'activo';
        document.getElementById('edtObs').value       = d.observacion || '';

        calcTermino();
        abrirModal(document.getElementById('modalEditar'));
    });
})();
</script>
@endpush
