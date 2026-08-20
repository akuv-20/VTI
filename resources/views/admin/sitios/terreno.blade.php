@extends('layouts.app')

@section('content')
<style>
    .tr-wrap { max-width:620px; margin:0 auto; }

    /* ── Barra de estado: conexión y fichas guardadas ─────────────────── */
    .tr-estado { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
                 background:#fff; border:1px solid #e2e8f0; border-radius:11px;
                 padding:.55rem .7rem; margin-bottom:.6rem; }
    .tr-senal { display:inline-flex; align-items:center; gap:.4rem; font-size:.76rem;
                font-weight:700; padding:.25rem .6rem; border-radius:20px; white-space:nowrap; }
    .tr-senal .punto { width:.5rem; height:.5rem; border-radius:50%; flex:0 0 auto; }
    .tr-senal.probando { background:#f1f5f9; color:#64748b; }
    .tr-senal.probando .punto { background:#94a3b8; animation:trLatir 1.1s ease-in-out infinite; }
    .tr-senal.online   { background:#dcfce7; color:#15803d; }
    .tr-senal.online .punto { background:#22c55e; }
    .tr-senal.offline  { background:#fee2e2; color:#b91c1c; }
    .tr-senal.offline .punto { background:#ef4444; animation:trLatir 1.6s ease-in-out infinite; }
    .tr-senal.sesion   { background:#fef3c7; color:#b45309; }
    .tr-senal.sesion .punto { background:#f59e0b; }
    @keyframes trLatir { 0%,100% { opacity:1 } 50% { opacity:.25 } }
    @media (prefers-reduced-motion: reduce) { .tr-senal .punto { animation:none !important } }

    .tr-guardadas { font-size:.73rem; color:#64748b; flex:1 1 auto; line-height:1.3; }
    .tr-guardadas b { color:#334155; }
    .tr-guardadas.completo b { color:#15803d; }
    .tr-guardadas.falta  b { color:#b45309; }

    /* ── Filtros ──────────────────────────────────────────────────────── */
    .tr-filtros { position:sticky; top:0; z-index:5; background:#e8ecf0;
                  padding:.5rem 0 .55rem; margin-bottom:.15rem; }
    .tr-zonas { display:flex; gap:.35rem; overflow-x:auto; padding:.5rem 0 .15rem;
                scrollbar-width:none; -webkit-overflow-scrolling:touch; }
    .tr-zonas::-webkit-scrollbar { display:none; }
    .tr-zona { flex:0 0 auto; font-size:.76rem; font-weight:600; color:#475569;
               background:#fff; border:1px solid #cbd5e1; border-radius:20px;
               padding:.32rem .75rem; cursor:pointer; white-space:nowrap; }
    .tr-zona[aria-pressed="true"] { background:#7c3aed; border-color:#7c3aed; color:#fff; }
    .tr-zona .n { opacity:.65; font-weight:500; margin-left:.25rem; }
    .tr-conteo { font-size:.72rem; color:#94a3b8; padding:.15rem .1rem 0; }

    /* ── Ficha del listado ────────────────────────────────────────────── */
    .tr-item { display:flex; align-items:center; gap:.75rem; background:#fff; border:1px solid #e2e8f0; border-radius:11px;
               padding:.7rem .85rem; margin-bottom:.6rem; text-decoration:none; color:inherit; position:relative; }
    .tr-item:active { background:#f8fafc; }
    .tr-item[hidden] { display:none; }
    .tr-foto { width:52px; height:52px; border-radius:9px; background:#f1f5f9; flex:0 0 auto; overflow:hidden;
               display:flex; align-items:center; justify-content:center; }
    .tr-foto img { width:100%; height:100%; object-fit:cover; }
    .tr-foto i { font-size:1.4rem; color:#cbd5e1; }
    .tr-nom { font-size:.95rem; font-weight:700; color:#1e293b; line-height:1.25; }
    .tr-sub { font-size:.75rem; color:#94a3b8; margin-top:1px; }
    .tr-badge { font-size:.66rem; font-weight:700; padding:2px 9px; border-radius:20px; color:#fff; }
    .tr-zchip { font-size:.66rem; font-weight:700; padding:2px 9px; border-radius:20px;
                background:#ede9fe; color:#6d28d9; }
    .tr-zchip.vacia { background:#f8fafc; color:#94a3b8; border:1px dashed #cbd5e1; }
    .tr-comp { font-size:.7rem; font-weight:700; color:#64748b; flex:0 0 auto; }

    /* Punto que indica que la ficha está descargada en el teléfono. Se pinta
       solo cuando NO lo está: lo normal es que todo esté guardado, y marcar lo
       normal sería ruido. */
    .tr-item .tr-sinbajar { position:absolute; top:.5rem; right:.5rem; font-size:.6rem;
                            font-weight:700; color:#b45309; background:#fffbeb;
                            border:1px solid #fde68a; border-radius:20px; padding:1px 7px; }

    .tr-vacio { text-align:center; color:#94a3b8; font-size:.85rem; padding:2.5rem 1rem; }

    @media (max-width:576px) {
        .tr-item { padding:.85rem; }
        .tr-nom { font-size:1rem; }
    }
</style>

<div class="container-fluid vti-page">
    <div class="tr-wrap">
        <div class="vti-page-header">
            <h4><i class="bi bi-phone me-2" style="color:#7c3aed"></i>Levantamiento en terreno</h4>
        </div>

        {{-- ── Estado: conexión real y fichas descargadas ──────────────────
             La conexión NO se toma de `navigator.onLine`: en un campo basta
             con estar pegado a una antena sin salida para que diga que sí.
             Se consulta el latido del servidor, que además toca la base. --}}
        <div class="tr-estado" id="trEstado">
            <span class="tr-senal probando" id="trSenal">
                <span class="punto"></span><span id="trSenalTxt">Comprobando…</span>
            </span>
            <span class="tr-guardadas" id="trGuardadas"></span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPreparar"
                    style="font-size:.72rem;padding:.2rem .55rem" hidden>
                <i class="bi bi-arrow-repeat"></i>
            </button>
        </div>

        {{-- Cola de fichas escritas en el teléfono que aún no llegan al servidor. --}}
        <div id="trPendientes" class="align-items-center gap-2 mb-2"
             style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.6rem .75rem">
            <i class="bi bi-cloud-arrow-up-fill" style="color:#b45309"></i>
            <div style="flex:1 1 auto;font-size:.8rem;color:#b45309" id="trPendTexto"></div>
            <button type="button" class="btn btn-warning btn-sm" id="btnSincronizar">Sincronizar</button>
        </div>

        <div id="trPrepMsg" class="mb-2" style="font-size:.74rem;color:#64748b"></div>

        {{-- ── Filtros ─────────────────────────────────────────────────────
             Todo del lado del navegador: en terreno no hay red para pedirle
             otra lista al servidor. --}}
        <div class="tr-filtros">
            <input type="search" id="trBuscar" class="form-control" placeholder="Buscar sitio…"
                   autocomplete="off" enterkeyhint="search">

            <div class="tr-zonas" id="trZonas" role="group" aria-label="Filtrar por zona">
                <button type="button" class="tr-zona" data-zona="" aria-pressed="true">
                    Todas<span class="n">{{ $sitios->count() }}</span>
                </button>
                @foreach($zonas as $z)
                <button type="button" class="tr-zona" data-zona="{{ $z->id }}" aria-pressed="false">
                    {{ $z->nombre }}<span class="n">{{ $sitios->where('zona_id', $z->id)->count() }}</span>
                </button>
                @endforeach
                @if($sinZona > 0)
                <button type="button" class="tr-zona" data-zona="sin" aria-pressed="false">
                    Sin zona<span class="n">{{ $sinZona }}</span>
                </button>
                @endif
            </div>

            <div class="tr-conteo" id="trConteo"></div>
        </div>

        <div id="trLista">
            @foreach($sitios as $s)
            @php
                $buscable = mb_strtolower(trim(implode(' ', array_filter([
                    $s->nombre, $s->codigo, $s->comuna, $s->tipo_label,
                    $s->zona?->nombre, $s->encargado_nombre, $s->estado_enlace_label,
                ]))));
            @endphp
            <a href="{{ route('admin.sitios.terreno.ficha', $s) }}" class="tr-item"
               data-id="{{ $s->id }}"
               data-zona="{{ $s->zona_id ?: 'sin' }}"
               data-buscar="{{ $buscable }}">
                <div class="tr-foto">
                    @if($s->portada && $s->portada->thumb_url)
                        <img src="{{ $s->portada->thumb_url }}" alt="" loading="lazy">
                    @else
                        <i class="bi {{ $s->icono }}"></i>
                    @endif
                </div>
                <div style="flex:1 1 auto;min-width:0">
                    <div class="tr-nom">{{ $s->titulo }}</div>
                    <div class="tr-sub">
                        {{ $s->tipo_label }}@if($s->comuna) · {{ $s->comuna }}@endif
                    </div>
                    <div class="mt-1 d-flex gap-1 flex-wrap">
                        <span class="tr-badge" style="background:{{ $s->estado_enlace_color }}">{{ $s->estado_enlace_label }}</span>
                        @if($s->zona)
                            <span class="tr-zchip">{{ $s->zona->nombre }}</span>
                        @else
                            <span class="tr-zchip vacia">Sin zona</span>
                        @endif
                    </div>
                </div>
                <div class="tr-comp">{{ $s->completitud }}%</div>
                <i class="bi bi-chevron-right text-muted"></i>
            </a>
            @endforeach
        </div>

        <div class="tr-vacio" id="trVacio" hidden>Ningún sitio coincide con el filtro.</div>
    </div>
</div>
@endsection

@push('styles')
<link rel="manifest" href="/manifest.webmanifest">
@endpush

@push('scripts')
<script src="/js/terreno-offline.js?v={{ filemtime(public_path('js/terreno-offline.js')) }}"></script>
<script>
(function () {
    'use strict';

    const T        = window.VtiTerreno;
    const URL_PING = @json(route('admin.sitios.terreno.ping'));
    const CLAVE_ZONA = 'vti.terreno.zona';

    /* ══ Filtrado (texto + zona), todo en el navegador ═══════════════════
       En terreno no hay red para pedirle otra lista al servidor, así que el
       servidor manda TODOS los sitios y acá se recorta. */

    const items   = [...document.querySelectorAll('.tr-item')];
    const buscar  = document.getElementById('trBuscar');
    const conteo  = document.getElementById('trConteo');
    const vacio   = document.getElementById('trVacio');
    const botones = [...document.querySelectorAll('.tr-zona')];

    // Sin tildes y en minúsculas: en el teléfono nadie escribe los acentos.
    const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    let zonaActiva = localStorage.getItem(CLAVE_ZONA) || '';
    // Una zona guardada puede haber desaparecido (se borró, o se quedó sin
    // sitios activos): sin esto el listado abriría vacío sin explicar por qué.
    if (zonaActiva && !botones.some((b) => b.dataset.zona === zonaActiva)) zonaActiva = '';

    function filtrar() {
        const terminos = norm(buscar.value).split(/\s+/).filter(Boolean);
        let visibles = 0;

        for (const a of items) {
            const okZona  = !zonaActiva || a.dataset.zona === zonaActiva;
            const texto   = norm(a.dataset.buscar);
            const okTexto = terminos.every((t) => texto.includes(t));
            const ver     = okZona && okTexto;

            a.hidden = !ver;
            if (ver) visibles++;
        }

        vacio.hidden = visibles > 0;
        conteo.textContent = visibles === items.length
            ? items.length + ' sitios'
            : visibles + ' de ' + items.length + ' sitios';
    }

    botones.forEach((b) => b.addEventListener('click', () => {
        zonaActiva = b.dataset.zona;
        botones.forEach((o) => o.setAttribute('aria-pressed', String(o === b)));
        // Se recuerda entre recargas: en terreno se entra y se sale de la
        // pantalla todo el rato y volver a elegir la zona cada vez cansa.
        localStorage.setItem(CLAVE_ZONA, zonaActiva);
        filtrar();
    }));

    buscar.addEventListener('input', filtrar);

    botones.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.zona === zonaActiva)));
    filtrar();

    /* ══ Estado de la conexión ═══════════════════════════════════════════
       No se usa `navigator.onLine`: devuelve true con solo estar pegado a un
       WiFi o a una antena sin salida, que es exactamente lo que pasa en un
       campo. Se pregunta al servidor, que además toca la base de datos. */

    const senal    = document.getElementById('trSenal');
    const senalTxt = document.getElementById('trSenalTxt');

    const CADENCIA_OK   = 20000;  // con conexión no hace falta insistir
    const CADENCIA_MAL  = 8000;   // sin ella, detectar el regreso rápido importa
    const CORTE_PING_MS = 6000;

    let hayRed = null;
    let temporizador = null;

    const SENALES = {
        online:  ['online',  'En línea',
                  'Hay salida hacia la aplicación y la base de datos responde.'],
        offline: ['offline', 'Sin conexión',
                  'No se llega al servidor. Lo que escribas se guarda en el teléfono y se envía al volver la señal.'],
        sesion:  ['sesion',  'Sesión caducada',
                  'Hay señal, pero tu sesión expiró: hay que volver a entrar para poder guardar.'],
    };

    function pintarSenal(estado) {
        const [clase, texto, ayuda] = SENALES[estado] || SENALES.offline;
        senal.className      = 'tr-senal ' + clase;
        senalTxt.textContent = texto;
        senal.title          = ayuda;
    }

    async function medirRed() {
        const control = new AbortController();
        const corte   = setTimeout(() => control.abort(), CORTE_PING_MS);
        let ok = false;

        let estado = 'offline';

        try {
            const r = await fetch(URL_PING + '?t=' + Date.now(), {
                cache: 'no-store', credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: control.signal,
            });

            // Con la sesión caducada Laravel redirige al login, que responde
            // 200. Darlo por bueno sería lo peor posible: diría «En línea»
            // justo cuando no se puede guardar nada.
            if (r.redirected && /\/login/.test(r.url)) {
                estado = 'sesion';
            } else {
                // No basta con que responda: tiene que ser NUESTRA respuesta y
                // decir que la base contestó. Un 503 es servidor arriba y base
                // caída, que para el levantamiento da igual.
                const datos = await r.json().catch(() => null);
                ok = r.ok && datos !== null && datos.ok === true;
                estado = ok ? 'online' : 'offline';
            }
        } catch {
            ok = false;
        } finally {
            clearTimeout(corte);
        }

        const cambio = ok !== hayRed;
        hayRed = ok;
        pintarSenal(estado);

        // Al recuperar la señal: mandar lo pendiente y terminar la descarga.
        if (cambio && ok) {
            pintarPendientes().then(() => asegurarDescarga());
        }
        return ok;
    }

    function programar() {
        clearTimeout(temporizador);
        // Con la pantalla apagada o en otra app no tiene sentido gastar batería.
        if (document.hidden) return;
        temporizador = setTimeout(latir, hayRed ? CADENCIA_OK : CADENCIA_MAL);
    }

    async function latir() { await medirRed(); programar(); }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(temporizador); else latir();
    });
    // Estos eventos no se creen como verdad, solo como aviso de que algo
    // cambió y conviene volver a medir.
    window.addEventListener('online',  latir);
    window.addEventListener('offline', latir);

    /* ══ Sincronización de lo escrito sin señal ══════════════════════════ */

    const barra = document.getElementById('trPendientes');
    const texto = document.getElementById('trPendTexto');
    const msg   = document.getElementById('trPrepMsg');

    async function pintarPendientes() {
        if (!('indexedDB' in window)) return;

        const cola = await T.pendientes();
        barra.style.display = cola.length ? 'flex' : 'none';
        texto.textContent = cola.length
            ? cola.length + ' ficha' + (cola.length > 1 ? 's' : '') +
              ' guardada' + (cola.length > 1 ? 's' : '') + ' en el teléfono sin enviar'
            : '';

        items.forEach((a) => { a.style.borderColor = ''; a.style.background = ''; });
        cola.forEach((r) => {
            const enlace = document.querySelector('.tr-item[data-id="' + r.sitioId + '"]');
            if (enlace) { enlace.style.borderColor = '#f59e0b'; enlace.style.background = '#fffbeb'; }
        });
    }

    document.getElementById('btnSincronizar').addEventListener('click', async function () {
        const b = this;
        b.disabled = true; b.textContent = 'Enviando…';

        const r = await T.sincronizar((hechas, total) => { b.textContent = hechas + '/' + total; });

        b.disabled = false; b.textContent = 'Sincronizar';
        await pintarPendientes();

        if (r.sesion) {
            msg.innerHTML = '<span style="color:#b91c1c">Tu sesión caducó. Vuelve a entrar y sincroniza de nuevo; nada se perdió.</span>';
        } else if (r.quedan) {
            msg.innerHTML = '<span style="color:#b45309">' + r.enviadas + ' enviada(s), quedan ' + r.quedan + '. Reintenta con mejor señal.</span>';
        } else if (r.enviadas) {
            msg.innerHTML = '<span style="color:#15803d">Listo: ' + r.enviadas + ' ficha(s) enviadas.</span>';
        }
    });

    /* ══ Descarga de TODAS las fichas ════════════════════════════════════
       Antes había que acordarse de tocar «Preparar para terreno», y si no se
       hacía, la ficha aparecía como «no está guardada» estando ya en el campo,
       donde no hay nada que hacer. Ahora se dispara sola al entrar: la primera
       vez baja todo y las siguientes solo lo que falte. */

    const cajaGuardadas = document.getElementById('trGuardadas');
    const btnPreparar   = document.getElementById('btnPreparar');

    /** Todo lo que hay que tener en el teléfono: las fichas y lo que ellas usan. */
    function urlsNecesarias() {
        const recursos = [
            ...[...document.querySelectorAll('script[src]')].map((s) => s.src),
            ...[...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.href),
            // Tipografías e iconos: los pide el CSS, así que no están en el DOM.
            ...performance.getEntriesByType('resource')
                .filter((e) => /\.(woff2?|ttf|png|svg|jpe?g)(\?|$)/i.test(e.name))
                .map((e) => e.name),
        ];

        return [...new Set([
            location.origin + '{{ route('admin.sitios.terreno', [], false) }}',
            // `items`, no lo visible: se baja el listado completo aunque haya
            // un filtro puesto. Filtrar es para mirar, no para decidir qué
            // llevarse — con un filtro activo se saldría a terreno con la
            // mitad de las fichas.
            ...items.map((a) => a.href),
            ...recursos,
        ])];
    }

    let sw = null;

    function pedirAlSw(mensaje) { sw?.postMessage(mensaje); }

    /** Cuántas fichas faltan por bajar, según el service worker. */
    function consultarEstado() {
        return new Promise((resolver) => {
            if (!sw) return resolver(null);

            const alResponder = (ev) => {
                if ((ev.data || {}).tipo !== 'estado') return;
                navigator.serviceWorker.removeEventListener('message', alResponder);
                resolver(ev.data);
            };
            navigator.serviceWorker.addEventListener('message', alResponder);
            pedirAlSw({ tipo: 'estado', urls: urlsNecesarias() });

            // Si el service worker no contesta, no dejar la promesa colgada.
            setTimeout(() => {
                navigator.serviceWorker.removeEventListener('message', alResponder);
                resolver(null);
            }, 5000);
        });
    }

    function pintarGuardadas(estado) {
        if (!estado) { cajaGuardadas.textContent = ''; return; }

        // Solo importan las fichas; que falte una tipografía no impide trabajar.
        const fichas = new Set(items.map((a) => a.href));
        const faltan = estado.faltan.filter((u) => fichas.has(u)).length;
        const listas = fichas.size - faltan;

        cajaGuardadas.className = 'tr-guardadas ' + (faltan ? 'falta' : 'completo');
        cajaGuardadas.innerHTML = faltan
            ? '<b>' + listas + ' de ' + fichas.size + '</b> fichas descargadas'
            : '<b>' + fichas.size + ' fichas</b> listas para salir sin señal';

        items.forEach((a) => {
            const marca = a.querySelector('.tr-sinbajar');
            if (estado.faltan.includes(a.href)) {
                if (!marca) {
                    const s = document.createElement('span');
                    s.className = 'tr-sinbajar';
                    s.textContent = 'sin bajar';
                    a.appendChild(s);
                }
            } else if (marca) {
                marca.remove();
            }
        });
    }

    let descargando = false;

    /** Baja lo que falte. `forzar` vuelve a pedirlo todo, aunque ya esté. */
    async function descargar(forzar) {
        if (descargando || !sw) return;

        // La marca se pone ANTES del primer `await`. Si se pusiera después,
        // dos llamadas casi simultáneas —la del arranque y la que dispara el
        // regreso de la señal— pasarían ambas el control y se descargaría todo
        // dos veces.
        descargando = true;
        btnPreparar.disabled = true;

        const estado = await consultarEstado();
        if (!forzar && estado && estado.faltan.length === 0) {
            descargando = false;
            btnPreparar.disabled = false;
            pintarGuardadas(estado);
            return;
        }

        const alAvanzar = (ev) => {
            const d = ev.data || {};
            if (d.tipo === 'precarga-avance') {
                const hechas = d.ok + d.fallos + d.saltadas;
                cajaGuardadas.className = 'tr-guardadas';
                cajaGuardadas.innerHTML = 'Descargando fichas… <b>' + hechas + '/' + d.total + '</b>';
            }
            if (d.tipo === 'precarga-fin') {
                navigator.serviceWorker.removeEventListener('message', alAvanzar);
                descargando = false;
                btnPreparar.disabled = false;

                if (d.fallos) {
                    msg.innerHTML = '<span style="color:#b45309">' + d.fallos +
                        ' ficha(s) no se pudieron descargar. Reintenta con mejor señal antes de salir.</span>';
                } else {
                    msg.textContent = '';
                }
                consultarEstado().then(pintarGuardadas);
            }
        };

        navigator.serviceWorker.addEventListener('message', alAvanzar);
        pedirAlSw({ tipo: 'precargar', urls: urlsNecesarias(), forzar: !!forzar });
    }

    /** Reintenta la descarga si quedó incompleta y hay señal. */
    async function asegurarDescarga() {
        if (!sw || !hayRed) return;
        const estado = await consultarEstado();
        pintarGuardadas(estado);
        if (estado && estado.faltan.length) descargar(false);
    }

    btnPreparar.addEventListener('click', () => descargar(true));

    /* ══ Arranque ════════════════════════════════════════════════════════ */

    (async function iniciar() {
        await medirRed();
        programar();

        if ('indexedDB' in window) await pintarPendientes();

        const reg = await T.registrarSw();
        if (!reg) {
            // Sin HTTPS el navegador no permite service workers: la pantalla
            // sirve igual, pero conviene que se sepa que no habrá modo terreno.
            cajaGuardadas.innerHTML =
                '<span style="color:#b45309">Este navegador no guarda las fichas ' +
                'para usarlas sin señal (requiere HTTPS).</span>';
            return;
        }

        await navigator.serviceWorker.ready;
        sw = navigator.serviceWorker.controller || reg.active;
        btnPreparar.hidden = false;
        btnPreparar.title  = 'Volver a descargar todas las fichas';

        if (hayRed) descargar(false);
        else pintarGuardadas(await consultarEstado());
    })();
})();
</script>
@endpush
