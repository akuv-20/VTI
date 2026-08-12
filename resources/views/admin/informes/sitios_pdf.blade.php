{{-- Informe ejecutivo de conectividad, para gerencia.

     Ojo con el CSS: lo renderiza dompdf, que no soporta flexbox ni grid. Todo
     el armado va con tablas y `display:inline-block`, que sí entiende. --}}
@php
    use App\Models\Sitio;
    $dato = fn($v) => $v === null || $v === '' ? null : $v;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 74px 26px 46px 26px; }

    body { font-family: DejaVu Sans, sans-serif; font-size: 7.4pt; color: #1e293b; margin: 0; }

    /* Cabecera y pie fijos: dompdf los repite en todas las páginas. */
    header { position: fixed; top: -60px; left: 0; right: 0; height: 52px; }
    header .logo { height: 34px; }
    header .tit { font-size: 14pt; font-weight: bold; color: #0f172a; }
    header .sub { font-size: 7.6pt; color: #64748b; margin-top: 2px; }
    header .der { text-align: right; font-size: 7pt; color: #94a3b8; }
    header hr { border: 0; border-top: 2px solid #7c3aed; margin: 6px 0 0; }

    footer { position: fixed; bottom: -32px; left: 0; right: 0; height: 24px;
             font-size: 6.6pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    footer .der { text-align: right; }

    .cab-tabla { width: 100%; border-collapse: collapse; }
    .cab-tabla td { vertical-align: middle; padding: 0; }

    /* Franja de totales. */
    .resumen { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
    .resumen td { border: 1px solid #e2e8f0; background: #f8fafc; padding: 5px 8px; text-align: center; }
    .resumen .n { font-size: 13pt; font-weight: bold; color: #0f172a; }
    .resumen .r { font-size: 6.4pt; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
    .resumen .pt { display: inline-block; width: 7px; height: 7px; border-radius: 4px; }

    /* Tabla principal. */
    table.datos { width: 100%; border-collapse: collapse; }
    table.datos th { background: #0f172a; color: #fff; font-size: 6.4pt; text-transform: uppercase;
                     letter-spacing: .03em; padding: 5px 4px; text-align: left; }
    table.datos td { padding: 4px; border-bottom: 1px solid #eef2f6; vertical-align: middle; }
    table.datos tr.par td { background: #f8fafc; }
    table.datos .nom { font-weight: bold; }
    table.datos .cen { text-align: center; }
    .vacio { color: #cbd5e1; }

    .est { display: inline-block; padding: 1px 6px; border-radius: 8px; color: #fff; font-size: 6.2pt; }

    /* Señal por operador: cuatro casillas con la inicial. */
    .senal { display: inline-block; width: 13px; height: 12px; line-height: 12px; text-align: center;
             font-size: 6pt; font-weight: bold; border-radius: 2px; margin-right: 1px; }
    .s-sin     { background: #fee2e2; color: #991b1b; }
    .s-mala    { background: #ffedd5; color: #9a3412; }
    .s-regular { background: #fef3c7; color: #854d0e; }
    .s-buena   { background: #dcfce7; color: #166534; }
    .s-na      { background: #fff; color: #cbd5e1; border: 1px dashed #e2e8f0; }

    .leyenda { margin-top: 8px; font-size: 6.4pt; color: #64748b; }
    .leyenda .senal { margin-left: 6px; }

    /* Numeración con contadores CSS, que dompdf resuelve solo. La alternativa
       —un <script type="text/php">— obliga a habilitar `isPhpEnabled`, y no vale
       la pena encender la ejecución de PHP dentro del renderizador por esto. */
    .pagina:after { content: counter(page) " de " counter(pages); }
</style>
</head>
<body>

<header>
    <table class="cab-tabla">
        <tr>
            @if($logo)
                <td style="width:46px"><img src="{{ $logo }}" class="logo" alt=""></td>
            @endif
            <td>
                <div class="tit">Estado de conectividad de sitios</div>
                <div class="sub">{{ $zonas }}</div>
            </td>
            <td class="der">
                {{ $emitido->format('d-m-Y H:i') }}<br>
                @if($usuario)Emitido por {{ $usuario }}@endif
            </td>
        </tr>
    </table>
    <hr>
</header>

<footer>
    <table class="cab-tabla">
        <tr>
            <td>{{ config('app.name') }} — documento interno</td>
            <td class="der">Página <span class="pagina"></span></td>
        </tr>
    </table>
</footer>

<main>
    {{-- ── Totales ─────────────────────────────────────────────────────────── --}}
    <table class="resumen">
        <tr>
            <td>
                <div class="n">{{ $resumen['sitios'] }}</div>
                <div class="r">Sitios</div>
            </td>
            @foreach($resumen['estados'] as $label => [$n, $color])
                <td>
                    <div class="n">{{ $n }}</div>
                    <div class="r"><span class="pt" style="background:{{ $color }}"></span> {{ $label }}</div>
                </td>
            @endforeach
            <td>
                <div class="n">{{ number_format($resumen['usuarios'], 0, ',', '.') }}</div>
                <div class="r">Usuarios</div>
            </td>
            <td>
                <div class="n">{{ number_format($resumen['pcs'], 0, ',', '.') }}</div>
                <div class="r">PCs / equipos</div>
            </td>
        </tr>
    </table>

    {{-- ── Detalle ─────────────────────────────────────────────────────────── --}}
    <table class="datos">
        <thead>
            <tr>
                <th style="width:16%">Sitio</th>
                <th style="width:9%">Zona</th>
                <th style="width:7%">Tipo</th>
                <th style="width:10%">Estado del enlace</th>
                <th style="width:8%">Enlace</th>
                <th style="width:9%">ISP</th>
                <th style="width:8%">Ancho de banda</th>
                <th style="width:5%" class="cen">VPN</th>
                <th style="width:10%">Señal móvil</th>
                <th style="width:8%">Gabinete</th>
                <th style="width:8%">UPS</th>
                <th style="width:4%" class="cen">Usu.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sitios as $s)
            <tr class="{{ $loop->even ? 'par' : '' }}">
                <td class="nom">{{ $s->titulo }}</td>
                <td>{{ $s->zona?->nombre ?? '—' }}</td>
                <td>{{ $s->tipo_label }}</td>
                <td><span class="est" style="background:{{ $s->estado_enlace_color }}">{{ $s->estado_enlace_label }}</span></td>
                <td>{{ Sitio::ENLACE_TIPOS[$s->enlace_tipo] ?? '—' }}</td>
                <td>{{ $s->isp?->nombre ?? '—' }}</td>
                <td>{{ $dato($s->ancho_banda) ?? '—' }}</td>
                <td class="cen">
                    @if($s->vpn === null)
                        <span class="vacio">—</span>
                    @else
                        {{ $s->vpn ? 'Sí' : 'No' }}
                    @endif
                </td>
                <td>
                    {{-- Cuatro casillas, una por operador, con la inicial y el color
                         de la señal medida. Entra en 10% de ancho y se lee de un
                         golpe, que es lo que un listado largo necesita. --}}
                    @foreach(Sitio::OPERADORES as $campo => $operador)
                        @php $v = $s->$campo; @endphp
                        <span class="senal s-{{ $v ?: 'na' }}"
                              title="{{ $operador }}: {{ Sitio::COBERTURA[$v] ?? 'sin medir' }}">{{ mb_substr($operador, 0, 1) }}</span>
                    @endforeach
                </td>
                <td>{{ $dato($s->gabinete) ?? '—' }}</td>
                <td>{{ $dato($s->ups_modelo) ?? '—' }}</td>
                <td class="cen">{{ $s->usuarios_cant ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="leyenda">
        <b>Señal móvil</b> — E Entel, M Movistar, W WOM, C Claro:
        <span class="senal s-buena">B</span> buena
        <span class="senal s-regular">R</span> regular
        <span class="senal s-mala">M</span> mala
        <span class="senal s-sin">S</span> sin señal
        <span class="senal s-na">?</span> sin medir
    </div>
</main>

</body>
</html>
