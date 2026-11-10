@extends('layouts.app')

@section('content')
<style>
    .rk-filtros .btn-check + .btn { font-size:.8rem; }
    .rk-kpi .valor { font-size:1.6rem; line-height:1; font-weight:700; }
    .rk-kpi .label { font-size:.75rem; color:#64748b; margin-top:2px; }

    /* Podio */
    .rk-podio { display:flex; justify-content:center; align-items:flex-end; gap:1rem; flex-wrap:wrap; }
    .rk-plaza { width:220px; text-align:center; }
    .rk-plaza .rk-avatar { width:64px; height:64px; font-size:1.3rem; margin:0 auto .5rem; }
    .rk-plaza.p1 .rk-avatar { width:84px; height:84px; font-size:1.7rem; box-shadow:0 0 0 4px #fbbf24; }
    .rk-plaza .nombre { font-weight:600; font-size:.95rem; }
    .rk-plaza .cifra { font-size:1.9rem; font-weight:800; line-height:1.1; }
    .rk-plaza .cifra small { font-size:.75rem; font-weight:500; color:#64748b; }
    .rk-escalon { border-radius:.6rem .6rem 0 0; color:#fff; font-weight:800; font-size:1.6rem;
                  display:flex; align-items:center; justify-content:center; margin-top:.6rem; }
    .rk-plaza.p1 .rk-escalon { height:110px; background:linear-gradient(180deg,#fbbf24,#d97706); }
    .rk-plaza.p2 .rk-escalon { height:80px;  background:linear-gradient(180deg,#cbd5e1,#64748b); }
    .rk-plaza.p3 .rk-escalon { height:60px;  background:linear-gradient(180deg,#fdba74,#b45309); }
    .rk-corona { font-size:2rem; line-height:1; display:block; margin-bottom:.2rem; }
    .rk-goat { background:#fef3c7; color:#92400e; font-weight:700; font-size:.7rem; letter-spacing:.05em;
               border-radius:999px; padding:.15rem .6rem; display:inline-block; margin-top:.25rem; }

    /* Tabla */
    .rk-avatar { border-radius:50%; color:#fff; font-weight:700; display:flex; align-items:center;
                 justify-content:center; flex-shrink:0; width:36px; height:36px; font-size:.8rem; }
    .rk-tabla td, .rk-tabla th { vertical-align:middle; font-size:.85rem; }
    .rk-tabla th { font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; white-space:nowrap; }
    .rk-puesto { font-weight:800; font-size:1rem; width:2.2rem; text-align:center; }
    .rk-barra { height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden; min-width:80px; }
    .rk-barra > span { display:block; height:100%; background:#2563eb; border-radius:3px; }
    .rk-mov { font-size:.72rem; font-weight:700; white-space:nowrap; }
    .rk-insignia { font-size:.68rem; border-radius:999px; padding:.1rem .45rem; white-space:nowrap; }
    .rk-fila-top { background:#fffbeb; }
</style>

@php
    // Duración legible: «3 d 4 h», «5 h 20 min», «35 min».
    $duracion = function (?int $seg) {
        if ($seg === null) return '—';
        $d = intdiv($seg, 86400); $h = intdiv($seg % 86400, 3600); $m = intdiv($seg % 3600, 60);
        if ($d > 0) return $d . ' d' . ($h ? " {$h} h" : '');
        if ($h > 0) return $h . ' h' . ($m ? " {$m} min" : '');
        return max($m, 1) . ' min';
    };

    // Color estable por persona, para que el avatar no cambie entre cargas.
    $color = fn(string $clave) => 'hsl(' . (crc32($clave) % 360) . ', 55%, 45%)';

    $insignias = [
        'goat'         => ['GOAT',           'bi-trophy-fill',         '#fef3c7', '#92400e', 'Más tickets resueltos del periodo'],
        'rapido'       => ['Más rápido',     'bi-lightning-charge-fill','#dbeafe', '#1d4ed8', 'Menor tiempo medio de resolución (mín. 5 tickets)'],
        'sla'          => ['Mejor SLA',      'bi-bullseye',            '#dcfce7', '#15803d', 'Mayor % de tickets dentro del SLA (mín. 5 con SLA)'],
        'satisfaccion' => ['Mejor evaluado', 'bi-star-fill',           '#fce7f3', '#be185d', 'Mejor nota de satisfacción (mín. 5 encuestas)'],
    ];

    $lider     = $tecnicos->max('total') ?: 1;
    $haySla    = $tecnicos->sum('con_sla') > 0;
    $haySatisf = $tecnicos->sum('satisfaccion_n') > 0;
    $podio     = $tecnicos->take(3)->values();
    $nombresPais = ['chile' => 'Chile', 'peru' => 'Perú'];
    $filtrosUrl = ['periodo' => $periodo, 'grupo' => $grupo, 'pais' => $pais,
                   'desde' => request('desde'), 'hasta' => request('hasta')];
@endphp

<div class="container-fluid vti-page">

    <div class="vti-page-header mb-3 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h4 class="mb-0"><i class="bi bi-trophy-fill me-2" style="color:#d97706"></i>Ranking de técnicos</h4>
            <small class="text-muted">
                Tickets solucionados y cerrados por técnico asignado en el GLPI de Unifrutti &nbsp;·&nbsp; {{ $etiquetaPeriodo }}
                @if($pais) &nbsp;·&nbsp; Técnicos de {{ $nombresPais[$pais] }} @endif
                @if($grupo) &nbsp;·&nbsp; Grupo {{ $grupo }} @endif
            </small>
        </div>
        <form method="POST" action="{{ route('asistencia.ranking.actualizar', $filtrosUrl) }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</button>
        </form>
    </div>

    {{-- ── Filtros ── --}}
    <form method="GET" action="{{ route('asistencia.ranking') }}" id="rk-form"
          class="card border-0 shadow-sm rounded-3 mb-3 rk-filtros">
        <div class="card-body py-2 d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label small text-muted mb-1" for="rk-periodo">Periodo</label>
                <select name="periodo" id="rk-periodo" class="form-select form-select-sm">
                    @foreach($periodos as $clave => $label)
                        <option value="{{ $clave }}" @selected($periodo === $clave)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="rk-rango" class="d-flex gap-2 align-items-end {{ $periodo === 'rango' ? '' : 'd-none' }}">
                <div>
                    <label class="form-label small text-muted mb-1" for="rk-desde">Desde</label>
                    <input type="date" name="desde" id="rk-desde" class="form-control form-control-sm"
                           value="{{ $periodo === 'rango' ? $desde?->format('Y-m-d') : '' }}">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1" for="rk-hasta">Hasta</label>
                    <input type="date" name="hasta" id="rk-hasta" class="form-control form-control-sm"
                           value="{{ $periodo === 'rango' ? $hasta?->format('Y-m-d') : '' }}">
                </div>
                <button class="btn btn-primary btn-sm">Aplicar</button>
            </div>
            <div>
                <label class="form-label small text-muted mb-1" for="rk-pais" title="Según la ubicación del técnico en GLPI">País del técnico</label>
                <select name="pais" id="rk-pais" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos los países</option>
                    @foreach($nombresPais as $clave => $label)
                        <option value="{{ $clave }}" @selected($pais === $clave)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small text-muted mb-1" for="rk-grupo">Grupo técnico</label>
                <select name="grupo" id="rk-grupo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos los grupos</option>
                    @foreach($grupos as $g)
                        <option value="{{ $g }}" @selected($grupo === $g)>{{ $g }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    @if($error)
        <div class="alert alert-warning py-2 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No se pudo consultar el GLPI de Unifrutti; intenta de nuevo en un minuto.
            <span class="text-muted">({{ \Illuminate\Support\Str::limit($error, 160) }})</span>
        </div>
    @endif

    {{-- ── Indicadores ── --}}
    @php
    $promedio = $resumen['tecnicos'] > 0 ? round($resumen['total'] / $resumen['tecnicos'], 1) : 0;
    $kpis = [
        ['Tickets resueltos',      number_format($resumen['total'], 0, ',', '.'), 'bi-check2-all',     '#dbeafe', '#2563eb',
         number_format($resumen['solucionados'], 0, ',', '.') . ' solucionados · ' . number_format($resumen['cerrados'], 0, ',', '.') . ' cerrados'],
        ['Técnicos con tickets',   $resumen['tecnicos'],                          'bi-people-fill',    '#ede9fe', '#7c3aed', null],
        ['Promedio por técnico',   number_format($promedio, 1, ',', '.'),         'bi-bar-chart-fill', '#dcfce7', '#15803d', null],
        ['Tiempo medio resolución', $duracion($resumen['prom_resolucion']),       'bi-stopwatch-fill', '#fef3c7', '#d97706', 'Desde la apertura hasta la solución'],
        ['Sin técnico asignado',   number_format($resumen['sin_tecnico'], 0, ',', '.'), 'bi-person-x-fill', '#fee2e2', '#dc2626', 'Resueltos sin técnico: no cuentan para nadie'],
    ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($kpis as [$label, $valor, $icono, $bg, $ic, $nota])
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-3 h-100 rk-kpi" style="border-left:4px solid {{ $ic }} !important" @if($nota) title="{{ $nota }}" @endif>
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:{{ $bg }}">
                        <i class="bi {{ $icono }}" style="font-size:1.4rem;color:{{ $ic }}"></i>
                    </div>
                    <div>
                        <div class="valor" style="color:{{ $ic }}">{{ $valor }}</div>
                        <div class="label">{{ $label }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($tecnicos->isEmpty())
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox" style="font-size:2.5rem"></i>
                <div class="mt-2">No hay tickets solucionados ni cerrados en este periodo.</div>
            </div>
        </div>
    @else

    {{-- ── Podio ── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body pb-0 pt-4">
            <div class="rk-podio">
                {{-- Orden visual 2º · 1º · 3º; si hay menos de tres, se muestran los que haya. --}}
                @foreach([1, 0, 2] as $i)
                    @continue(!isset($podio[$i]))
                    @php $t = $podio[$i]; $p = $i + 1; @endphp
                    <div class="rk-plaza p{{ $p }}">
                        @if($p === 1)<span class="rk-corona">👑</span>@endif
                        <div class="rk-avatar" style="background:{{ $color($t['clave']) }}">{{ $t['iniciales'] }}</div>
                        <div class="nombre" title="{{ $t['nombre_completo'] }}">{{ $t['nombre'] }}</div>
                        @if(in_array('goat', $t['insignias']))<span class="rk-goat">GOAT</span>@endif
                        <div class="cifra mt-1">{{ number_format($t['total'], 0, ',', '.') }} <small>tickets</small></div>
                        <div class="rk-escalon">{{ $t['puesto'] }}º</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Tabla completa ── --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white fw-semibold border-bottom py-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ol me-1 text-primary"></i> Ranking completo</span>
            @if($prevDesde)
                <small class="text-muted fw-normal">
                    Movimiento vs. {{ $periodo === 'rango' ? $prevDesde->format('d/m/Y') . ' – ' . $prevHasta->format('d/m/Y')
                        : (in_array($periodo, ['mes', 'mes_anterior']) ? ucfirst($prevDesde->copy()->locale('es')->isoFormat('MMMM YYYY')) : $prevDesde->year) }}
                </small>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 rk-tabla">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">#</th>
                        <th>Técnico</th>
                        <th style="min-width:180px">Resueltos</th>
                        <th class="text-end">Solucionados</th>
                        <th class="text-end">Cerrados</th>
                        <th class="text-end" title="Tiempo medio desde la apertura hasta la solución">T. medio</th>
                        @if($haySla)<th class="text-end" title="Tickets con SLA resueltos dentro del plazo">SLA</th>@endif
                        @if($haySatisf)<th class="text-end" title="Promedio de la encuesta de satisfacción (0 a 5)">Satisf.</th>@endif
                        <th>Insignias</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($tecnicos as $t)
                    <tr class="{{ $t['puesto'] === 1 ? 'rk-fila-top' : '' }}">
                        <td class="text-center">
                            <div class="rk-puesto">{{ $t['puesto'] }}</div>
                            @if($t['movimiento'] === 'nuevo')
                                <span class="rk-mov text-primary" title="Sin tickets en el periodo anterior">nuevo</span>
                            @elseif(is_int($t['movimiento']) && $t['movimiento'] > 0)
                                <span class="rk-mov text-success" title="Era {{ $t['puesto_anterior'] }}º"><i class="bi bi-caret-up-fill"></i>{{ $t['movimiento'] }}</span>
                            @elseif(is_int($t['movimiento']) && $t['movimiento'] < 0)
                                <span class="rk-mov text-danger" title="Era {{ $t['puesto_anterior'] }}º"><i class="bi bi-caret-down-fill"></i>{{ abs($t['movimiento']) }}</span>
                            @elseif($t['movimiento'] === 0)
                                <span class="rk-mov text-muted" title="Mismo puesto">=</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rk-avatar" style="background:{{ $color($t['clave']) }}">{{ $t['iniciales'] }}</div>
                                <div class="lh-sm">
                                    <div class="fw-semibold" title="{{ $t['nombre_completo'] }}">
                                        {{ $t['nombre'] }}
                                        @unless($t['activo'])
                                            <span class="badge text-bg-secondary ms-1" style="font-size:.6rem" title="Usuario inactivo o eliminado en GLPI">inactivo</span>
                                        @endunless
                                    </div>
                                    <small class="text-muted">{{ $t['login'] }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold" style="min-width:3rem">{{ number_format($t['total'], 0, ',', '.') }}</span>
                                <div class="rk-barra flex-grow-1"><span style="width:{{ round($t['total'] * 100 / $lider) }}%"></span></div>
                            </div>
                        </td>
                        <td class="text-end">{{ number_format($t['solucionados'], 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($t['cerrados'], 0, ',', '.') }}</td>
                        <td class="text-end text-nowrap">{{ $duracion($t['prom_resolucion']) }}</td>
                        @if($haySla)
                            <td class="text-end" title="{{ $t['sla_ok'] }} de {{ $t['con_sla'] }} con SLA">
                                {{ $t['pct_sla'] !== null ? number_format($t['pct_sla'], 1, ',', '.') . '%' : '—' }}
                            </td>
                        @endif
                        @if($haySatisf)
                            <td class="text-end" title="{{ $t['satisfaccion_n'] }} encuestas respondidas">
                                {{ $t['satisfaccion'] !== null ? number_format($t['satisfaccion'], 2, ',', '.') : '—' }}
                            </td>
                        @endif
                        <td>
                            @foreach($t['insignias'] as $ins)
                                @php [$txt, $ico, $bg, $fg, $desc] = $insignias[$ins]; @endphp
                                <span class="rk-insignia" style="background:{{ $bg }};color:{{ $fg }}" title="{{ $desc }}"><i class="bi {{ $ico }} me-1"></i>{{ $txt }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Cada ticket cuenta para el técnico asignado en GLPI y se ubica en el periodo por su fecha de solución.
            Un ticket con varios técnicos suma uno a cada uno, por eso la columna puede superar el total de tickets.
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    // Al elegir «Rango de fechas» se muestran las fechas y se espera «Aplicar»;
    // con cualquier otro periodo el filtro se aplica de inmediato.
    document.getElementById('rk-periodo').addEventListener('change', function () {
        const rango = document.getElementById('rk-rango');
        if (this.value === 'rango') {
            rango.classList.remove('d-none');
        } else {
            rango.classList.add('d-none');
            this.form.submit();
        }
    });
</script>
@endpush
@endsection
