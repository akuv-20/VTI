@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header" style="max-width:800px;margin-left:auto;margin-right:auto">
        <h4>
            <i class="bi bi-phone-fill me-2"></i>Equipo
            @if($equipo->propiedad === 'Empresa')
                <span class="badge bg-primary ms-2" style="font-size:.65rem;vertical-align:middle">Empresa</span>
            @else
                <span class="badge bg-secondary ms-2" style="font-size:.65rem;vertical-align:middle">Personal</span>
            @endif
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('equipos.edit', $equipo) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil-fill me-1"></i>Editar</a>
            <a href="{{ route('equipos.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>
    </div>

    <div class="row g-4" style="max-width:800px;margin-left:auto;margin-right:auto">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header fw-bold border-0" style="background:#f8fafc">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Datos del equipo
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @php
                            $item = fn($label, $val) => "<div class='col-6 col-md-4'><div class='text-muted' style='font-size:.72rem;text-transform:uppercase;letter-spacing:.06em'>$label</div><div class='fw-semibold'>" . ($val ?: '—') . "</div></div>";
                        @endphp
                        {!! $item('Modelo', e($equipo->modelo_completo)) !!}
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">IMEI</div>
                            <div class="fw-semibold font-monospace" style="font-size:.85rem">{{ $equipo->imei ?? '—' }}</div>
                        </div>
                        {!! $item('Propiedad', e($equipo->propiedad)) !!}
                        {!! $item('Estado', e($equipo->estado)) !!}
                        {!! $item('Usuario', e($equipo->usuario->nombre ?? '')) !!}
                        {!! $item('Ubicación', e($equipo->ubicacion->nombre ?? '')) !!}
                        <div class="col-6 col-md-4">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Línea asociada</div>
                            <div class="fw-semibold">
                                @if($equipo->lineaTelefonica)
                                    <a href="{{ route('lineas_telefonicas.show', $equipo->lineaTelefonica->id) }}"
                                       class="font-monospace text-decoration-none">{{ $equipo->lineaTelefonica->linea }}</a>
                                    <span class="text-muted">({{ $equipo->lineaTelefonica->emisor->nombre ?? '—' }})</span>
                                @else
                                    <span class="badge bg-light text-muted border">Sin línea</span>
                                @endif
                            </div>
                        </div>
                        @if($equipo->observacion)
                        <div class="col-12">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Observación</div>
                            <div>{{ $equipo->observacion }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
