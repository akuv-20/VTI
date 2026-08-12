@extends('layouts.app')

@section('content')
<style>
    .in-filtro { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:.6rem .75rem; margin-bottom:.85rem; }
    .in-filtro .tit { font-size:.66rem; font-weight:700; color:#94a3b8; text-transform:uppercase;
                      letter-spacing:.04em; margin-bottom:.45rem; }
    .in-zonas { display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; }
    .in-z { position:relative; }
    .in-z input { position:absolute; opacity:0; width:0; height:0; }
    .in-z span { display:inline-block; font-size:.75rem; padding:.25rem .7rem; border-radius:20px;
                 border:1px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; user-select:none; }
    .in-z span:hover { border-color:#94a3b8; }
    .in-z input:checked + span { background:#0f172a; border-color:#0f172a; color:#fff; font-weight:600; }
    .in-z input:focus-visible + span { outline:2px solid #7c3aed; outline-offset:2px; }
    .in-z .n { opacity:.6; margin-left:.25rem; font-size:.7rem; }

    /* La tabla puede tener 50 columnas: scroll horizontal propio y encabezado
       fijo, con las dos primeras columnas ancladas para no perder de vista de
       qué sitio es cada fila. */
    .in-caja { background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:auto;
               max-height:calc(100vh - var(--topbar-h) - 210px); }
    .in-tabla { border-collapse:separate; border-spacing:0; font-size:.76rem; white-space:nowrap; }
    .in-tabla th, .in-tabla td { padding:.4rem .6rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    .in-tabla thead th { position:sticky; top:0; z-index:3; background:#f8fafc; color:#475569;
                         font-size:.66rem; text-transform:uppercase; letter-spacing:.03em;
                         border-bottom:1px solid #e2e8f0; }
    .in-tabla thead tr.grupos th { top:0; z-index:4; font-size:.62rem; color:#94a3b8; background:#fff;
                                   border-bottom:1px solid #f1f5f9; }
    .in-tabla tbody tr:hover td { background:#f8fafc; }
    .in-tabla td.larga { max-width:280px; overflow:hidden; text-overflow:ellipsis; }

    .in-tabla .fij1, .in-tabla .fij2 { position:sticky; z-index:2; background:#fff; }
    .in-tabla .fij1 { left:0; }
    .in-tabla .fij2 { left:var(--fij1, 190px); border-right:1px solid #e2e8f0; }
    .in-tabla thead .fij1, .in-tabla thead .fij2 { z-index:5; background:#f8fafc; }
    .in-tabla tbody tr:hover .fij1, .in-tabla tbody tr:hover .fij2 { background:#f8fafc; }
    .in-tabla tbody .fij1 a { color:#1e293b; font-weight:600; text-decoration:none; }
    .in-tabla tbody .fij1 a:hover { color:#7c3aed; }

    .in-vacio { color:#e2e8f0; }
    .in-nota { font-size:.72rem; color:#94a3b8; margin-top:.5rem; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-table me-2" style="color:#7c3aed"></i>Informe de sitios
            <span class="text-muted fw-normal" style="font-size:.82rem">
                {{ $sitios->count() }} {{ $sitios->count() === 1 ? 'sitio' : 'sitios' }} ·
                {{ count($columnas) }} de {{ $totales }} columnas con datos
            </span>
        </h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal" data-bs-target="#modalPdf"
                    title="Resumen ejecutivo de 12 columnas">
                <i class="bi bi-file-earmark-pdf me-1"></i>Informe PDF
            </button>
            <a href="{{ route('admin.informes.sitios.excel', request()->only('zonas', 'filtrado')) }}"
               class="btn btn-success btn-sm" title="Todas las columnas con datos">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar a Excel
            </a>
        </div>
    </div>

    {{-- ── Filtro por zona ─────────────────────────────────────────────────
         `filtrado=1` viaja siempre para distinguir «no filtró» de «destildó
         todo»: sin eso, quitar todas las marcas se leería como no haber
         filtrado nunca y el servidor restauraría la selección guardada. --}}
    <form method="GET" action="{{ route('admin.informes.sitios') }}" class="in-filtro" id="fFiltro">
        <input type="hidden" name="filtrado" value="1">
        <div class="tit">Zonas</div>
        <div class="in-zonas">
            @foreach($zonas as $z)
                <label class="in-z">
                    <input type="checkbox" name="zonas[]" value="{{ $z->id }}"
                           @checked(in_array((string) $z->id, array_map('strval', $elegidas), true))>
                    <span>{{ $z->nombre }} <span class="n">{{ $z->sitios_count }}</span></span>
                </label>
            @endforeach

            @if($sinZona)
                <label class="in-z">
                    <input type="checkbox" name="zonas[]" value="sin"
                           @checked(in_array('sin', $elegidas, true))>
                    <span>Sin zona <span class="n">{{ $sinZona }}</span></span>
                </label>
            @endif

            <span class="ms-auto d-flex gap-2 align-items-center">
                @if($elegidas)
                    <a href="{{ route('admin.informes.sitios', ['filtrado' => 1]) }}"
                       class="btn btn-link btn-sm p-0" style="font-size:.74rem">Limpiar</a>
                @endif
                <button type="submit" class="btn btn-outline-secondary btn-sm py-0">Aplicar</button>
            </span>
        </div>
        @unless($elegidas)
            <div class="in-nota">Sin marcar ninguna se muestran todas.</div>
        @endunless
    </form>

    @if($sitios->isEmpty())
        <div class="in-filtro text-center text-muted py-5" style="font-size:.85rem">
            Ningún sitio en las zonas elegidas.
        </div>
    @else
    <div class="in-caja">
        <table class="in-tabla">
            <thead>
                @php
                    // Cabecera de grupos: una celda por grupo, del ancho de sus
                    // columnas. Ubica al leer una tabla de decenas de columnas.
                    $grupos = [];
                    foreach ($columnas as $c) {
                        $g = $c[1];
                        $grupos[$g] = ($grupos[$g] ?? 0) + 1;
                    }
                @endphp
                <tr class="grupos">
                    @foreach($grupos as $g => $n)
                        <th colspan="{{ $n }}">{{ $g }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($columnas as $clave => $c)
                        <th class="{{ $loop->index === 0 ? 'fij1' : ($loop->index === 1 ? 'fij2' : '') }}">{{ $c[0] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($sitios as $s)
                <tr>
                    @foreach($columnas as $clave => $c)
                        @php $v = $c[2]($s); @endphp
                        <td class="{{ $loop->index === 0 ? 'fij1' : ($loop->index === 1 ? 'fij2' : '') }} {{ mb_strlen((string) $v) > 40 ? 'larga' : '' }}"
                            @if(mb_strlen((string) $v) > 40) title="{{ $v }}" @endif>
                            @if($loop->index === 0)
                                <a href="{{ route('admin.sitios.show', $s) }}">{{ $v }}</a>
                            @elseif($v === null || $v === '')
                                <span class="in-vacio">—</span>
                            @else
                                {{ $v }}
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="in-nota">
        Se ocultan {{ $totales - count($columnas) }} columnas porque ningún sitio de esta selección
        tiene datos en ellas. Al filtrar por zona, la tabla se ajusta sola.
    </div>
    @endif
</div>

{{-- ── Vista previa del informe ─────────────────────────────────────────────
     El `src` del iframe se pone recién al abrir el modal: si viniera en el HTML,
     cada carga de esta pantalla generaría un PDF de ~900 KB que casi siempre
     nadie va a mirar. --}}
<div class="modal fade" id="modalPdf" tabindex="-1" aria-labelledby="tituloPdf" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height:88vh">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="tituloPdf">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Informe ejecutivo
                    <span class="text-muted fw-normal" style="font-size:.78rem">
                        — {{ $sitios->count() }} {{ $sitios->count() === 1 ? 'sitio' : 'sitios' }},
                        {{ $elegidas ? 'zonas filtradas' : 'todas las zonas' }}
                    </span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            {{-- El aviso va SUPERPUESTO al iframe, no encima en el flujo: si los
                 dos ocupan alto, el modal muestra el spinner arriba y el informe
                 abajo, cada uno con la mitad. --}}
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
                <a href="{{ route('admin.informes.sitios.pdf', request()->only('zonas', 'filtrado')) }}"
                   target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm me-auto">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Abrir en pestaña nueva
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                {{-- Enlace de verdad —funciona sin JavaScript— pero el script lo
                     intercepta: navegar a esta URL deja la pestaña «cargando»
                     durante todo lo que tarde el servidor en armar el PDF, sin
                     que nada en pantalla lo explique. --}}
                <a href="{{ route('admin.informes.sitios.pdf', request()->only('zonas', 'filtrado') + ['descargar' => 1]) }}"
                   id="pdfBajar" class="btn btn-danger btn-sm">
                    <i class="bi bi-download me-1"></i>Descargar PDF
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    /* ── Vista previa del PDF ────────────────────────────────────────────── */
    const modalPdf = document.getElementById('modalPdf');
    if (modalPdf) {
        const marco   = document.getElementById('pdfMarco');
        const cargando = document.getElementById('pdfCargando');
        const url = @json(route('admin.informes.sitios.pdf', request()->only('zonas', 'filtrado')));

        // El `load` de un iframe con PDF no es confiable: segun el navegador y el
        // visor, puede no dispararse nunca. Por eso el aviso se quita por evento
        // O por tiempo, lo que ocurra primero, y nunca se queda pegado tapando el
        // informe ya renderizado.
        let reloj = null;
        const listo = () => {
            clearTimeout(reloj);
            marco.dataset.cargado = '1';
            cargando.style.display = 'none';
        };

        modalPdf.addEventListener('show.bs.modal', () => {
            if (marco.dataset.cargado) return;      // ya está, no se regenera
            cargando.style.display = 'flex';
            marco.src = url;
            reloj = setTimeout(listo, 8000);
        });

        marco.addEventListener('load', () => {
            if (marco.src) listo();
        });

        // Si cambia el filtro, la próxima apertura tiene que regenerarlo. Como el
        // filtro recarga la página entera, basta con no persistir nada acá.

        /* ── Descarga sin dejar la página colgada ────────────────────────────
           Un <a> normal navega la pestaña actual: mientras el servidor arma el
           PDF, el navegador muestra la página «cargando» y no hay nada que diga
           qué está pasando. Se baja por fetch y se guarda desde memoria, así la
           pantalla nunca se mueve y el botón puede avisar que está trabajando. */
        const bajar = document.getElementById('pdfBajar');

        bajar?.addEventListener('click', async e => {
            if (!window.fetch || !window.URL?.createObjectURL) return;   // sin soporte, que navegue
            e.preventDefault();
            if (bajar.dataset.ocupado) return;

            const original = bajar.innerHTML;
            bajar.dataset.ocupado = '1';
            bajar.classList.add('disabled');
            bajar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando…';

            try {
                const r = await fetch(bajar.href, { headers: { 'Accept': 'application/pdf' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);

                const blob = await r.blob();

                // El nombre lo decide el servidor; si no viene, se arma uno.
                const cd = r.headers.get('content-disposition') || '';
                const m  = cd.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                const nombre = m ? decodeURIComponent(m[1].replace(/"$/, ''))
                                 : 'estado_conectividad.pdf';

                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = nombre;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (err) {
                // Que no se quede en silencio: si falló, se abre en pestaña nueva.
                window.open(bajar.href, '_blank', 'noopener');
            } finally {
                delete bajar.dataset.ocupado;
                bajar.classList.remove('disabled');
                bajar.innerHTML = original;
            }
        });
    }

    // Marcar o desmarcar envía solo: con checkboxes, obligar a apretar "Aplicar"
    // hace que uno crea que ya filtró cuando no.
    document.querySelectorAll('#fFiltro input[name="zonas[]"]').forEach(ch => {
        ch.addEventListener('change', () => ch.form.submit());
    });

    // La segunda columna se ancla justo después de la primera, que no tiene un
    // ancho fijo: se mide y se pasa a CSS.
    const tabla = document.querySelector('.in-tabla');
    if (!tabla) return;

    const medir = () => {
        const primera = tabla.querySelector('thead .fij1');
        if (primera) tabla.style.setProperty('--fij1', primera.offsetWidth + 'px');
    };
    medir();
    window.addEventListener('resize', medir);
});
</script>
@endpush
@endsection
