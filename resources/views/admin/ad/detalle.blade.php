@extends('layouts.app')

@section('content')
<style>
/* add- : detalle de cuenta de Active Directory */
.add-wrap      { max-width:900px }

/* Banner del veredicto: es lo primero y muchas veces lo único que se mira. */
.add-veredicto { border-radius:12px; padding:1.1rem 1.25rem; display:flex; gap:.9rem;
                 align-items:flex-start; margin-bottom:1rem }
.add-veredicto i        { font-size:1.6rem; line-height:1; flex:0 0 auto; margin-top:.1rem }
.add-veredicto h5       { font-size:1.05rem; font-weight:700; margin:0 0 .2rem }
.add-veredicto p        { font-size:.86rem; margin:0; opacity:.92 }
.add-ok       { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d }
.add-aviso    { background:#fffbeb; border:1px solid #fde68a; color:#b45309 }
.add-problema { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c }

.add-card      { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem; margin-bottom:1rem }
.add-card h6   { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase;
                 letter-spacing:.05em; margin-bottom:.8rem }

/* Señales: una línea por hallazgo, con el color como primer indicio */
.add-senal     { display:flex; align-items:flex-start; gap:.65rem; padding:.5rem 0;
                 border-bottom:1px solid #f1f5f9; font-size:.87rem }
.add-senal:last-child { border-bottom:0 }
.add-senal i   { font-size:1.05rem; flex:0 0 1.3rem; text-align:center; margin-top:.05rem }
.add-senal-tit { font-weight:600; color:#1e293b }
.add-senal-det { font-size:.79rem; color:#64748b }
.add-i-ok       { color:#16a34a }
.add-i-aviso    { color:#d97706 }
.add-i-problema { color:#dc2626 }

.add-datos     { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:.1rem .9rem }
.add-dato      { padding:.45rem 0; border-bottom:1px solid #f1f5f9 }
.add-dato dt   { font-size:.72rem; color:#94a3b8; font-weight:600; margin:0 }
.add-dato dd   { font-size:.86rem; color:#1e293b; margin:0; font-variant-numeric:tabular-nums }

.add-pol       { display:flex; gap:1.4rem; flex-wrap:wrap; font-size:.82rem; color:#475569 }
.add-pol b     { display:block; font-size:1.15rem; color:#1e293b; font-variant-numeric:tabular-nums }
</style>

@php
    $u   = $estado['usuario'];
    $res = $estado['resumen'];
    $pol = $estado['politica'];

    $iniciales = collect(explode(' ', (string) $u['nombre']))->take(2)
        ->map(fn($p) => strtoupper(substr($p, 0, 1)))->join('');
@endphp

<div class="container-fluid vti-page">
    <div class="add-wrap">

        <div class="vti-page-header">
            <h4><i class="bi bi-person-vcard me-2"></i>Estado de la cuenta</h4>
            <div class="d-flex gap-2">
                <a href="{{ $urlEditar }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
                <a href="{{ $urlVolver }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>

        {{-- Identificación --}}
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                 style="width:46px;height:46px;font-size:.95rem;background:#2563eb">{{ $iniciales }}</div>
            <div style="min-width:0">
                <div class="fw-bold" style="font-size:1.05rem;color:#1e293b">{{ $u['nombre'] }}</div>
                <div class="text-muted" style="font-size:.8rem">
                    <span class="font-monospace">{{ $u['sam'] }}</span>
                    @if($u['mail']) · {{ $u['mail'] }} @endif
                    · {{ $dominio }}
                </div>
                @if($u['cargo'] || $u['departamento'])
                    <div class="text-muted" style="font-size:.78rem">
                        {{ $u['cargo'] }}@if($u['cargo'] && $u['departamento']) · @endif{{ $u['departamento'] }}
                    </div>
                @endif
            </div>
        </div>

        {{-- ── El veredicto ─────────────────────────────────────────────────
             Responde de una la pregunta con la que llega el técnico: ¿por qué
             este usuario no puede entrar? --}}
        <div class="add-veredicto add-{{ $res['nivel'] }}">
            <i class="bi {{ $res['nivel'] === 'ok' ? 'bi-check-circle-fill'
                          : ($res['nivel'] === 'aviso' ? 'bi-exclamation-triangle-fill' : 'bi-x-octagon-fill') }}"></i>
            <div>
                <h5>{{ $res['titulo'] }}</h5>
                <p>{{ $res['detalle'] }}</p>
            </div>
        </div>

        @if($estado['aviso'])
            <div class="alert alert-warning py-2" style="font-size:.8rem">
                <i class="bi bi-info-circle-fill me-1"></i>{{ $estado['aviso'] }}
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="add-card h-100">
                    <h6><i class="bi bi-clipboard-check me-1"></i>Revisión</h6>
                    @foreach($estado['senales'] as $s)
                        <div class="add-senal">
                            <i class="bi {{ $s['icono'] }} add-i-{{ $s['nivel'] }}"></i>
                            <div>
                                <div class="add-senal-tit">{{ $s['titulo'] }}</div>
                                <div class="add-senal-det">{{ $s['detalle'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-6">
                <div class="add-card h-100">
                    <h6><i class="bi bi-calendar3 me-1"></i>Fechas</h6>
                    <dl class="add-datos mb-0">
                        @foreach($estado['datos'] as $etiqueta => $valor)
                            <div class="add-dato">
                                <dt>{{ $etiqueta }}</dt>
                                <dd>{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>

        @if($estado['intentos'])
        <div class="add-card">
            <h6><i class="bi bi-shield-exclamation me-1"></i>Intentos fallidos por controlador</h6>
            <div class="add-pol">
                @foreach($estado['intentos'] as $host => $n)
                    <div>
                        <b class="{{ $n !== null && $pol['intentos'] && $n >= $pol['intentos'] ? 'text-danger' : '' }}">
                            {{ $n === null ? '—' : $n }}
                        </b>
                        <span class="font-monospace" style="font-size:.75rem">{{ $host }}</span>
                    </div>
                @endforeach
            </div>
            <div class="text-muted mt-2" style="font-size:.75rem">
                Este contador no se replica entre controladores: cada uno lleva el suyo, así que
                el que importa es el más alto.
            </div>
        </div>
        @endif

        @if($pol['intentos'] || $pol['vigencia_dias'])
        <div class="add-card">
            <h6><i class="bi bi-sliders me-1"></i>Política del dominio</h6>
            <div class="add-pol">
                @if($pol['intentos'])
                    <div><b>{{ $pol['intentos'] }}</b> intentos antes de bloquear</div>
                @endif
                @if($pol['duracion_min'])
                    <div><b>{{ $pol['duracion_min'] }}</b> minutos de bloqueo</div>
                @endif
                @if($pol['vigencia_dias'])
                    <div><b>{{ $pol['vigencia_dias'] }}</b> días de vigencia</div>
                @endif
                @if($pol['largo_min'])
                    <div><b>{{ $pol['largo_min'] }}</b> caracteres mínimo</div>
                @endif
            </div>
        </div>
        @endif

        {{-- Lo que este tablero no puede responder. Vale decirlo: si no, el
             técnico da vueltas buscando acá algo que no está en el directorio. --}}
        <div class="text-muted" style="font-size:.76rem;line-height:1.5">
            <i class="bi bi-lightbulb me-1"></i>
            <strong>Si la cuenta se bloquea una y otra vez</strong>, la causa no está en Active Directory:
            suele ser un dispositivo con la contraseña antigua guardada —el correo del teléfono, una sesión
            de escritorio remoto abierta o un servicio—. Averiguar cuál exige revisar el evento 4740 del
            registro de seguridad del controlador de dominio principal.
        </div>

        <div class="mt-3">
            <a href="{{ $urlIndex }}" style="font-size:.82rem">← Volver al listado</a>
        </div>

    </div>
</div>
@endsection
