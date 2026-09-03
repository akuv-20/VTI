@extends('layouts.app')

@php
    use App\Services\UsoBuzones;

    $cohortes = collect($resumen['cohortes'])->filter(fn($c) => $c['total'] >= 20);
    $picoMes  = $cohortes->sortByDesc(fn($c) => $c['sin_uso'])->keys()->first();
    $pico     = $picoMes ? $resumen['cohortes'][$picoMes] : null;

    $meses = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio',
              '07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
    $nombreMes = fn($m) => $m ? ($meses[substr($m,5,2)] ?? $m) . ' ' . substr($m,0,4) : '';
@endphp

@section('content')
<style>
    /* buz- : informe de uso de buzones */
    .buz-informe { max-width: 1000px; }

    .buz-hero {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 2rem 2.25rem; margin-bottom: 1rem;
        display: flex; align-items: baseline; gap: 1.75rem; flex-wrap: wrap;
    }
    .buz-hero-num {
        font-size: 4.5rem; font-weight: 800; line-height: .9;
        color: #a63a22; letter-spacing: -.03em; font-variant-numeric: tabular-nums;
    }
    .buz-hero-txt { font-size: 1.05rem; color: #334155; max-width: 30rem; }
    .buz-hero-txt strong { color: #0f172a; }

    .buz-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px,1fr)); gap: .7rem; margin-bottom: 1rem; }
    .buz-tile {
        background: #fff; border: 1px solid #e2e8f0; border-left-width: 4px;
        border-radius: 10px; padding: .85rem 1rem; display: block;
        color: inherit; text-decoration: none; transition: box-shadow .12s;
    }
    .buz-tile:hover { box-shadow: 0 3px 12px rgba(0,0,0,.08); color: inherit; }
    .buz-tile-val { font-size: 1.75rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .buz-tile-lbl { font-size: .74rem; color: #64748b; margin-top: .3rem; display: flex; align-items: center; gap: .3rem; }
    .buz-tile-sub { font-size: .68rem; color: #94a3b8; margin-top: .15rem; }

    .buz-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 1.4rem 1.6rem; margin-bottom: 1rem;
    }
    .buz-card h6 {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .09em;
        color: #64748b; font-weight: 700; margin-bottom: 1rem;
    }

    .buz-barra { height: 2rem; display: flex; border-radius: 4px; overflow: hidden; background: #f1f5f9; }
    .buz-barra span { display: block; }

    .buz-cohorte { display: grid; grid-template-columns: 8.5rem 1fr 5.5rem; gap: .8rem; align-items: center; margin-bottom: .55rem; }
    .buz-cohorte-mes { font-size: .82rem; color: #475569; font-weight: 600; }
    .buz-cohorte-bar { height: 1.35rem; background: #f1f5f9; border-radius: 3px; overflow: hidden; display: flex; }
    .buz-cohorte-pct { font-size: .78rem; color: #64748b; text-align: right; font-variant-numeric: tabular-nums; }

    .buz-alerta {
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
        padding: 1.1rem 1.4rem; margin-bottom: 1rem;
    }
    .buz-alerta h6 { color: #b91c1c; }
    .buz-alerta p { margin-bottom: 0; color: #7f1d1d; font-size: .92rem; }

    .buz-lista { list-style: none; padding: 0; margin: 0; }
    .buz-lista li {
        display: flex; justify-content: space-between; gap: 1rem;
        padding: .45rem 0; border-bottom: 1px solid #f1f5f9; font-size: .86rem;
    }
    .buz-lista li:last-child { border-bottom: 0; }
    .buz-lista .n { font-weight: 700; font-variant-numeric: tabular-nums; color: #0f172a; }

    .buz-pie { font-size: .76rem; color: #94a3b8; line-height: 1.7; }

    @media print {
        .buz-noprint { display: none !important; }
        .buz-card, .buz-hero, .buz-tile { break-inside: avoid; }
    }
</style>

<div class="container-fluid vti-page buz-informe">

    <div class="vti-page-header buz-noprint">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-envelope-exclamation me-2" style="color:#a63a22"></i>Uso de buzones</span>
            <span class="badge bg-light text-secondary border" style="font-size:.7rem">unifrutti.com</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.buzones.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Ver listado
            </a>
            <form method="POST" action="{{ route('admin.buzones.refrescar') }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2 buz-noprint">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2 buz-noprint">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($nombresOcultos)
        <div class="alert alert-warning py-2">
            <i class="bi bi-eye-slash-fill me-2"></i>
            El tenant tiene los nombres ofuscados en los informes de Microsoft 365, así que el
            detalle por persona no es fiable. Se apaga en <em>Microsoft 365 admin → Configuración
            de la organización → Informes</em>.
        </div>
    @endif

    {{-- Cifra principal --}}
    <div class="buz-hero">
        <div class="buz-hero-num">{{ $resumen['sin_uso_pct'] }}%</div>
        <div class="buz-hero-txt">
            <strong>{{ number_format($resumen['sin_uso']) }} de {{ number_format($resumen['total']) }} buzones
            con licencia no registran un solo inicio de sesión.</strong>
            Nadie entró nunca, ni por Outlook, ni por el navegador, ni por el teléfono.
        </div>
    </div>

    {{-- Clasificación --}}
    <div class="buz-tiles">
        @foreach(UsoBuzones::CLASES as $clave => [$lbl, $desc, $color, $ico])
        <a href="{{ route('admin.buzones.index', ['clase' => $clave]) }}"
           class="buz-tile" style="border-left-color:{{ $color }}">
            <div class="buz-tile-val" style="color:{{ $color }}">{{ number_format($resumen[$clave]) }}</div>
            <div class="buz-tile-lbl"><i class="bi {{ $ico }}"></i>{{ $lbl }}</div>
            <div class="buz-tile-sub">{{ $desc }}</div>
        </a>
        @endforeach
    </div>

    <div class="buz-card">
        <h6>Composición del universo</h6>
        <div class="buz-barra">
            @foreach(UsoBuzones::CLASES as $clave => [$lbl, $desc, $color])
                @if($resumen[$clave] > 0)
                <span style="width:{{ $resumen[$clave] * 100 / max(1,$resumen['total']) }}%;background:{{ $color }}"
                      title="{{ $lbl }}: {{ number_format($resumen[$clave]) }}"></span>
                @endif
            @endforeach
        </div>
        <p class="mt-3 mb-0" style="font-size:.86rem;color:#475569">
            Se considera <strong>sin uso</strong> únicamente a quien nunca inició sesión. No se usa
            el contador de correos leídos: hay gente que trabaja a diario con miles de correos sin
            marcar como leídos, y tomar «sin leer» por «sin usar» daría falsos positivos.
            @if($resumen['excluidos'] > 0)
                Quedan fuera {{ $resumen['excluidos'] }} buzón(es) compartido(s) excluidos a mano.
            @endif
        </p>
    </div>

    {{-- El correo que se pierde --}}
    @if($resumen['correo_al_vacio'] > 0)
    <div class="buz-alerta">
        <h6>Correo que está cayendo en el vacío</h6>
        <p>
            En los últimos 180 días entraron <strong>{{ number_format($resumen['correo_al_vacio']) }} correos</strong>
            a buzones que nadie abrió jamás, y <strong>{{ number_format($resumen['con_correo']) }}</strong> de ellos
            recibieron más de 50 cada uno. Si ahí va comunicación laboral —prevención de riesgos,
            liquidaciones, avisos de personal— no está llegando a nadie.
        </p>
    </div>
    @endif

    <div class="row g-3">
        {{-- Cohortes --}}
        @if($cohortes->isNotEmpty())
        <div class="col-lg-7">
            <div class="buz-card h-100">
                <h6>Sin uso según cuándo se creó la cuenta</h6>
                @foreach($cohortes->sortKeysDesc()->take(10) as $mes => $c)
                    @php $pct = $c['sin_uso'] * 100 / max(1, $c['total']); @endphp
                    <div class="buz-cohorte">
                        <div class="buz-cohorte-mes">{{ $nombreMes($mes) }}</div>
                        <div class="buz-cohorte-bar">
                            <span style="width:{{ $pct }}%;background:#a63a22"></span>
                        </div>
                        <div class="buz-cohorte-pct">
                            {{ number_format($c['sin_uso']) }}/{{ number_format($c['total']) }}
                        </div>
                    </div>
                @endforeach
                @if($pico && $pico['total'] >= 100 && $pico['sin_uso'] * 100 / $pico['total'] > 60)
                <p class="mt-3 mb-0" style="font-size:.84rem;color:#475569">
                    El problema no está repartido: se concentra en la tanda de
                    <strong>{{ $nombreMes($picoMes) }}</strong>, donde
                    {{ number_format($pico['sin_uso']) }} de {{ number_format($pico['total']) }} buzones
                    ({{ round($pico['sin_uso'] * 100 / $pico['total']) }}%) nunca se abrieron.
                </p>
                @endif
            </div>
        </div>
        @endif

        {{-- Licencias --}}
        <div class="col-lg-5">
            <div class="buz-card h-100">
                <h6>Licencias en buzones nunca abiertos</h6>
                @if(empty($resumen['licencias']))
                    <p class="text-muted mb-0" style="font-size:.88rem">Sin licencias que reportar.</p>
                @else
                <ul class="buz-lista">
                    @foreach($resumen['licencias'] as $sku => $n)
                    <li>
                        <span class="font-monospace" style="font-size:.8rem">{{ $sku }}</span>
                        <span class="n">{{ number_format($n) }}</span>
                    </li>
                    @endforeach
                </ul>
                <p class="mt-3 mb-0" style="font-size:.8rem;color:#64748b">
                    Liberar asignaciones no baja la factura por sí solo: el ahorro está en no
                    renovar el excedente. Ocupan {{ $resumen['mb_sin_uso'] }} GB de almacenamiento.
                </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Departamentos --}}
    @if(!empty($resumen['departamentos']))
    <div class="buz-card">
        <h6>Dónde se concentran los buzones sin uso</h6>
        <div class="row">
            @foreach(array_chunk($resumen['departamentos'], 5, true) as $grupo)
            <div class="col-md-6">
                <ul class="buz-lista">
                    @foreach($grupo as $dep => $n)
                    <li>
                        <a href="{{ route('admin.buzones.index', ['clase' => 'nunca_activado', 'departamento' => $dep]) }}"
                           class="text-decoration-none text-body">{{ $dep }}</a>
                        <span class="n">{{ number_format($n) }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <p class="buz-pie">
        Datos de Microsoft Graph: reportes <code>getEmailActivityUserDetail</code> y
        <code>getMailboxUsageDetail</code> (período de 180 días, el máximo que ofrece la API),
        propiedad <code>signInActivity</code> y <code>subscribedSkus</code>.
        Universo: cuentas de tipo Member, habilitadas, con licencia y con buzón.
        El inicio de sesión cubre toda la vida de la cuenta; los contadores de correo, solo los últimos 180 días.<br>
        Actualizado {{ $generado->diffForHumans() }} · se recalcula todos los días a las 05:00.
    </p>

</div>
@endsection
