@extends('layouts.app')

@php use App\Models\Sitio; @endphp

@section('content')
<style>
    .sit-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }

    /* ── Filtros: una sola barra, no una tarjeta con tres bloques ─────────── */
    .sit-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:10px;
                   padding:.5rem .6rem; margin-bottom:.85rem;
                   display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .sit-buscar { position:relative; flex:0 1 280px; min-width:200px; }
    .sit-buscar .bi-search { position:absolute; left:.6rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.8rem; }
    .sit-buscar input { padding-left:1.9rem; padding-right:1.8rem; font-size:.78rem; }
    .sit-buscar .limpiar { position:absolute; right:.2rem; top:50%; transform:translateY(-50%);
                           color:#94a3b8; text-decoration:none; padding:.2rem .35rem; line-height:1; font-size:.75rem; }
    .sit-buscar .limpiar:hover { color:#475569; }
    .sit-sep { width:1px; align-self:stretch; background:#e2e8f0; margin:.1rem .15rem; }
    .sit-zona-sel { font-size:.72rem; padding:.2rem 1.4rem .2rem .5rem; height:auto; width:auto; max-width:190px; }
    .sit-pais-sel { width:auto; min-width:160px; font-size:.78rem; }
    .sit-zona { display:inline-block; font-size:.62rem; font-weight:600; padding:1px 7px;
                border-radius:5px; background:#ede9fe; color:#5b21b6; max-width:100%;
                overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    /* Sin asignar se ve, pero apagado: con 52 campos por clasificar, el hueco
       hay que notarlo sin que grite en cada tarjeta. */
    .sit-zona.vacia { background:#f8fafc; color:#cbd5e1; border:1px dashed #e2e8f0; font-weight:500; }
    .sit-chips { display:flex; gap:.25rem; flex-wrap:wrap; }
    .sit-tab { font-size:.72rem; padding:.2rem .6rem; border-radius:20px; border:1px solid #e2e8f0;
               background:#fff; color:#475569; text-decoration:none; white-space:nowrap; line-height:1.5; }
    .sit-tab:hover { border-color:#94a3b8; color:#1e293b; }
    .sit-tab.on { background:#0f172a; border-color:#0f172a; color:#fff; font-weight:600; }
    .sit-tab .n { opacity:.6; margin-left:.25rem; font-size:.68rem; }
    .sit-tab .pt { display:inline-block; width:6px; height:6px; border-radius:50%; margin-right:4px; }

    /* ── Tarjeta compacta: una línea por sitio ────────────────────────────
       La miniatura pasó de una banda de 74 px a un cuadrado de 40: la foto
       sirve para reconocer el sitio de un vistazo, y para eso 40 bastan,
       mientras los 74 eran casi toda la altura de la tarjeta.

       Queda fuera el técnico (cargado en 1 de 54) y la barra de completitud:
       42 de 54 fichas están al 100%, así que la barra sale llena en cuatro
       de cada cinco tarjetas y no distingue nada. El número de al lado dice
       lo mismo con una cifra exacta y sin ocupar una fila. ───────────────── */
    .sit-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(252px,1fr)); gap:.45rem; }
    .sit-item { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff;
                transition:border-color .15s, box-shadow .15s; }
    .sit-item:hover { border-color:#94a3b8; box-shadow:0 2px 8px rgba(15,23,42,.06); }
    .sit-item a.lnk { text-decoration:none; color:inherit;
                      display:flex; align-items:center; gap:.5rem; padding:.4rem .55rem; }
    .sit-foto { flex:0 0 40px; width:40px; height:40px; border-radius:6px; background:#f1f5f9;
                display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .sit-foto img { width:100%; height:100%; object-fit:cover; }
    .sit-foto i { font-size:1.05rem; color:#cbd5e1; }
    /* min-width:0 es lo que permite que el nombre largo se recorte en vez de
       estirar la tarjeta: un hijo flex no baja del ancho de su contenido sin
       esto, y hay nombres de 37 caracteres. */
    .sit-body { flex:1; min-width:0; }
    .sit-body h5 { font-size:.81rem; font-weight:700; color:#1e293b; margin:0 0 .2rem;
                   display:flex; align-items:center; gap:.3rem; min-width:0; }
    .sit-body h5 .nom { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sit-body h5 .bnd { flex:0 0 auto; font-size:.78rem; line-height:1; }
    .sit-tags { display:flex; align-items:center; gap:.3rem; min-width:0; white-space:nowrap; }
    .sit-punto { flex:0 0 auto; width:7px; height:7px; border-radius:50%; }
    .sit-tipo { flex:0 0 auto; font-size:.62rem; font-weight:600; padding:1px 6px;
                border-radius:5px; background:#eef2ff; color:#4338ca; }
    .sit-com { min-width:0; font-size:.66rem; color:#94a3b8;
               overflow:hidden; text-overflow:ellipsis; }
    .sit-pct { margin-left:auto; flex:0 0 auto; font-size:.65rem; font-weight:700; }

    /* ── Live: el estado medido, no el declarado ──────────────────────────
       Llega por fetch despues de dibujar la pagina, asi que todo nace en
       «comprobando» y se enciende solo. Si CheckMK no responde se queda en
       «—»: decir «offline» porque no pudimos preguntar seria mentir. */
    .sit-live { display:inline-flex; align-items:center; gap:.3rem; white-space:nowrap; }
    .sit-live .pto { width:8px; height:8px; border-radius:50%; background:#cbd5e1; flex:0 0 auto; }
    .sit-live.cargando .pto { animation:sitLatido 1.1s ease-in-out infinite; }
    .sit-live .txt { font-size:.68rem; font-weight:600; }
    /* Sin host enlazado no hay nada que medir, y es distinto de «no se pudo
       preguntar»: va en hueco, no en gris de error. */
    .sit-live.vacio .pto { background:transparent; border:1px dashed #cbd5e1; }
    .sit-live.vacio .txt { color:#64748b; font-weight:500; }
    @keyframes sitLatido { 0%,100% { opacity:.25 } 50% { opacity:.8 } }

    /* Resumen que aparece cuando llegan los datos. Con cinco estados posibles
       hace falta decir que significa cada color, y de paso cuantos hay. */
    .sit-resumen { display:none; align-items:center; gap:.5rem; flex-wrap:wrap;
                   margin-bottom:.6rem; font-size:.72rem; color:#64748b; }
    .sit-resumen.hay { display:flex; }
    .sit-resumen .it { display:inline-flex; align-items:center; gap:.3rem; }
    .sit-resumen .it .pto { width:8px; height:8px; border-radius:50%; }
    .sit-resumen .it b { color:#334155; }
    .sit-resumen .aviso { color:#a855f7; }
    .sit-resumen .fallo { color:#dc2626; }
    /* La hora del dato: sin esto, un listado abierto desde la mañana se ve
       igual de fresco que uno recien cargado. */
    .sit-resumen .sello { color:#94a3b8; font-variant-numeric:tabular-nums; }
    .sit-resumen.viejo .sello { color:#b45309; font-weight:600; }

    /* En la tarjeta el punto va junto al nombre: es lo primero que se busca. */
    .sit-body h5 .sit-live { flex:0 0 auto; }

    /* ── Vista de tabla ──────────────────────────────────────────────── */
    .sit-vistas { display:flex; gap:0; border:1px solid #cbd5e1; border-radius:7px; overflow:hidden; }
    .sit-vistas a { display:flex; align-items:center; gap:.3rem; font-size:.75rem; font-weight:600;
                    padding:.25rem .6rem; color:#64748b; background:#fff; text-decoration:none; }
    .sit-vistas a + a { border-left:1px solid #e2e8f0; }
    .sit-vistas a:hover { background:#f8fafc; color:#334155; }
    .sit-vistas a.on { background:#7c3aed; color:#fff; }

    .sit-tabla-env { background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:auto;
                     max-height:calc(100vh - var(--topbar-h) - 230px); }
    .sit-tabla { border-collapse:separate; border-spacing:0; width:100%; font-size:.8rem; }
    .sit-tabla th { text-align:left; font-size:.65rem; letter-spacing:.06em; text-transform:uppercase;
                    color:#94a3b8; font-weight:700; padding:.45rem .6rem; white-space:nowrap;
                    background:#f8fafc; border-bottom:1px solid #e2e8f0; position:sticky; top:0; z-index:2; }
    .sit-tabla th.orden { cursor:pointer; user-select:none; }
    .sit-tabla th.orden:hover { color:#7c3aed; }
    .sit-tabla th.orden::after { content:''; display:inline-block; width:.6em; }
    .sit-tabla th.asc::after  { content:'▲'; font-size:.55em; margin-left:.25em; color:#7c3aed; }
    .sit-tabla th.desc::after { content:'▼'; font-size:.55em; margin-left:.25em; color:#7c3aed; }
    .sit-tabla td { padding:.3rem .6rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .sit-tabla tbody tr { cursor:pointer; }
    .sit-tabla tbody tr:hover td { background:#f8fafc; }
    .sit-tabla tbody tr[hidden] { display:none; }
    .sit-tabla .nom a { font-weight:600; color:#1e293b; text-decoration:none; white-space:nowrap; }
    .sit-tabla .nom a:hover { color:#7c3aed; }
    .sit-tabla .mudo { color:#64748b; }
    .sit-tabla .num { text-align:right; font-variant-numeric:tabular-nums; }
    .sit-tabla .nowrap { white-space:nowrap; }
    .sit-tabla td.mini { width:34px; padding-left:.6rem; padding-right:0; }
    .sit-mini { width:26px; height:26px; border-radius:5px; background:#f1f5f9; overflow:hidden;
                display:flex; align-items:center; justify-content:center; }
    .sit-mini img { width:100%; height:100%; object-fit:cover; }
    .sit-mini i { font-size:.7rem; color:#cbd5e1; }
    .sit-tabla .pt { display:inline-block; width:.5rem; height:.5rem; border-radius:50%;
                     margin-right:.35rem; vertical-align:middle; }
    .sit-tabla .sit-zona { font-size:.66rem; font-weight:700; padding:1px 7px; border-radius:20px;
                           background:#ede9fe; color:#6d28d9; white-space:nowrap; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-pin-map-fill me-2" style="color:#7c3aed"></i>Sitios
            <span class="text-muted fw-normal" style="font-size:.82rem">{{ $sitios->count() }} de {{ $conteos['total'] }}</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sitios.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-graph-up me-1"></i>Avance</a>
            {{-- La elección se recuerda en cookie por un año: la sesión caduca
                 en una hora y se olvidaría cada mañana. Los filtros vigentes
                 viajan en el enlace para no perderlos al cambiar de vista. --}}
            <div class="sit-vistas" role="group" aria-label="Forma de ver el listado">
                <a href="{{ route('admin.sitios.index', array_merge(request()->only(['pais','tipo','estado','zona','q']), ['vista' => 'tabla'])) }}"
                   class="{{ $vista === 'tabla' ? 'on' : '' }}" title="Ver como tabla">
                    <i class="bi bi-list-ul"></i>Tabla
                </a>
                <a href="{{ route('admin.sitios.index', array_merge(request()->only(['pais','tipo','estado','zona','q']), ['vista' => 'tarjetas'])) }}"
                   class="{{ $vista === 'tarjetas' ? 'on' : '' }}" title="Ver como tarjetas">
                    <i class="bi bi-grid-3x3-gap"></i>Tarjetas
                </a>
            </div>            <a href="{{ route('admin.sitios.descubrimiento') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Descubrir hosts</a>
            <a href="{{ route('admin.sitios.importar') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Importar</a>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoSitio">
                <i class="bi bi-plus-lg me-1"></i>Nuevo sitio
            </button>
        </div>
    </div>

    {{-- ── Filtros ────────────────────────────────────────────────────────── --}}
    <div class="sit-filtros">
        <form method="GET" action="{{ route('admin.sitios.index') }}" class="sit-buscar">
            <input type="hidden" name="pais" value="{{ $pais }}">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <input type="hidden" name="zona" value="{{ $zona }}">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control form-control-sm" value="{{ $q }}"
                   placeholder="Nombre, código, comuna o encargado…" autocomplete="off">
            @if($q)
                <a href="{{ route('admin.sitios.index', ['pais' => $pais, 'tipo' => $tipo, 'estado' => $estado, 'zona' => $zona]) }}"
                   class="limpiar" title="Limpiar búsqueda"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>

        <div class="sit-sep"></div>

        <div class="sit-chips">
            <a href="{{ route('admin.sitios.index', ['pais' => $pais, 'tipo' => null, 'estado' => $estado, 'zona' => $zona, 'q' => $q]) }}" class="sit-tab {{ $tipo ? '' : 'on' }}">
                Todos <span class="n">{{ $conteos['total'] }}</span>
            </a>
            @foreach(Sitio::TIPOS as $k => $label)
                <a href="{{ route('admin.sitios.index', ['pais' => $pais, 'tipo' => $k, 'estado' => $estado, 'zona' => $zona, 'q' => $q]) }}"
                   class="sit-tab {{ $tipo === $k ? 'on' : '' }}">
                    <i class="bi {{ Sitio::ICONOS_TIPO[$k] }} me-1"></i>{{ $label }} <span class="n">{{ $conteos['tipos'][$k] }}</span>
                </a>
            @endforeach
        </div>

        <div class="sit-sep"></div>

        <div class="sit-chips">
            <a href="{{ route('admin.sitios.index', ['pais' => $pais, 'tipo' => $tipo, 'estado' => null, 'zona' => $zona, 'q' => $q]) }}" class="sit-tab {{ $estado ? '' : 'on' }}">Cualquier estado</a>
            @foreach(Sitio::ESTADOS_ENLACE as $k => $label)
                <a href="{{ route('admin.sitios.index', ['pais' => $pais, 'tipo' => $tipo, 'estado' => $k, 'zona' => $zona, 'q' => $q]) }}"
                   class="sit-tab {{ $estado === $k ? 'on' : '' }}">
                    <span class="pt" style="background:{{ Sitio::COLORES_ENLACE[$k] }}"></span>{{ $label }}
                    <span class="n">{{ $conteos['estados'][$k] }}</span>
                </a>
            @endforeach
        </div>

        <div class="sit-sep"></div>

        {{-- País: va primero porque es el filtro más grueso —Chile o Perú son
             dos operaciones distintas— y porque de él depende si «región» y
             «comuna» se eligen de un listado o se escriben a mano. --}}
        <form method="GET" action="{{ route('admin.sitios.index') }}" class="d-flex align-items-center gap-1">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <input type="hidden" name="zona" value="{{ $zona }}">
            <input type="hidden" name="q" value="{{ $q }}">
            <select name="pais" class="form-select form-select-sm sit-pais-sel" onchange="this.form.submit()"
                    title="Filtrar por país">
                <option value="">Selecciona el País</option>
                @foreach(Sitio::PAISES as $k => $label)
                    <option value="{{ $k }}" @selected($pais === $k)>
                        {{ Sitio::BANDERAS[$k] }} {{ $label }} ({{ $conteos['paises'][$k] }})
                    </option>
                @endforeach
                @if($conteos['sin_pais'])
                    <option value="sin" @selected($pais === 'sin')>— Sin país ({{ $conteos['sin_pais'] }})</option>
                @endif
            </select>
        </form>

        <div class="sit-sep"></div>
        {{-- Zona: un select y no chips, porque el mantenedor no tiene tope y
             una decena de zonas partiría la barra en tres líneas. --}}
        <form method="GET" action="{{ route('admin.sitios.index') }}" class="d-flex align-items-center gap-1">
            <input type="hidden" name="pais" value="{{ $pais }}">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <input type="hidden" name="q" value="{{ $q }}">
            <select name="zona" class="form-select form-select-sm sit-zona-sel" onchange="this.form.submit()"
                    title="Filtrar por zona">
                <option value="">Cualquier zona</option>
                @foreach($zonas as $z)
                    <option value="{{ $z->id }}" @selected((string) $zona === (string) $z->id)>
                        {{ $z->nombre }} ({{ $z->sitios_count }})
                    </option>
                @endforeach
                @if($conteos['sin_zona'])
                    <option value="sin" @selected($zona === 'sin')>— Sin zona ({{ $conteos['sin_zona'] }})</option>
                @endif
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                    data-bs-toggle="modal" data-bs-target="#modalZonas" title="Administrar zonas">
                <i class="bi bi-gear"></i>
            </button>
        </form>
    </div>

    @if(session('import_errores') && count(session('import_errores')))
    <div class="alert alert-warning py-2" style="font-size:.8rem">
        <b>Filas con problemas en la importación:</b>
        <ul class="mb-0 mt-1">
            @foreach(array_slice(session('import_errores'), 0, 10) as $e)<li>{{ $e }}</li>@endforeach
            @if(count(session('import_errores')) > 10)<li>… y {{ count(session('import_errores')) - 10 }} más</li>@endif
        </ul>
    </div>
    @endif

    {{-- Resumen de lo medido. Nace oculto y se muestra cuando llegan los datos. --}}
    <div class="sit-resumen" id="sitResumen"></div>

    {{-- ── Listado ────────────────────────────────────────────────────────── --}}
    @if($sitios->isEmpty())
        <div class="sit-card text-center text-muted py-5" style="font-size:.85rem">
            No hay sitios con estos filtros. Crea uno con <b>Nuevo sitio</b>, o
            <a href="{{ route('admin.sitios.importar') }}">importa varios desde Excel</a>.
        </div>
    @else
    {{-- ── A · Tabla ───────────────────────────────────────────────────────
         Con medio centenar de sitios comparar pesa más que reconocer la foto:
         de un vistazo se ve qué campos de una zona siguen sin ISP o cuáles
         quedaron en «Sin enlace». La foto queda como miniatura.

         Se ordena en el navegador y no en el servidor: los datos ya están en
         la página, así que pedir otra no aporta nada y se pierde el scroll. --}}
    @if($vista === 'tabla')
    <div class="sit-tabla-env">
        <table class="sit-tabla" id="tablaSitios">
            <thead>
                <tr>
                    <th class="mini"></th>
                    <th data-ord="nom" class="orden">Sitio</th>
                    <th data-ord="pais" class="orden">País</th>
                    <th data-ord="zona" class="orden">Zona</th>
                    <th data-ord="comuna" class="orden">Comuna</th>
                    <th data-ord="tipo" class="orden">Tipo</th>
                    <th data-ord="estado" class="orden">Estado</th>
                    {{-- Al lado de Estado a proposito: uno es lo que dice la ficha
                         y el otro lo que mide CheckMK, y verlos juntos delata las
                         fichas que quedaron desactualizadas. --}}
                    <th data-ord="live" class="orden" title="Estado medido por CheckMK">Live</th>
                    <th data-ord="enlace" class="orden">Enlace</th>
                    <th data-ord="isp" class="orden">ISP</th>
                    <th data-ord="ab" class="orden num">Mbps</th>
                    <th data-ord="pct" class="orden num">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sitios as $s)
                @php
                    $c  = $s->completitud;
                    $cc = $c >= 80 ? '#16a34a' : ($c >= 40 ? '#d97706' : '#dc2626');
                @endphp
                <tr onclick="location='{{ route('admin.sitios.show', $s) }}'"
                    data-nom="{{ $s->titulo }}"
                    data-pais="{{ $s->pais_label }}"
                    data-zona="{{ $s->zona?->nombre }}"
                    data-comuna="{{ $s->comuna }}"
                    data-tipo="{{ $s->tipo_label }}"
                    data-estado="{{ $s->estado_enlace_label }}"
                    data-enlace="{{ Sitio::ENLACE_TIPOS[$s->enlace_tipo] ?? '' }}"
                    data-isp="{{ $s->isp?->nombre }}"
                    data-ab="{{ (int) filter_var($s->ancho_banda, FILTER_SANITIZE_NUMBER_INT) }}"
                    data-pct="{{ $c }}">
                    <td class="mini">
                        <span class="sit-mini">
                            @if($s->portada && $s->portada->thumb_url)
                                <img src="{{ $s->portada->thumb_url }}" alt="" loading="lazy">
                            @else
                                <i class="bi {{ $s->icono }}"></i>
                            @endif
                        </span>
                    </td>
                    <td class="nom">
                        <a href="{{ route('admin.sitios.show', $s) }}">{{ $s->titulo }}</a>
                    </td>
                    <td>{{ $s->bandera }} <span class="mudo">{{ $s->pais_label }}</span></td>
                    <td>
                        @if($s->zona)<span class="sit-zona">{{ $s->zona->nombre }}</span>
                        @else<span class="mudo">—</span>@endif
                    </td>
                    <td class="mudo">{{ $s->comuna ?: '—' }}</td>
                    <td class="mudo">{{ $s->tipo_label }}</td>
                    <td class="nowrap">
                        <span class="pt" style="background:{{ $s->estado_enlace_color }}"></span>{{ $s->estado_enlace_label }}
                    </td>
                    <td class="nowrap">
                        <span class="sit-live cargando" data-live="{{ $s->id }}">
                            <span class="pto"></span><span class="txt"></span>
                        </span>
                    </td>
                    <td class="mudo">{{ Sitio::ENLACE_TIPOS[$s->enlace_tipo] ?? '—' }}</td>
                    <td class="mudo">{{ $s->isp?->nombre ?: '—' }}</td>
                    <td class="num mudo">{{ $s->ancho_banda ?: '—' }}</td>
                    <td class="num" style="font-weight:700;color:{{ $cc }}">{{ $c }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @else
    <div class="sit-grid">
        @foreach($sitios as $s)
        @php $c = $s->completitud; $cc = $c >= 80 ? '#16a34a' : ($c >= 40 ? '#d97706' : '#dc2626'); @endphp
        <div class="sit-item">
            {{-- El title lleva lo que la tarjeta recorta: el nombre completo
                 —hay varios de más de 30 caracteres— y el estado del enlace,
                 que en la tarjeta es solo un punto de color. --}}
            <a class="lnk" href="{{ route('admin.sitios.show', $s) }}"
               title="{{ $s->titulo }} · {{ $s->estado_enlace_label }}{{ $s->comuna ? ' · ' . $s->comuna : '' }} · ficha {{ $c }}% completa">
                <div class="sit-foto">
                    @if($s->portada && $s->portada->thumb_url)
                        <img src="{{ $s->portada->thumb_url }}" alt="">
                    @else
                        <i class="bi {{ $s->icono }}"></i>
                    @endif
                </div>
                <div class="sit-body">
                    <h5>
                        <span class="sit-live cargando" data-live="{{ $s->id }}"><span class="pto"></span></span>
                        @if($s->bandera)<span class="bnd">{{ $s->bandera }}</span>@endif
                        <span class="nom">{{ $s->titulo }}</span>
                        <span class="sit-pct" style="color:{{ $cc }}">{{ $c }}%</span>
                    </h5>
                    <div class="sit-tags">
                        <span class="sit-punto" style="background:{{ $s->estado_enlace_color }}"></span>
                        <span class="visually-hidden">{{ $s->estado_enlace_label }}</span>
                        <span class="sit-tipo">{{ $s->tipo_label }}</span>
                        @if($s->zona)
                            <span class="sit-zona">{{ $s->zona->nombre }}</span>
                        @else
                            <span class="sit-zona vacia">Sin zona</span>
                        @endif
                        @if($s->comuna)<span class="sit-com">{{ $s->comuna }}</span>@endif
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
    @endif
    @endif
</div>

{{-- ── Alta rápida ────────────────────────────────────────────────────────
     Sale del flujo de la página: se usa unas pocas veces y ocupaba una tarjeta
     entera encima del listado, que es lo que de verdad se viene a mirar. --}}
<div class="modal fade" id="modalNuevoSitio" tabindex="-1" aria-labelledby="tituloNuevoSitio" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.sitios.store') }}">
                @csrf
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="tituloNuevoSitio">
                        <i class="bi bi-plus-square me-1"></i>Nuevo sitio
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size:.78rem">
                        Solo nombre y tipo; el resto de la ficha se completa después, o en terreno.
                    </p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label" style="font-size:.75rem">Código</label>
                            <input type="text" name="codigo" class="form-control form-control-sm @error('codigo') is-invalid @enderror"
                                   value="{{ old('codigo') }}" placeholder="51">
                            @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-8">
                            <label class="form-label" style="font-size:.75rem">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="nuevoSitioNombre"
                                   class="form-control form-control-sm @error('nombre') is-invalid @enderror"
                                   value="{{ old('nombre') }}" required placeholder="Campo Las Palmas">
                            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem">País <span class="text-danger">*</span></label>
                            <select name="pais" class="form-select form-select-sm @error('pais') is-invalid @enderror" required>
                                <option value="">Selecciona el País</option>
                                @foreach(Sitio::PAISES as $k => $label)
                                    {{-- Si estás filtrando por un país, el sitio nuevo
                                         casi seguro es de ese mismo. --}}
                                    <option value="{{ $k }}" @selected(old('pais', $pais !== 'sin' ? $pais : '') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('pais')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select form-select-sm @error('tipo') is-invalid @enderror" required>
                                @foreach(Sitio::TIPOS as $k => $label)
                                    <option value="{{ $k }}" @selected(old('tipo', $tipo ?: 'campo') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem">Zona</label>
                            <select name="zona_id" class="form-select form-select-sm @error('zona_id') is-invalid @enderror">
                                <option value="">— Sin zona —</option>
                                @foreach($zonas as $z)
                                    {{-- Si estás filtrando por una zona, lo más probable es
                                         que el sitio nuevo sea de esa misma. --}}
                                    <option value="{{ $z->id }}" @selected((string) old('zona_id', $zona) === (string) $z->id)>{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                            @error('zona_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm px-3"><i class="bi bi-plus-lg me-1"></i>Crear ficha</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Al cerrarlo se recarga la página si cambió algo: el filtro por zona de arriba
     se habría quedado viejo. --}}
@include('admin.sitios._modal_zonas', ['recargarAlCerrar' => true])

@push('scripts')
<script>
// En DOMContentLoaded, no antes: `bootstrap` lo publica el bundle de Vite, que
// es un módulo y por lo tanto se ejecuta después de este script en línea.
document.addEventListener('DOMContentLoaded', () => {
    /* ── Ordenar la tabla ──────────────────────────────────────────────
       En el navegador y no pidiéndole otra página al servidor: las filas ya
       están acá, así que ir y volver solo agregaría una espera y perdería el
       punto donde ibas leyendo. */
    (function () {
        const tabla = document.getElementById('tablaSitios');
        if (!tabla) return;

        const cuerpo = tabla.tBodies[0];
        let columna = null, inverso = false;

        // Sin tildes y en minúsculas, para que «Ñuñoa» y «Nunoa» ordenen juntas.
        const texto = v => (v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

        tabla.querySelectorAll('th.orden').forEach(th => {
            th.addEventListener('click', () => {
                const c = th.dataset.ord;
                inverso = columna === c ? !inverso : false;
                columna = c;

                tabla.querySelectorAll('th.orden').forEach(o => o.classList.remove('asc', 'desc'));
                th.classList.add(inverso ? 'desc' : 'asc');

                // Numéricas por valor; el resto alfabético respetando el español.
                // 'live' guarda la gravedad (1 = peor), para que un clic suba los
                // problemas en vez de ordenar «Offline, Online, Sin medir» alfabetico.
                const numerica = c === 'pct' || c === 'ab' || c === 'live';

                [...cuerpo.rows]
                    .sort((a, b) => {
                        const x = a.dataset[c] ?? '', y = b.dataset[c] ?? '';
                        // Lo vacío siempre al final, se ordene como se ordene:
                        // son los que faltan por completar y estorban arriba.
                        if (x === '' && y !== '') return 1;
                        if (y === '' && x !== '') return -1;
                        const r = numerica
                            ? (Number(x) || 0) - (Number(y) || 0)
                            : texto(x).localeCompare(texto(y), 'es');
                        return inverso ? -r : r;
                    })
                    .forEach(fila => cuerpo.appendChild(fila));
            });
        });
    })();

    /* ── Live: encender los puntos con lo que mide CheckMK ──────────────
       Se pide despues de dibujar y no al renderizar: la consulta a CheckMK
       cuesta ~830 ms en frio y este listado es la pantalla que mas se abre.
       Bloquearla ahi dejaria la pagina en blanco casi un segundo, y caida
       del todo cuando CheckMK no responda.

       El refresco NUNCA recarga la pagina: vuelve a pedir el mismo JSON y
       reescribe el contenido de las celdas que ya estan. Por eso no se
       pierde nada de lo que hayas hecho —el filtro, el orden que elegiste,
       donde ibas leyendo, la vista—: el DOM no se rehace y las filas no se
       mueven de sitio. Un `location.reload()` habria sido una linea, pero te
       devolveria al principio cada 45 segundos. */
    (function () {
        const celdas = document.querySelectorAll('.sit-live[data-live]');
        if (!celdas.length) return;

        const resumen = document.getElementById('sitResumen');
        const url     = @json(route('admin.sitios.live'));
        const CADA    = 45000;   // holgado sobre el cache de 8 s del servidor

        let primeraVez = true;   // el primer fallo se ve distinto que los demas
        let pidiendo   = false;  // que una peticion lenta no se solape con la siguiente
        let ultimaBuena = null;

        const hora = (d) => d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });

        const pintar = (datos) => {
            const leyenda = datos.leyenda || {};
            const cuenta  = {};
            let   sinHost = 0;

            celdas.forEach(cel => {
                const info = datos.sitios ? datos.sitios[cel.dataset.live] : null;
                const pto  = cel.querySelector('.pto');
                const txt  = cel.querySelector('.txt');
                cel.classList.remove('cargando');

                // Sin host enlazado: no hay nada que medir. Es distinto de un
                // sitio caido y tiene que verse distinto.
                if (!info) {
                    cel.classList.add('vacio');
                    pto.style.background = '';
                    if (txt) { txt.textContent = 'Sin host'; txt.style.color = ''; }
                    cel.title = 'Este sitio no tiene ningun host de CheckMK enlazado';
                    cel.closest('tr')?.setAttribute('data-live', '');
                    sinHost++;
                    return;
                }

                cel.classList.remove('vacio');
                const par = leyenda[info.estado] || ['Sin dato', '#94a3b8'];
                pto.style.background = par[1];
                if (txt) { txt.textContent = par[0]; txt.style.color = par[1]; }

                const partes = [info.host, par[0]];
                if (info.desde)   partes.push('desde ' + info.desde);
                if (info.detalle) partes.push(info.detalle);
                if (info.otros && info.otros.length) {
                    partes.push('+ ' + info.otros.map(o => o.host + ': ' + (leyenda[o.estado]?.[0] ?? o.estado)).join(', '));
                }
                cel.title = partes.join(' · ');

                // Para ordenar: 1 es lo mas grave, porque ESTADOS viene ordenado
                // por gravedad desde el servidor. Se actualiza el dato pero NO se
                // reordena la tabla: una fila que salta sola bajo el cursor
                // mientras la estas leyendo es peor que un orden desactualizado.
                const orden = Object.keys(leyenda).indexOf(info.estado) + 1;
                cel.closest('tr')?.setAttribute('data-live', orden || '');

                cuenta[info.estado] = (cuenta[info.estado] || 0) + 1;
            });

            ultimaBuena = new Date();
            if (!resumen) return;

            const trozos = Object.entries(leyenda)
                .filter(([k]) => cuenta[k])
                .map(([k, par]) =>
                    `<span class="it"><span class="pto" style="background:${par[1]}"></span><b>${cuenta[k]}</b> ${par[0]}</span>`);
            if (sinHost) trozos.push(`<span class="it" style="color:#64748b"><b>${sinHost}</b> sin host enlazado</span>`);

            // «Sin medir» merece una frase, no solo un numero: es un host que
            // CheckMK da por caido sin haberlo medido nunca, y quien lee el
            // listado no tiene por que adivinarlo.
            if (cuenta.sin_ip) {
                trozos.push(`<span class="aviso" title="CheckMK los reporta caidos pero no tienen IP configurada, asi que nunca los midio">`
                    + `&#9432; «Sin medir» = sin IP en CheckMK, no es una caida</span>`);
            }
            trozos.push(`<span class="sello">${hora(ultimaBuena)}</span>`);

            resumen.innerHTML = trozos.join('<span style="color:#e2e8f0">·</span>');
            resumen.classList.add('hay');
            resumen.classList.remove('viejo');
        };

        const fallar = (motivo) => {
            // Si ya habia datos buenos se CONSERVAN: borrarlos porque un
            // refresco fallo dejaria la pantalla peor que antes de refrescar.
            // Solo se avisa de que lo que se ve ya no es de ahora.
            if (!primeraVez && ultimaBuena) {
                if (resumen) {
                    resumen.classList.add('viejo');
                    const sello = resumen.querySelector('.sello');
                    if (sello) {
                        sello.textContent = 'sin actualizar desde ' + hora(ultimaBuena);
                        sello.title = 'El ultimo intento fallo: ' + motivo;
                    }
                }
                return;
            }

            celdas.forEach(cel => {
                cel.classList.remove('cargando');
                const txt = cel.querySelector('.txt');
                if (txt) { txt.textContent = '—'; txt.style.color = '#94a3b8'; }
                // Nunca marcar «offline» por no haber podido preguntar.
                cel.title = 'No se pudo consultar CheckMK: ' + motivo;
                cel.closest('tr')?.setAttribute('data-live', '');
            });
            if (resumen) {
                resumen.innerHTML = '<span class="fallo">No se pudo consultar CheckMK, los puntos quedan sin dato</span>';
                resumen.classList.add('hay');
            }
        };

        const pedir = () => {
            if (pidiendo) return;
            pidiendo = true;

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => {
                    // Una sesion caducada redirige al login y devuelve HTML con 200:
                    // sin esto, el JSON.parse falla con un mensaje incomprensible.
                    if (r.redirected && /\/login/.test(r.url)) throw new Error('la sesion caduco, recarga la pagina');
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(datos => datos && datos.ok ? pintar(datos) : fallar(datos?.error || 'sin detalle'))
                .catch(e => fallar(e.message))
                .finally(() => { pidiendo = false; primeraVez = false; });
        };

        pedir();

        // En una pestaña de fondo no se refresca: serian consultas a CheckMK
        // que nadie va a mirar. Al volver se pide de inmediato, porque lo que
        // quedo en pantalla puede ser de hace rato.
        setInterval(() => { if (document.visibilityState === 'visible') pedir(); }, CADA);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') pedir();
        });
    })();

    const modal = document.getElementById('modalNuevoSitio');
    if (!modal) return;

    modal.addEventListener('shown.bs.modal', () => document.getElementById('nuevoSitioNombre').focus());

    // Si la validación del servidor rechazó el alta, el modal ya está cerrado y
    // los mensajes quedarían invisibles: se reabre para que se vean.
    @if($errors->has('nombre') || $errors->has('tipo') || $errors->has('codigo'))
        try { new bootstrap.Modal(modal).show(); } catch (e) {}
    @endif
});
</script>
@endpush
@endsection
