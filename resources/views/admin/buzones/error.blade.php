@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-envelope-exclamation me-2" style="color:#a63a22"></i>Actividad de buzones
        </h4>
    </div>

    <div class="alert alert-danger d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>No se pudo cargar el {{ $pantalla }}:</strong> {{ $error }}<br>
            <a href="{{ route('admin.configuracion.index') }}" class="alert-link">Ir a Configuración</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold" style="font-size:.85rem">Permisos que necesita la aplicación en Azure</h6>
            <p class="text-muted mb-2" style="font-size:.86rem">
                Son permisos <strong>de aplicación</strong> —no delegados— y hay que otorgarles
                consentimiento de administrador para que surtan efecto.
            </p>
            <ul class="mb-0" style="font-size:.86rem">
                <li><code>Reports.Read.All</code> — los informes de uso de correo y buzones</li>
                <li><code>AuditLog.Read.All</code> — la propiedad <code>signInActivity</code></li>
                <li><code>User.Read.All</code> — el directorio</li>
                <li><code>Organization.Read.All</code> — los nombres de las licencias</li>
            </ul>
        </div>
    </div>

</div>
@endsection
