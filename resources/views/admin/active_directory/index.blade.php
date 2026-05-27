@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-diagram-3 me-2"></i>Active Directory
            @if(isset($total) && $total > 0)
                <span class="badge bg-secondary fw-normal ms-1" style="font-size:.7rem">{{ $total }}</span>
            @endif
        </h4>
        <a href="{{ route('admin.active_directory.importar_correos') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-envelope-arrow-up me-1"></i>Importar correos
        </a>
    </div>

    @if(isset($ldapError))
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>No se pudo conectar al AD:</strong> {{ $ldapError }}<br>
                <a href="{{ route('admin.configuracion.index') }}#pane-ldap" class="alert-link">
                    Ir a Configuración → Active Directory
                </a>
            </div>
        </div>
    @else

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.active_directory.index') }}" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="buscar" class="form-control"
                               value="{{ $buscar }}" placeholder="Nombre, usuario, email, área…"
                               autofocus>
                        @if($buscar)
                            <a href="{{ route('admin.active_directory.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i>
                            </a>
                        @endif
                    </div>
                </div>
                <div class="col-8 col-md-3">
                    <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="habilitados"   {{ $filtroEstado === 'habilitados'   ? 'selected' : '' }}>Solo habilitados</option>
                        <option value="deshabilitados"{{ $filtroEstado === 'deshabilitados'? 'selected' : '' }}>Solo deshabilitados</option>
                        <option value="todos"         {{ $filtroEstado === 'todos'         ? 'selected' : '' }}>Todos los estados</option>
                    </select>
                </div>
                <div class="col-4 col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i><span class="d-none d-sm-inline">Filtrar</span>
                    </button>
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
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Área / Cargo</th>
                            <th>Unidad Org.</th>
                            <th>Teléfono</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end" style="width:100px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $u)
                        @php
                            $sam      = $u->getFirstAttribute('samaccountname');
                            $nombre   = $u->getFirstAttribute('cn') ?: $sam;
                            $mail     = $u->getFirstAttribute('mail');
                            $depto    = $u->getFirstAttribute('department');
                            $cargo    = $u->getFirstAttribute('title');
                            $telefono = $u->getFirstAttribute('telephonenumber') ?: $u->getFirstAttribute('mobile');
                            $enabled  = !(((int)$u->getFirstAttribute('useraccountcontrol')) & 2);
                            $iniciales = collect(explode(' ', $nombre))->take(2)->map(fn($p) => strtoupper(substr($p,0,1)))->join('');
                            // Extraer OUs del distinguishedName: CN=...,OU=Desarrollo,OU=Soporte,OU=Verfrut,DC=...
                            // El DN viene de más específico a más general → revertir para mostrar raíz primero
                            $dn = $u->getFirstAttribute('distinguishedname') ?? '';
                            preg_match_all('/OU=([^,]+)/i', $dn, $ouMatches);
                            $ouSegmentos = array_reverse($ouMatches[1]);   // ['Verfrut','Soporte','Desarrollo']
                            $ouPath      = implode(' / ', $ouSegmentos);   // "Verfrut / Soporte / Desarrollo"
                        @endphp
                        <tr>
                            {{-- Avatar --}}
                            <td>
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white"
                                     style="width:32px;height:32px;font-size:.72rem;
                                            background:{{ $enabled ? '#2563eb' : '#94a3b8' }}">
                                    {{ $iniciales }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $nombre }}</div>
                            </td>
                            <td class="font-monospace text-muted" style="font-size:.8rem">{{ $sam }}</td>
                            <td>
                                @if($mail)
                                    <a href="mailto:{{ $mail }}" class="text-decoration-none text-reset">{{ $mail }}</a>
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
                            <td>
                                @if($ouSegmentos)
                                    <span class="text-muted"
                                          style="font-size:.78rem"
                                          title="{{ $ouPath }}">
                                        @foreach($ouSegmentos as $idx => $seg)
                                            @if($idx > 0)<span style="opacity:.4;margin:0 2px">/</span>@endif
                                            @if($idx === count($ouSegmentos) - 1)
                                                <strong class="text-dark">{{ $seg }}</strong>
                                            @else
                                                {{ $seg }}
                                            @endif
                                        @endforeach
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted" style="font-size:.82rem">{{ $telefono ?: '—' }}</td>
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
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('admin.active_directory.edit', $sam) }}"
                                       class="btn btn-outline-primary btn-sm" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.active_directory.toggle', $sam) }}" method="POST"
                                          data-confirm="{{ $nombre }}"
                                          data-confirm-verb="{{ $enabled ? 'deshabilitar' : 'habilitar' }}"
                                          data-confirm-title="{{ $enabled ? 'Deshabilitar cuenta' : 'Habilitar cuenta' }}"
                                          data-confirm-sub="El cambio se aplica inmediatamente en Active Directory."
                                          data-confirm-btn="{{ $enabled ? 'Sí, deshabilitar' : 'Sí, habilitar' }}"
                                          data-confirm-icon="{{ $enabled ? 'bi-person-x-fill' : 'bi-person-check-fill' }}"
                                          data-confirm-color="{{ $enabled ? 'warning' : 'success' }}">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm {{ $enabled ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                title="{{ $enabled ? 'Deshabilitar' : 'Habilitar' }}">
                                            <i class="bi {{ $enabled ? 'bi-person-x' : 'bi-person-check' }}"></i>
                                        </button>
                                    </form>
                                </div>
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
