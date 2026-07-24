@php
    use App\Services\KpiDisponibilidad;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KPI Disponibilidad {{ $anio }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; background: #e8ecf0; }

        .toolbar { display:flex; gap:.6rem; padding:.6rem 1.2rem; background:#fff; border-bottom:1px solid #dee2e6; position:sticky; top:0; z-index:10; }
        .toolbar a, .toolbar button { display:inline-flex; align-items:center; gap:5px; padding:5px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; border:1px solid #ced4da; background:#fff; color:#334155; }
        .toolbar button.print { background:#16a34a; color:#fff; border-color:#16a34a; }

        .sheet { max-width:900px; margin:1.2rem auto; background:#fff; padding:2.2rem 2.4rem; box-shadow:0 1px 6px rgba(0,0,0,.1); }

        .head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0f172a; padding-bottom:.9rem; margin-bottom:1.2rem; }
        .head h1 { font-size:1.35rem; color:#0f172a; }
        .head .sub { font-size:.8rem; color:#64748b; margin-top:3px; }
        .head .meta { text-align:right; font-size:.74rem; color:#64748b; line-height:1.6; }

        .resultbox { display:flex; gap:1.5rem; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.4rem; margin-bottom:1.4rem; }
        .resultbox .big { font-size:2.6rem; font-weight:800; line-height:1; }
        .resultbox .lvl { width:56px; height:56px; border-radius:10px; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.9rem; font-weight:800; }
        .resultbox .desc { font-size:.8rem; color:#475569; }
        .resultbox .desc b { color:#0f172a; }

        table { width:100%; border-collapse:collapse; font-size:.72rem; margin-top:.4rem; }
        th, td { padding:.35rem .4rem; text-align:center; border:1px solid #e2e8f0; white-space:nowrap; }
        th { background:#f1f5f9; font-size:.64rem; text-transform:uppercase; letter-spacing:.03em; color:#475569; }
        td.svc { text-align:left; font-weight:600; }
        td.svc small { display:block; font-weight:400; color:#94a3b8; font-family:ui-monospace,monospace; font-size:.62rem; }
        tr.grupo td { background:#f8fafc; text-align:left; font-weight:700; text-transform:uppercase; font-size:.62rem; letter-spacing:.04em; color:#475569; }
        .cell { display:inline-block; min-width:46px; padding:1px 4px; border-radius:4px; color:#fff; font-weight:700; }
        .cell.empty { color:#cbd5e1; }
        .anual { font-weight:800; }
        .lvlb { display:inline-block; min-width:18px; padding:1px 6px; border-radius:4px; color:#fff; font-weight:800; }

        .escala { margin-top:1.4rem; font-size:.72rem; color:#475569; }
        .escala table { max-width:520px; }
        .escala td, .escala th { text-align:left; }

        .foot { margin-top:2rem; font-size:.7rem; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:.7rem; }

        @media print {
            body { background:#fff; }
            .toolbar { display:none; }
            .sheet { box-shadow:none; margin:0; max-width:none; padding:0; }
            @page { margin: 1.4cm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.kpi.disponibilidad.dashboard', ['anio' => $anio, 'sector' => $sector]) }}"><span>&larr;</span> Volver</a>
        <button class="print" onclick="window.print()">🖨 Imprimir / PDF</button>
    </div>

    <div class="sheet">
        @php
            $color = KpiDisponibilidad::colorPct($pctGlobal);
        @endphp

        <div class="head">
            <div>
                <h1>KPI 1 — Disponibilidad de servicios críticos@if($sectorLabel) · {{ $sectorLabel }}@endif</h1>
                <div class="sub">Infraestructura · Unifrutti LATAM · Período enero–diciembre {{ $anio }}@if($sectorLabel) · Sector: {{ $sectorLabel }}@endif</div>
            </div>
            <div class="meta">
                Presentado a: <b>Erick Olguín</b><br>
                NLATAM Infrastructure Manager<br>
                Generado: {{ $generado->format('d/m/Y H:i') }}
            </div>
        </div>

        <div class="resultbox">
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8">Disponibilidad anual</div>
                <div class="big" style="color:{{ $color }}">{{ $pctGlobal === null ? '—' : number_format($pctGlobal, 2).'%' }}</div>
            </div>
            <div class="lvl" style="background:{{ $nivelGlobal ? KpiDisponibilidad::colorNivel($nivelGlobal) : '#94a3b8' }}">
                {{ $nivelGlobal ?? '—' }}
            </div>
            <div class="desc">
                <div>Nivel KPI: <b>{{ $nivelGlobal ?? '—' }} / 5</b> — {{ $nivelGlobal ? KpiDisponibilidad::NIVELES[$nivelGlobal] : 'Sin datos' }}</div>
                <div>Meta (nivel 3): <b>{{ number_format($meta, 2) }}%</b> · Peso en evaluación: <b>{{ $peso }}%</b></div>
                <div style="margin-top:3px;color:#64748b">Cálculo excluye mantenimientos programados.</div>
            </div>
        </div>

        <table>
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
                @forelse($filas as $f)
                    @php $g = $f['servicio']->grupo ?: 'Sin grupo'; @endphp
                    @if($g !== $grupoActual)
                        @php $grupoActual = $g; @endphp
                        <tr class="grupo"><td colspan="15">{{ $g }}</td></tr>
                    @endif
                    <tr>
                        <td class="svc">{{ $f['servicio']->etiqueta }}<small>{{ $f['servicio']->objeto }}</small></td>
                        @foreach(range(1,12) as $m)
                            @php $v = $f['meses'][$m] ?? null; @endphp
                            <td>
                                @if($v === null)<span class="cell empty">·</span>
                                @elseif($v['pct'] === null)<span class="cell" style="background:#94a3b8">N/A</span>
                                @else<span class="cell" style="background:{{ KpiDisponibilidad::colorPct($v['pct']) }}">{{ number_format($v['pct'],2) }}@if($v['exc'] > 0)<sup>⚑</sup>@endif</span>@endif
                            </td>
                        @endforeach
                        <td class="anual" style="color:{{ KpiDisponibilidad::colorPct($f['anual']) }}">
                            {{ $f['anual'] === null ? '—' : number_format($f['anual'],2).'%' }}
                        </td>
                        <td>
                            @if($f['nivel'])<span class="lvlb" style="background:{{ KpiDisponibilidad::colorNivel($f['nivel']) }}">{{ $f['nivel'] }}</span>
                            @else—@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" style="padding:1.5rem;color:#94a3b8">Sin servicios críticos definidos.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="escala">
            <b>Escala del KPI</b>
            <table>
                <tr><th>Nivel</th><th>Disponibilidad</th><th>Descripción</th></tr>
                <tr><td><span class="lvlb" style="background:{{ KpiDisponibilidad::colorNivel(1) }}">1</span></td><td>&lt; 99,0%</td><td>Significativamente por debajo</td></tr>
                <tr><td><span class="lvlb" style="background:{{ KpiDisponibilidad::colorNivel(2) }}">2</span></td><td>≥ 99,0%</td><td>Por debajo de la meta</td></tr>
                <tr><td><span class="lvlb" style="background:{{ KpiDisponibilidad::colorNivel(3) }}">3</span></td><td>≥ 99,5% (meta)</td><td>Cumple la meta</td></tr>
                <tr><td><span class="lvlb" style="background:{{ KpiDisponibilidad::colorNivel(4) }}">4</span></td><td>≥ 99,7%</td><td>Sobre la meta</td></tr>
                <tr><td><span class="lvlb" style="background:{{ KpiDisponibilidad::colorNivel(5) }}">5</span></td><td>≥ 99,9%</td><td>Significativamente por encima</td></tr>
            </table>
        </div>

        @if($excepciones->isNotEmpty())
        <div class="escala" style="margin-top:1.6rem">
            <b>Excepciones justificadas (descontadas del KPI)</b>
            <span style="color:#64748b"> — total {{ number_format($horasJustificadas, 1) }} h</span>
            <table>
                <tr><th>Objeto</th><th>Período</th><th>Categoría</th><th>Justificación</th></tr>
                @foreach($excepciones as $x)
                <tr>
                    <td>{{ $x->objeto }}</td>
                    <td style="white-space:nowrap">{{ $x->desde->format('d/m/Y H:i') }} — {{ $x->hasta->format('d/m/Y H:i') }}</td>
                    <td>{{ $x->categoria ?: '—' }}</td>
                    <td>{{ $x->justificacion }}</td>
                </tr>
                @endforeach
            </table>
            <div style="font-size:.68rem;color:#94a3b8;margin-top:4px">
                <sup>⚑</sup> Los meses marcados incluyen caídas ajenas a la gestión de infraestructura (cortes eléctricos, fallas de proveedores, etc.), descontadas del cálculo con su justificación.
            </div>
        </div>
        @endif

        <div class="foot">
            Fuente de datos: CheckMK (API REST). Disponibilidad mensual congelada por servicio crítico; el resultado anual es el acumulado ponderado por tiempo monitoreado, excluyendo mantenimientos programados y excepciones justificadas.
        </div>
    </div>
</body>
</html>
