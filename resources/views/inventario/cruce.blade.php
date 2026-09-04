@extends('layouts.app')

@php
    // Catálogo de estados: clave => [etiqueta, color, icono]
    $ESTADOS = [
        'posible_baja'  => ['Posible baja',        '#ef4444', 'bi-trash3'],
        'huerfano_glpi' => ['En GLPI sin AD',      '#a855f7', 'bi-question-octagon'],
        'falta_agente'  => ['Falta agente',        '#eab308', 'bi-cloud-arrow-down'],
        'agente_mudo'   => ['Agente mudo',         '#f97316', 'bi-volume-mute'],
        'deshabilitado' => ['Deshabilitado en AD', '#64748b', 'bi-slash-circle'],
        'ok'            => ['Inventariado al día', '#22c55e', 'bi-check-circle'],
    ];
@endphp

@section('content')
<style>
    .iu-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.6rem; margin-bottom:1rem; }
    .iu-tile {
        background:#fff; border:1px solid #e2e8f0; border-left-width:4px; border-radius:10px;
        padding:.7rem .9rem; cursor:pointer; transition:box-shadow .12s, transform .08s;
        text-align:left;
    }
    .iu-tile:hover  { box-shadow:0 3px 12px rgba(0,0,0,.08); }
    .iu-tile.active { box-shadow:0 0 0 2px rgba(59,130,246,.35); }
    .iu-tile-val    { font-size:1.5rem; font-weight:800; line-height:1; }
    .iu-tile-lbl    { font-size:.72rem; color:#64748b; margin-top:.25rem; display:flex; align-items:center; gap:.3rem; }

    .iu-meta { display:flex; gap:1.2rem; flex-wrap:wrap; font-size:.78rem; color:#64748b; margin-bottom:.75rem; }
    .iu-meta strong { color:#1e293b; }

    .iu-badge {
        display:inline-flex; align-items:center; gap:.3rem;
        font-size:.72rem; font-weight:600; padding:2px 8px; border-radius:20px;
        border:1px solid transparent; white-space:nowrap;
    }

    .iu-dias-alerta { color:#dc2626; font-weight:600; }
    .iu-dias-ok     { color:#64748b; }

    #tablaCruce tbody tr { cursor:default; }
    .iu-search { max-width:320px; }

    .iu-ajustes {
        background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
        padding:.9rem 1.1rem; margin-bottom:1rem;
    }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-diagram-3 me-2" style="color:{{ $dom->color() }}"></i>Cruce AD ↔ GLPI</span>
            @include('inventario._dominio', ['seccion' => 'cruce'])
        </h4>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" type="button"
                    data-bs-toggle="collapse" data-bs-target="#paneAjustes">
                <i class="bi bi-sliders me-1"></i>Umbrales
            </button>
            <form method="POST" action="{{ route("inventario.{$dom->clave}.cruce.refrescar") }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </form>
        </div>
    </div>

    {{-- Ajustes de umbrales --}}
    <div class="collapse" id="paneAjustes">
        <div class="iu-ajustes">
            <form method="POST" action="{{ route("inventario.{$dom->clave}.cruce.ajustes") }}"
                  class="row g-3 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label fw-semibold" style="font-size:.8rem">
                        Días para <span style="color:#ef4444">posible baja</span> (sin logon en AD)
                    </label>
                    <input type="number" name="cruce_dias_baja" class="form-control form-control-sm"
                           style="max-width:120px" value="{{ $diasBaja }}" min="1" max="3650">
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold" style="font-size:.8rem">
                        Días para <span style="color:#f97316">agente mudo</span> (sin reporte en GLPI)
                    </label>
                    <input type="number" name="cruce_dias_agente" class="form-control form-control-sm"
                           style="max-width:120px" value="{{ $diasAgente }}" min="1" max="3650">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    @if(isset($error))
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>No se pudo generar el cruce:</strong> {{ $error }}<br>
                <a href="{{ route('admin.configuracion.index') }}#pane-glpi" class="alert-link">Ir a Configuración</a>
            </div>
        </div>
    @else

    {{-- Meta --}}
    <div class="iu-meta">
        <span><i class="bi bi-diagram-3 me-1"></i>Equipos en AD: <strong>{{ number_format($resumen['total']) }}</strong></span>
        <span><i class="bi bi-pc-display me-1"></i>En GLPI: <strong>{{ number_format($resumen['en_glpi']) }}</strong></span>
        <span><i class="bi bi-pc-display-horizontal me-1"></i>Total en GLPI: <strong>{{ number_format($resumen['total_glpi']) }}</strong></span>
        @if($generado)
            <span class="ms-auto"><i class="bi bi-clock-history me-1"></i>Actualizado {{ $generado->diffForHumans() }} · caché 5 min</span>
        @endif
    </div>

    {{-- Tiles / filtros --}}
    <div class="iu-tiles">
        <button class="iu-tile active" data-filtro="todos" style="border-left-color:#3b82f6">
            <div class="iu-tile-val">{{ number_format($resumen['filas']) }}</div>
            <div class="iu-tile-lbl"><i class="bi bi-list-ul"></i>Todos</div>
        </button>
        @foreach($ESTADOS as $clave => [$lbl, $color, $ico])
        <button class="iu-tile" data-filtro="{{ $clave }}" style="border-left-color:{{ $color }}">
            <div class="iu-tile-val" style="color:{{ $color }}">{{ number_format($resumen[$clave]) }}</div>
            <div class="iu-tile-lbl"><i class="bi {{ $ico }}"></i>{{ $lbl }}</div>
        </button>
        @endforeach
    </div>

    {{-- Buscador --}}
    <div class="mb-2">
        <div class="input-group input-group-sm iu-search">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="buscarEquipo" class="form-control" placeholder="Buscar equipo, OU, SO…" autocomplete="off">
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.83rem" id="tablaCruce">
                <thead class="table-light">
                    <tr>
                        <th>Equipo</th>
                        <th>OU</th>
                        <th>Sistema operativo</th>
                        <th>Último logon (AD)</th>
                        <th class="text-center">GLPI</th>
                        <th>Último reporte agente</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($equipos as $eq)
                    @php [$lbl, $color, $ico] = $ESTADOS[$eq['estado']]; @endphp
                    <tr data-estado="{{ $eq['estado'] }}">
                        <td class="fw-semibold font-monospace" style="font-size:.82rem">
                            @if($eq['glpi_id'])
                                <a href="{{ route("inventario.{$dom->clave}.equipos.show", $eq['glpi_id']) }}"
                                   class="text-decoration-none" title="Abrir la ficha en el inventario">
                                    {{ $eq['nombre'] }}
                                </a>
                            @else
                                {{ $eq['nombre'] }}
                            @endif
                        </td>
                        <td class="text-muted" style="font-size:.78rem">{{ $eq['ou'] ?: '—' }}</td>
                        <td class="text-muted" style="font-size:.78rem">{{ $eq['so'] ?: '—' }}</td>
                        <td>
                            @if($eq['estado'] === 'huerfano_glpi')
                                {{-- No tiene cuenta en el AD: no hay logon que mostrar --}}
                                <span class="text-muted fst-italic" style="font-size:.78rem">no está en el AD</span>
                            @elseif($eq['ultimo_login'])
                                {{ $eq['ultimo_login']->format('d/m/Y') }}
                                <span class="{{ $eq['dias_sin_login'] > $diasBaja ? 'iu-dias-alerta' : 'iu-dias-ok' }}" style="font-size:.75rem">
                                    · hace {{ number_format($eq['dias_sin_login']) }} d
                                </span>
                            @else
                                <span class="iu-dias-alerta" style="font-size:.78rem"><i class="bi bi-dash-circle me-1"></i>sin registro</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($eq['en_glpi'])
                                <span class="iu-badge" style="background:#dcfce7;color:#16a34a;border-color:#86efac">
                                    <i class="bi bi-check-lg"></i>Sí
                                </span>
                            @else
                                <span class="iu-badge" style="background:#fef3c7;color:#b45309;border-color:#fde68a">
                                    <i class="bi bi-x-lg"></i>No
                                </span>
                            @endif
                        </td>
                        <td>
                            @if(!$eq['en_glpi'])
                                <span class="text-muted">—</span>
                            @elseif($eq['last_contact'])
                                {{ $eq['last_contact']->format('d/m/Y') }}
                                <span class="{{ $eq['dias_sin_reporte'] > $diasAgente ? 'iu-dias-alerta' : 'iu-dias-ok' }}" style="font-size:.75rem">
                                    · hace {{ number_format($eq['dias_sin_reporte']) }} d
                                </span>
                            @else
                                <span class="iu-dias-alerta" style="font-size:.78rem">sin reporte</span>
                            @endif
                        </td>
                        <td>
                            <span class="iu-badge" style="background:{{ $color }}1a;color:{{ $color }};border-color:{{ $color }}66">
                                <i class="bi {{ $ico }}"></i>{{ $lbl }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-muted mt-2" id="sinResultados" style="display:none;font-size:.85rem">
        <i class="bi bi-search me-1"></i>Sin equipos que coincidan con el filtro.
    </div>

    <script>
    (function () {
        const filas   = Array.prototype.slice.call(document.querySelectorAll('#tablaCruce tbody tr'));
        const tiles   = Array.prototype.slice.call(document.querySelectorAll('.iu-tile'));
        const input   = document.getElementById('buscarEquipo');
        const vacio    = document.getElementById('sinResultados');
        let filtro    = 'todos';

        function aplicar() {
            const q = input.value.trim().toLowerCase();
            let visibles = 0;
            filas.forEach(function (tr) {
                const okEstado = filtro === 'todos' || tr.dataset.estado === filtro;
                const okTexto  = !q || tr.textContent.toLowerCase().includes(q);
                const mostrar  = okEstado && okTexto;
                tr.style.display = mostrar ? '' : 'none';
                if (mostrar) visibles++;
            });
            vacio.style.display = visibles === 0 ? 'block' : 'none';
        }

        tiles.forEach(function (t) {
            t.addEventListener('click', function () {
                tiles.forEach(x => x.classList.remove('active'));
                t.classList.add('active');
                filtro = t.dataset.filtro;
                aplicar();
            });
        });

        input.addEventListener('input', aplicar);
    })();
    </script>

    @endif
</div>
@endsection
