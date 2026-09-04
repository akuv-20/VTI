@extends('layouts.app')

@php
    use App\Http\Controllers\Admin\ActividadBuzonesController;
    use App\Services\ActividadBuzones;

    $cohortes = collect($resumen['cohortes'])->filter(fn($c) => $c['total'] >= 20);
    $picoMes  = $cohortes->sortByDesc(fn($c) => $c['sin_uso'])->keys()->first();
    $pico     = $picoMes ? $resumen['cohortes'][$picoMes] : null;

    $meses = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio',
              '07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
    $nombreMes = fn($m) => $m ? ($meses[substr($m,5,2)] ?? $m) . ' ' . substr($m,0,4) : '';
@endphp

@section('content')
<style>
    /* buz- : informe de actividad de buzones.
       Densidad y colores de estado al frente, como una consola de monitoreo:
       el número manda, el adorno se aparta. */

    .buz-informe { max-width: 1080px; }

    .buz-etq {
        font-size: .68rem; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; color: #94a3b8;
    }

    /* Cabecera de cada bloque, con su casilla para el PDF */
    .buz-cab {
        display: flex; align-items: center; gap: .6rem;
        margin: 1.35rem 0 .6rem; padding-bottom: .4rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .buz-cab .buz-etq { flex: 1; }
    .buz-cab input { cursor: pointer; margin: 0; }
    .buz-cab label { cursor: pointer; font-size: .7rem; color: #94a3b8; user-select: none; }
    .buz-cab label:hover { color: #475569; }

    .buz-bloque { transition: opacity .15s; }
    .buz-bloque.buz-off { opacity: .38; }

    /* Veredicto */
    .buz-hero {
        background: #fff; border: 1px solid #e2e8f0; border-left: 3px solid #a63a22;
        border-radius: 6px; padding: 1.35rem 1.6rem;
        display: flex; align-items: center; gap: 1.75rem; flex-wrap: wrap;
    }
    .buz-hero-num {
        font-size: 3.4rem; font-weight: 700; line-height: .9; color: #a63a22;
        letter-spacing: -.03em; font-variant-numeric: tabular-nums;
    }
    .buz-hero-txt { font-size: .96rem; color: #334155; max-width: 34rem; line-height: 1.55; }
    .buz-hero-txt strong { color: #0f172a; font-weight: 600; }

    /* Rejilla de cifras */
    .buz-tiles {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(168px,1fr));
        gap: 1px; background: #e2e8f0; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;
    }
    .buz-tile {
        background: #fff; padding: .8rem .95rem; display: block;
        color: inherit; text-decoration: none; transition: background .12s;
    }
    .buz-tile:hover { background: #f8fafc; color: inherit; }
    .buz-tile-val { font-size: 1.7rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
    .buz-tile-lbl {
        font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
        color: #64748b; margin-top: .4rem; display: flex; align-items: center; gap: .35rem;
    }
    .buz-tile-sub { font-size: .7rem; color: #94a3b8; margin-top: .2rem; line-height: 1.35; }
    .buz-punto { width: 7px; height: 7px; border-radius: 2px; display: inline-block; }

    .buz-barra { height: 1.5rem; display: flex; border-radius: 4px; overflow: hidden; background: #f1f5f9; margin-top: .7rem; }
    .buz-barra span { display: block; }

    .buz-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1.1rem 1.3rem; }

    /* Tabla de datos compacta */
    .buz-tabla { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .buz-tabla td { padding: .45rem .5rem; border-bottom: 1px solid #f1f5f9; }
    .buz-tabla tr:last-child td { border-bottom: 0; }
    .buz-tabla .n { text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; color: #0f172a; width: 4.5rem; }
    .buz-tabla .pct { text-align: right; font-variant-numeric: tabular-nums; color: #94a3b8; width: 3rem; font-size: .78rem; }
    .buz-tabla .lec { color: #64748b; font-size: .8rem; }
    .buz-tabla a { color: #1e293b; text-decoration: none; font-weight: 600; }
    .buz-tabla a:hover { color: #2563eb; }

    .buz-mini { height: .55rem; background: #f1f5f9; border-radius: 2px; overflow: hidden; min-width: 5rem; }
    .buz-mini div { height: 100%; }

    .buz-pie { font-size: .74rem; color: #94a3b8; line-height: 1.65; margin-top: 1.5rem; }

    @media print { .buz-noprint { display: none !important; } .buz-bloque.buz-off { display: none; } }
</style>

<div class="container-fluid vti-page buz-informe">

    <div class="vti-page-header buz-noprint">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-envelope-exclamation me-2" style="color:#a63a22"></i>Actividad de buzones</span>
            <span class="badge bg-light text-secondary border" style="font-size:.7rem">unifrutti.com</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.buzones.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Ver listado
            </a>
            <button type="button" id="btnPdf" class="btn btn-danger btn-sm"
                    data-bs-toggle="modal" data-bs-target="#modalPdf">
                <i class="bi bi-file-earmark-pdf me-1"></i>Vista previa PDF
            </button>
            <form method="POST" action="{{ route('admin.buzones.refrescar') }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </form>
        </div>
    </div>

    @if($nombresOcultos)
        <div class="alert alert-warning py-2">
            <i class="bi bi-eye-slash-fill me-2"></i>
            El tenant tiene los nombres ofuscados en los informes de Microsoft 365, así que el
            detalle por persona no es fiable. Se apaga en <em>Microsoft 365 admin → Configuración
            de la organización → Informes</em>.
        </div>
    @endif

    <p class="buz-etq buz-noprint" style="margin-bottom:0">
        <i class="bi bi-check2-square me-1"></i>Desmarca los bloques que no quieras en el PDF
    </p>

    {{-- ── Veredicto ────────────────────────────────────────────────────── --}}
    @include('admin.buzones._cab', ['clave' => 'veredicto', 'titulo' => 'Veredicto'])
    <div class="buz-bloque" data-bloque="veredicto">
        <div class="buz-hero">
            <div class="buz-hero-num">{{ $resumen['sin_uso_pct'] }}%</div>
            <div class="buz-hero-txt">
                <strong>{{ number_format($resumen['sin_uso']) }} de {{ number_format($resumen['total']) }} buzones
                con licencia no registran un solo inicio de sesión.</strong>
                Nadie entró nunca, ni por Outlook, ni por el navegador, ni por el teléfono.
            </div>
        </div>
    </div>

    {{-- ── Clasificación ────────────────────────────────────────────────── --}}
    @include('admin.buzones._cab', ['clave' => 'clasificacion', 'titulo' => 'Clasificación'])
    <div class="buz-bloque" data-bloque="clasificacion">
        <div class="buz-tiles">
            @foreach(ActividadBuzones::CLASES as $clave => [$lbl, $desc, $color, $ico])
            <a href="{{ route('admin.buzones.index', ['clase' => $clave]) }}" class="buz-tile">
                <div class="buz-tile-val" style="color:{{ $color }}">{{ number_format($resumen[$clave]) }}</div>
                <div class="buz-tile-lbl">
                    <span class="buz-punto" style="background:{{ $color }}"></span>{{ $lbl }}
                </div>
                <div class="buz-tile-sub">{{ $desc }}</div>
            </a>
            @endforeach
        </div>

        <div class="buz-barra">
            @foreach(ActividadBuzones::CLASES as $clave => [$lbl, $desc, $color])
                @if($resumen[$clave] > 0)
                <span style="width:{{ $resumen[$clave] * 100 / max(1,$resumen['total']) }}%;background:{{ $color }}"
                      title="{{ $lbl }}: {{ number_format($resumen[$clave]) }}"></span>
                @endif
            @endforeach
        </div>

        <p class="mt-2 mb-0" style="font-size:.82rem;color:#64748b">
            Se considera <strong>sin uso</strong> únicamente a quien nunca inició sesión. No se usa el
            contador de correos leídos: hay gente que trabaja a diario con miles de correos sin marcar
            como leídos.
            @if($resumen['excluidos'] > 0)
                Quedan fuera {{ $resumen['excluidos'] }} buzón(es) compartido(s).
            @endif
        </p>
    </div>

    {{-- ── Prioridad ────────────────────────────────────────────────────── --}}
    @if($resumen['sin_uso'] > 0)
    @include('admin.buzones._cab', ['clave' => 'prioridad', 'titulo' => 'Por dónde empezar'])
    <div class="buz-bloque" data-bloque="prioridad">
        <div class="buz-card">
            <p style="font-size:.85rem;color:#475569;margin-bottom:.9rem">
                Por antigüedad son todos parecidos y correo reciben todos, así que eso no ordena nada.
                Lo que sí los separa es <strong>cuánto</strong> reciben: uno con cinco correos en medio
                año es ruido automático; uno con doscientos está perdiendo comunicación real.
            </p>

            <table class="buz-tabla">
                @foreach(ActividadBuzones::TRAMOS as $clave => [$lbl, $min, $max, $lectura])
                    @php
                        $n   = $resumen['tramos_correo'][$clave] ?? 0;
                        $pct = $n * 100 / max(1, $resumen['sin_uso']);
                        $col = $clave === 'critico' ? '#a63a22' : ($clave === 'mucho' ? '#d97706' : '#cbd5e1');
                    @endphp
                    @if($n > 0)
                    <tr>
                        <td style="width:9.5rem">
                            <a href="{{ route('admin.buzones.index', ['clase' => 'nunca_activado', 'correo' => $clave]) }}">{{ $lbl }}</a>
                        </td>
                        <td class="n">{{ number_format($n) }}</td>
                        <td class="pct">{{ round($pct) }}%</td>
                        <td style="width:30%">
                            <div class="buz-mini"><div style="width:{{ $pct }}%;background:{{ $col }}"></div></div>
                        </td>
                        <td class="lec">{{ $lectura }}</td>
                    </tr>
                    @endif
                @endforeach
            </table>
        </div>
    </div>
    @endif

    {{-- ── Licencias ────────────────────────────────────────────────────── --}}
    @if(!empty($resumen['licencias']))
    @include('admin.buzones._cab', ['clave' => 'licencias', 'titulo' => 'Licencias comprometidas'])
    <div class="buz-bloque" data-bloque="licencias">
        <div class="buz-card">
            <table class="buz-tabla">
                @foreach($resumen['licencias'] as $sku => $n)
                <tr>
                    <td class="font-monospace" style="font-size:.8rem">{{ $sku }}</td>
                    <td class="n">{{ number_format($n) }}</td>
                    <td class="lec">buzones que nunca se abrieron</td>
                </tr>
                @endforeach
            </table>

            <p class="mt-3 mb-1" style="font-size:.86rem;color:#334155">
                Acumulan <strong>{{ number_format($resumen['meses_licencia']) }} meses de licencia</strong>
                pagados sin que nadie abriera el buzón — una mediana de
                {{ $resumen['meses_mediana'] }} meses cada uno.
            </p>
            <p class="mb-0" style="font-size:.79rem;color:#94a3b8">
                Liberar asignaciones no baja la factura por sí solo: el ahorro está en no renovar el
                excedente. Ocupan {{ $resumen['mb_sin_uso'] }} GB de almacenamiento.
            </p>
        </div>
    </div>
    @endif

    {{-- ── Cohortes ─────────────────────────────────────────────────────── --}}
    @if($cohortes->isNotEmpty())
    @include('admin.buzones._cab', ['clave' => 'cohortes', 'titulo' => 'Según cuándo se creó la cuenta'])
    <div class="buz-bloque" data-bloque="cohortes">
        <div class="buz-card">
            <table class="buz-tabla">
                @foreach($cohortes->sortKeysDesc()->take(10) as $mes => $c)
                    @php $pct = $c['sin_uso'] * 100 / max(1, $c['total']); @endphp
                    <tr>
                        <td style="width:9.5rem">
                            <a href="{{ route('admin.buzones.index', ['clase' => 'nunca_activado', 'cohorte' => $mes]) }}">{{ $nombreMes($mes) }}</a>
                        </td>
                        <td class="n">{{ number_format($c['sin_uso']) }}</td>
                        <td class="pct">{{ round($pct) }}%</td>
                        <td>
                            <div class="buz-mini"><div style="width:{{ $pct }}%;background:#a63a22"></div></div>
                        </td>
                        <td class="lec">de {{ number_format($c['total']) }} creados</td>
                    </tr>
                @endforeach
            </table>

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

    <p class="buz-pie">
        Microsoft Graph · reportes <code>getEmailActivityUserDetail</code> y
        <code>getMailboxUsageDetail</code> (180 días, el máximo de la API), <code>signInActivity</code>
        y <code>subscribedSkus</code>. Universo: cuentas Member habilitadas, con licencia y con buzón.
        El inicio de sesión cubre toda la vida de la cuenta; los contadores de correo, solo 180 días.<br>
        Actualizado {{ $generado->diffForHumans() }} · se recalcula todos los días a las 05:00.
    </p>

</div>

{{-- ── Vista previa del PDF ─────────────────────────────────────────────────
     El `src` del iframe se pone al abrir el modal, no en el HTML: si no, cada
     carga de esta pantalla generaría un PDF que casi nadie va a mirar. Y se
     regenera cada vez que cambian las casillas, porque el informe ya no es
     siempre el mismo. --}}
<div class="modal fade" id="modalPdf" tabindex="-1" aria-labelledby="tituloPdf" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height:88vh">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="tituloPdf">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Informe de actividad de buzones
                    <span class="text-muted fw-normal" style="font-size:.78rem" id="pdfBloques"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            {{-- El aviso va superpuesto al iframe, no encima en el flujo: si los
                 dos ocupan alto, el modal parte el espacio entre ambos. --}}
            <div class="modal-body p-0" style="background:#f1f5f9;position:relative;overflow:hidden">
                <iframe id="pdfMarco" title="Vista previa del informe"
                        style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
                <div id="pdfCargando"
                     style="position:absolute;inset:0;z-index:2;background:#f1f5f9;
                            display:flex;align-items:center;justify-content:center;
                            font-size:.85rem;color:#94a3b8">
                    <span class="spinner-border spinner-border-sm me-2"></span>Generando el informe…
                </div>
            </div>

            <div class="modal-footer py-2">
                <a href="#" id="pdfNuevaPestana" target="_blank" rel="noopener"
                   class="btn btn-outline-secondary btn-sm me-auto">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Abrir en pestaña nueva
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" id="pdfBajar" class="btn btn-danger btn-sm">
                    <i class="bi bi-download me-1"></i>Descargar PDF
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var casillas = Array.prototype.slice.call(document.querySelectorAll('.buz-chk'));
    var boton    = document.getElementById('btnPdf');
    var modal    = document.getElementById('modalPdf');
    var marco    = document.getElementById('pdfMarco');
    var cargando = document.getElementById('pdfCargando');
    var rotulo   = document.getElementById('pdfBloques');
    var base     = @json(route('admin.buzones.pdf'));

    var elegidos = [];

    function url(descargar) {
        return base + '?bloques=' + encodeURIComponent(elegidos.join(',')) + (descargar ? '&descargar=1' : '');
    }

    function aplicar() {
        elegidos = [];

        casillas.forEach(function (c) {
            var bloque = document.querySelector('[data-bloque="' + c.value + '"]');
            if (bloque) { bloque.classList.toggle('buz-off', !c.checked); }
            if (c.checked) { elegidos.push(c.value); }
        });

        // Sin bloques no hay informe que generar.
        boton.disabled = elegidos.length === 0;

        rotulo.textContent = elegidos.length === casillas.length
            ? '— informe completo'
            : '— ' + elegidos.length + ' de ' + casillas.length + ' bloques';

        // Lo ya renderizado dejó de corresponder a lo que está marcado.
        marco.removeAttribute('data-cargado');

        document.getElementById('pdfBajar').href = url(true);
        document.getElementById('pdfNuevaPestana').href = url(false);
    }

    // El `load` de un iframe con PDF no es confiable: según el navegador y el
    // visor puede no dispararse nunca. Por eso el aviso se quita por evento o
    // por tiempo, lo que ocurra primero, y no se queda tapando el informe.
    var reloj = null;
    function listo() {
        clearTimeout(reloj);
        marco.dataset.cargado = '1';
        cargando.style.display = 'none';
    }

    modal.addEventListener('show.bs.modal', function () {
        if (marco.dataset.cargado) { return; }
        cargando.style.display = 'flex';
        marco.src = url(false);
        reloj = setTimeout(listo, 10000);
    });

    marco.addEventListener('load', listo);

    casillas.forEach(function (c) { c.addEventListener('change', aplicar); });
    aplicar();
})();
</script>
@endsection
