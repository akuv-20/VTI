@extends('layouts.app')

@php
    use App\Services\RegistroMfa;

    $ruta = fn($p = []) => route('admin.entra_id.mfa.index', $p);
@endphp

@section('content')
<style>
    /* mfa- : resumen de MFA */
    .mfa-panel { max-width: 1000px; }

    .mfa-hero {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 1.75rem 2rem; margin-bottom: 1rem;
    }
    .mfa-hero-fila { display: flex; align-items: baseline; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .mfa-hero-num {
        font-size: 4rem; font-weight: 800; line-height: .9;
        letter-spacing: -.03em; font-variant-numeric: tabular-nums;
    }
    .mfa-hero-txt { font-size: 1rem; color: #334155; max-width: 30rem; }

    .mfa-barra { height: 2.25rem; display: flex; border-radius: 5px; overflow: hidden; background: #f1f5f9; }
    .mfa-barra span { display: flex; align-items: center; justify-content: center;
                      font-size: .74rem; font-weight: 700; color: #fff; }

    .mfa-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: .7rem; margin-bottom: 1rem; }
    .mfa-tile {
        background: #fff; border: 1px solid #e2e8f0; border-left-width: 4px;
        border-radius: 10px; padding: .9rem 1.05rem; display: block;
        color: inherit; text-decoration: none; transition: box-shadow .12s;
    }
    .mfa-tile:hover { box-shadow: 0 3px 12px rgba(0,0,0,.08); color: inherit; }
    .mfa-tile-val { font-size: 1.9rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .mfa-tile-lbl { font-size: .75rem; color: #64748b; margin-top: .3rem; display: flex; align-items: center; gap: .3rem; }

    .mfa-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 1.3rem 1.5rem; margin-bottom: 1rem;
    }
    .mfa-card h6 {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .09em;
        color: #64748b; font-weight: 700; margin-bottom: 1rem;
    }

    .mfa-lista { list-style: none; padding: 0; margin: 0; }
    .mfa-lista li {
        display: flex; justify-content: space-between; gap: 1rem;
        padding: .45rem 0; border-bottom: 1px solid #f1f5f9; font-size: .86rem;
    }
    .mfa-lista li:last-child { border-bottom: 0; }
    .mfa-lista .n { font-weight: 700; font-variant-numeric: tabular-nums; color: #0f172a; }

    .mfa-alerta {
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
        padding: 1.1rem 1.4rem; margin-bottom: 1rem;
    }
    .mfa-alerta h6 { color: #b91c1c; }
    .mfa-alerta p { margin-bottom: 0; color: #7f1d1d; font-size: .92rem; }
    .mfa-alerta a { color: #7f1d1d; font-weight: 600; }

    .mfa-pie { font-size: .76rem; color: #94a3b8; line-height: 1.7; }

    @media print { .mfa-noprint { display: none !important; } }
</style>

<div class="container-fluid vti-page mfa-panel">

    <div class="vti-page-header mfa-noprint">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-shield-lock me-2" style="color:#7c3aed"></i>Autenticación multifactor</span>
            <span class="badge bg-light text-secondary border" style="font-size:.7rem">Entra ID</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ $ruta() }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Ver listado
            </a>
            <form method="POST" action="{{ route('admin.entra_id.mfa.refrescar') }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </form>
        </div>
    </div>

    {{-- Cifra principal y composición --}}
    <div class="mfa-hero">
        <div class="mfa-hero-fila">
            <div class="mfa-hero-num" style="color:#dc2626">{{ $resumen['sin_mfa_pct'] }}%</div>
            <div class="mfa-hero-txt">
                <strong>{{ number_format($resumen['sin_mfa']) }} de {{ number_format($resumen['total']) }}
                cuentas con licencia no tienen ningún segundo factor configurado.</strong>
                Hoy entran solo con su contraseña.
            </div>
        </div>

        <div class="mfa-barra">
            @if($resumen['con_mfa'] > 0)
            <span style="width:{{ $resumen['con_mfa'] * 100 / max(1,$resumen['total']) }}%;background:#16a34a"
                  title="Con MFA: {{ number_format($resumen['con_mfa']) }}">
                {{ $resumen['con_mfa'] * 100 / max(1,$resumen['total']) > 8 ? number_format($resumen['con_mfa']) : '' }}
            </span>
            @endif
            @if($resumen['sin_mfa'] > 0)
            <span style="width:{{ $resumen['sin_mfa'] * 100 / max(1,$resumen['total']) }}%;background:#dc2626"
                  title="Sin MFA: {{ number_format($resumen['sin_mfa']) }}">
                {{ $resumen['sin_mfa'] * 100 / max(1,$resumen['total']) > 8 ? number_format($resumen['sin_mfa']) : '' }}
            </span>
            @endif
            @if($resumen['sin_dato'] > 0)
            <span style="width:{{ $resumen['sin_dato'] * 100 / max(1,$resumen['total']) }}%;background:#94a3b8"
                  title="Sin dato: {{ number_format($resumen['sin_dato']) }}"></span>
            @endif
        </div>
    </div>

    {{-- Los tres números que se pidieron --}}
    <div class="mfa-tiles">
        <a href="{{ $ruta() }}" class="mfa-tile" style="border-left-color:#3b82f6">
            <div class="mfa-tile-val">{{ number_format($resumen['total']) }}</div>
            <div class="mfa-tile-lbl"><i class="bi bi-people"></i>Total de usuarios</div>
        </a>
        <a href="{{ $ruta(['estado' => 'con']) }}" class="mfa-tile" style="border-left-color:#16a34a">
            <div class="mfa-tile-val" style="color:#16a34a">{{ number_format($resumen['con_mfa']) }}</div>
            <div class="mfa-tile-lbl"><i class="bi bi-shield-check"></i>MFA activo</div>
        </a>
        <a href="{{ $ruta(['estado' => 'sin']) }}" class="mfa-tile" style="border-left-color:#dc2626">
            <div class="mfa-tile-val" style="color:#dc2626">{{ number_format($resumen['sin_mfa']) }}</div>
            <div class="mfa-tile-lbl"><i class="bi bi-shield-x"></i>MFA inactivo</div>
        </a>
        @if($resumen['sin_dato'] > 0)
        <a href="{{ $ruta(['estado' => 'sin_dato']) }}" class="mfa-tile" style="border-left-color:#94a3b8">
            <div class="mfa-tile-val" style="color:#64748b">{{ number_format($resumen['sin_dato']) }}</div>
            <div class="mfa-tile-lbl"><i class="bi bi-question-circle"></i>Sin dato en el reporte</div>
        </a>
        @endif
    </div>

    {{-- Administradores: el riesgo concentrado --}}
    @if($resumen['admins_sin_mfa'] > 0)
    <div class="mfa-alerta">
        <h6>Cuentas administrativas sin segundo factor</h6>
        <p>
            <strong>{{ $resumen['admins_sin_mfa'] }} de {{ $resumen['admins'] }}</strong> cuentas con
            rol administrativo no tienen MFA configurado. Son las que más pueden hacer si alguien
            se apodera de su contraseña, y hoy solo las protege eso.
            <a href="{{ $ruta(['solo_admins' => 1, 'estado' => 'sin']) }}">Ver cuáles son →</a>
        </p>
    </div>
    @endif

    <div class="row g-3">
        {{-- Métodos --}}
        <div class="col-lg-6">
            <div class="mfa-card h-100">
                <h6>Métodos configurados por quienes sí tienen MFA</h6>
                @if(empty($resumen['metodos']))
                    <p class="text-muted mb-0" style="font-size:.88rem">Nadie tiene métodos registrados.</p>
                @else
                <ul class="mfa-lista">
                    @foreach($resumen['metodos'] as $metodo => $n)
                    <li>
                        <span>{{ RegistroMfa::METODOS[$metodo] ?? $metodo }}</span>
                        <span class="n">{{ number_format($n) }}</span>
                    </li>
                    @endforeach
                </ul>
                <p class="mt-3 mb-0" style="font-size:.78rem;color:#64748b">
                    Una persona puede tener más de un método, así que la suma supera
                    las {{ number_format($resumen['con_mfa']) }} cuentas con MFA.
                </p>
                @endif
            </div>
        </div>

        {{-- Departamentos --}}
        <div class="col-lg-6">
            <div class="mfa-card h-100">
                <h6>Dónde se concentran las cuentas sin MFA</h6>
                @if(empty($resumen['departamentos']))
                    <p class="text-muted mb-0" style="font-size:.88rem">Sin datos de departamento.</p>
                @else
                <ul class="mfa-lista">
                    @foreach($resumen['departamentos'] as $dep => $n)
                    <li>
                        <a href="{{ $ruta(['estado' => 'sin', 'departamento' => $dep]) }}"
                           class="text-decoration-none text-body">{{ $dep }}</a>
                        <span class="n">{{ number_format($n) }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="mfa-card">
        <h6>Con licencia y sin licencia</h6>
        <p style="font-size:.88rem;color:#475569">
            El universo son todas las cuentas internas habilitadas, tengan licencia o no. Las
            cuentas administrativas y de servicio normalmente no la tienen, y exigirla las dejaría
            fuera del tablero justo a las que más importa vigilar.
        </p>
        <ul class="mfa-lista">
            <li>
                <a href="{{ $ruta(['estado' => 'sin', 'licencia' => 'con']) }}" class="text-decoration-none text-body">
                    Personas con licencia sin MFA
                </a>
                <span class="n">{{ number_format($resumen['con_licencia_sin_mfa']) }}
                    <span class="text-muted fw-normal">de {{ number_format($resumen['con_licencia']) }}</span></span>
            </li>
            <li>
                <a href="{{ $ruta(['estado' => 'sin', 'licencia' => 'sin']) }}" class="text-decoration-none text-body">
                    Cuentas sin licencia sin MFA
                </a>
                <span class="n">{{ number_format($resumen['sin_licencia_sin_mfa']) }}
                    <span class="text-muted fw-normal">de {{ number_format($resumen['sin_licencia']) }}</span></span>
            </li>
        </ul>
    </div>

    <div class="mfa-card">
        <h6>Qué mide este número</h6>
        <p class="mb-0" style="font-size:.88rem;color:#475569">
            Mide si la persona tiene un segundo factor <strong>configurado</strong> —Authenticator,
            SMS, llave o Windows Hello—, no si se le <strong>exige</strong> usarlo. La exigencia vive
            en las directivas de acceso condicional, y leerlas necesita el permiso
            <code>Policy.Read.All</code>, que la aplicación todavía no tiene.
            De todas formas, quien no lo tiene configurado no puede usarlo aunque se lo exijan.
        </p>
    </div>

    <p class="mfa-pie">
        Datos de Microsoft Graph: reporte <code>authenticationMethods/userRegistrationDetails</code>.
        Universo: cuentas de tipo Member, habilitadas y con licencia asignada.<br>
        Actualizado {{ $generado->diffForHumans() }} · se recalcula cada 6 horas.
    </p>

</div>
@endsection
