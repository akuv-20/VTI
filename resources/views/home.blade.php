@extends('layouts.app')

@php use App\Models\Sitio; @endphp

@section('content')
<style>
    /* ── Rejilla principal: 12 columnas que se colapsan en pantallas chicas ── */
    /* dense: si el usuario no tiene algún módulo, los paneles rellenan el hueco */
    .dsh { display:grid; grid-template-columns:repeat(12,1fr); gap:1rem; grid-auto-flow:row dense; }
    .dsh > * { min-width:0; }
    .c3 { grid-column:span 3 } .c4 { grid-column:span 4 } .c5 { grid-column:span 5 }
    .c6 { grid-column:span 6 } .c7 { grid-column:span 7 } .c8 { grid-column:span 8 }
    .c9 { grid-column:span 9 } .c12 { grid-column:span 12 }
    @media (max-width:1400px) { .c3 { grid-column:span 4 } .c5 { grid-column:span 6 } .c7 { grid-column:span 6 } }
    @media (max-width:1100px) { .c3,.c4,.c5,.c6,.c7,.c8,.c9 { grid-column:span 12 } }

    /* ── Panel base ────────────────────────────────────────────────────── */
    .pn { background:#fff; border:1px solid #e2e8f0; border-radius:14px; display:flex; flex-direction:column;
          overflow:hidden; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .pn > header { display:flex; align-items:center; gap:.5rem; padding:.75rem 1rem .55rem; }
    .pn > header h6 { margin:0; font-size:.74rem; font-weight:700; color:#334155;
                      text-transform:uppercase; letter-spacing:.06em; display:flex; align-items:center; gap:.4rem; }
    .pn > header .go { margin-left:auto; font-size:.71rem; text-decoration:none; color:#94a3b8; white-space:nowrap; }
    .pn > header .go:hover { color:#2563eb; }
    .pn > .bd { padding:.35rem 1rem 1rem; flex:1 1 auto; }

    /* ── Encabezado de la página ───────────────────────────────────────── */
    .dsh-hero { background:linear-gradient(120deg,#1e3a5f,#2563eb 60%,#7c3aed);
                border-radius:14px; color:#fff; padding:1.1rem 1.35rem; display:flex;
                align-items:center; gap:1.4rem; flex-wrap:wrap; }
    .dsh-hero .hi { font-size:1.28rem; font-weight:700; line-height:1.2; }
    .dsh-hero .fe { font-size:.78rem; opacity:.82; text-transform:capitalize; }
    .dsh-hero .mini { margin-left:auto; display:flex; gap:1.6rem; flex-wrap:wrap; }
    .dsh-hero .mini a { text-decoration:none; color:#fff; display:block; }
    .dsh-hero .mini .n { font-size:1.5rem; font-weight:700; line-height:1; }
    .dsh-hero .mini .l { font-size:.68rem; opacity:.8; text-transform:uppercase; letter-spacing:.05em; }
    .dsh-hero .mini a:hover .n { text-decoration:underline; }

    /* ── Centro de alertas ─────────────────────────────────────────────── */
    .al { display:flex; align-items:center; gap:.7rem; padding:.55rem .7rem; border-radius:10px;
          text-decoration:none; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:.45rem; }
    .al:hover { background:#f1f5f9; border-color:#cbd5e1; }
    .al .ic { width:30px; height:30px; border-radius:8px; flex:0 0 auto; display:flex;
              align-items:center; justify-content:center; font-size:.85rem; }
    .al .n { font-size:1.05rem; font-weight:700; line-height:1; }
    .al .tx { font-size:.76rem; color:#475569; line-height:1.25; }
    .al .ch { margin-left:auto; color:#cbd5e1; }
    .al-ok { text-align:center; padding:1.6rem .5rem; color:#166534; }
    .al-ok i { font-size:2.2rem; color:#86efac; display:block; margin-bottom:.4rem; }

    /* ── Gráficos ──────────────────────────────────────────────────────── */
    .gr { position:relative; }
    .gr canvas { cursor:pointer; }
    .gr-centro { position:absolute; inset:0; display:flex; flex-direction:column;
                 align-items:center; justify-content:center; pointer-events:none; text-align:center; }
    .gr-centro .n { font-size:1.65rem; font-weight:700; color:#1e293b; line-height:1; }
    .gr-centro .l { font-size:.68rem; color:#94a3b8; margin-top:2px; }

    /* ── Micro-indicadores dentro de los paneles ───────────────────────── */
    .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(92px,1fr)); gap:.5rem; }
    .kpis a { text-decoration:none; border:1px solid #e2e8f0; border-radius:10px; padding:.5rem .6rem; display:block;
              border-left-width:3px; border-left-style:solid; transition:background .12s,border-color .12s; }
    .kpis a:hover { background:#f8fafc; border-color:#cbd5e1; }
    .kpis .n { font-size:1.22rem; font-weight:700; color:#1e293b; line-height:1.1; }
    .kpis .l { font-size:.66rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.03em; line-height:1.25; }

    /* ── Barras de listas ──────────────────────────────────────────────── */
    .lst a { display:flex; align-items:center; gap:.6rem; text-decoration:none; padding:.35rem 0; }
    .lst .nm { font-size:.76rem; color:#334155; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lst .br { flex:1 1 auto; height:6px; background:#f1f5f9; border-radius:4px; overflow:hidden; min-width:40px; }
    .lst .br span { display:block; height:100%; border-radius:4px; }
    .lst .vl { font-size:.74rem; font-weight:700; color:#1e293b; width:62px; text-align:right; flex:0 0 auto; }
    .pn .vacio { color:#94a3b8; font-size:.78rem; text-align:center; padding:1.2rem 0; }
</style>

<div class="container-fluid vti-page">

    {{-- ── Encabezado ─────────────────────────────────────────────────── --}}
    <div class="dsh-hero mb-3">
        <div>
            <div class="hi">{{ $saludo }}@if($nombre), {{ $nombre }}@endif</div>
            <div class="fe">{{ $fechaLarga }}</div>
        </div>
        <div class="mini">
            @if($p['red'] && ($p['red']['ok'] ?? false))
            <a href="{{ $p['red']['url'] }}">
                <div class="n">{{ $p['red']['pct'] }}%</div><div class="l">Red en línea</div>
            </a>
            @endif
            @if($p['sitios'])
            <a href="{{ $p['sitios']['url'] }}">
                <div class="n">{{ $p['sitios']['total'] }}</div><div class="l">Sitios</div>
            </a>
            @endif
            @if($p['telefonia'])
            <a href="{{ $p['telefonia']['url'] }}">
                <div class="n">{{ $p['telefonia']['total'] }}</div><div class="l">Líneas activas</div>
            </a>
            @endif
            @if($p['inventario'] && ($p['inventario']['ok'] ?? false))
            <a href="{{ $p['inventario']['url'] }}">
                <div class="n">{{ $p['inventario']['total'] }}</div><div class="l">Equipos</div>
            </a>
            @endif
            @if($p['kpi'] && ($p['kpi']['ok'] ?? false))
            <a href="{{ $p['kpi']['url'] }}">
                <div class="n">{{ number_format($p['kpi']['pct'], 2) }}%</div><div class="l">Disponibilidad</div>
            </a>
            @endif
        </div>
    </div>

    @if($vacio)
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox" style="font-size:2.5rem;color:#cbd5e1"></i>
            <div class="mt-2" style="font-size:.9rem">Todavía no tienes módulos asignados.</div>
            <div style="font-size:.8rem">Pídele acceso a un administrador de la plataforma.</div>
        </div>
    @else

    <div class="dsh">

        {{-- ── Requiere atención ──────────────────────────────────────── --}}
        <div class="pn c5">
            <header>
                <h6><i class="bi bi-exclamation-diamond-fill" style="color:#dc2626"></i>Requiere atención</h6>
                @if(count($alertas))<span class="go">{{ count($alertas) }} punto(s)</span>@endif
            </header>
            <div class="bd">
                @forelse($alertas as $a)
                <a href="{{ $a['url'] }}" class="al">
                    <span class="ic" style="background:{{ $a['color'] }}1a"><i class="bi {{ $a['icono'] }}" style="color:{{ $a['color'] }}"></i></span>
                    <span class="n" style="color:{{ $a['color'] }}">{{ $a['n'] }}</span>
                    <span class="tx">{{ $a['texto'] }}</span>
                    <i class="bi bi-chevron-right ch"></i>
                </a>
                @empty
                <div class="al-ok">
                    <i class="bi bi-check-circle-fill"></i>
                    <div style="font-weight:600;font-size:.88rem">Todo en orden</div>
                    <div style="font-size:.76rem;color:#64748b">No hay nada pendiente de revisar.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- ── Red en vivo ────────────────────────────────────────────── --}}
        @if($p['red'])
        <div class="pn c3">
            <header>
                <h6><i class="bi bi-broadcast-pin" style="color:#0ea5e9"></i>Red en vivo</h6>
                <a href="{{ $p['red']['url'] }}" class="go">mapas <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd">
                @if($p['red']['ok'])
                <div class="gr" style="height:170px">
                    <canvas id="grRed"></canvas>
                    <div class="gr-centro">
                        <div class="n">{{ $p['red']['en_linea'] }}</div>
                        <div class="l">de {{ $p['red']['total'] }} hosts</div>
                    </div>
                </div>
                @else
                <div class="vacio"><i class="bi bi-plug d-block mb-2" style="font-size:1.6rem"></i>Sin conexión a CheckMK</div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── KPI de disponibilidad ──────────────────────────────────── --}}
        @if($p['kpi'])
        <div class="pn c4">
            <header>
                <h6><i class="bi bi-activity" style="color:#7c3aed"></i>KPI 1 — Disponibilidad</h6>
                <a href="{{ $p['kpi']['url'] }}" class="go">ver KPI <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd">
                @if($p['kpi']['ok'] ?? false)
                <div class="d-flex align-items-center gap-2">
                    <div class="gr" style="height:120px;width:190px;flex:0 0 auto">
                        <canvas id="grKpi"></canvas>
                        <div class="gr-centro" style="justify-content:flex-end;padding-bottom:6px">
                            <div class="n" style="color:{{ $p['kpi']['color'] }}">{{ number_format($p['kpi']['pct'], 2) }}%</div>
                            <div class="l">meta {{ $p['kpi']['meta'] }}%</div>
                        </div>
                    </div>
                    <div style="min-width:0">
                        <a href="{{ $p['kpi']['url_informe'] }}" class="text-decoration-none d-block mb-2">
                            <div style="font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em">Nivel alcanzado</div>
                            <div style="font-size:1.15rem;font-weight:700;color:{{ $p['kpi']['color'] }};line-height:1.1">
                                {{ $p['kpi']['nivel'] }} <span style="font-size:.8rem;color:#94a3b8">de 5</span>
                            </div>
                            <div style="font-size:.7rem;color:#64748b">{{ $p['kpi']['nivel_txt'] }}</div>
                        </a>
                        <a href="{{ $p['kpi']['url_servicios'] }}" class="text-decoration-none">
                            <div style="font-size:.72rem;color:#475569">
                                <b>{{ $p['kpi']['servicios'] }}</b> servicios críticos · {{ mb_strtolower($p['kpi']['mes']) }}
                            </div>
                        </a>
                    </div>
                </div>
                @else
                <div class="vacio"><i class="bi bi-hourglass d-block mb-2" style="font-size:1.6rem"></i>Aún no hay meses cerrados</div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Evolución de la disponibilidad ─────────────────────────── --}}
        @if(($p['kpi']['ok'] ?? false) && count($p['kpi']['serie']) > 1)
        <div class="pn c8">
            <header>
                <h6><i class="bi bi-graph-up" style="color:#7c3aed"></i>Evolución de la disponibilidad</h6>
                <a href="{{ $p['kpi']['url_informe'] }}" class="go">informe anual <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd"><div class="gr" style="height:200px"><canvas id="grSerie"></canvas></div></div>
        </div>
        @endif

        {{-- ── Servicios con peor disponibilidad ──────────────────────── --}}
        @if(($p['kpi']['ok'] ?? false) && count($p['kpi']['peores']))
        <div class="pn c4">
            <header>
                <h6><i class="bi bi-hdd-stack" style="color:#7c3aed"></i>Peor disponibilidad</h6>
                <a href="{{ $p['kpi']['url_servicios'] }}" class="go">servicios <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd lst">
                @foreach($p['kpi']['peores'] as $s)
                <a href="{{ $p['kpi']['url'] }}">
                    <span class="nm" style="flex:0 0 42%" title="{{ $s['nombre'] }}">{{ $s['nombre'] }}</span>
                    <span class="br"><span style="width:{{ max(2, $s['pct']) }}%;background:{{ $s['pct'] >= $p['kpi']['meta'] ? '#16a34a' : ($s['pct'] >= 99 ? '#d97706' : '#dc2626') }}"></span></span>
                    <span class="vl">{{ number_format($s['pct'], 2) }}%</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Sitios ─────────────────────────────────────────────────── --}}
        @if($p['sitios'])
        <div class="pn c7">
            <header>
                <h6><i class="bi bi-pin-map-fill" style="color:#7c3aed"></i>Avance del enlazamiento</h6>
                <a href="{{ $p['sitios']['url_avance'] }}" class="go">ver avance <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd">
                @if(count($p['sitios']['por_tipo']))
                <div class="gr mb-3" style="height:{{ 48 + count($p['sitios']['por_tipo']) * 34 }}px"><canvas id="grSitios"></canvas></div>
                @endif
                <div class="kpis">
                    {{-- Se muestran todas las etapas, incluso en cero: es un embudo de avance --}}
                    @foreach(Sitio::ESTADOS_ENLACE as $k => $l)
                    <a href="{{ $p['sitios']['url_estado'][$k] }}" style="border-left-color:{{ Sitio::COLORES_ENLACE[$k] }}">
                        <div class="n">{{ $p['sitios']['por_estado'][$k] ?? 0 }}</div>
                        <div class="l">{{ $l }}</div>
                    </a>
                    @endforeach
                    <a href="{{ $p['sitios']['url_avance'] }}" style="border-left-color:#7c3aed">
                        <div class="n">{{ $p['sitios']['completitud'] }}%</div>
                        <div class="l">Fichas completas</div>
                    </a>
                    @if($p['sitios']['rotos']['ok'])
                    <a href="{{ $p['sitios']['url_enlaces'] }}" style="border-left-color:{{ $p['sitios']['rotos']['n'] ? '#dc2626' : '#16a34a' }}">
                        <div class="n" @if($p['sitios']['rotos']['n']) style="color:#dc2626" @endif>{{ $p['sitios']['rotos']['n'] }}</div>
                        <div class="l">Enlaces rotos</div>
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ── Telefonía ──────────────────────────────────────────────── --}}
        @if($p['telefonia'])
        <div class="pn c5">
            <header>
                <h6><i class="bi bi-phone" style="color:#2563eb"></i>Telefonía</h6>
                <a href="{{ $p['telefonia']['url'] }}" class="go">ver líneas <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="gr" style="height:150px;width:150px;flex:0 0 auto">
                        <canvas id="grTel"></canvas>
                        <div class="gr-centro">
                            <div class="n">{{ $p['telefonia']['total'] }}</div>
                            <div class="l">activas</div>
                        </div>
                    </div>
                    <div class="lst flex-grow-1" style="min-width:190px">
                        @foreach($p['telefonia']['emisores'] as $nombre => $e)
                        <a href="{{ $e['url'] }}">
                            <span class="nm" style="flex:0 0 74px">{{ $nombre }}</span>
                            <span class="br"><span style="width:{{ $p['telefonia']['total'] ? round($e['n'] / $p['telefonia']['total'] * 100) : 0 }}%;background:{{ $e['color'] }}"></span></span>
                            <span class="vl">{{ $e['n'] }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
                <div class="kpis mt-3">
                    <a href="{{ $p['telefonia']['url_sin_usuario'] }}" style="border-left-color:{{ $p['telefonia']['sin_usuario'] ? '#f59e0b' : '#22c55e' }}">
                        <div class="n">{{ $p['telefonia']['sin_usuario'] }}</div><div class="l">Sin usuario</div>
                    </a>
                    <a href="{{ $p['telefonia']['url_incompletas'] }}" style="border-left-color:{{ $p['telefonia']['incompletas'] ? '#f59e0b' : '#22c55e' }}">
                        <div class="n">{{ $p['telefonia']['incompletas'] }}</div><div class="l">Incompletas</div>
                    </a>
                    @isset($p['telefonia']['roaming'])
                    <a href="{{ $p['telefonia']['url_roaming'] }}" style="border-left-color:#0d9488">
                        <div class="n">{{ $p['telefonia']['roaming'] }}</div><div class="l">Roaming activo</div>
                    </a>
                    @endisset
                    <a href="{{ route('lineas_telefonicas.index', ['estado' => 'Inactivo']) }}" style="border-left-color:#94a3b8">
                        <div class="n">{{ $p['telefonia']['inactivas'] }}</div><div class="l">Inactivas</div>
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Facturación ────────────────────────────────────────────── --}}
        @if($p['facturacion'])
        <div class="pn c6">
            <header>
                <h6><i class="bi bi-receipt" style="color:#0284c7"></i>Facturación</h6>
                <a href="{{ $p['facturacion']['url_pendientes'] }}" class="go">{{ $periodoLabel }} <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="bd">
                <div class="d-flex align-items-center gap-3">
                    <div class="gr" style="height:140px;width:140px;flex:0 0 auto">
                        <canvas id="grFac"></canvas>
                        <div class="gr-centro">
                            <div class="n">{{ $p['facturacion']['pct'] }}%</div>
                            <div class="l">facturado</div>
                        </div>
                    </div>
                    <div class="kpis flex-grow-1" style="grid-template-columns:1fr">
                        <a href="{{ $p['facturacion']['url'] }}" style="border-left-color:#64748b">
                            <div class="n">{{ $p['facturacion']['periodicos'] }}</div><div class="l">Servicios periódicos</div>
                        </a>
                        <a href="{{ $p['facturacion']['url_pendientes'] }}" style="border-left-color:{{ $p['facturacion']['pendientes'] ? '#ef4444' : '#22c55e' }}">
                            <div class="n" @if($p['facturacion']['pendientes']) style="color:#ef4444" @endif>{{ $p['facturacion']['pendientes'] }}</div>
                            <div class="l">Sin facturar</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Inventario y administración ────────────────────────────── --}}
        @if($p['inventario'] || $p['admin'])
        <div class="pn c6">
            <header>
                <h6><i class="bi bi-pc-display" style="color:#0891b2"></i>Equipos y usuarios</h6>
            </header>
            <div class="bd">
                <div class="kpis">
                    @if($p['inventario'])
                        @if($p['inventario']['ok'])
                        <a href="{{ $p['inventario']['url'] }}" style="border-left-color:#0891b2">
                            <div class="n">{{ $p['inventario']['total'] }}</div><div class="l">Equipos GLPI</div>
                        </a>
                        <a href="{{ $p['inventario']['url'] }}" style="border-left-color:{{ $p['inventario']['sin_dueno'] ? '#f59e0b' : '#22c55e' }}">
                            <div class="n">{{ $p['inventario']['sin_dueno'] }}</div><div class="l">Sin responsable</div>
                        </a>
                        @else
                        <a href="{{ $p['inventario']['url'] }}" style="border-left-color:#94a3b8;grid-column:1/-1">
                            <div class="n">—</div><div class="l">GLPI sin conexión</div>
                        </a>
                        @endif
                    @endif
                    @if($p['admin'])
                    <a href="{{ $p['admin']['url'] }}" style="border-left-color:#64748b">
                        <div class="n">{{ $p['admin']['activos'] }}</div><div class="l">Usuarios activos</div>
                    </a>
                    <a href="{{ $p['admin']['url'] }}" style="border-left-color:#cbd5e1">
                        <div class="n">{{ $p['admin']['inactivos'] }}</div><div class="l">Deshabilitados</div>
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endif

    </div>
    @endif
</div>
@endsection

@push('scripts')
@php
    // Los datos de los gráficos se arman acá: Blade no acepta funciones flecha
    // dentro de @json, así que a la plantilla solo llegan arrays planos.
    $telLabels = $telDatos = $telColores = $telUrls = [];
    foreach ($p['telefonia']['emisores'] ?? [] as $nombre => $e) {
        $telLabels[]  = $nombre;
        $telDatos[]   = $e['n'];
        $telColores[] = $e['color'];
        $telUrls[]    = $e['url'];
    }

    $sitEstados = [];
    foreach (Sitio::ESTADOS_ENLACE as $k => $l) {
        $sitEstados[] = ['k' => $k, 'label' => $l, 'color' => Sitio::COLORES_ENLACE[$k]];
    }
    $sitTipos = array_values($p['sitios']['por_tipo'] ?? []);
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;   // sin CDN, la página sigue sirviendo

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    const sinLeyenda = { legend: { display: false } };
    const tip = { padding: 9, cornerRadius: 7, displayColors: true, boxPadding: 3 };

    // Al hacer clic en un segmento se navega a su vista filtrada.
    const irA = (destinos) => (ev, els) => {
        if (!els.length) return;
        const url = destinos[els[0].index];
        if (url) window.location = url;
    };

    const dona = (id, labels, datos, colores, destinos, corte) => {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data: datos, backgroundColor: colores, borderWidth: 0, hoverOffset: 6 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: corte || '72%',
                plugins: { ...sinLeyenda, tooltip: tip },
                onClick: irA(destinos || []),
                onHover: (e, els) => e.native.target.style.cursor = els.length ? 'pointer' : 'default',
            },
        });
    };

    @if($p['red'] && ($p['red']['ok'] ?? false))
    dona('grRed',
        ['En línea', 'Caídos', 'En mantención'],
        [{{ $p['red']['en_linea'] }}, {{ $p['red']['caidos'] }}, {{ $p['red']['downtime'] }}],
        ['#16a34a', '#dc2626', '#d97706'],
        Array(3).fill(@json($p['red']['url'])));
    @endif

    @if($p['telefonia'])
    dona('grTel', @json($telLabels), @json($telDatos), @json($telColores), @json($telUrls));
    @endif

    @if($p['facturacion'])
    dona('grFac',
        ['Facturados', 'Sin facturar'],
        [{{ $p['facturacion']['facturados'] }}, {{ $p['facturacion']['pendientes'] }}],
        ['#22c55e', '#ef4444'],
        [@json($p['facturacion']['url_pendientes']), @json($p['facturacion']['url_pendientes'])]);
    @endif

    @if($p['kpi']['ok'] ?? false)
    // Semicírculo: lo alcanzado contra lo que falta para el 100%.
    (function () {
        const el = document.getElementById('grKpi');
        if (!el) return;
        const pct = {{ $p['kpi']['pct'] }};
        new Chart(el, {
            type: 'doughnut',
            data: { labels: ['Disponible', 'Indisponible'],
                    datasets: [{ data: [pct, Math.max(0, 100 - pct)],
                                 backgroundColor: [@json($p['kpi']['color']), '#f1f5f9'], borderWidth: 0 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                circumference: 180, rotation: -90, cutout: '74%',
                plugins: { ...sinLeyenda, tooltip: tip },
                onClick: () => window.location = @json($p['kpi']['url']),
            },
        });
    })();

    @if(count($p['kpi']['serie']) > 1)
    (function () {
        const el = document.getElementById('grSerie');
        if (!el) return;
        const serie = @json($p['kpi']['serie']);
        const meta = {{ $p['kpi']['meta'] }};
        const ctx = el.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0, 'rgba(124,58,237,.22)');
        grad.addColorStop(1, 'rgba(124,58,237,0)');

        new Chart(el, {
            type: 'line',
            data: {
                labels: serie.map(s => s.label),
                datasets: [
                    { label: 'Disponibilidad', data: serie.map(s => s.pct), borderColor: '#7c3aed',
                      backgroundColor: grad, fill: true, tension: .35, borderWidth: 2,
                      pointRadius: 3, pointBackgroundColor: '#7c3aed', pointHoverRadius: 5 },
                    { label: 'Meta ' + meta + '%', data: serie.map(() => meta), borderColor: '#16a34a',
                      borderDash: [5, 4], borderWidth: 1.5, pointRadius: 0, fill: false },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                    tooltip: { ...tip, callbacks: { label: c => c.dataset.label + ': ' + c.parsed.y.toFixed(2) + '%' } },
                },
                scales: {
                    y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => v + '%' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } },
                },
                onClick: () => window.location = @json($p['kpi']['url_informe']),
            },
        });
    })();
    @endif
    @endif

    @if($p['sitios'] && count($p['sitios']['por_tipo']))
    (function () {
        const el = document.getElementById('grSitios');
        if (!el) return;
        const estados = @json($sitEstados);
        const tipos = @json($sitTipos);
        const urls = @json($p['sitios']['url_estado']);

        new Chart(el, {
            type: 'bar',
            data: {
                labels: tipos.map(t => t.label),
                datasets: estados.map(e => ({
                    label: e.label,
                    data: tipos.map(t => t.estados[e.k] || 0),
                    backgroundColor: e.color,
                    borderRadius: 3,
                    borderSkipped: false,
                })),
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                    tooltip: { ...tip, callbacks: { label: c => c.dataset.label + ': ' + c.parsed.x } },
                },
                scales: {
                    x: { stacked: true, grid: { color: '#f1f5f9' }, border: { display: false },
                         ticks: { precision: 0 } },
                    y: { stacked: true, grid: { display: false }, border: { display: false } },
                },
                onClick: (ev, els) => {
                    if (!els.length) return;
                    const url = urls[estados[els[0].datasetIndex].k];
                    if (url) window.location = url;
                },
                onHover: (e, els) => e.native.target.style.cursor = els.length ? 'pointer' : 'default',
            },
        });
    })();
    @endif
})();
</script>
@endpush
