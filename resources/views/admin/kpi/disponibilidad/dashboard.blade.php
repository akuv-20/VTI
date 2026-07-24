@extends('layouts.app')

@php
    use App\Services\KpiDisponibilidad;
@endphp

@section('content')
<style>
    .kpid-hero {
        background: linear-gradient(135deg, #0f172a 0%, #14532d 100%);
        border-radius: 12px;
        padding: 1.5rem 1.75rem;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 2.25rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }
    .kpid-gauge { position: relative; width: 140px; height: 140px; flex-shrink: 0; }
    .kpid-gauge svg { transform: rotate(-90deg); }
    .kpid-gauge-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .kpid-gauge-val   { font-size: 1.7rem; font-weight: 800; line-height: 1; }
    .kpid-gauge-label { font-size: .6rem; text-transform: uppercase; letter-spacing: .09em; color: #94a3b8; margin-top: 4px; }

    .kpid-hero-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
    .kpid-stat-val   { font-size: 1.4rem; font-weight: 700; line-height: 1.1; }
    .kpid-stat-label { font-size: .68rem; color: #cbd5e1; text-transform: uppercase; letter-spacing: .06em; margin-top: 2px; }

    /* Escala de nivel 1-5 */
    .kpid-niveles { display: flex; gap: 6px; margin-top: 6px; }
    .kpid-nivel {
        width: 30px; height: 30px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: .9rem;
        background: rgba(255,255,255,.10); color: #94a3b8;
        border: 2px solid transparent;
    }

    .kpid-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.15rem; margin-bottom:1.25rem; }
    .kpid-card h6 { font-size:.82rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }

    /* Matriz */
    .kpid-matrix { width:100%; border-collapse:collapse; font-size:.8rem; }
    .kpid-matrix th, .kpid-matrix td { padding:.4rem .5rem; text-align:center; border-bottom:1px solid #f1f5f9; white-space:nowrap; }
    .kpid-matrix th { font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; font-weight:700; }
    .kpid-matrix td.svc { text-align:left; font-weight:600; color:#1e293b; }
    .kpid-matrix td.svc small { display:block; font-weight:400; color:#94a3b8; font-size:.68rem; }
    .kpid-matrix .grupo td { background:#f8fafc; font-weight:700; color:#475569; text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; }
    .kpid-cell { display:inline-block; min-width:52px; padding:2px 5px; border-radius:5px; font-weight:700; font-size:.74rem; color:#fff; }
    .kpid-cell.empty { background:transparent; color:#cbd5e1; font-weight:400; }
    .kpid-anual { font-weight:800; }
    .kpid-lvl-badge { display:inline-block; min-width:22px; padding:1px 7px; border-radius:5px; color:#fff; font-weight:800; font-size:.75rem; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-activity me-2" style="color:#16a34a"></i>KPI 1 · Disponibilidad de servicios críticos</h4>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="d-flex align-items-center gap-1">
                @php $anioMax = max((int) date('Y'), 2027); @endphp
                <input type="hidden" name="sector" value="{{ $sector }}">
                <select name="anio" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    @foreach(range($anioMax, 2026) as $y)
                        <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('admin.kpi.disponibilidad.informe', ['anio' => $anio, 'sector' => $sector]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-bar-graph me-1"></i>Informe
            </a>
            <a href="{{ route('admin.kpi.disponibilidad.excepciones') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-shield-check me-1"></i>Excepciones
            </a>
            <a href="{{ route('admin.kpi.disponibilidad.servicios') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-hdd-stack me-1"></i>Servicios críticos
            </a>
        </div>
    </div>

    {{-- ── Filtro por sector ──────────────────────────────────────────────── --}}
    @php
        $tabs = [
            ''            => ['Todos', 'bi-grid'],
            'planta'      => ['Plantas', 'bi-building-fill'],
            'campo'       => ['Campos', 'bi-tree-fill'],
            'sin_asignar' => ['Sin asignar', 'bi-question-circle'],
        ];
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($tabs as $val => [$lbl, $ico])
            @if($val === 'sin_asignar' && ($conteos[$val] ?? 0) === 0) @continue @endif
            <a href="{{ route('admin.kpi.disponibilidad.dashboard', ['anio' => $anio, 'sector' => $val]) }}"
               class="btn btn-sm {{ $sector === $val ? 'btn-success' : 'btn-outline-secondary' }}">
                <i class="bi {{ $ico }} me-1"></i>{{ $lbl }}
                <span class="badge {{ $sector === $val ? 'bg-light text-dark' : 'bg-secondary' }} ms-1">{{ $conteos[$val] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    {{-- ── Hero: resultado anual y nivel ──────────────────────────────────── --}}
    @php
        $color  = KpiDisponibilidad::colorPct($pctGlobal);
        $pctTxt = $pctGlobal === null ? '—' : number_format($pctGlobal, 2) . '%';
        $circ   = 2 * M_PI * 58;
        $frac   = $pctGlobal === null ? 0 : min(1, $pctGlobal / 100);
        $offset = $circ - ($frac * $circ);
    @endphp
    <div class="kpid-hero">
        <div class="kpid-gauge">
            <svg width="140" height="140">
                <circle cx="70" cy="70" r="58" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="12"/>
                <circle cx="70" cy="70" r="58" fill="none" stroke="{{ $color }}" stroke-width="12"
                        stroke-linecap="round" stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"/>
            </svg>
            <div class="kpid-gauge-center">
                <div class="kpid-gauge-val" style="color:{{ $color }}">{{ $pctTxt }}</div>
                <div class="kpid-gauge-label">Disponibilidad {{ $anio }}</div>
                <div class="kpid-gauge-label" style="color:#cbd5e1">{{ $tabs[$sector][0] ?? 'Todos' }}</div>
            </div>
        </div>

        <div>
            <div class="kpid-stat-label mb-1">Nivel KPI alcanzado</div>
            <div class="d-flex align-items-baseline gap-2">
                <div style="font-size:2.6rem;font-weight:800;line-height:1;color:{{ $nivelGlobal ? KpiDisponibilidad::colorNivel($nivelGlobal) : '#94a3b8' }}">
                    {{ $nivelGlobal ?? '—' }}
                </div>
                <div style="font-size:.8rem;color:#cbd5e1">/ 5</div>
            </div>
            <div style="font-size:.78rem;color:#e2e8f0;margin-top:2px">
                {{ $nivelGlobal ? KpiDisponibilidad::NIVELES[$nivelGlobal] : 'Sin datos aún' }}
            </div>
            <div class="kpid-niveles">
                @foreach([1,2,3,4,5] as $n)
                    <div class="kpid-nivel"
                         style="{{ $nivelGlobal === $n ? 'background:'.KpiDisponibilidad::colorNivel($n).';color:#fff;border-color:#fff' : '' }}">
                        {{ $n }}
                    </div>
                @endforeach
            </div>
        </div>

        <div class="kpid-hero-stats ms-auto">
            <div>
                <div class="kpid-stat-val">{{ number_format($meta, 2) }}%</div>
                <div class="kpid-stat-label">Meta (nivel 3)</div>
            </div>
            <div>
                <div class="kpid-stat-val">{{ $servicios->count() }}</div>
                <div class="kpid-stat-label">Servicios críticos</div>
            </div>
            <div>
                <div class="kpid-stat-val">{{ $peso }}%</div>
                <div class="kpid-stat-label">Peso evaluación</div>
            </div>
            @if($horasJustificadas > 0)
            <div>
                <div class="kpid-stat-val" style="color:#818cf8">{{ number_format($horasJustificadas, 1) }} h</div>
                <div class="kpid-stat-label">Justificadas</div>
            </div>
            @endif
        </div>
    </div>

    @if($servicios->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-hdd-stack" style="font-size:2.5rem;opacity:.35"></i>
            <p class="mt-2 mb-3">Aún no hay servicios críticos definidos.</p>
            <a href="{{ route('admin.kpi.disponibilidad.servicios') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Definir servicios críticos
            </a>
        </div>
    @else

    {{-- ── Evolución mensual (SVG nativo) ─────────────────────────────────── --}}
    @php
        // Escala Y adaptativa: desde el mínimo observado (con margen) hasta 100%.
        $pcts = $evolucion->pluck('pct')->filter()->all();
        $minY = count($pcts) ? min(min($pcts), $meta) : 98.0;
        $minY = floor(min($minY, 99.0) * 10) / 10 - 0.2;   // un poco de aire abajo
        $minY = max(0, $minY);
        $maxY = 100.0;
        $W = 720; $H = 220; $padL = 44; $padR = 16; $padT = 16; $padB = 26;
        $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
        $xFor = fn($m) => $padL + ($plotW * ($m - 1) / 11);
        $yFor = fn($v) => $padT + $plotH * (1 - (($v - $minY) / max(0.001, ($maxY - $minY))));
        $puntos = $evolucion->filter(fn($e) => $e['pct'] !== null)->values();
    @endphp
    <div class="kpid-card">
        <h6><i class="bi bi-graph-up me-1"></i>Evolución mensual {{ $anio }}</h6>
        <div style="overflow-x:auto">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;min-width:560px;height:auto">
            {{-- Grid horizontal + etiquetas Y --}}
            @foreach([$minY + ($maxY-$minY)*0.25, $minY + ($maxY-$minY)*0.5, $minY + ($maxY-$minY)*0.75, $maxY] as $gy)
                <line x1="{{ $padL }}" y1="{{ $yFor($gy) }}" x2="{{ $W-$padR }}" y2="{{ $yFor($gy) }}" stroke="#f1f5f9"/>
                <text x="{{ $padL-6 }}" y="{{ $yFor($gy)+3 }}" text-anchor="end" font-size="9" fill="#94a3b8">{{ number_format($gy,2) }}</text>
            @endforeach

            {{-- Línea de meta --}}
            <line x1="{{ $padL }}" y1="{{ $yFor($meta) }}" x2="{{ $W-$padR }}" y2="{{ $yFor($meta) }}"
                  stroke="#84cc16" stroke-width="1.5" stroke-dasharray="5 4"/>
            <text x="{{ $W-$padR }}" y="{{ $yFor($meta)-4 }}" text-anchor="end" font-size="9" fill="#65a30d" font-weight="700">meta {{ number_format($meta,2) }}%</text>

            {{-- Etiquetas de mes --}}
            @foreach(range(1,12) as $m)
                <text x="{{ $xFor($m) }}" y="{{ $H-8 }}" text-anchor="middle" font-size="9" fill="#94a3b8">{{ KpiDisponibilidad::MESES[$m] }}</text>
            @endforeach

            {{-- Serie --}}
            @if($puntos->count() > 1)
                <polyline fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linejoin="round"
                    points="@foreach($puntos as $p){{ $xFor($p['mes']) }},{{ $yFor($p['pct']) }} @endforeach"/>
            @endif
            @foreach($puntos as $p)
                <circle cx="{{ $xFor($p['mes']) }}" cy="{{ $yFor($p['pct']) }}" r="3.5"
                        fill="{{ KpiDisponibilidad::colorPct($p['pct']) }}" stroke="#fff" stroke-width="1.5">
                    <title>{{ KpiDisponibilidad::MESES[$p['mes']] }}: {{ number_format($p['pct'],2) }}%</title>
                </circle>
            @endforeach
        </svg>
        </div>
    </div>

    {{-- ── Matriz servicios × meses ───────────────────────────────────────── --}}
    <div class="kpid-card">
        <h6><i class="bi bi-grid-3x3 me-1"></i>Disponibilidad por servicio</h6>
        <div style="overflow-x:auto">
        <table class="kpid-matrix">
            <thead>
                <tr>
                    <th style="text-align:left">Servicio</th>
                    @foreach(range(1,12) as $m)<th>{{ KpiDisponibilidad::MESES[$m] }}</th>@endforeach
                    <th>Anual</th>
                    <th>Nivel</th>
                </tr>
            </thead>
            <tbody>
                @php $grupoActual = '__none__'; @endphp
                @foreach($filas as $f)
                    @php $g = $f['servicio']->grupo ?: 'Sin grupo'; @endphp
                    @if($g !== $grupoActual)
                        @php $grupoActual = $g; @endphp
                        <tr class="grupo"><td colspan="15">{{ $g }}</td></tr>
                    @endif
                    <tr>
                        <td class="svc">
                            {{ $f['servicio']->etiqueta }}
                            <small>{{ $f['servicio']->objeto }}</small>
                        </td>
                        @foreach(range(1,12) as $m)
                            @php $v = $f['meses'][$m] ?? null; @endphp
                            <td>
                                @if($v === null)
                                    <span class="kpid-cell empty">·</span>
                                @elseif($v['pct'] === null)
                                    <span class="kpid-cell" style="background:#94a3b8"
                                          title="Sin tiempo evaluable — periodo justificado{{ $v['exc'] > 0 ? ' ('.round($v['exc']/3600,1).' h)' : '' }}">N/A</span>
                                @else
                                    <span class="kpid-cell" style="background:{{ KpiDisponibilidad::colorPct($v['pct']) }}"
                                          @if($v['exc'] > 0) title="Incluye {{ round($v['exc']/3600,1) }} h justificadas (descontadas)" @endif>
                                        {{ number_format($v['pct'],2) }}@if($v['exc'] > 0)<sup style="font-size:.7em">⚑</sup>@endif
                                    </span>
                                @endif
                            </td>
                        @endforeach
                        <td class="kpid-anual" style="color:{{ KpiDisponibilidad::colorPct($f['anual']) }}">
                            {{ $f['anual'] === null ? '—' : number_format($f['anual'],2).'%' }}
                        </td>
                        <td>
                            @if($f['nivel'])
                                <span class="kpid-lvl-badge" style="background:{{ KpiDisponibilidad::colorNivel($f['nivel']) }}">{{ $f['nivel'] }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="text-muted mt-2" style="font-size:.72rem">
            Los valores son % de disponibilidad del mes, excluyendo mantenimientos programados. El nivel se calcula sobre el acumulado anual.
            <sup>⚑</sup> indica que el mes tiene caídas justificadas descontadas (ver <a href="{{ route('admin.kpi.disponibilidad.excepciones') }}">Excepciones</a>).
        </div>
    </div>

    @endif

    {{-- ── Capturar disponibilidad de un mes ──────────────────────────────── --}}
    <div class="kpid-card">
        <h6><i class="bi bi-cloud-download me-1"></i>Capturar disponibilidad mensual</h6>
        <form method="POST" action="{{ route('admin.kpi.disponibilidad.capturar') }}" class="d-flex flex-wrap gap-2 align-items-end">
            @csrf
            <div>
                <label class="form-label" style="font-size:.75rem">Año</label>
                <input type="number" name="anio" class="form-control form-control-sm" style="width:100px" value="{{ $anio }}" min="2020" max="2100">
            </div>
            <div>
                <label class="form-label" style="font-size:.75rem">Mes</label>
                <select name="mes" class="form-select form-select-sm" style="width:auto">
                    @foreach(KpiDisponibilidad::MESES as $num => $nom)
                        <option value="{{ $num }}" {{ (int) date('n') === $num ? 'selected' : '' }}>{{ $nom }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-cloud-download me-1"></i>Capturar desde CheckMK
            </button>
        </form>
        <div class="text-muted mt-2" style="font-size:.72rem">
            Consulta CheckMK y congela el % del mes para cada servicio crítico activo. Puedes ejecutarlo al cierre de cada mes; re-ejecutar sobreescribe el snapshot.
        </div>
    </div>

</div>
@endsection
