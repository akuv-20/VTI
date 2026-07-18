@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-microsoft me-2" style="color:#0078d4"></i>Entra ID
            @if(isset($total) && $total > 0)
                <span class="badge bg-secondary fw-normal ms-1" style="font-size:.7rem">{{ $total }}</span>
            @endif
        </h4>
        <a href="{{ route('admin.entra_id.inspector') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-clipboard2-data me-1"></i>Value Inspector
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(isset($graphError))
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>No se pudo conectar a Microsoft Entra ID:</strong> {{ $graphError }}<br>
                <a href="{{ route('admin.configuracion.index') }}#pane-azure" class="alert-link">
                    Ir a Configuración → Azure / Microsoft 365
                </a>
            </div>
        </div>
    @else

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.entra_id.index') }}" class="mb-3">
            <div class="row g-2 align-items-end mb-2">
                <div class="col">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="buscar" class="form-control"
                               value="{{ $buscar }}" placeholder="Nombre, email, área, cargo…"
                               autofocus>
                        @if($buscar)
                            <a href="{{ route('admin.entra_id.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i>
                            </a>
                        @endif
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Buscar</span>
                    </button>
                </div>
            </div>

            {{-- Filtro de estado --}}
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="estado" id="estado_habilitados"
                           value="habilitados" autocomplete="off"
                           {{ $filtroEstado === 'habilitados' ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-success fw-semibold" for="estado_habilitados">
                        Habilitados <span class="badge bg-success ms-1">{{ $countHabilitados }}</span>
                    </label>

                    <input type="radio" class="btn-check" name="estado" id="estado_deshabilitados"
                           value="deshabilitados" autocomplete="off"
                           {{ $filtroEstado === 'deshabilitados' ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-danger fw-semibold" for="estado_deshabilitados">
                        Deshabilitados <span class="badge bg-danger ms-1">{{ $countDeshabilitados }}</span>
                    </label>

                    <input type="radio" class="btn-check" name="estado" id="estado_todos"
                           value="todos" autocomplete="off"
                           {{ $filtroEstado === 'todos' ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-secondary fw-semibold" for="estado_todos">
                        Todos <span class="badge bg-secondary ms-1">{{ $countTodos }}</span>
                    </label>
                </div>
            </div>
        </form>

        @if($usuarios && $usuarios->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px"></th>
                            <th>Nombre</th>
                            <th>Usuario (UPN)</th>
                            <th>Email</th>
                            <th>Área / Cargo</th>
                            <th>Teléfono</th>
                            <th>País</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $u)
                        @php
                            $nombre   = $u['displayName'] ?? ($u['userPrincipalName'] ?? '—');
                            $upn      = $u['userPrincipalName'] ?? '';
                            $mail     = $u['mail'] ?? null;
                            $depto    = $u['department'] ?? null;
                            $cargo    = $u['jobTitle'] ?? null;
                            $telefono = $u['mobilePhone'] ?? (($u['businessPhones'] ?? [])[0] ?? null);
                            $pais     = $u['country'] ?? null;
                            $enabled  = $u['accountEnabled'] !== false;
                            $tipo     = $u['userType'] ?? 'Member';
                            $iniciales = collect(explode(' ', $nombre))->take(2)->map(fn($p) => strtoupper(substr($p,0,1)))->join('');
                        @endphp
                        <tr>
                            <td>
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white"
                                     style="width:32px;height:32px;font-size:.72rem;
                                            background:{{ $enabled ? '#0078d4' : '#94a3b8' }}">
                                    {{ $iniciales }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $nombre }}</div>
                            </td>
                            <td class="font-monospace text-muted" style="font-size:.8rem">{{ $upn }}</td>
                            <td>
                                @if($mail)
                                    <a href="mailto:{{ $mail }}" class="text-decoration-none text-reset">{{ $mail }}</a>
                                @elseif($mail !== $upn && $upn)
                                    <span class="text-muted" style="font-size:.8rem">{{ $upn }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($depto || $cargo)
                                    @if($depto)<div>{{ $depto }}</div>@endif
                                    @if($cargo)<div class="text-muted" style="font-size:.8rem">{{ $cargo }}</div>@endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted" style="font-size:.82rem">{{ $telefono ?: '—' }}</td>
                            <td class="text-muted" style="font-size:.82rem">{{ $pais ?: '—' }}</td>
                            <td class="text-center">
                                @if($tipo === 'Guest')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.75rem">
                                        <i class="bi bi-person-badge me-1"></i>Invitado
                                    </span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.75rem">
                                        <i class="bi bi-person me-1"></i>Miembro
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($enabled)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle-fill me-1"></i>Habilitado
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
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

        {{-- Paginación --}}
        @if($usuarios->hasPages())
            <div class="mt-3 d-flex justify-content-center">
                {{ $usuarios->links() }}
            </div>
        @endif

        @elseif($usuarios !== null)
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-x" style="font-size:2.5rem"></i>
                <p class="mt-2">No se encontraron usuarios con ese criterio.</p>
            </div>
        @endif

    @endif

</div>
@endsection
