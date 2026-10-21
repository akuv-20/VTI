@php
    $ln = $equipo->lineaTelefonica;
    $zonaBtn = trim(($ln?->empresa?->nombre ?? '') . ' - ' . ($ln?->ubicacion?->nombre ?? $equipo->ubicacion?->nombre ?? ''), ' -');
@endphp
<button type="button" class="btn btn-outline-success btn-sm btn-acta-preset"
    title="Generar acta de entrega"
    data-target="{{ route('actas_entrega_telefono.store_equipo', $equipo) }}"
    data-tiene-linea="{{ $ln ? '1' : '0' }}"
    data-tiene-equipo="1"
    data-linea="{{ $ln?->linea ?? ($equipo->imei ? 'IMEI '.$equipo->imei : 'Equipo #'.$equipo->id) }}"
    data-usuario="{{ $ln?->usuario?->nombre ?? $equipo->usuario?->nombre ?? '' }}"
    data-zona="{{ $zonaBtn }}"
    data-equipo="{{ $equipo->modelo_completo }}"
    data-propiedad="{{ $equipo->propiedad }}"
    data-emisor="{{ $ln?->emisor?->nombre ?? 'Sin línea' }}">
    <i class="bi bi-file-earmark-text{{ ($compacto ?? false) ? '' : ' me-1' }}"></i>{{ ($compacto ?? false) ? '' : 'Generar acta' }}
</button>
