<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appNombre ?? 'Monitoreo' }} · Muro de monitoreo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        /*
         | Réplica del «Main Dashboard for all» de CheckMK (tema modern-dark):
         | mismos colores de estado que el modo TV de los mapas, para que se lea
         | igual que la consola. Todo se mide en rem y el rem escala con el ancho
         | de la pantalla: en la TV de 75" se ve igual que en un monitor.
         */
        :root {
            --bg:      #11181d;
            --panel:   #1c2228;
            --panel-2: #232b33;
            --line:    #303946;
            --txt:     #ffffff;
            --muted:   #8a96a1;

            --ok:      #13d389;
            --warn:    #ffd703;
            --crit:    #c83232;
            --unknown: #ff8400;
            --pending: #838383;
            --dt:      #3cc2ff;
            --alarm:   #ff3232;

            /* Barras de rendimiento (perfómetros), como en CheckMK */
            --perf-fondo: #cfd5da;
            --perf-cpu:   #a6e05a;
            --perf-ram:   #4f8fd6;
            --perf-disco: #48dcb5;
            --perf-in:    #3fd6d0;
            --perf-out:   #4f8fd6;
            --perf-up:    #b0ac52;
            --perf-mem:   #b9a3f0;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        /* Tamaño inicial; después lo afina encajar() para que el muro llene
           justo el alto de la pantalla (ver el script). */
        html { font-size:clamp(9px, .6vw, 28px); }
        html, body { height:100%; background:var(--bg); color:var(--txt); overflow:hidden;
                     font-family:Roboto, system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing:antialiased; }
        body.tv { cursor:none; }
        button { font:inherit; color:inherit; cursor:pointer; }

        /* ── Cabecera ─────────────────────────────────────────────────────── */
        .cab { height:3.4rem; display:flex; align-items:center; gap:1.4rem; padding:0 .8rem; border-bottom:1px solid var(--line); }
        .cab h1 { font-size:1.35rem; font-weight:700; line-height:1; }
        .cab .ruta { font-size:.75rem; color:var(--muted); margin-top:.2rem; }
        .cab .der { margin-left:auto; display:flex; align-items:center; gap:1rem; }
        .cab a { color:var(--muted); font-size:.85rem; text-decoration:none; }
        .cab a:hover { color:var(--txt); }
        .estado-api { font-size:.8rem; color:var(--muted); display:flex; align-items:center; gap:.4rem; }
        .estado-api i { width:.6rem; height:.6rem; border-radius:50%; background:var(--ok); display:inline-block; }
        .estado-api.error { color:var(--alarm); } .estado-api.error i { background:var(--alarm); }
        .reloj { font-size:1.5rem; font-weight:700; font-variant-numeric:tabular-nums; }
        .btn-sonido { border:1px solid var(--warn); background:transparent; border-radius:3px; padding:.3rem .7rem; font-size:.85rem; color:var(--warn);
                      animation:atencion 1.6s ease-in-out infinite; }
        .btn-sonido.on { border-color:var(--line); color:var(--muted); animation:none; }
        @keyframes atencion { 50% { background:rgba(255,215,3,.15); } }

        /* ── Grilla: misma distribución que el dashboard de CheckMK ───────── */
        .muro { height:calc(100% - 3.4rem); padding:.35rem; display:grid; gap:.35rem;
                grid-template-columns:34fr 10fr 22fr 34fr; grid-template-rows:minmax(0, 1fr) auto;
                grid-template-areas:"izquierda segmentos centro derecha" "abajo_izq abajo abajo abajo"; }
        .area { display:flex; flex-direction:column; gap:.35rem; min-height:0; min-width:0; }
        .fila { display:flex; gap:.35rem; min-width:0; }
        .fila > * { flex:1; min-width:0; }
        .crece { flex:1 1 0; min-height:5rem; }
        .fila.crece > .panel { height:100%; }
        #area-abajo .fila { height:100%; }
        #area-abajo { min-height:11rem; }

        .panel { background:var(--panel); border:1px solid var(--line); display:flex; flex-direction:column; min-width:0; position:relative; }
        .panel > h2 { font-size:.82rem; font-weight:700; text-align:center; padding:.3rem .4rem; background:var(--panel-2);
                      white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .panel.vacio { background:transparent; border:none; }

        /* ── Tablas de hosts ──────────────────────────────────────────────── */
        .t-cab, .t-fila { display:grid; align-items:center; gap:.35rem; padding:0 .3rem; }
        .t-cab { font-size:.72rem; color:var(--muted); font-weight:700; height:1.6rem; }
        .t-fila { height:1.95rem; font-size:.78rem; position:relative; z-index:1; border-radius:2px;
                  transition:transform .45s cubic-bezier(.2,.8,.2,1.15), box-shadow .45s, background .4s;
                  /* Las tablas van pegadas al borde izquierdo: crecen hacia la derecha para no cortarse. */
                  transform-origin:left center; }
        .c-host { height:1.55rem; display:flex; align-items:center; padding:0 .4rem; font-weight:700; color:#000;
                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; background:var(--pending); border-radius:1px; }
        .up      .c-host { background:var(--ok); }
        .down    .c-host { background:var(--crit); color:#fff; }
        .unreach .c-host { background:var(--unknown); }
        .mant    .c-host { background:var(--dt); }
        .c-n { text-align:center; font-weight:700; height:1.55rem; display:flex; align-items:center; justify-content:center; }
        .c-n.wa.hay { background:var(--warn); color:#000; }
        .c-n.cr.hay { background:var(--crit); color:#fff; }

        .perf { position:relative; height:1.4rem; background:var(--perf-fondo); color:#111; font-size:.72rem; overflow:hidden; border-radius:1px; }
        .perf > b { position:absolute; inset:0 auto 0 0; transition:width .8s ease; }
        .perf > span { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; white-space:nowrap; font-weight:500; }
        .perf.cpu > b { background:var(--perf-cpu); } .perf.ram > b { background:var(--perf-ram); }
        .perf.disco > b { background:var(--perf-disco); } .perf.uptime > b { background:var(--perf-up); }
        .perf.ram_gib > b { background:var(--perf-mem); }
        .perf.interfaz > b.in  { background:var(--perf-in); }
        .perf.interfaz > b.out { background:var(--perf-out); left:50%; }
        .perf.vacia { background:transparent; }

        /* ── Listas de hosts ──────────────────────────────────────────────── */
        .l-cab, .l-fila { display:grid; grid-template-columns:3.6rem 1fr; gap:.5rem; align-items:center; padding:0 .35rem; }
        .l-cab { font-size:.72rem; color:var(--muted); font-weight:700; height:1.5rem; }
        .l-fila { height:1.62rem; font-size:.78rem; position:relative; z-index:1;
                  transition:transform .45s cubic-bezier(.2,.8,.2,1.15), box-shadow .45s, background .4s; }
        .l-estado { height:1.3rem; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.72rem; color:#000; background:var(--pending); }
        .up .l-estado { background:var(--ok); } .down .l-estado { background:var(--crit); color:#fff; }
        .unreach .l-estado { background:var(--unknown); } .mant .l-estado { background:var(--dt); }
        .l-nombre { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* ── Hexágonos ────────────────────────────────────────────────────── */
        .hex-cuerpo { display:flex; align-items:center; gap:1rem; padding:.5rem .9rem; flex:1; }
        .hex { width:5.4rem; height:5.4rem; flex-shrink:0; transition:transform .45s cubic-bezier(.2,.8,.2,1.15); }
        .hex-ley { font-size:.76rem; line-height:1.45; }
        .hex-ley div { display:grid; grid-template-columns:2rem .4rem 1fr; gap:.4rem; align-items:center; }
        .hex-ley b { text-align:right; font-variant-numeric:tabular-nums; }
        .hex-ley i { height:.85rem; }

        /* ── Medidores y números ──────────────────────────────────────────── */
        .medidor { flex:1; display:flex; align-items:center; justify-content:center; padding:.2rem; }
        .medidor svg { width:100%; max-width:11rem; height:auto; }
        .numero { flex:1; display:flex; align-items:baseline; justify-content:center; gap:.5rem; padding:.8rem .4rem; }
        .numero b { font-size:2.6rem; font-weight:700; font-variant-numeric:tabular-nums; line-height:1; }
        .numero span { font-size:1.1rem; color:#d7dde2; }

        /* Gráficos: el canvas va en absoluto para no empujar la grilla; el
           alto lo da el panel, y así encajar() no se confunde al medir. */
        .graf { flex:1; position:relative; min-height:0; margin:.25rem .35rem; }
        .graf canvas { position:absolute; inset:0; }
        .graf .sin-datos { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#4b5865; font-size:.78rem; }
        .graf .sin-datos[hidden] { display:none; }
        .graf-ley { display:grid; grid-template-columns:1.6fr repeat(4, 1fr); gap:0 .5rem; font-size:.66rem; padding:.1rem .6rem .35rem; color:#d7dde2; }
        .graf-ley .c { text-align:right; font-variant-numeric:tabular-nums; }
        .graf-ley .t { color:var(--muted); font-weight:700; }
        .graf-ley .m { display:flex; align-items:center; gap:.35rem; }
        .graf-ley .m i { width:.65rem; height:.65rem; display:inline-block; }

        /* ── Caída nueva: la fila crece, late y queda encima de todo ──────── */
        .alarma { z-index:50 !important; }
        .t-fila.alarma, .l-fila.alarma {
            background:#5c121d; box-shadow:0 0 0 2px var(--alarm), 0 .6rem 2.6rem .3rem rgba(255,50,50,.55);
            animation:latido-fila 1.15s ease-in-out infinite;
        }
        @keyframes latido-fila {
            0%   { transform:scale(1.12); }
            12%  { transform:scale(1.22); }
            24%  { transform:scale(1.12); }
            36%  { transform:scale(1.19); }
            60%, 100% { transform:scale(1.12); }
        }
        .t-fila.alarma::after, .l-fila.alarma::after {
            content:""; position:absolute; inset:-2px; border:2px solid var(--alarm); border-radius:3px;
            animation:onda 1.15s ease-out infinite; pointer-events:none;
        }
        @keyframes onda { from { opacity:.9; transform:scale(1); } to { opacity:0; transform:scale(1.08, 1.6); } }
        .panel.alarma-hex { z-index:50; }
        .panel.alarma-hex .hex { animation:latido-hex 1.15s ease-in-out infinite; filter:drop-shadow(0 0 1rem rgba(255,50,50,.8)); }
        @keyframes latido-hex { 0% { transform:scale(1.35); } 12% { transform:scale(1.6); } 24% { transform:scale(1.35); } 36% { transform:scale(1.52); } 60%, 100% { transform:scale(1.35); } }

        /* Recuperado: destello verde breve. */
        .recupera { animation:recupera 2.4s ease-out; }
        @keyframes recupera { 0% { box-shadow:0 0 0 2px var(--ok), 0 0 1.8rem .4rem rgba(19,211,137,.6); background:#12402a; } 100% { box-shadow:none; } }

        /* ── Aviso grande de caída ────────────────────────────────────────── */
        .aviso { position:fixed; left:50%; top:.8rem; z-index:100; transform:translate(-50%, -150%);
                 transition:transform .5s cubic-bezier(.2,.9,.2,1.15); background:var(--alarm); color:#fff; border-radius:.6rem;
                 padding:.8rem 2rem; box-shadow:0 1rem 3rem -.6rem rgba(255,50,50,.6); display:flex; align-items:center; gap:1.2rem; max-width:90vw; }
        .aviso.ver { transform:translate(-50%, 0); }
        .aviso .ico { font-size:2.4rem; line-height:1; animation:latido-ico 1.15s ease-in-out infinite; }
        @keyframes latido-ico { 12% { transform:scale(1.25); } 36% { transform:scale(1.15); } }
        .aviso b { display:block; font-size:2rem; line-height:1.05; }
        .aviso span { font-size:1rem; opacity:.92; }

        @media (prefers-reduced-motion: reduce) {
            .t-fila.alarma, .l-fila.alarma, .panel.alarma-hex .hex, .aviso .ico, .alarma::after { animation:none; }
        }
    </style>
</head>
<body class="{{ $modoTv ? 'tv' : '' }}">

<header class="cab">
    <div>
        <h1>Main Dashboard for all</h1>
        <div class="ruta">Muro de monitoreo · CheckMK</div>
    </div>
    <div class="der">
        <button type="button" class="btn-sonido" id="btn-sonido">Activar sonido</button>
        <div class="estado-api" id="estado-api"><i></i><span>conectando…</span></div>
        @if(!$modoTv)
            @if($urlTv)<a href="{{ $urlTv }}" target="_blank" rel="noopener">Abrir modo TV ↗</a>@endif
            <a href="{{ route('admin.monitoreo.mapas.index') }}">Volver a VTI</a>
        @endif
        <div class="reloj" id="reloj">--:--</div>
    </div>
</header>

<main class="muro">
    @foreach(array_keys($areas) as $area)
        <div class="area" id="area-{{ $area }}" style="grid-area:{{ $area }}"></div>
    @endforeach
</main>

<div class="aviso" id="aviso" role="alert" aria-live="assertive">
    <div class="ico">▼</div>
    <div><b id="aviso-t"></b><span id="aviso-s"></span></div>
</div>

<script>
(() => {
    const AREAS      = @json($areas);
    const URL_ESTADO = @json($urlEstado);
    const URL_GRAFICOS = @json($urlGraficos);
    const INTERVALO  = @json($intervalo) * 1000;
    const RESALTADO  = @json($resaltado) * 1000;
    // Una caída que ya estaba al abrir la pantalla no suena, salvo que sea así de reciente:
    // si la TV se reinicia justo en medio de una caída, igual se avisa.
    const RECIENTE_S = 180;

    const $ = id => document.getElementById(id);
    const el = (tag, cls, html) => { const e = document.createElement(tag); if (cls) e.className = cls; if (html !== undefined) e.innerHTML = html; return e; };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const enCarpeta = (h, carpeta) => h.carpeta === carpeta || h.carpeta.startsWith(carpeta + '/');

    /* ── Formatos, como los muestra CheckMK ───────────────────────────────── */
    const pct = v => v == null ? '' : `${v.toFixed(2).replace(/\.?0+$/, '')} %`;
    function bits(v) {
        if (v == null) return '';
        const u = ['bit/s', 'kbit/s', 'Mbit/s', 'Gbit/s'];
        let i = 0; while (v >= 1000 && i < u.length - 1) { v /= 1000; i++; }
        return `${v >= 100 ? v.toFixed(0) : v >= 10 ? v.toFixed(1) : v.toFixed(2)} ${u[i]}`;
    }
    function uptime(s) {
        if (!s) return '';
        const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
        if (d >= 365) return `${Math.floor(d / 365)} y ${d % 365} d`;
        if (d >= 10)  return `${d} d`;
        if (d >= 1)   return `${d} d ${h} h`;
        return `${h} h ${m} m`;
    }
    const gib = b => b == null ? '' : `${(b / 1073741824).toFixed(2)} GiB`;
    // Escala logarítmica para tráfico y uptime, igual que los perfómetros de CheckMK.
    const log = (v, max) => v > 0 ? Math.min(100, Math.log10(1 + v) / Math.log10(1 + max) * 100) : 0;

    /* ── Construcción de los paneles desde config/muro.php ────────────────── */
    const paneles = [];          // cada uno con su función actualizar(datos)
    const elementosHost = new Map();   // nombre → filas que lo muestran (para animar su caída)
    const hexagonos = [];        // hexágonos de hosts, que también laten si cae uno de su carpeta
    function registrarElemento(nombre, fila) {
        if (!elementosHost.has(nombre)) elementosHost.set(nombre, []);
        elementosHost.get(nombre).push(fila);
    }

    function crear(p) {
        if (p.tipo === 'fila') {
            const f = el('div', 'fila');
            p.paneles.forEach(x => f.appendChild(crear(x)));
            if (p.paneles.some(x => x.tipo === 'grafico')) f.classList.add('crece');
            return f;
        }
        if (p.tipo === 'vacio') return el('div', 'panel vacio');

        const panel = el('section', 'panel');
        panel.appendChild(el('h2', '', esc(p.titulo)));
        const fabrica = { tabla: crearTabla, lista: crearLista, hexagono_hosts: crearHexHosts,
                          hexagono_servicios: crearHexServicios, medidor: crearMedidor, numero: crearNumero, grafico: crearGrafico }[p.tipo];
        if (!fabrica) return panel;
        const actualizar = fabrica(panel, p);
        if (actualizar) paneles.push({ p, actualizar, panel });
        if (p.tipo === 'grafico') panel.classList.add('crece');
        return panel;
    }

    /* Tablas: Host · Wa · Cr · métricas. Las filas se reutilizan entre
       sondeos (por nombre de host) para que las animaciones no se corten. */
    const COLS = {
        cpu:      ['CPU', '1.1fr'],
        ram:      ['RAM', '1.1fr'],
        ram_gib:  ['RAM', '1.3fr'],
        disco:    ['DISK C:/', '1.1fr'],
        interfaz: ['Interface 1', '1.6fr'],
        uptime:   ['Uptime', '1fr'],
    };
    function crearTabla(panel, p) {
        const plantilla = `minmax(0, 1.25fr) 1.7rem 1.7rem ${p.columnas.map(c => COLS[c][1]).join(' ')}`;
        const cab = el('div', 't-cab', `<span>Host</span><span class="c-n">Wa</span><span class="c-n">Cr</span>${p.columnas.map(c => `<span>${COLS[c][0]}</span>`).join('')}`);
        cab.style.gridTemplateColumns = plantilla;
        const cuerpo = el('div');
        panel.append(cab, cuerpo);
        const filas = new Map();

        return d => {
            const hosts = Object.entries(d.hosts).filter(([, h]) => enCarpeta(h, p.carpeta)).sort(([a], [b]) => a.localeCompare(b));
            const vivos = new Set(hosts.map(([n]) => n));
            filas.forEach((f, n) => { if (!vivos.has(n)) { f.remove(); filas.delete(n); } });

            hosts.forEach(([nombre, h], i) => {
                let f = filas.get(nombre);
                if (!f) {
                    f = el('div', 't-fila');
                    f.style.gridTemplateColumns = plantilla;
                    f.innerHTML = `<div class="c-host"></div><div class="c-n wa"></div><div class="c-n cr"></div>` +
                        p.columnas.map(c => `<div class="perf ${c}" data-c="${c}">${c === 'interfaz' ? '<b class="in"></b><b class="out"></b>' : '<b></b>'}<span></span></div>`).join('');
                    f.querySelector('.c-host').textContent = nombre;
                    filas.set(nombre, f);
                    registrarElemento(nombre, f);
                }
                if (cuerpo.children[i] !== f) cuerpo.insertBefore(f, cuerpo.children[i] || null);

                claseEstado(f, h);
                const wa = f.querySelector('.wa'), cr = f.querySelector('.cr');
                wa.textContent = h.wa; wa.classList.toggle('hay', h.wa > 0);
                cr.textContent = h.cr; cr.classList.toggle('hay', h.cr > 0);
                const m = d.metricas[nombre] || {};
                f.querySelectorAll('.perf').forEach(c => pintarPerf(c, c.dataset.c, m));
            });
        };
    }

    function pintarPerf(celda, c, m) {
        const barra = celda.querySelector('b'), txt = celda.querySelector('span');
        let ancho = null, texto = '';
        if (c === 'cpu')    { ancho = m.cpu;   texto = pct(m.cpu); }
        if (c === 'ram')    { ancho = m.ram;   texto = pct(m.ram); }
        if (c === 'disco')  { ancho = m.disco; texto = pct(m.disco); }
        if (c === 'uptime') { ancho = m.uptime ? log(m.uptime / 86400, 730) : null; texto = uptime(m.uptime); }
        if (c === 'ram_gib') {
            ancho = m.ram_bytes && m.ram_total ? m.ram_bytes / m.ram_total * 100 : (m.ram ?? null);
            texto = m.ram_bytes ? gib(m.ram_bytes) : pct(m.ram);
        }
        if (c === 'interfaz') {
            const vel = m.vel_bps || 1e9;
            const hay = m.in_bps != null || m.out_bps != null;
            celda.querySelector('.in').style.width  = `${log(m.in_bps || 0, vel) / 2}%`;
            celda.querySelector('.out').style.width = `${log(m.out_bps || 0, vel) / 2}%`;
            txt.textContent = hay ? `${bits(m.in_bps)} / ${bits(m.out_bps)}` : '';
            celda.classList.toggle('vacia', !hay);
            return;
        }
        celda.classList.toggle('vacia', ancho == null);
        barra.style.width = `${Math.max(0, Math.min(100, ancho ?? 0))}%`;
        txt.textContent = texto;
    }

    /* Listas: State · Host. */
    function crearLista(panel, p) {
        panel.appendChild(el('div', 'l-cab', '<span>State</span><span>Host</span>'));
        const cuerpo = el('div');
        panel.appendChild(cuerpo);
        const filas = new Map();
        const TXT = { up: 'UP', down: 'DOWN', unreach: 'UNREACH', pend: 'PEND', mant: 'UP' };

        return d => {
            const hosts = Object.entries(d.hosts).filter(([, h]) => enCarpeta(h, p.carpeta)).sort(([a], [b]) => a.localeCompare(b, 'es', { numeric: true }));
            const vivos = new Set(hosts.map(([n]) => n));
            filas.forEach((f, n) => { if (!vivos.has(n)) { f.remove(); filas.delete(n); } });
            hosts.forEach(([nombre, h], i) => {
                let f = filas.get(nombre);
                if (!f) {
                    f = el('div', 'l-fila', '<div class="l-estado"></div><div class="l-nombre"></div>');
                    f.querySelector('.l-nombre').textContent = nombre;
                    filas.set(nombre, f);
                    registrarElemento(nombre, f);
                }
                if (cuerpo.children[i] !== f) cuerpo.insertBefore(f, cuerpo.children[i] || null);
                claseEstado(f, h);
                f.querySelector('.l-estado').textContent = TXT[h.estado] || '?';
            });
        };
    }

    /* Hexágonos: el exterior marca que hay datos y el interior se pone del
       color del peor estado de la carpeta, como el dashlet de CheckMK. */
    const HEX = (r, cx = 50, cy = 50) => Array.from({ length: 6 }, (_, i) => {
        const a = Math.PI / 180 * (60 * i - 30);
        return `${(cx + r * Math.cos(a)).toFixed(1)},${(cy + r * Math.sin(a)).toFixed(1)}`;
    }).join(' ');
    function hexSvg() {
        return `<svg class="hex" viewBox="0 0 100 100"><polygon points="${HEX(46)}" fill="#1a2a26" stroke="#1f6e52" stroke-width="2"/><polygon class="hex-in" points="${HEX(20)}" fill="var(--ok)"/></svg>`;
    }
    function leyenda(filas) {
        return filas.map(([n, color, txt]) => `<div><b>${n}</b><i style="background:${color}"></i><span>${txt}</span></div>`).join('');
    }
    function crearHexHosts(panel, p) {
        const cuerpo = el('div', 'hex-cuerpo', hexSvg() + '<div class="hex-ley"></div>');
        panel.appendChild(cuerpo);
        hexagonos.push({ p, panel });
        return d => {
            const hs = Object.values(d.hosts).filter(h => enCarpeta(h, p.carpeta));
            const c = { up: 0, mant: 0, unreach: 0, down: 0 };
            hs.forEach(h => { if (h.mant) c.mant++; else if (c[h.estado] !== undefined) c[h.estado]++; });
            cuerpo.querySelector('.hex-in').setAttribute('fill', c.down ? 'var(--crit)' : c.unreach ? 'var(--unknown)' : 'var(--ok)');
            cuerpo.querySelector('.hex-ley').innerHTML = leyenda([
                [c.up, 'var(--ok)', 'Up'], [c.mant, 'var(--dt)', 'In downtime'], [c.unreach, 'var(--unknown)', 'Unreachable'],
                [c.down, 'var(--crit)', 'Down'], [hs.length, 'transparent', 'Total'],
            ]);
        };
    }
    function crearHexServicios(panel, p) {
        const cuerpo = el('div', 'hex-cuerpo', hexSvg() + '<div class="hex-ley"></div>');
        panel.appendChild(cuerpo);
        return d => {
            const c = d.servicios[p.carpeta] || { ok: 0, mant: 0, host_caido: 0, warn: 0, unknown: 0, crit: 0, total: 0 };
            cuerpo.querySelector('.hex-in').setAttribute('fill', c.crit ? 'var(--crit)' : c.warn ? 'var(--warn)' : c.unknown ? 'var(--unknown)' : 'var(--ok)');
            cuerpo.querySelector('.hex-ley').innerHTML = leyenda([
                [c.ok, 'var(--ok)', 'OK'], [c.mant, 'var(--dt)', 'In downtime'], [c.host_caido, 'var(--pending)', 'On down host'],
                [c.warn, 'var(--warn)', 'Warning'], [c.unknown, 'var(--unknown)', 'Unknown'], [c.crit, 'var(--crit)', 'Critical'],
                [c.total, 'transparent', 'Total'],
            ]);
        };
    }

    /* Medidor: arco de 270°, con el valor al centro. */
    function arco(r, desde, hasta) {
        const pt = a => { const rad = (a - 90) * Math.PI / 180; return [(60 + r * Math.cos(rad)).toFixed(2), (60 + r * Math.sin(rad)).toFixed(2)]; };
        const [x1, y1] = pt(desde), [x2, y2] = pt(hasta);
        return `M${x1} ${y1} A${r} ${r} 0 ${hasta - desde > 180 ? 1 : 0} 1 ${x2} ${y2}`;
    }
    function crearMedidor(panel, p) {
        const caja = el('div', 'medidor', `<svg viewBox="0 0 120 100">
            <path d="${arco(44, -135, 135)}" fill="none" stroke="#e8ecef" stroke-width="9"/>
            <path class="m-val" d="" fill="none" stroke="var(--ok)" stroke-width="9"/>
            <rect class="m-caja" x="22" y="44" width="76" height="24" rx="3" fill="var(--ok)"/>
            <text class="m-txt" x="60" y="62" text-anchor="middle" font-size="17" font-weight="700" fill="#000">—</text>
            <text x="14" y="98" font-size="7" fill="#c8d0d6">0%</text><text x="94" y="98" font-size="7" fill="#c8d0d6">100%</text>
            <rect class="m-svc" x="30" y="78" width="60" height="12" rx="2" fill="var(--ok)"/>
            <text class="m-svc-t" x="60" y="87" text-anchor="middle" font-size="7.5" font-weight="700" fill="#000">Service: OK</text>
        </svg>`);
        panel.appendChild(caja);
        return d => {
            const v = (d.metricas[p.host] || {})[p.metrica];
            const color = v == null ? 'var(--pending)' : v >= 90 ? 'var(--crit)' : v >= 80 ? 'var(--warn)' : 'var(--ok)';
            const svc = v == null ? 'Sin datos' : v >= 90 ? 'Service: CRIT' : v >= 80 ? 'Service: WARN' : 'Service: OK';
            caja.querySelector('.m-val').setAttribute('d', v ? arco(44, -135, -135 + Math.min(100, v) * 2.7) : '');
            caja.querySelector('.m-val').setAttribute('stroke', color);
            caja.querySelector('.m-caja').setAttribute('fill', color);
            caja.querySelector('.m-svc').setAttribute('fill', color);
            caja.querySelector('.m-txt').textContent = v == null ? '—' : `${v.toFixed(2)}%`;
            caja.querySelector('.m-svc-t').textContent = svc;
        };
    }

    function crearNumero(panel, p) {
        const caja = el('div', 'numero', '<b>—</b><span></span>');
        panel.appendChild(caja);
        return d => {
            const v = (d.metricas[p.host] || {})[p.metrica];
            const [num, unidad] = v == null ? ['—', ''] : bits(v).split(' ');
            caja.querySelector('b').textContent = num;
            caja.querySelector('span').textContent = unidad;
        };
    }

    /* Gráficos históricos: se crean vacíos y se llenan con cargarGraficos(),
       que corre aparte del sondeo de estado (los datos avanzan por minuto). */
    const graficos = new Map();   // título → { p, caja, ley, chart }
    function crearGrafico(panel, p) {
        const caja = el('div', 'graf', '<canvas></canvas><div class="sin-datos">cargando…</div>');
        panel.appendChild(caja);
        const ley = p.clase === 'perdida' ? el('div', 'graf-ley') : null;
        if (ley) panel.appendChild(ley);
        graficos.set(p.titulo, { p, caja, ley, chart: null });
        return null;
    }

    // Colores de las series, como los pinta CheckMK.
    const ESTILO_GRAFICO = {
        cpu:         [{ borde: '#9ad85a', relleno: 'rgba(126,200,64,.55)' }],
        ram:         [{ borde: '#5a9be6', relleno: 'rgba(61,120,216,.7)' }],
        ancho_banda: [{ borde: '#2ecc71', relleno: 'rgba(46,204,113,.18)' }, { borde: '#5a9be6', relleno: 'rgba(61,120,216,.7)' }],
        perdida:     [{ borde: '#e5b52a', relleno: 'rgba(229,181,42,.25)' }],
    };
    // Cómo se transforma y rotula cada clase.
    const ESCALA_GRAFICO = {
        cpu:         { valor: v => v,              eje: v => `${v}%`,      tope: 10 },
        ram:         { valor: v => v / 1073741824, eje: v => `${v} GiB`,   tope: null },
        ancho_banda: { valor: (v, i) => (i === 1 ? -v : v) / 1e6, eje: v => `${Math.abs(v)}`, tope: null },
        perdida:     { valor: v => v,              eje: v => `${v}%`,      tope: 1 },
    };
    const hhmm = ts => new Date(ts * 1000).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });

    function pintarGrafico(g, d) {
        const vacio = g.caja.querySelector('.sin-datos');
        if (!d || !d.series.length) { vacio.textContent = 'sin datos de CheckMK'; vacio.hidden = false; return; }
        vacio.hidden = true;

        // Los últimos puntos suelen venir vacíos (CheckMK aún no consolida el minuto): se recortan.
        let largo = Math.max(...d.series.map(s => s.datos.length));
        while (largo > 0 && d.series.every(s => s.datos[largo - 1] == null)) largo--;

        const escala = ESCALA_GRAFICO[g.p.clase];
        const etiquetas = Array.from({ length: largo }, (_, i) => hhmm(d.inicio + i * d.paso));
        const conjuntos = d.series.map((s, i) => ({
            label: s.nombre,
            data: s.datos.slice(0, largo).map(v => v == null ? null : escala.valor(v, i)),
            borderColor: ESTILO_GRAFICO[g.p.clase][i].borde,
            backgroundColor: ESTILO_GRAFICO[g.p.clase][i].relleno,
            fill: 'origin', borderWidth: 1.2, pointRadius: 0, tension: 0, spanGaps: false,
        }));

        if (!g.chart) {
            g.chart = new Chart(g.caja.querySelector('canvas'), {
                type: 'line',
                data: { labels: etiquetas, datasets: conjuntos },
                options: {
                    animation: false, responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false }, tooltip: { enabled: !document.body.classList.contains('tv') } },
                    scales: {
                        x: { ticks: { maxTicksLimit: 6, maxRotation: 0, autoSkipPadding: 12 }, grid: { color: 'rgba(255,255,255,.07)' } },
                        y: { beginAtZero: true, suggestedMax: escala.tope ?? undefined, ticks: { maxTicksLimit: 6, callback: escala.eje }, grid: { color: 'rgba(255,255,255,.07)' } },
                    },
                },
            });
        } else {
            g.chart.data.labels = etiquetas;
            g.chart.data.datasets.forEach((ds, i) => { ds.data = conjuntos[i]?.data ?? []; });
            g.chart.update('none');
        }

        if (g.ley) pintarLeyendaPerdida(g.ley, d.series[0].datos);
    }

    /* Leyenda de pérdida de paquetes: mínimo, máximo, promedio y último. */
    function pintarLeyendaPerdida(ley, datos) {
        const v = datos.filter(x => x != null);
        const f = x => v.length ? `${x.toFixed(2).replace(/\.?0+$/, '')}%` : '—';
        const min = Math.min(...v), max = Math.max(...v), prom = v.reduce((a, b) => a + b, 0) / (v.length || 1), ult = v[v.length - 1];
        ley.innerHTML = `
            <span></span><span class="c t">Minimum</span><span class="c t">Maximum</span><span class="c t">Average</span><span class="c t">Last</span>
            <span class="m"><i style="background:#e5b52a"></i>Packet loss</span><span class="c">${f(min)}</span><span class="c">${f(max)}</span><span class="c">${f(prom)}</span><span class="c">${f(ult)}</span>
            <span class="m"><i style="background:var(--warn)"></i>Warning</span><span></span><span></span><span></span><span class="c">80%</span>
            <span class="m"><i style="background:var(--crit)"></i>Critical</span><span></span><span></span><span></span><span class="c">100%</span>`;
    }

    function fuenteGraficos() {
        if (!window.Chart) return;
        Chart.defaults.color = '#9aa5af';
        Chart.defaults.font.size = Math.max(8, parseFloat(getComputedStyle(document.documentElement).fontSize) * .72);
        graficos.forEach(g => g.chart && g.chart.update('none'));
    }

    async function cargarGraficos() {
        if (!window.Chart) return;
        try {
            const r = await fetch(URL_GRAFICOS, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error);
            graficos.forEach((g, titulo) => pintarGrafico(g, d.graficos[titulo]));
        } catch (e) {
            // Se dejan los últimos gráficos dibujados; el estado de conexión lo muestra el sondeo.
        }
    }

    function claseEstado(fila, h) {
        fila.classList.remove('up', 'down', 'unreach', 'pend', 'mant');
        fila.classList.add(h.mant && h.estado !== 'down' ? 'mant' : h.estado);
    }

    /* ── Caídas: aviso una sola vez por host + cambio de estado firme ─────── */
    const anunciados = new Set((() => { try { return JSON.parse(localStorage.getItem('muro-anunciados') || '[]'); } catch (e) { return []; } })());
    function anunciar(clave) {
        anunciados.add(clave);
        try { localStorage.setItem('muro-anunciados', JSON.stringify([...anunciados].slice(-500))); } catch (e) {}
    }

    let anterior = null;     // estados del sondeo anterior, por host
    function detectar(d) {
        const ahora = Math.floor(Date.now() / 1000);
        const caidas = [];
        Object.entries(d.hosts).forEach(([nombre, h]) => {
            const visible = elementosHost.has(nombre) || hexagonos.some(x => enCarpeta(h, x.p.carpeta));
            if (!visible) return;

            const antes = anterior ? anterior[nombre] : null;
            if (h.estado === 'down' && h.firme) {
                const clave = `${nombre}|${h.cambio}`;
                const yaEstaba = anterior === null && ahora - h.cambio > RECIENTE_S;
                if (!anunciados.has(clave)) {
                    anunciar(clave);
                    if (!yaEstaba) caidas.push([nombre, h]);
                }
            } else if (antes === 'down' && h.estado === 'up') {
                (elementosHost.get(nombre) || []).forEach(f => { f.classList.remove('alarma', 'recupera'); void f.offsetWidth; f.classList.add('recupera'); setTimeout(() => f.classList.remove('recupera'), 2500); });
            }
        });
        anterior = Object.fromEntries(Object.entries(d.hosts).map(([n, h]) => [n, h.estado]));
        if (caidas.length) avisar(caidas);
    }

    let tAviso;
    function avisar(caidas) {
        caidas.forEach(([nombre, h]) => {
            (elementosHost.get(nombre) || []).forEach(f => {
                f.classList.add('alarma');
                clearTimeout(f._t);
                f._t = setTimeout(() => f.classList.remove('alarma'), RESALTADO);
            });
            hexagonos.filter(x => enCarpeta(h, x.p.carpeta)).forEach(x => {
                x.panel.classList.add('alarma-hex');
                clearTimeout(x.panel._t);
                x.panel._t = setTimeout(() => x.panel.classList.remove('alarma-hex'), RESALTADO);
            });
        });

        // Dónde está cada host caído: el título del panel que lo muestra.
        const donde = ([nombre, h]) => {
            const p = paneles.find(x => ['tabla', 'lista'].includes(x.p.tipo) && enCarpeta(h, x.p.carpeta))
                   || hexagonos.find(x => enCarpeta(h, x.p.carpeta));
            return p ? p.p.titulo : h.carpeta;
        };
        const hora = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
        if (caidas.length === 1) {
            $('aviso-t').textContent = `${caidas[0][0]} cayó`;
            $('aviso-s').textContent = `${donde(caidas[0])} · ${hora}`;
        } else {
            $('aviso-t').textContent = `${caidas.length} hosts caídos`;
            $('aviso-s').textContent = `${[...new Set(caidas.map(donde))].join(', ')} · ${hora}`;
        }
        $('aviso').classList.add('ver');
        clearTimeout(tAviso);
        tAviso = setTimeout(() => $('aviso').classList.remove('ver'), RESALTADO);
        sonidoCaida();   // uno solo, aunque hayan caído varios en el mismo sondeo
    }

    /* ── Sonido sintetizado (Web Audio). El navegador exige un clic antes
       de sonar: en la TV, presionar «Activar sonido» una vez al encenderla,
       o lanzar Chrome con --autoplay-policy=no-user-gesture-required. ──── */
    let audio = null;
    function tono(f, ini, dur, tipo, vol) {
        const o = audio.createOscillator(), g = audio.createGain();
        o.type = tipo; o.frequency.setValueAtTime(f, ini);
        g.gain.setValueAtTime(0, ini); g.gain.linearRampToValueAtTime(vol, ini + .02); g.gain.exponentialRampToValueAtTime(.001, ini + dur);
        o.connect(g).connect(audio.destination); o.start(ini); o.stop(ini + dur + .05);
    }
    function sonidoCaida() {
        if (!audio || audio.state !== 'running') return;
        const t = audio.currentTime;
        [[880, 0], [587, .18], [880, .5], [587, .68]].forEach(([f, d]) => { tono(f, t + d, .32, 'triangle', .35); tono(f / 2, t + d, .32, 'sine', .2); });
    }
    function activarSonido() {
        try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); audio.resume(); } catch (e) { return; }
        $('btn-sonido').classList.add('on'); $('btn-sonido').textContent = 'Sonido activo';
    }
    $('btn-sonido').addEventListener('click', activarSonido);
    // Si el navegador ya permite audio (kiosco con autoplay habilitado), queda activo solo.
    try {
        const prueba = new (window.AudioContext || window.webkitAudioContext)();
        if (prueba.state === 'running') { audio = prueba; activarSonido(); } else prueba.close();
    } catch (e) {}
    document.addEventListener('click', () => { if (!audio) activarSonido(); }, { once: true });

    /* ── Sondeo ───────────────────────────────────────────────────────────── */
    /* ── Encaje en pantalla ────────────────────────────────────────────────
       Todo el muro está medido en rem. Se busca el tamaño de letra más grande
       con el que nada desborda: así llena la TV sin huecos y ningún panel se
       monta sobre otro, en cualquier resolución o zoom. Se recalcula al
       cambiar el tamaño de la ventana y cuando cambia la cantidad de filas. */
    const raiz = document.documentElement;
    const muro = document.querySelector('.muro');
    function cabe() {
        if (muro.scrollHeight > muro.clientHeight + 1) return false;
        return [...document.querySelectorAll('.area')].every(a => a.scrollHeight <= a.clientHeight + 1);
    }
    function encajar() {
        // El techo depende del ancho, para que las columnas no se queden angostas para el texto.
        let min = 6, max = Math.max(8, window.innerWidth * 0.0078);
        for (let i = 0; i < 12; i++) {
            const medio = (min + max) / 2;
            raiz.style.fontSize = `${medio}px`;
            if (cabe()) min = medio; else max = medio;
        }
        raiz.style.fontSize = `${Math.floor(min * 10) / 10}px`;
        fuenteGraficos();
    }
    let tEncaje;
    window.addEventListener('resize', () => { clearTimeout(tEncaje); tEncaje = setTimeout(encajar, 150); });
    let filasAntes = -1;

    async function sondear() {
        try {
            const r = await fetch(URL_ESTADO, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'CheckMK no responde');
            paneles.forEach(x => x.actualizar(d));
            const filas = document.querySelectorAll('.t-fila, .l-fila').length;
            if (filas !== filasAntes) { filasAntes = filas; encajar(); }
            detectar(d);
            $('estado-api').classList.remove('error');
            $('estado-api').querySelector('span').textContent = 'actualizado ' + new Date(d.generado * 1000).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        } catch (e) {
            // Se mantiene lo último que se vio y se avisa arriba: un muro en blanco asusta más que uno desactualizado.
            $('estado-api').classList.add('error');
            $('estado-api').querySelector('span').textContent = 'sin conexión con CheckMK';
        }
    }

    /* ── Arranque: los paneles se arman al final, cuando ya está declarado
       todo lo que usan (sus columnas, formas y contadores). ──────────────── */
    Object.entries(AREAS).forEach(([area, lista]) => {
        const cont = $(`area-${area}`);
        lista.forEach(p => cont.appendChild(crear(p)));
    });

    const reloj = () => { $('reloj').textContent = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }); };
    reloj(); setInterval(reloj, 10000);
    sondear(); setInterval(sondear, INTERVALO);
    // Los gráficos avanzan de a un punto por minuto en CheckMK: basta con refrescarlos cada minuto.
    cargarGraficos(); setInterval(cargarGraficos, 60000);
})();
</script>
</body>
</html>
