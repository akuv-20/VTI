@extends('layouts.app')

@section('content')
<style>
    .eidd-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
        border-radius: 12px;
        padding: 1.5rem 1.75rem;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 2rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .eidd-gauge { position: relative; width: 130px; height: 130px; flex-shrink: 0; }
    .eidd-gauge svg { transform: rotate(-90deg); }
    .eidd-gauge-center {
        position: absolute; inset: 0;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
    }
    .eidd-gauge-val   { font-size: 1.85rem; font-weight: 800; line-height: 1; }
    .eidd-gauge-label { font-size: .64rem; text-transform: uppercase; letter-spacing: .09em; color: #94a3b8; margin-top: 3px; }

    .eidd-hero-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
    .eidd-stat-val   { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
    .eidd-stat-label { font-size: .7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-top: 2px; }

    /* Tarjeta de regla */
    .eidd-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem 1.15rem;
        height: 100%;
        transition: box-shadow .15s, border-color .15s;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        color: inherit;
    }
    .eidd-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 3px 12px rgba(0,0,0,.07);
        color: inherit;
    }
    .eidd-card.na { background: #f8fafc; opacity: .85; }

    .eidd-card-head {
        display: flex; align-items: flex-start;
        justify-content: space-between; gap: .6rem;
        margin-bottom: .55rem;
    }
    .eidd-card-title { font-size: .87rem; font-weight: 700; color: #1e293b; line-height: 1.3; }
    .eidd-card-meta  { font-size: .66rem; color: #94a3b8; margin-top: 2px; }
    .eidd-card-meta code { font-size: .64rem; color: #cbd5e1; }
    .eidd-card-pct   { font-size: 1.25rem; font-weight: 800; line-height: 1; white-space: nowrap; }

    .eidd-bar {
        display: flex; height: 8px;
        border-radius: 5px; overflow: hidden;
        background: #f1f5f9; margin-bottom: .55rem;
    }
    .eidd-bar-seg { transition: width .5s ease; }

    .eidd-card-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: .5rem; margin-top: auto; padding-top: .3rem;
        font-size: .74rem;
    }
    .eidd-fallos { font-weight: 700; }
    .eidd-ver { color: #94a3b8; font-size: .7rem; }

    .eidd-sev {
        font-size: .6rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .05em;
        padding: 1px 6px; border-radius: 4px;
    }
    .eidd-sev-error { background: #fee2e2; color: #dc2626; }
    .eidd-sev-adv   { background: #fef3c7; color: #b45309; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-heart-pulse me-2" style="color:#0078d4"></i>Salud de datos
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.entra_id.reglas') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-sliders me-1"></i>Reglas
            </a>
            <a href="{{ route('admin.entra_id.inspector') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clipboard2-data me-1"></i>Inspector
            </a>
            <a href="{{ route('admin.entra_id.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i>Cuentas
            </a>
        </div>
    </div>

    @if(isset($graphError))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $graphError }}
        </div>
    @else

    {{-- ── Hero ──────────────────────────────────────────────────────────── --}}
    @php
        $scoreColor = $scoreGlobal >= 85 ? '#22c55e' : ($scoreGlobal >= 60 ? '#eab308' : '#ef4444');
        $circ       = 2 * M_PI * 54;
        $offset     = $circ - ($scoreGlobal / 100 * $circ);
    @endphp
    <div class="eidd-hero">
        <div class="eidd-gauge">
            <svg width="130" height="130">
                <circle cx="65" cy="65" r="54" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="11"/>
                <circle cx="65" cy="65" r="54" fill="none" stroke="{{ $scoreColor }}" stroke-width="11"
                        stroke-linecap="round"
                        stroke-dasharray="{{ $circ }}"
                        stroke-dashoffset="{{ $offset }}"/>
            </svg>
            <div class="eidd-gauge-center">
                <div class="eidd-gauge-val" style="color:{{ $scoreColor }}">{{ $scoreGlobal }}%</div>
                <div class="eidd-gauge-label">Salud global</div>
            </div>
        </div>

        <div class="eidd-hero-stats">
            <div>
                <div class="eidd-stat-val">{{ number_format($total) }}</div>
                <div class="eidd-stat-label">Cuentas totales</div>
            </div>
            <div>
                <div class="eidd-stat-val" style="color:#4ade80">{{ number_format($habilitados) }}</div>
                <div class="eidd-stat-label">Habilitadas</div>
            </div>
            <div>
                <div class="eidd-stat-val" style="color:#f87171">{{ number_format($deshabilitados) }}</div>
                <div class="eidd-stat-label">Deshabilitadas</div>
            </div>
            <div>
                <div class="eidd-stat-val" style="color:#fbbf24">{{ number_format($totalHallazgos) }}</div>
                <div class="eidd-stat-label">Hallazgos</div>
            </div>
            <div>
                <div class="eidd-stat-val">{{ $resultados->count() }}</div>
                <div class="eidd-stat-label">Reglas activas</div>
            </div>
        </div>
    </div>

    @unless($tieneSignIn)
        <div class="alert alert-warning d-flex align-items-start gap-2 py-2" style="font-size:.83rem">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                Las reglas de <strong>actividad reciente</strong> no se pudieron evaluar porque la
                aplicación de Azure no tiene el permiso <code>AuditLog.Read.All</code>.
                Agrégalo como permiso de aplicación y otorga consentimiento de administrador.
            </div>
        </div>
    @endunless

    @if($resultados->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-sliders" style="font-size:2.5rem;opacity:.35"></i>
            <p class="mt-2 mb-3">No hay reglas activas.</p>
            <a href="{{ route('admin.entra_id.reglas') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Configurar reglas
            </a>
        </div>
    @else

    {{-- ── Tarjetas por regla ────────────────────────────────────────────── --}}
    <div class="row g-3">
        @foreach($resultados as $r)
        @php
            $regla = $r['regla'];
            $na    = !$r['disponible'];
            $score = $r['score'] ?? 0;
            $color = $na ? '#94a3b8' : ($score >= 85 ? '#22c55e' : ($score >= 60 ? '#eab308' : '#ef4444'));

            $ev     = max(1, $r['evaluadas']);
            $pctOk  = $r['ok']     / $ev * 100;
            $pctBad = $r['fallos'] / $ev * 100;
        @endphp
        <div class="col-12 col-md-6 col-xl-4">
            <a href="{{ $na ? '#' : route('admin.entra_id.hallazgos', $regla) }}"
               class="eidd-card {{ $na ? 'na' : '' }}"
               @if($na) onclick="return false" style="cursor:default" @endif>

                <div class="eidd-card-head">
                    <div style="min-width:0">
                        <div class="eidd-card-title">{{ $regla->etiqueta }}</div>
                        <div class="eidd-card-meta">
                            {{ $regla->tipo_etiqueta }}
                            @if($regla->campo)
                                · <code>{{ $regla->campo }}</code>
                            @endif
                        </div>
                    </div>
                    <div class="eidd-card-pct" style="color:{{ $color }}">
                        {{ $na ? '—' : $score . '%' }}
                    </div>
                </div>

                @if($na)
                    <div class="text-muted" style="font-size:.75rem;line-height:1.4">
                        <i class="bi bi-lock me-1"></i>{{ $r['motivo'] }}
                    </div>
                @else
                    <div class="eidd-bar">
                        @if($pctOk > 0)
                            <div class="eidd-bar-seg" style="width:{{ $pctOk }}%;background:#22c55e"></div>
                        @endif
                        @if($pctBad > 0)
                            <div class="eidd-bar-seg" style="width:{{ $pctBad }}%;background:{{ $regla->severidad === 'error' ? '#ef4444' : '#eab308' }}"></div>
                        @endif
                    </div>

                    <div class="eidd-card-foot">
                        <span class="eidd-fallos" style="color:{{ $r['fallos'] > 0 ? ($regla->severidad === 'error' ? '#dc2626' : '#b45309') : '#22c55e' }}">
                            @if($r['fallos'] > 0)
                                {{ number_format($r['fallos']) }} cuenta{{ $r['fallos'] == 1 ? '' : 's' }}
                            @else
                                <i class="bi bi-check-circle-fill me-1"></i>Sin hallazgos
                            @endif
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="eidd-sev {{ $regla->severidad === 'error' ? 'eidd-sev-error' : 'eidd-sev-adv' }}">
                                {{ $regla->severidad === 'error' ? 'error' : 'aviso' }}
                            </span>
                            @if($r['fallos'] > 0)
                                <span class="eidd-ver">ver <i class="bi bi-arrow-right"></i></span>
                            @endif
                        </span>
                    </div>
                @endif
            </a>
        </div>
        @endforeach
    </div>

    @endif
    @endif
</div>
@endsection
