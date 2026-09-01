@extends('layouts.app')

@section('content')
<style>
    .kpi-card { color:inherit; transition:box-shadow .12s, transform .08s; }
    .kpi-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.12); transform:translateY(-2px); color:inherit; }
</style>
<div class="container-fluid vti-page">

    <div class="vti-page-header mb-3">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-speedometer2 me-2" style="color:{{ $dom->color() }}"></i>Salud del Inventario</span>
            @include('inventario._dominio', ['seccion' => 'dashboard'])
        </h4>
        <small class="text-muted">
            Datos en tiempo real desde el GLPI de {{ $dom->label() }} &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}
        </small>
    </div>

    {{-- ── KPIs ── --}}
    <div class="row g-3 mb-4">
        @php
        $kpis = [
            ['label'=>'Equipos Activos',      'value'=>$totalEquipos,    'icon'=>'bi-display-fill',          'bg'=>'#dbeafe','ic'=>'#2563eb', 'params'=>[]],
            ['label'=>'Sin Usuario',           'value'=>$sinUsuario,      'icon'=>'bi-person-x-fill',         'bg'=>'#fef3c7','ic'=>'#d97706', 'params'=>['filtro'=>'sin_usuario']],
            ['label'=>'Sin Ubicación',         'value'=>$sinUbicacion,    'icon'=>'bi-geo-alt',               'bg'=>'#fef3c7','ic'=>'#d97706', 'params'=>['filtro'=>'sin_ubicacion']],
            ['label'=>'Sin Agente',            'value'=>$sinAgente,       'icon'=>'bi-plugin',                'bg'=>'#fee2e2','ic'=>'#dc2626', 'params'=>['sin_agente'=>1]],
            ['label'=>'Agente Inactivo +90d',  'value'=>$agenteInactivo,  'icon'=>'bi-wifi-off',              'bg'=>'#fee2e2','ic'=>'#dc2626', 'params'=>['agente_inactivo'=>1]],
            ['label'=>'Duplicados por Serial', 'value'=>$cantDuplicados,  'icon'=>'bi-copy',                  'bg'=>'#fee2e2','ic'=>'#dc2626', 'params'=>['duplicados'=>1]],
            ['label'=>'Sin '.($antivirusNombre ?: 'Antivirus'), 'value'=>$sinAntivirus, 'icon'=>'bi-shield-x', 'bg'=>'#fef3c7','ic'=>'#d97706', 'params'=>['filtro'=>'sin_antivirus']],
        ];
        @endphp
        @foreach($kpis as $k)
        <div class="col-6 col-md-4 col-xl-3">
            <a href="{{ route("inventario.{$dom->clave}.equipos", $k['params']) }}"
               class="card border-0 shadow-sm rounded-3 h-100 text-decoration-none kpi-card"
               style="border-left: 4px solid {{ $k['ic'] }} !important;"
               title="Ver equipos: {{ $k['label'] }}">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:{{ $k['bg'] }}">
                        <i class="bi {{ $k['icon'] }}" style="font-size:1.4rem;color:{{ $k['ic'] }}"></i>
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:1.6rem;line-height:1;color:{{ $k['ic'] }}">{{ number_format($k['value']) }}</div>
                        <div class="text-muted" style="font-size:.75rem;margin-top:2px">{{ $k['label'] }}</div>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>

    {{-- ── Fila gráficos ── --}}
    <div class="row g-3 mb-4">

        {{-- Torta SO --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-pie-chart-fill me-1 text-primary"></i> Distribución por Sistema Operativo
                </div>
                <div class="card-body d-flex flex-column">
                    <div style="height:220px;position:relative">
                        <canvas id="chartSO"></canvas>
                    </div>
                    <div class="mt-3" style="font-size:.78rem">
                        @foreach($porSO as $so)
                        @php $pct = $totalConSO > 0 ? round($so->total * 100 / $totalConSO, 1) : 0; @endphp
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="text-truncate" style="max-width:170px" title="{{ $so->name }}">{{ $so->name }}</span>
                            <span class="d-inline-flex align-items-center gap-2 ms-2">
                                <span class="fw-semibold">{{ $so->total }} <span class="text-muted">({{ $pct }}%)</span></span>
                                <a href="{{ route("inventario.{$dom->clave}.equipos", ['so' => $so->name]) }}"
                                   class="btn btn-outline-secondary btn-sm py-0 px-1"
                                   title="Ver equipos con {{ $so->name }}"
                                   style="font-size:.7rem;line-height:1.4">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Barras versión agente --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-bar-chart-fill me-1 text-success"></i> Versiones del Agente GLPI
                </div>
                <div class="card-body d-flex flex-column">
                    <div style="height:220px;position:relative">
                        <canvas id="chartAgente"></canvas>
                    </div>
                    <div class="mt-3" style="font-size:.78rem">
                        @foreach($porVersionAgente->sortByDesc('version') as $va)
                        @php $esLatest = $va->version === $versionLatest; @endphp
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span>
                                v{{ $va->version }}
                                @if($esLatest)
                                    <span class="badge text-bg-success ms-1" style="font-size:.65rem">última</span>
                                @else
                                    <span class="badge text-bg-warning ms-1" style="font-size:.65rem">desactualizado</span>
                                @endif
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="fw-semibold">{{ $va->total }}</span>
                                <a href="{{ route("inventario.{$dom->clave}.equipos", ['version_agente' => $va->version]) }}"
                                   class="btn btn-outline-secondary btn-sm py-0 px-1"
                                   title="Ver equipos con agente v{{ $va->version }}"
                                   style="font-size:.7rem;line-height:1.4">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Barras por ubicación --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-geo-alt-fill me-1 text-danger"></i> Top 10 Ubicaciones
                </div>
                <div class="card-body d-flex flex-column">
                    <div style="height:220px;position:relative">
                        <canvas id="chartUbicacion"></canvas>
                    </div>
                    <div class="mt-3" style="font-size:.78rem">
                        @foreach($porUbicacion as $ub)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="text-truncate" style="max-width:170px" title="{{ $ub->ubicacion }}">{{ $ub->ubicacion }}</span>
                            <span class="d-inline-flex align-items-center gap-2 ms-2">
                                <span class="fw-semibold">{{ $ub->total }}</span>
                                <a href="{{ route("inventario.{$dom->clave}.equipos", ['ubicacion' => $ub->ubicacion]) }}"
                                   class="btn btn-outline-secondary btn-sm py-0 px-1"
                                   title="Ver equipos en {{ $ub->ubicacion }}"
                                   style="font-size:.7rem;line-height:1.4">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Fila tablas alertas ── --}}
    <div class="row g-3 mb-4">

        {{-- Inactivos --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos sin comunicación +90 días',
                'icono'   => 'bi-wifi-off',
                'color'   => 'danger',
                'columnas'=> ['Equipo','Usuario','Ubicación','Último contacto','Ver. agente'],
                'filas'   => $inactivos->map(fn($r) => [
                    $r->equipo,
                    trim($r->usuario) ?: '—',
                    $r->ubicacion ?? '—',
                    \Carbon\Carbon::parse($r->last_contact)->format('d/m/Y'),
                    'v'.$r->version,
                ]),
            ])
        </div>

        {{-- Sin usuario --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos sin usuario asignado',
                'icono'   => 'bi-person-x-fill',
                'color'   => 'warning',
                'columnas'=> ['Equipo','Serial','Ubicación','Última modificación'],
                'filas'   => $sinUsuarioLista->map(fn($r) => [
                    $r->equipo,
                    $r->serial ?: '—',
                    $r->ubicacion ?? '—',
                    $r->date_mod ? \Carbon\Carbon::parse($r->date_mod)->format('d/m/Y') : '—',
                ]),
            ])
        </div>

    </div>

    <div class="row g-3 mb-4">

        {{-- Sin ubicación --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos sin ubicación',
                'icono'   => 'bi-geo-alt',
                'color'   => 'warning',
                'columnas'=> ['Equipo','Serial','Usuario'],
                'filas'   => $sinUbicacionLista->map(fn($r) => [
                    $r->equipo,
                    $r->serial ?: '—',
                    trim($r->usuario) ?: '—',
                ]),
            ])
        </div>

        {{-- Sin agente --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos sin agente GLPI',
                'icono'   => 'bi-plugin',
                'color'   => 'danger',
                'columnas'=> ['Equipo','Serial','Usuario','Ubicación'],
                'filas'   => $sinAgenteLista->map(fn($r) => [
                    $r->equipo,
                    $r->serial ?: '—',
                    trim($r->usuario) ?: '—',
                    $r->ubicacion ?? '—',
                ]),
            ])
        </div>

    </div>

    <div class="row g-3 mb-4">

        {{-- Duplicados --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos duplicados por N° de Serie',
                'icono'   => 'bi-copy',
                'color'   => 'danger',
                'columnas'=> ['Serial','Equipo','Usuario'],
                'filas'   => $duplicadosDetalle->map(fn($r) => [
                    $r->serial,
                    $r->equipo,
                    trim($r->usuario) ?: '—',
                ]),
            ])
        </div>

        {{-- Sin antivirus --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos sin ' . ($antivirusNombre ?: 'antivirus') . ' activo',
                'icono'   => 'bi-shield-x',
                'color'   => 'warning',
                'columnas'=> ['Equipo','Usuario','Ubicación'],
                'filas'   => $sinAntivirusLista->map(fn($r) => [
                    $r->equipo,
                    trim($r->usuario) ?: '—',
                    $r->ubicacion ?? '—',
                ]),
            ])
        </div>

    </div>

    <div class="row g-3 mb-4">

        {{-- Más antiguos --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Top 10 equipos más antiguos',
                'icono'   => 'bi-hourglass-split',
                'color'   => 'secondary',
                'columnas'=> ['Equipo','Marca','Usuario','Fecha creación'],
                'filas'   => $masAntiguos->map(fn($r) => [
                    $r->equipo,
                    $r->marca ?? '—',
                    trim($r->usuario) ?: '—',
                    $r->date_creation ? \Carbon\Carbon::parse($r->date_creation)->format('d/m/Y') : '—',
                ]),
            ])
        </div>

        {{-- Recientes --}}
        <div class="col-lg-6">
            @include('inventario._tabla_alerta', [
                'titulo'  => 'Equipos agregados último mes',
                'icono'   => 'bi-calendar-plus-fill',
                'color'   => 'success',
                'columnas'=> ['Equipo','Marca','Usuario','Fecha ingreso'],
                'filas'   => $recientes->map(fn($r) => [
                    $r->equipo,
                    $r->marca ?? '—',
                    trim($r->usuario) ?: '—',
                    $r->date_creation ? \Carbon\Carbon::parse($r->date_creation)->format('d/m/Y') : '—',
                ]),
            ])
        </div>

    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const COLORS = [
    '#2563eb','#16a34a','#d97706','#dc2626','#7c3aed',
    '#0891b2','#be185d','#65a30d','#ea580c','#0f172a',
];

