@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-grid-3x3-gap me-2"></i>Resumen Anual por Servicio</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('facturas.resumen_servicios') }}" class="d-flex gap-2 align-items-center">
                <select name="anio" class="form-select form-select-sm" style="width:90px" onchange="this.form.submit()">
                    @foreach($aniosDisponibles as $a)
                        <option value="{{ $a }}" {{ $a == $anio ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('facturas.resumen') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-journal-bookmark me-1"></i>Por Cuenta Contable
            </a>
            <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Ver listado
            </a>
        </div>
    </div>

    @php
        $totalAnual = collect($totalesCol)->sum();
    @endphp

    {{-- Tarjetas resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">Total Neto {{ $anio }}</div>
                    <div class="fs-5 fw-bold text-primary">$ {{ number_format($totalAnual, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">IVA estimado (19%)</div>
                    <div class="fs-5 fw-bold text-secondary">$ {{ number_format($totalAnual * 0.19, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">Servicios con facturación</div>
                    <div class="fs-5 fw-bold text-success">{{ count($servicios) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pestañas --}}
    <ul class="nav nav-tabs mb-3" id="tabResumen" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-tabla" data-bs-toggle="tab" data-bs-target="#pane-tabla"
                    type="button" role="tab">
                <i class="bi bi-table me-1"></i>Tabla
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-grafico" data-bs-toggle="tab" data-bs-target="#pane-grafico"
                    type="button" role="tab">
                <i class="bi bi-bar-chart-line me-1"></i>Gráfico
            </button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- ── PESTAÑA TABLA ───────────────────────────────────────────────── --}}
        <div class="tab-pane fade show active" id="pane-tabla" role="tabpanel">
            <div class="vti-table-wrapper" style="overflow-x:auto">
                <table class="vti-table" style="font-size:.82rem;min-width:1000px">
                    <thead>
                        <tr>
                            <th style="min-width:200px">Servicio</th>
                            <th style="min-width:130px">Empresa / Compañía</th>
                            <th style="min-width:110px" class="text-muted" style="font-size:.75rem">Cuenta Contable</th>
                            @foreach(range(1,12) as $m)
                                <th class="text-end" style="min-width:82px">{{ $meses[$m] }}</th>
                            @endforeach
                            <th class="text-end" style="min-width:100px;background:rgba(59,130,246,.07)">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($servicios as $id => $s)
                        @php
                            $totalFila = collect($matriz[$id] ?? [])->sum();
                            $maxMes    = !empty($matriz[$id]) ? max($matriz[$id]) : 0;
                            $cc        = $s->cuentaContable;
                        @endphp
                        <tr>
                            <td>
                                @if($s->codigo_servicio)
                                    <span class="badge bg-light text-secondary border me-1" style="font-size:.7rem">{{ $s->codigo_servicio }}</span>
                                @endif
                                <span class="fw-semibold">{{ $s->servicio }}</span>
                                @if($s->concepto)
                                    <span class="text-muted small d-block">{{ $s->concepto }}</span>
                                @endif
                            </td>
                            <td style="font-size:.79rem">
                                <div>{{ $s->empresa->nombre ?? '—' }}</div>
                                <div class="text-muted">{{ $s->compania->nombre ?? '—' }}</div>
                            </td>
                            <td style="font-size:.76rem;color:#64748b">
                                @if($cc)
                                    <span title="{{ $cc->nombre_cuenta }}">{{ $cc->numero_cuenta }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @foreach(range(1,12) as $m)
                            @php $val = $matriz[$id][$m] ?? 0; @endphp
                            <td class="text-end {{ $val > 0 ? 'fw-semibold' : 'text-muted' }}"
                                style="{{ $val > 0 && $maxMes > 0 ? 'background:rgba(59,130,246,'.min(0.18, ($val/$maxMes)*0.18).')' : '' }}">
                                @if($val > 0)
                                    $ {{ number_format($val, 0, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            @endforeach
                            <td class="text-end fw-bold" style="background:rgba(59,130,246,.07)">
                                $ {{ number_format($totalFila, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr class="vti-empty">
                            <td colspan="16">No hay facturas de servicios periódicos registradas para {{ $anio }}.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($servicios) > 0)
                    <tfoot>
                        <tr class="fw-bold" style="border-top:2px solid #dee2e6;background:rgba(59,130,246,.06)">
                            <td colspan="3" class="text-end text-muted small pe-3">Total mes:</td>
                            @foreach(range(1,12) as $m)
                            @php $col = $totalesCol[$m] ?? 0; @endphp
                            <td class="text-end {{ $col > 0 ? 'text-primary' : 'text-muted' }}">
                                @if($col > 0)
                                    $ {{ number_format($col, 0, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            @endforeach
                            <td class="text-end text-primary" style="background:rgba(59,130,246,.12)">
                                $ {{ number_format($totalAnual, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-2">
                <i class="bi bi-info-circle me-1"></i>
                Solo incluye facturas mensuales de servicios periódicos. Los montos corresponden a valor neto.
            </p>
        </div>

        {{-- ── PESTAÑA GRÁFICO ─────────────────────────────────────────────── --}}
        <div class="tab-pane fade" id="pane-grafico" role="tabpanel">
            @if(count($servicios) > 0)

            {{-- Selector de servicio --}}
            <div class="mb-3 d-flex align-items-center gap-2" style="max-width:560px">
                <label for="selectServicio" class="text-muted small text-nowrap mb-0">
                    <i class="bi bi-funnel me-1"></i>Servicio:
                </label>
                <select id="selectServicio" class="form-select form-select-sm">
                    @foreach($servicios as $sid => $s)
                        <option value="{{ $sid }}">
                            {{ $s->codigo_servicio ? '[' . $s->codigo_servicio . '] ' : '' }}{{ $s->servicio }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Card con stats + gráfico --}}
            <div class="card border-0 shadow-sm p-3 mb-2">
                {{-- Mini stats --}}
                <div class="row g-0 mb-3 text-center" style="border-bottom:1px solid #f1f5f9;padding-bottom:.8rem">
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Total anual</div>
                        <div class="fw-bold text-primary fs-6" id="statTotal">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Promedio mensual</div>
                        <div class="fw-bold text-secondary fs-6" id="statProm">—</div>
                    </div>
                    <div class="col-6 col-md-3 mt-2 mt-md-0">
                        <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Mes más alto</div>
                        <div class="fw-bold text-success fs-6" id="statMax">—</div>
                    </div>
                    <div class="col-6 col-md-3 mt-2 mt-md-0">
                        <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Meses facturados</div>
                        <div class="fw-bold fs-6" id="statMeses">—</div>
                    </div>
                </div>
                {{-- Gráfico --}}
                <canvas id="chartServicio" style="max-height:360px"></canvas>
            </div>

            <p class="text-muted small mt-1">
                <i class="bi bi-info-circle me-1"></i>
                Facturación mensual del servicio seleccionado. Montos en valor neto.
            </p>

            @else
            <div class="text-center text-muted py-5">
                <i class="bi bi-bar-chart-line fs-1 d-block mb-2 opacity-25"></i>
                No hay datos para graficar en {{ $anio }}.
            </div>
            @endif
        </div>

    </div>{{-- /tab-content --}}

</div>
@endsection

@push('scripts')
@if(count($servicios) > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const meses  = @json(array_values(array_slice($meses, 1)));
    const matriz = @json($matriz);

    const fmt  = v => '$ ' + Math.round(v).toLocaleString('es-CL');
    const fmtK = v => v >= 1e6
        ? '$ ' + (v / 1e6).toFixed(1) + 'M'
        : (v >= 1e3 ? '$ ' + (v / 1e3).toFixed(0) + 'K' : '$ ' + Math.round(v));

    function dataForService(id) {
        return Array.from({length: 12}, (_, i) =>
            (matriz[id] && matriz[id][i + 1]) ? matriz[id][i + 1] : 0
        );
    }

    function updateStats(data) {
        const total   = data.reduce((s, v) => s + v, 0);
        const active  = data.filter(v => v > 0);
        const maxVal  = active.length ? Math.max(...data) : 0;
        const maxIdx  = data.indexOf(maxVal);

        document.getElementById('statTotal').textContent = total   ? fmt(total)  : '—';
        document.getElementById('statProm').textContent  = active.length
            ? fmt(total / active.length) : '—';
        document.getElementById('statMax').textContent   = maxVal
            ? meses[maxIdx] + ' · ' + fmt(maxVal) : '—';
        document.getElementById('statMeses').textContent = active.length + ' / 12';
    }

    const ctx = document.getElementById('chartServicio').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: '',
                data: [],
                backgroundColor: 'rgba(59,130,246,0.72)',
                borderColor:     'rgba(37,99,235,0.9)',
                borderWidth:     1,
                borderRadius:    5,
                borderSkipped:   false,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: items => items[0].label,
                        label: item  => item.raw ? '  ' + fmt(item.raw) : '  —',
                    }
                },
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { callback: fmtK },
                    grid: { color: 'rgba(0,0,0,.05)' },
                },
            },
        },
    });

    function refresh(id) {
        const data = dataForService(id);
        const name = document.getElementById('selectServicio').selectedOptions[0].text.trim();
        chart.data.datasets[0].data  = data;
        chart.data.datasets[0].label = name;
        chart.update('active');
        updateStats(data);
    }

    const sel = document.getElementById('selectServicio');
    refresh(sel.value);
    sel.addEventListener('change', () => refresh(sel.value));

    // Forzar resize al abrir la pestaña (por estar oculta al cargar)
    document.getElementById('tab-grafico').addEventListener('shown.bs.tab', () => chart.resize());
})();
</script>
@endif
@endpush
