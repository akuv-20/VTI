@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header mb-3">
        <h4>
            <i class="bi bi-microsoft me-1" style="color:#0078d4"></i>Entra ID
            <span class="text-muted fw-normal mx-1">/</span>
            <a href="{{ route('admin.entra_id.inspector') }}" class="text-decoration-none">Value Inspector</a>
            <span class="text-muted fw-normal mx-1">/</span>
            <code>{{ $campo }}</code>
            @if($valor === '')
                <span class="badge bg-secondary ms-1" style="font-size:.7rem">(vacío)</span>
            @else
                <span class="badge bg-primary ms-1" style="font-size:.7rem">{{ $valor }}</span>
            @endif
        </h4>
        <a href="{{ route('admin.entra_id.inspector') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver al inspector
        </a>
    </div>

    <p class="text-muted mb-3" style="font-size:.85rem">
        <strong>{{ $etiqueta }}</strong> →
        @if($valor === '')
            cuentas sin valor ingresado
        @else
            cuentas con valor <strong>"{{ $valor }}"</strong>
        @endif
        — <strong>{{ $usuarios->count() }}</strong> cuenta(s)
    </p>

    @if($usuarios->count() > 0)
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>UPN / Email</th>
                        <th>Área</th>
                        <th>Cargo</th>
                        <th>País</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $u)
                    @php
                        $nombre  = $u['displayName'] ?? $u['userPrincipalName'] ?? '—';
                        $upn     = $u['userPrincipalName'] ?? '—';
                        $mail    = $u['mail'] ?? null;
                        $enabled = $u['accountEnabled'] !== false;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $nombre }}</td>
                        <td class="font-monospace text-muted" style="font-size:.78rem">
                            {{ $mail ?: $upn }}
                        </td>
                        <td>{{ $u['department'] ?? '—' }}</td>
                        <td>{{ $u['jobTitle'] ?? '—' }}</td>
                        <td>{{ $u['country'] ?? '—' }}</td>
                        <td class="text-center">
                            @if($enabled)
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.72rem">
                                    <i class="bi bi-check-circle-fill me-1"></i>Habilitado
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.72rem">
                                    <i class="bi bi-x-circle-fill me-1"></i>Deshabilitado
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
        <div class="text-center py-5 text-muted">
            <i class="bi bi-person-x" style="font-size:2.5rem"></i>
            <p class="mt-2">No se encontraron cuentas con ese valor.</p>
        </div>
    @endif

</div>
@endsection
