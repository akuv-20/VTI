@extends('layouts.app')

@php
    use App\Services\RegistroMfa;

    $f     = $filtros['activos'];
    $orden = $f['orden'] ?? 'upn';
    $dir   = $f['dir'] ?? 'asc';

    $enlaceOrden = fn($campo) => route('admin.entra_id.mfa.index', array_filter(array_merge($f, [
        'orden' => $campo,
        'dir'   => ($orden === $campo && $dir === 'asc') ? 'desc' : 'asc',
    ]), fn($v) => $v !== null && $v !== ''));

    $flecha = fn($campo) => $orden === $campo
        ? '<i class="bi bi-caret-' . ($dir === 'desc' ? 'down' : 'up') . '-fill ms-1"></i>'
        : '';
@endphp

@section('content')
<style>
    /* mfal- : listado de MFA */
    .mfal-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:.9rem 1.1rem; margin-bottom:1rem; }
    .mfal-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.6rem; margin-bottom:1rem; }
    .mfal-tile {
        background:#fff; border:1px solid #e2e8f0; border-left-width:4px; border-radius:10px;
        padding:.7rem .9rem; text-decoration:none; display:block; color:inherit; transition:box-shadow .12s;
    }
    .mfal-tile:hover { box-shadow:0 3px 12px rgba(0,0,0,.08); color:inherit; }
    .mfal-tile.active { box-shadow:0 0 0 2px rgba(59,130,246,.35); }
    .mfal-tile-val { font-size:1.5rem; font-weight:800; line-height:1; font-variant-numeric:tabular-nums; }
    .mfal-tile-lbl { font-size:.72rem; color:#64748b; margin-top:.25rem; display:flex; align-items:center; gap:.3rem; }

    .mfal-badge {
        display:inline-flex; align-items:center; gap:.25rem;
        font-size:.72rem; font-weight:600; padding:1px 8px; border-radius:20px;
        border:1px solid transparent; white-space:nowrap;
    }
    .mfal-metodo {
        display:inline-block; font-size:.68rem; background:#f1f5f9; color:#475569;
        border:1px solid #e2e8f0; border-radius:4px; padding:0 5px; margin:1px 2px 1px 0;
    }
    .mfal-tabla th a { color:inherit; text-decoration:none; }
    .mfal-tabla th a:hover { color:#2563eb; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-shield-lock me-2" style="color:#7c3aed"></i>Autenticación multifactor</span>
            <span class="badge bg-light text-secondary border" style="font-size:.7rem">Entra ID</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.entra_id.mfa.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bar-chart-line me-1"></i>Resumen
            </a>
            <a href="{{ route('admin.entra_id.mfa.excel', $f) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Indicadores que filtran --}}
    <div class="mfal-tiles">
        <a href="{{ route('admin.entra_id.mfa.index') }}"
           class="mfal-tile {{ empty($f['estado']) ? 'active' : '' }}" style="border-left-color:#3b82f6">
            <div class="mfal-tile-val">{{ number_format($resumen['total']) }}</div>
            <div class="mfal-tile-lbl"><i class="bi bi-people"></i>Total</div>
        </a>
        <a href="{{ route('admin.entra_id.mfa.index', ['estado' => 'con']) }}"
           class="mfal-tile {{ ($f['estado'] ?? null) === 'con' ? 'active' : '' }}" style="border-left-color:#16a34a">
            <div class="mfal-tile-val" style="color:#16a34a">{{ number_format($resumen['con_mfa']) }}</div>
            <div class="mfal-tile-lbl"><i class="bi bi-shield-check"></i>MFA activo</div>
        </a>
        <a href="{{ route('admin.entra_id.mfa.index', ['estado' => 'sin']) }}"
           class="mfal-tile {{ ($f['estado'] ?? null) === 'sin' ? 'active' : '' }}" style="border-left-color:#dc2626">
            <div class="mfal-tile-val" style="color:#dc2626">{{ number_format($resumen['sin_mfa']) }}</div>
            <div class="mfal-tile-lbl"><i class="bi bi-shield-x"></i>MFA inactivo</div>
        </a>
        @if($resumen['admins'] > 0)
        <a href="{{ route('admin.entra_id.mfa.index', ['solo_admins' => 1, 'estado' => 'sin']) }}"
           class="mfal-tile {{ request()->boolean('solo_admins') ? 'active' : '' }}" style="border-left-color:#ea580c">
            <div class="mfal-tile-val" style="color:#ea580c">{{ number_format($resumen['admins_sin_mfa']) }}</div>
            <div class="mfal-tile-lbl"><i class="bi bi-person-fill-gear"></i>Admins sin MFA</div>
        </a>
        @endif
        @if($resumen['sin_dato'] > 0)
        <a href="{{ route('admin.entra_id.mfa.index', ['estado' => 'sin_dato']) }}"
           class="mfal-tile {{ ($f['estado'] ?? null) === 'sin_dato' ? 'active' : '' }}" style="border-left-color:#94a3b8">
            <div class="mfal-tile-val" style="color:#64748b">{{ number_format($resumen['sin_dato']) }}</div>
            <div class="mfal-tile-lbl"><i class="bi bi-question-circle"></i>Sin dato</div>
        </a>
        @endif
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.entra_id.mfa.index') }}" class="mfal-filtros">
        @if($f['estado'] ?? null)  <input type="hidden" name="estado" value="{{ $f['estado'] }}"> @endif
        <input type="hidden" name="orden" value="{{ $orden }}">
        <input type="hidden" name="dir" value="{{ $dir }}">

        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Buscar</label>
                <input type="text" name="q" value="{{ $f['q'] ?? '' }}" class="form-control form-control-sm"
                       placeholder="Correo, nombre o departamento…">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Departamento</label>
                <select name="departamento" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($filtros['departamentos'] as $d)
                        <option value="{{ $d }}" @selected(($f['departamento'] ?? null) === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Licencia</label>
                <select name="licencia" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="con" @selected(($f['licencia'] ?? null) === 'con')>Con licencia</option>
                    <option value="sin" @selected(($f['licencia'] ?? null) === 'sin')>Sin licencia</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-center pb-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="solo_admins" value="1"
                           id="soloAdmins" @checked(request()->boolean('solo_admins'))>
                    <label class="form-check-label" for="soloAdmins" style="font-size:.8rem">
                        Solo administradores
                    </label>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="{{ route('admin.entra_id.mfa.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted"><strong>{{ number_format($total) }}</strong> cuentas con estos filtros</small>
        <small class="text-muted"><i class="bi bi-clock-history me-1"></i>Actualizado {{ $generado->diffForHumans() }}</small>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table mfal-tabla">
            <thead>
                <tr>
                    <th><a href="{{ $enlaceOrden('upn') }}">Correo{!! $flecha('upn') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('nombre') }}">Nombre{!! $flecha('nombre') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('departamento') }}">Departamento{!! $flecha('departamento') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('mfa') }}">MFA{!! $flecha('mfa') !!}</a></th>
                    <th>Métodos configurados</th>
                    <th class="text-center">Licencia</th>
                    <th class="text-center">Admin</th>
                    <th><a href="{{ $enlaceOrden('actualizado') }}">Actualizado{!! $flecha('actualizado') !!}</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr>
                    <td class="font-monospace" style="font-size:.78rem">{{ $u['upn'] }}</td>
                    <td>{{ $u['nombre'] }}</td>
                    <td style="font-size:.8rem">{{ $u['departamento'] ?: '—' }}</td>
                    <td>
                        @if($u['mfa'] === null)
                            <span class="mfal-badge" style="background:#f1f5f9;color:#64748b;border-color:#cbd5e1"
                                  title="La cuenta no aparece en el reporte de registro">
                                <i class="bi bi-question-circle"></i>sin dato
                            </span>
                        @elseif($u['mfa'])
                            <span class="mfal-badge" style="background:#dcfce7;color:#16a34a;border-color:#86efac">
                                <i class="bi bi-shield-check"></i>activo
                            </span>
                        @else
                            <span class="mfal-badge" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5">
                                <i class="bi bi-shield-x"></i>inactivo
                            </span>
                        @endif
                    </td>
                    <td>
                        @forelse($u['metodos'] as $m)
                            <span class="mfal-metodo">{{ RegistroMfa::METODOS[$m] ?? $m }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td class="text-center">
                        @if($u['licenciado'])
                            <i class="bi bi-check-lg" style="color:#16a34a" title="Con licencia asignada"></i>
                        @else
                            <span class="text-muted">·</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($u['es_admin'])
                            <i class="bi bi-person-fill-gear" style="color:#ea580c" title="Cuenta con rol administrativo"></i>
                        @else
                            <span class="text-muted">·</span>
                        @endif
                    </td>
                    <td style="font-size:.8rem">{{ $u['actualizado']?->format('d/m/Y') ?: '—' }}</td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="8">
                        <i class="bi bi-inbox" style="font-size:1.4rem"></i>
                        <div class="mt-1">No hay cuentas con estos filtros.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center px-1">
        <small class="text-muted">{{ number_format($total) }} cuentas</small>
        {{ $usuarios->links() }}
    </div>

</div>
@endsection
