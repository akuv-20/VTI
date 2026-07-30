<button type="button" class="btn btn-outline-warning btn-sm btn-editar-roaming" title="Editar"
    data-id="{{ $r->id }}"
    data-tipo="{{ $r->tipo }}"
    data-carrier="{{ $r->carrier }}"
    data-usuario="{{ $r->nombre_usuario }}"
    data-numero="{{ $r->numero }}"
    data-dias="{{ $r->pasaporte_dias }}"
    data-inicio="{{ $r->fecha_inicio?->format('Y-m-d\TH:i') }}"
    data-destino="{{ $r->destino }}"
    data-solicitud="{{ $r->id_solicitud }}"
    data-estado="{{ $r->estado }}"
    data-observacion="{{ $r->observacion }}">
    <i class="bi bi-pencil-fill"></i>
</button>
