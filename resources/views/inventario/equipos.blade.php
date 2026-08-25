@extends('layouts.app')

@section('content')
<style>
    .iue-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.6rem; margin-bottom:1rem; }
    .iue-tile {
        background:#fff; border:1px solid #e2e8f0; border-left-width:4px; border-radius:10px;
        padding:.7rem .9rem; text-decoration:none; display:block; color:inherit;
        transition:box-shadow .12s, transform .08s;
    }
    .iue-tile:hover  { box-shadow:0 3px 12px rgba(0,0,0,.08); color:inherit; }
    .iue-tile.active { box-shadow:0 0 0 2px rgba(59,130,246,.35); }
    .iue-tile-val    { font-size:1.5rem; font-weight:800; line-height:1; }
    .iue-tile-lbl    { font-size:.72rem; color:#64748b; margin-top:.25rem; display:flex; align-items:center; gap:.3rem; }

    .iue-badge {
        display:inline-flex; align-items:center; gap:.25rem;
        font-size:.72rem; font-weight:600; padding:1px 7px; border-radius:20px;
        border:1px solid transparent; white-space:nowrap;
    }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-display-fill me-2" style="color:{{ $dom->color() }}"></i>Equipos</span>
            @include('inventario._dominio', ['seccion' => 'equipos'])
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="{{ route("inventario.{$dom->clave}.equipos") }}" class="vti-search">
                @if($filtro !== 'todos')
                    <input type="hidden" name="filtro" value="{{ $filtro }}">
                @endif
                <input type="text" name="q" value="{{ $search }}"
                       class="form-control" placeholder="Buscar equipo, serial o usuario…" style="width:250px">
                <button class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i>
                </button>
                @if($search)
                    <a href="{{ route("inventario.{$dom->clave}.equipos", ['filtro' => $filtro]) }}"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </form>
            <a href="{{ route("inventario.{$dom->clave}.cruce") }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-diagram-3 me-1"></i>Cruce AD
            </a>
        </div>
    </div>

    @if(isset($error))
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>No se pudo leer el inventario:</strong> {{ $error }}<br>
                <a href="{{ route('admin.configuracion.index') }}#pane-glpi" class="alert-link">Ir a Configuración</a>
            </div>
        </div>
    @else

    {{-- Indicadores: muestran el conteo y al pulsarlos filtran la tabla --}}
    <div class="iue-tiles">
        @foreach($filtros as $clave => [$lbl, $ico, $color])
        <a href="{{ route("inventario.{$dom->clave}.equipos", array_filter(['filtro' => $clave, 'q' => $search])) }}"
           class="iue-tile {{ $filtro === $clave ? 'active' : '' }}"
           style="border-left-color:{{ $color }}">
            <div class="iue-tile-val" style="color:{{ $clave === 'todos' ? '#1e293b' : $color }}">
                {{ number_format($conteos[$clave] ?? 0) }}
            </div>
            <div class="iue-tile-lbl"><i class="bi {{ $ico }}"></i>{{ $lbl }}</div>
        </a>
        @endforeach
    </div>

    @if($search)
        <p class="text-muted mb-2" style="font-size:.8rem">
            <i class="bi bi-funnel me-1"></i>Los indicadores reflejan la búsqueda «{{ $search }}».
        </p>
    @endif

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario Asignado</th>
                    <th>Marca / Modelo</th>
                    <th>N° Serie</th>
                    <th>Sistema Operativo</th>
                    <th>Ubicación</th>
                    <th>{{ $dom->antivirus() ?: "Antivirus" }}</th>
                    <th>Último reporte</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($computadores as $eq)
                @php
                    $ultimo = $eq->last_contact ? \Carbon\Carbon::parse($eq->last_contact) : null;
                    $dias   = $ultimo ? (int) floor($ultimo->diffInDays(now())) : null;
                    $mudo   = $dias === null || $dias > $diasAgente;
                    $avOk   = $eq->av_version !== null;
                    $avOn   = $avOk && (int) $eq->av_activo === 1;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $eq->nombre_equipo }}</td>
                    <td>{{ trim($eq->nombre_usuario ?? '') ?: '—' }}</td>
                    <td>
                        @if($eq->marca || $eq->modelo)
                            {{ $eq->marca }} {{ $eq->modelo }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="font-monospace" style="font-size:.78rem">{{ $eq->numero_serie ?: '—' }}</td>
                    <td style="font-size:.8rem">{{ $eq->sistema_operativo ?: '—' }}</td>
                    <td style="font-size:.8rem">{{ $eq->ubicacion ?: '—' }}</td>
                    <td>
                        @if($avOn)
                            <span class="iue-badge" style="background:#dcfce7;color:#16a34a;border-color:#86efac"
                                  title="{{ $dom->antivirus() }} {{ $eq->av_version }}">
                                <i class="bi bi-shield-check"></i>{{ $eq->av_version }}
                            </span>
                        @elseif($avOk)
                            <span class="iue-badge" style="background:#fef3c7;color:#b45309;border-color:#fde68a"
                                  title="Instalado pero desactivado">
                                <i class="bi bi-shield-slash"></i>inactivo
                            </span>
                        @else
                            <span class="iue-badge" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5">
                                <i class="bi bi-shield-exclamation"></i>sin {{ $dom->antivirus() }}
                            </span>
                        @endif
                    </td>
                    <td style="font-size:.8rem">
                        @if($ultimo)
                            {{ $ultimo->format('d/m/Y') }}
                            <span class="{{ $mudo ? 'text-danger fw-semibold' : 'text-muted' }}" style="font-size:.75rem">
                                · {{ number_format($dias) }} d
                            </span>
                        @else
                            <span class="text-muted fst-italic">sin agente</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="vti-actions justify-content-end">
                            <a href="{{ route("inventario.{$dom->clave}.equipos.show", $eq->id) }}"
                               class="vti-btn-view" title="Ver ficha">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="9">
                        <i class="bi bi-inbox" style="font-size:1.4rem"></i>
                        <div class="mt-1">No se encontraron equipos con este filtro.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center px-1">
        <small class="text-muted">{{ number_format($computadores->total()) }} equipos encontrados</small>
        {{ $computadores->links() }}
    </div>

    @endif

</div>
@endsection
