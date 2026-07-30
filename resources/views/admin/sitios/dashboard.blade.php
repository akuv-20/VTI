@extends('layouts.app')

@php use App\Models\Sitio; use App\Models\SitioEquipo; @endphp

@section('content')
<style>
    .sd-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.1rem; }
    .sd-card h6 { font-size:.78rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }
    .sd-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.85rem; margin-bottom:1.1rem; }
    .sd-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:.9rem 1rem; }
    .sd-kpi .lb { font-size:.68rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; font-weight:600; }
    .sd-kpi .vl { font-size:1.7rem; font-weight:800; line-height:1.15; }
    .sd-kpi .sb { font-size:.7rem; color:#94a3b8; }
    .sd-bar { display:flex; height:24px; border-radius:6px; overflow:hidden; background:#f1f5f9; }
    .sd-bar div { display:flex; align-items:center; justify-content:center; font-size:.66rem; font-weight:700; color:#fff; }
    .sd-leg { display:flex; gap:.9rem; flex-wrap:wrap; font-size:.72rem; color:#64748b; margin-top:.5rem; }
    .sd-leg span i { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:4px; }
    .sd-table { width:100%; border-collapse:collapse; font-size:.78rem; }
    .sd-table th, .sd-table td { padding:.45rem .55rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    .sd-table th { font-size:.66rem; text-transform:uppercase; color:#94a3b8; letter-spacing:.04em; }
    .sd-mini { height:5px; background:#f1f5f9; border-radius:3px; overflow:hidden; width:80px; display:inline-block; vertical-align:middle; }
    .sd-mini span { display:block; height:100%; }
    .sd-mapa { position:relative; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; height:330px; overflow:hidden; }
    .sd-pin { position:absolute; width:12px; height:12px; border-radius:50%; border:2px solid #fff; transform:translate(-50%,-50%); cursor:help; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-graph-up me-2" style="color:#7c3aed"></i>Avance de enlazamiento</h4>
        <a href="{{ route('admin.sitios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pin-map-fill me-1"></i>Sitios</a>
    </div>

    @php
        $total = $sitios->count();
        $pctOper = $total ? round($operativos / $total * 100, 1) : 0;
    @endphp

    <div class="sd-kpis">
        <div class="sd-kpi">
            <div class="lb">Sitios registrados</div>
            <div class="vl">{{ $total }}</div>
            <div class="sb">{{ collect(Sitio::TIPOS)->map(fn($l, $k) => $sitios->where('tipo', $k)->count() . ' ' . mb_strtolower($l))->implode(' · ') }}</div>
        </div>
        <div class="sd-kpi">
            <div class="lb">Con enlace operativo</div>
            <div class="vl" style="color:{{ $pctOper >= 80 ? '#16a34a' : ($pctOper >= 50 ? '#d97706' : '#dc2626') }}">{{ number_format($pctOper, 1) }}%</div>
            <div class="sb">{{ $operativos }} de {{ $total }} sitios</div>
        </div>
        <div class="sd-kpi">
            <div class="lb">Completitud de fichas</div>
            <div class="vl" style="color:{{ $completitudProm >= 80 ? '#16a34a' : ($completitudProm >= 40 ? '#d97706' : '#dc2626') }}">{{ $completitudProm }}%</div>
            <div class="sb">promedio de todas las fichas</div>
        </div>
        <div class="sd-kpi">
            <div class="lb">Sin monitoreo</div>
            <div class="vl" style="color:{{ $sinMonitoreo->count() ? '#d97706' : '#16a34a' }}">{{ $sinMonitoreo->count() }}</div>
            <div class="sb">fichas sin ningún host en CheckMK</div>
        </div>
        <div class="sd-kpi">
            <div class="lb">Hosts sin ficha</div>
            <div class="vl" style="color:{{ $hostsSinFicha ? '#0284c7' : '#16a34a' }}">{{ $checkmkOk ? $hostsSinFicha : '—' }}</div>
            <div class="sb">
                @if($checkmkOk)
                    <a href="{{ route('admin.sitios.descubrimiento') }}">ir a descubrir →</a>
                @else
                    CheckMK sin conexión
                @endif
            </div>
        </div>
    </div>

    {{-- ── Avance por tipo ────────────────────────────────────────────────── --}}
    <div class="sd-card">
        <h6><i class="bi bi-bar-chart-steps me-1"></i>Avance del enlazamiento por tipo de sitio</h6>
        @foreach($matriz as $tipo => $fila)
            @if($fila['total'] > 0)
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1" style="font-size:.78rem">
                    <b><i class="bi {{ Sitio::ICONOS_TIPO[$tipo] }} me-1"></i>{{ $fila['label'] }}</b>
                    <span class="text-muted">{{ $fila['total'] }} sitios</span>
                </div>
                <div class="sd-bar">
                    @foreach($fila['estados'] as $est => $n)
                        @if($n > 0)
                        <div style="width:{{ $n / $fila['total'] * 100 }}%;background:{{ Sitio::COLORES_ENLACE[$est] }}"
                             title="{{ Sitio::ESTADOS_ENLACE[$est] }}: {{ $n }}">{{ $n }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
        <div class="sd-leg">
            @foreach(Sitio::ESTADOS_ENLACE as $k => $l)
                <span><i style="background:{{ Sitio::COLORES_ENLACE[$k] }}"></i>{{ $l }}</span>
            @endforeach
        </div>
    </div>

    <div class="row g-3">
        {{-- ── Fichas incompletas ─────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="sd-card">
                <h6><i class="bi bi-clipboard-x me-1"></i>Fichas por completar</h6>
                @if($incompletas->isEmpty())
                    <div class="text-muted text-center py-3" style="font-size:.82rem">Todas las fichas están completas. 👏</div>
                @else
                <table class="sd-table">
                    <thead><tr><th>Sitio</th><th>Tipo</th><th style="width:130px">Completitud</th></tr></thead>
                    <tbody>
                    @foreach($incompletas as $s)
                        <tr>
                            <td><a href="{{ route('admin.sitios.show', $s) }}" style="text-decoration:none">{{ $s->titulo }}</a></td>
                            <td class="text-muted" style="font-size:.72rem">{{ $s->tipo_label }}</td>
                            <td>
                                <span class="sd-mini"><span style="width:{{ $s->completitud }}%;background:{{ $s->completitud >= 80 ? '#16a34a' : ($s->completitud >= 40 ? '#d97706' : '#dc2626') }}"></span></span>
                                <span style="font-size:.72rem;margin-left:5px">{{ $s->completitud }}%</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        {{-- ── Sin monitoreo ──────────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="sd-card">
                <h6><i class="bi bi-eye-slash me-1"></i>Sitios sin monitoreo en CheckMK</h6>
                @if($sinMonitoreo->isEmpty())
                    <div class="text-muted text-center py-3" style="font-size:.82rem">Todos los sitios tienen al menos un host monitoreado.</div>
                @else
                <div style="font-size:.76rem;color:#64748b" class="mb-2">
                    Estos sitios existen en la ficha pero no hay ningún host de CheckMK asociado — no se ven en el mapa ni en disponibilidad.
                </div>
                <table class="sd-table">
                    <thead><tr><th>Sitio</th><th>Tipo</th><th>Estado enlace</th></tr></thead>
                    <tbody>
                    @foreach($sinMonitoreo->take(12) as $s)
                        <tr>
                            <td><a href="{{ route('admin.sitios.show', $s) }}" style="text-decoration:none">{{ $s->titulo }}</a></td>
                            <td class="text-muted" style="font-size:.72rem">{{ $s->tipo_label }}</td>
                            <td><span style="font-size:.68rem;font-weight:700;padding:1px 8px;border-radius:20px;color:#fff;background:{{ $s->estado_enlace_color }}">{{ $s->estado_enlace_label }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($sinMonitoreo->count() > 12)
                    <div class="text-muted mt-1" style="font-size:.72rem">… y {{ $sinMonitoreo->count() - 12 }} más</div>
                @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ── Mapa geográfico ────────────────────────────────────────────────── --}}
    @if($conGeo->isNotEmpty())
    @php
        $lats = $conGeo->pluck('latitud'); $lons = $conGeo->pluck('longitud');
        $minLat = $lats->min(); $maxLat = $lats->max();
        $minLon = $lons->min(); $maxLon = $lons->max();
        $spanLat = max(0.05, $maxLat - $minLat); $spanLon = max(0.05, $maxLon - $minLon);
    @endphp
    <div class="sd-card">
        <h6><i class="bi bi-geo-alt me-1"></i>Distribución geográfica <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400">— {{ $conGeo->count() }} sitios con coordenadas</span></h6>
        <div class="sd-mapa">
            @foreach($conGeo as $s)
                @php
                    $x = 6 + (($s->longitud - $minLon) / $spanLon) * 88;
                    $y = 92 - (($s->latitud - $minLat) / $spanLat) * 84;
                @endphp
                <a href="{{ route('admin.sitios.show', $s) }}" class="sd-pin"
                   style="left:{{ $x }}%;top:{{ $y }}%;background:{{ $s->estado_enlace_color }}"
                   title="{{ $s->titulo }} — {{ $s->estado_enlace_label }}"></a>
            @endforeach
        </div>
        <div class="sd-leg">
            @foreach(Sitio::ESTADOS_ENLACE as $k => $l)
                <span><i style="background:{{ Sitio::COLORES_ENLACE[$k] }}"></i>{{ $l }}</span>
            @endforeach
            <span class="ms-auto text-muted">Posición relativa (norte arriba). Clic en un punto para abrir su ficha.</span>
        </div>
    </div>
    @endif

    <div class="row g-3">
        {{-- ── Equipamiento ───────────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="sd-card">
                <h6><i class="bi bi-hdd-network me-1"></i>Equipamiento registrado</h6>
                @if($porTipoEquipo->isEmpty())
                    <div class="text-muted text-center py-3" style="font-size:.82rem">Aún no hay equipos registrados en las fichas.</div>
                @else
                <table class="sd-table">
                    <tbody>
                    @foreach($porTipoEquipo as $tipo => $n)
                        <tr>
                            <td><i class="bi {{ SitioEquipo::ICONOS_TIPO[$tipo] ?? 'bi-box' }} me-1 text-muted"></i>{{ SitioEquipo::TIPOS[$tipo] ?? $tipo }}</td>
                            <td style="text-align:right;font-weight:700">{{ $n }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($porMarca->isNotEmpty())
                <div class="mt-3">
                    <div style="font-size:.7rem;color:#94a3b8;text-transform:uppercase;font-weight:600;margin-bottom:.4rem">Por marca</div>
                    @foreach($porMarca as $marca => $n)
                        <span style="display:inline-block;font-size:.7rem;background:#f1f5f9;border-radius:20px;padding:2px 9px;margin:0 3px 3px 0">
                            {{ $marca }} <b>{{ $n }}</b>
                        </span>
                    @endforeach
                </div>
                @endif
                @endif
            </div>
        </div>

        {{-- ── Garantías ──────────────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="sd-card">
                <h6><i class="bi bi-shield-exclamation me-1"></i>Garantías por vencer <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400">— próximos 90 días</span></h6>
                @if($garantias->isEmpty())
                    <div class="text-muted text-center py-3" style="font-size:.82rem">Ninguna garantía vence en los próximos 90 días.</div>
                @else
                <table class="sd-table">
                    <thead><tr><th>Equipo</th><th>Sitio</th><th>Vence</th></tr></thead>
                    <tbody>
                    @foreach($garantias as $eq)
                        <tr>
                            <td>{{ $eq->nombre }}<div class="text-muted" style="font-size:.68rem">{{ $eq->marca_modelo }}</div></td>
                            <td style="font-size:.74rem">{{ $eq->sitio?->titulo }}</td>
                            <td style="font-size:.74rem;white-space:nowrap">{{ $eq->garantia_hasta->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
