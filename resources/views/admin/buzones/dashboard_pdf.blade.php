{{-- Informe de actividad de buzones, para gerencia.

     Ojo con el CSS: lo renderiza dompdf, que no soporta flexbox ni grid. Todo
     el armado va con tablas y anchos en porcentaje, que sí entiende.

     `$bloques` decide qué secciones salen; se eligen con las casillas de la
     pantalla. Sin parámetro salen todas. --}}
@php
    use App\Services\ActividadBuzones;

    $ver = fn($b) => in_array($b, $bloques, true);

    $meses = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio',
              '07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
    $nombreMes = fn($m) => ($meses[substr($m,5,2)] ?? $m) . ' ' . substr($m,0,4);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 84px 34px 48px 34px; }

    body { font-family: DejaVu Sans, sans-serif; font-size: 8.6pt; color: #1e293b; margin: 0; }

    header { position: fixed; top: -70px; left: 0; right: 0; height: 60px; }
    header .logo { height: 32px; }
    header .tit { font-size: 15pt; font-weight: bold; color: #0f172a; letter-spacing: -.2pt; }
    header .sub { font-size: 7.6pt; color: #64748b; margin-top: 3px; }
    header .der { text-align: right; font-size: 7pt; color: #94a3b8; line-height: 1.5; }
    header hr { border: 0; border-top: 2px solid #a63a22; margin: 8px 0 0; }

    footer { position: fixed; bottom: -34px; left: 0; right: 0; height: 24px;
             font-size: 6.8pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    footer .der { text-align: right; }

    .cab-tabla { width: 100%; border-collapse: collapse; }
    .cab-tabla td { vertical-align: middle; padding: 0; }

    /* Rótulo de sección: fino y en versalitas, sin pesar */
    h2 { font-size: 7.4pt; font-weight: bold; color: #94a3b8; margin: 18px 0 7px;
         padding-bottom: 4px; border-bottom: 1px solid #e2e8f0;
         text-transform: uppercase; letter-spacing: .12em; }

    p { margin: 0 0 7px; line-height: 1.5; }
    .nota { font-size: 7.4pt; color: #64748b; line-height: 1.5; }

    /* Veredicto */
    .hero { width: 100%; border-collapse: collapse; }
    .hero td { border: 1px solid #e2e8f0; border-left: 0; background: #fff; padding: 14px 16px; vertical-align: middle; }
    .hero td.num { border-left: 3px solid #a63a22; font-size: 30pt; font-weight: bold;
                   color: #a63a22; width: 108px; text-align: center; padding: 14px 8px; }
    .hero .txt { font-size: 9.2pt; color: #334155; line-height: 1.5; }

    /* Rejilla de cifras */
    .cifras { width: 100%; border-collapse: collapse; }
    .cifras td { border: 1px solid #e2e8f0; background: #fff; padding: 9px 8px; width: 25%; }
    .cifras .n { font-size: 16pt; font-weight: bold; line-height: 1; }
    .cifras .r { font-size: 6.4pt; color: #64748b; text-transform: uppercase;
                 letter-spacing: .06em; margin-top: 5px; font-weight: bold; }
    .cifras .d { font-size: 6.4pt; color: #94a3b8; margin-top: 2px; line-height: 1.35; }

    /* Barra de composición */
    .compo { width: 100%; border-collapse: collapse; margin-top: 6px; height: 11px; }
    .compo td { padding: 0; height: 11px; }

    /* Tablas de datos */
    table.datos { width: 100%; border-collapse: collapse; }
    table.datos td { padding: 5px 7px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    table.datos tr:last-child td { border-bottom: 0; }
    table.datos td.k { font-weight: bold; color: #1e293b; }
    table.datos td.n { text-align: right; font-weight: bold; width: 56px; }
    table.datos td.pct { text-align: right; width: 40px; color: #94a3b8; font-size: 7.6pt; }
    table.datos td.lec { color: #64748b; font-size: 7.6pt; }

    .barra { height: 7px; background: #f1f5f9; }
    .barra div { height: 7px; }

    .cierre { border: 1px solid #e2e8f0; border-left: 3px solid #cbd5e1;
              background: #f8fafc; padding: 9px 12px; margin-top: 8px; font-size: 8pt; color: #475569; }
</style>
</head>
<body>

<header>
    <table class="cab-tabla">
        <tr>
            @if($logo)
            <td style="width:110px"><img src="{{ $logo }}" class="logo" alt=""></td>
            @endif
            <td>
                <div class="tit">Actividad de buzones · Microsoft 365</div>
                <div class="sub">unifrutti.com — {{ number_format($resumen['total']) }} buzones con licencia</div>
            </td>
            <td class="der">
                {{ $emitido->format('d/m/Y H:i') }}<br>
                @if($usuario) {{ $usuario }}<br> @endif
                datos del {{ $generado->format('d/m/Y') }}
            </td>
        </tr>
    </table>
    <hr>
</header>

<footer>
    <table class="cab-tabla">
        <tr>
            <td>Plataforma TI · Actividad de buzones de Microsoft 365</td>
            <td class="der">Página <span class="pagenum"></span></td>
        </tr>
    </table>
</footer>

<main>

    @if($ver('veredicto'))
    <table class="hero">
        <tr>
            <td class="num">{{ $resumen['sin_uso_pct'] }}%</td>
            <td class="txt">
                <strong>{{ number_format($resumen['sin_uso']) }} de {{ number_format($resumen['total']) }}
                buzones con licencia no registran un solo inicio de sesión.</strong><br>
                Nadie entró nunca, ni por Outlook, ni por el navegador, ni por el teléfono.
            </td>
        </tr>
    </table>
    @endif

    @if($ver('clasificacion'))
    <h2>Clasificación</h2>

    <table class="cifras">
        <tr>
            @foreach(ActividadBuzones::CLASES as $clave => [$lbl, $desc, $color])
            <td>
                <div class="n" style="color:{{ $color }}">{{ number_format($resumen[$clave]) }}</div>
                <div class="r" style="color:{{ $color }}">{{ $lbl }}</div>
                <div class="d">{{ $desc }}</div>
            </td>
            @endforeach
        </tr>
    </table>

    <table class="compo">
        <tr>
            @foreach(ActividadBuzones::CLASES as $clave => [$lbl, $desc, $color])
                @if($resumen[$clave] > 0)
                <td style="width:{{ $resumen[$clave] * 100 / max(1,$resumen['total']) }}%;background:{{ $color }}"></td>
                @endif
            @endforeach
        </tr>
    </table>

    <p class="nota" style="margin-top:7px">
        Se considera <strong>sin uso</strong> únicamente a quien nunca inició sesión. No se usa el
        contador de correos leídos: mucha gente trabaja con miles de correos sin marcar como leídos
        y sí usa su cuenta a diario.
        @if($resumen['excluidos'] > 0)
            Quedan fuera {{ $resumen['excluidos'] }} buzón(es) compartido(s) excluido(s).
        @endif
    </p>
    @endif

    @if($ver('prioridad') && $resumen['sin_uso'] > 0)
    <h2>Por dónde empezar con los {{ number_format($resumen['sin_uso']) }} sin uso</h2>

    <p>
        Por antigüedad son todos parecidos y correo reciben todos, así que eso no ordena nada. Lo que
        sí los separa es <strong>cuánto</strong> reciben: uno con cinco correos en medio año es ruido
        automático; uno con doscientos está perdiendo comunicación real todos los días.
    </p>

    <table class="datos">
        @foreach(ActividadBuzones::TRAMOS as $clave => [$lbl, $min, $max, $lectura])
            @php
                $n   = $resumen['tramos_correo'][$clave] ?? 0;
                $pct = $n * 100 / max(1, $resumen['sin_uso']);
                $col = $clave === 'critico' ? '#a63a22' : ($clave === 'mucho' ? '#d97706' : '#cbd5e1');
            @endphp
            @if($n > 0)
            <tr>
                <td class="k" style="width:118px">{{ $lbl }}</td>
                <td class="n">{{ number_format($n) }}</td>
                <td class="pct">{{ round($pct) }}%</td>
                <td style="width:120px">
                    <div class="barra"><div style="width:{{ round($pct) }}%;background:{{ $col }}"></div></div>
                </td>
                <td class="lec">{{ $lectura }}</td>
            </tr>
            @endif
        @endforeach
    </table>
    @endif

    @if($ver('licencias') && !empty($resumen['licencias']))
    <h2>Licencias comprometidas</h2>

    <table class="datos">
        @foreach($resumen['licencias'] as $sku => $n)
        <tr>
            <td class="k">{{ $sku }}</td>
            <td class="n">{{ number_format($n) }}</td>
            <td class="lec">buzones que nunca se abrieron</td>
        </tr>
        @endforeach
    </table>

    <div class="cierre">
        Acumulan <strong>{{ number_format($resumen['meses_licencia']) }} meses de licencia</strong>
        pagados sin que nadie abriera el buzón —una mediana de {{ $resumen['meses_mediana'] }} meses
        cada uno— y ocupan {{ $resumen['mb_sin_uso'] }} GB de almacenamiento.
        Liberar asignaciones no baja la factura por sí solo: el ahorro está en no renovar el
        excedente, y eso se decide en la negociación del contrato.
    </div>
    @endif

    @if($ver('cohortes') && !empty($resumen['cohortes']))
    <h2>Según cuándo se creó la cuenta</h2>

    <table class="datos">
        @foreach(collect($resumen['cohortes'])->filter(fn($c) => $c['total'] >= 20)->sortKeysDesc()->take(10) as $mes => $c)
            @php $pct = $c['sin_uso'] * 100 / max(1, $c['total']); @endphp
            <tr>
                <td class="k" style="width:118px">{{ $nombreMes($mes) }}</td>
                <td class="n">{{ number_format($c['sin_uso']) }}</td>
                <td class="pct">{{ round($pct) }}%</td>
                <td style="width:120px">
                    <div class="barra"><div style="width:{{ round($pct) }}%;background:#a63a22"></div></div>
                </td>
                <td class="lec">de {{ number_format($c['total']) }} creados ese mes</td>
            </tr>
        @endforeach
    </table>
    @endif

    <p class="nota" style="margin-top:16px">
        Microsoft Graph — reportes <em>getEmailActivityUserDetail</em> y <em>getMailboxUsageDetail</em>
        (180 días, el máximo que entrega la API), propiedad <em>signInActivity</em> y
        <em>subscribedSkus</em>. Universo: cuentas de tipo Member, habilitadas, con licencia y con
        buzón. El inicio de sesión cubre toda la vida de la cuenta; los contadores de correo, solo los
        últimos 180 días.
    </p>

</main>

<script type="text/php">
    // Numeración de páginas: dompdf la resuelve en su propio motor, no en Blade.
    if (isset($pdf)) {
        $pdf->page_text(516, 806, "{PAGE_NUM} de {PAGE_COUNT}", null, 7, [0.58, 0.64, 0.72]);
    }
</script>

</body>
</html>
