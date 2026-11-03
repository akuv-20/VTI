@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">
    <div class="vti-page-header mb-3">
        <h4 class="mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Asignación de tickets</h4>
    </div>

    <div class="card border-0 shadow-sm rounded-3" style="max-width:720px">
        <div class="card-body p-4">
            @switch($motivo)
                @case('sin_usuario')
                    <h5><i class="bi bi-person-x text-danger me-2"></i>No encontramos tu usuario en GLPI</h5>
                    <p class="text-muted mb-0">
                        Entraste a VTI como <strong>{{ $correo }}</strong>, pero en el GLPI de Unifrutti no hay ningún
                        usuario con ese correo. Para asignar tickets tienes que entrar a VTI con tu cuenta de Unifrutti
                        (la misma de Entra ID que usas en GLPI).
                    </p>
                    @break

                @case('sin_perfil')
                    <h5><i class="bi bi-shield-lock text-warning me-2"></i>Tu perfil de GLPI no permite asignar</h5>
                    <p class="text-muted">
                        Asignar tickets está reservado a los perfiles
                        <strong>{{ implode(', ', \App\Services\AsignacionTickets::PERFILES_ASIGNADORES) }}</strong>.
                        Tu usuario de GLPI ({{ $glpi['nombre'] }}) tiene:
                        <strong>{{ implode(', ', $glpi['perfiles']) ?: 'ningún perfil' }}</strong>.
                    </p>
                    <p class="text-muted mb-0">Si deberías poder asignar, pide a un administrador de GLPI que te dé uno de esos perfiles.</p>
                    @break

                @case('sin_app_token')
                    <h5><i class="bi bi-key text-warning me-2"></i>Falta configurar la API de GLPI</h5>
                    <p class="text-muted mb-0">
                        Para asignar tickets, VTI necesita el <strong>App-Token</strong> del cliente de API de GLPI.
                        Un administrador de VTI debe cargarlo en <strong>Admin → Configuración → BD GLPI Unifrutti</strong>.
                    </p>
                    @break

                @default
                    <h5><i class="bi bi-wifi-off text-danger me-2"></i>No se pudo consultar GLPI</h5>
                    <p class="text-muted mb-0">Intenta de nuevo en un momento. <span class="small">({{ \Illuminate\Support\Str::limit($detalle ?? '', 200) }})</span></p>
            @endswitch
        </div>
    </div>
</div>
@endsection
