@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0"><i class="bi bi-person-fill-gear me-2"></i>Editar Usuario: {{ $usuario->name }}</h4>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.usuarios.sincronizar_azure', $usuario) }}" method="POST" data-loader>
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm"
                        title="Trae el nombre y correo actuales desde Entra ID (Microsoft 365)">
                    <i class="bi bi-arrow-repeat me-1"></i>Sincronizar con Entra ID
                </button>
            </form>
            <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @if($usuario->provider === 'azure')
        <p class="text-muted small mb-3">
            <i class="bi bi-microsoft me-1"></i>Cuenta vinculada a Entra ID
            <span class="font-monospace" style="font-size:.75rem">({{ $usuario->provider_id }})</span>
        </p>
    @endif

    <form action="{{ route('admin.usuarios.update', $usuario) }}" method="POST" data-loader>
        @csrf @method('PUT')
        @include('admin.usuarios._form', ['asignados' => $asignados])

        <div class="mt-3">
            <button type="submit" class="btn btn-warning" id="btn-submit">
                <i class="bi bi-check-lg"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>
@endsection