// ── Torta SO ──────────────────────────────────────────────────────────────
const soLabels  = @json($porSO->pluck('name'));
const soData    = @json($porSO->pluck('total'));
new Chart(document.getElementById('chartSO'), {
    type: 'doughnut',
    data: { labels: soLabels, datasets: [{ data: soData, backgroundColor: COLORS, borderWidth: 2 }] },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
    }
});

// ── Barras versión agente ─────────────────────────────────────────────────
const agLabels = @json($porVersionAgente->sortByDesc('version')->pluck('version')->map(fn($v) => 'v'.$v)->values());
const agData   = @json($porVersionAgente->sortByDesc('version')->pluck('total')->values());
const latest   = @json($versionLatest);
const agColors = agLabels.map(l => l === 'v'+latest ? '#16a34a' : '#f59e0b');
new Chart(document.getElementById('chartAgente'), {
    type: 'bar',
    data: { labels: agLabels, datasets: [{ data: agData, backgroundColor: agColors, borderRadius: 4 }] },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    }
});

// ── Barras ubicación ──────────────────────────────────────────────────────
const ubLabels = @json($porUbicacion->pluck('ubicacion'));
const ubData   = @json($porUbicacion->pluck('total'));
new Chart(document.getElementById('chartUbicacion'), {
    type: 'bar',
    data: { labels: ubLabels, datasets: [{ data: ubData, backgroundColor: '#6366f1', borderRadius: 4 }] },
    options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { ticks: { font: { size: 10 } } }
        },
    }
});
</script>
@endpush
@endsection
