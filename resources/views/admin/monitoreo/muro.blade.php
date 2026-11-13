<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appNombre ?? 'Monitoreo' }} · Muro de monitoreo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@600;700&family=JetBrains+Mono:wght@400;600&display=swap">
    <style>
        /*
         | Réplica del «Main Dashboard for all» de CheckMK (tema modern-dark):
         | mismos colores de estado que el modo TV de los mapas, para que se lea
         | igual que la consola. Todo se mide en rem y el rem escala con el ancho
         | de la pantalla: en la TV de 75" se ve igual que en un monitor.
         */
        :root {
            --bg:      #11181d;
            /* Piel 2.0: títulos en Chakra Petch y datos en JetBrains Mono, con respaldo local si la TV no llega a Google Fonts. */
            --f-display: 'Chakra Petch', 'Segoe UI', Roboto, system-ui, sans-serif;
            --f-mono:    'JetBrains Mono', Consolas, 'Courier New', monospace;
            --f-body:    Roboto, system-ui, -apple-system, 'Segoe UI', sans-serif;
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

            /* Capa HUD: un solo color de acento, el verde de CheckMK, para que nunca compita con el rojo de las caídas. */
            --hud:       #13d389;
            --hud-rgb:   19,211,137;
            --hud-suave: rgba(19,211,137,.22);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        /* Tamaño inicial; después lo afina encajar() para que el muro llene
           justo el alto de la pantalla (ver el script). */
        html { font-size:clamp(9px, .6vw, 28px); }
        html, body { height:100%; background:var(--bg); color:var(--txt); overflow:hidden;
                     font-family:Roboto, system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing:antialiased; }
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
        #area-abajo { min-height:15rem; }

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
        /* Los hexágonos se pueden pulsar: abren el detalle de su carpeta. */
        .panel.abre { cursor:pointer; transition:border-color .2s, box-shadow .2s; }
        .panel.abre:hover { border-color:var(--hud); box-shadow:0 0 .8rem -.2rem var(--hud); }
        .panel.abre > h2::after { content:"  ⤢"; color:var(--muted); font-weight:400; }

        /* ── Modal de detalle (prefijo md-: «.aviso», «.up»… ya tienen dueño) ── */
        .md-fondo { position:fixed; inset:0; z-index:60; background:rgba(4,10,12,.72); backdrop-filter:blur(3px);
                    display:flex; align-items:center; justify-content:center; padding:3vh 3vw; font-size:max(13px, 1.15rem); }
        .md-fondo[hidden] { display:none; }
        .md-caja { width:min(1500px, 100%); max-height:100%; display:flex; flex-direction:column; background:var(--panel);
                   border:1px solid var(--hud); box-shadow:0 0 2.5rem -.5rem rgba(var(--hud-rgb),.45); border-radius:4px;
                   animation:md-entra .22s ease-out; }
        @keyframes md-entra { from { opacity:0; transform:translateY(1.2rem) scale(.98); } }
        .md-cab { display:flex; align-items:center; gap:1em; flex-wrap:wrap; padding:.8em 1em; border-bottom:1px solid var(--line); background:var(--panel-2); }
        .md-cab h3 { font-size:1.35em; font-weight:700; margin-right:auto; }
        .md-cab h3 small { display:block; font-size:.6em; font-weight:400; color:var(--muted); letter-spacing:.04em; }
        .md-chips { display:flex; gap:.4em; flex-wrap:wrap; }
        .md-chip { border:1px solid var(--line); background:transparent; border-radius:999px; padding:.2em .75em; font-size:.85em; display:flex; align-items:center; gap:.4em; }
        .md-chip i { width:.65em; height:.65em; border-radius:50%; background:var(--c, var(--muted)); }
        .md-chip.on { border-color:var(--c, var(--hud)); background:color-mix(in srgb, var(--c, var(--hud)) 18%, transparent); }
        .md-chip b { font-variant-numeric:tabular-nums; }
        .md-buscar { background:#0d1417; border:1px solid var(--line); color:var(--txt); border-radius:3px; padding:.35em .7em; font:inherit; font-size:.9em; width:14em; }
        .md-buscar:focus { outline:none; border-color:var(--hud); }
        .md-x { border:1px solid var(--line); background:transparent; border-radius:3px; width:2em; height:2em; font-size:1em; }
        .md-x:hover { border-color:var(--crit); color:var(--crit); }
        .md-cuerpo { overflow:auto; flex:1; min-height:0; }
        .md-tabla { width:100%; border-collapse:collapse; font-size:.92em; }
        .md-tabla th { position:sticky; top:0; background:var(--panel); z-index:1; text-align:left; font-size:.78em; color:var(--muted);
                       text-transform:uppercase; letter-spacing:.05em; padding:.6em .7em; border-bottom:1px solid var(--line); white-space:nowrap; }
        .md-tabla td { padding:.5em .7em; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle; }
        .md-tabla tr.md-fila { cursor:pointer; }
        .md-tabla tr.md-fila:hover td { background:rgba(255,255,255,.035); }
        .md-tabla tr.md-abierta td { background:rgba(var(--hud-rgb),.06); }
        .md-tabla .num { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .md-tabla .tenue { color:var(--muted); }
        .md-tabla .salida { color:#c3cbd1; max-width:34em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .md-est { display:inline-block; min-width:5.2em; text-align:center; font-weight:700; font-size:.8em; color:#000; padding:.2em .5em; border-radius:2px; background:var(--c); }
        .md-up, .md-ok { --c:var(--ok); } .md-down, .md-crit { --c:var(--crit); } .md-unreach, .md-unknown { --c:var(--unknown); }
        .md-pend { --c:var(--pending); } .md-mant { --c:var(--dt); } .md-warn { --c:var(--warn); }
        .md-down .md-est, .md-crit .md-est { color:#fff; }
        .md-svc { display:inline-flex; gap:.3em; }
        .md-svc span { min-width:1.8em; text-align:center; font-weight:700; font-size:.8em; border-radius:2px; padding:.1em .35em; background:#24313a; color:var(--muted); }
        .md-svc span.ok { background:color-mix(in srgb, var(--ok) 25%, transparent); color:var(--ok); }
        .md-svc span.warn { background:var(--warn); color:#000; } .md-svc span.crit { background:var(--crit); color:#fff; }
        .md-det td { background:#0f181b; padding:.7em 1em 1em 2.6em; }
        .md-det .md-salida { font-family:Consolas, monospace; font-size:.85em; color:#c3cbd1; white-space:pre-wrap; word-break:break-word; margin-bottom:.6em; }
        .md-det ul { list-style:none; display:grid; gap:.35em; }
        .md-det li { display:grid; grid-template-columns:5.6em 14em 1fr; gap:.7em; align-items:start; font-size:.88em; }
        .md-det li .md-est { min-width:0; }
        .md-det a { color:var(--hud); text-decoration:none; font-size:.85em; }
        .md-pie { padding:.5em 1em; border-top:1px solid var(--line); font-size:.78em; color:var(--muted); display:flex; justify-content:space-between; gap:1em; }
        .md-vacio { padding:3em; text-align:center; color:var(--muted); }
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

        /* ── Ranking de técnicos: podio 2º · 1º · 3º ──────────────────────────
           Como los gráficos, va en absoluto para no empujar la grilla, y se
           mide en unidades del contenedor (cqh/cqw) para caber en el alto que
           le toque sin que encajar() tenga que achicar el muro entero. */
        .rkm-caja { flex:1; position:relative; min-height:0; container-type:size; }
        .rkm { position:absolute; inset:0; display:flex; align-items:flex-end; justify-content:center; gap:4cqw;
               padding:2cqh 5cqw 0; transition:opacity .4s ease; }
        .rkm.cambia { opacity:0; }
        .rkm-plaza { flex:1; max-width:30cqw; min-width:0; display:flex; flex-direction:column; align-items:center; }
        .rkm-av { width:min(19cqh, 15cqw); aspect-ratio:1; border-radius:50%; display:flex; align-items:center; justify-content:center;
                  font-weight:700; font-size:min(7.5cqh, 6cqw); color:#fff; flex-shrink:0; }
        .rkm-plaza.p1 .rkm-av { width:min(25cqh, 19cqw); font-size:min(10cqh, 8cqw);
                                box-shadow:0 0 0 min(1.2cqh, .25rem) #fbbf24, 0 0 1.2rem rgba(251,191,36,.45); }
        .rkm-corona { font-size:min(11cqh, 8cqw); line-height:1.05; }
        /* Nombre y apellido en dos líneas si no caben: el panel es angosto. */
        .rkm-nom { font-size:min(6.5cqh, 4.6cqw); font-weight:700; margin-top:1.5cqh; max-width:100%; text-align:center;
                   line-height:1.1; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow-wrap:anywhere; }
        .rkm-n { font-size:min(12cqh, 8cqw); font-weight:800; line-height:1.1; font-variant-numeric:tabular-nums; }
        .rkm-n small { font-size:.42em; font-weight:500; color:var(--muted); margin-left:.25em; }
        .rkm-esc { width:100%; border-radius:.3rem .3rem 0 0; color:#fff; font-weight:800; font-size:min(10cqh, 7cqw);
                   display:flex; align-items:center; justify-content:center; margin-top:1cqh; }
        .rkm-plaza.p1 .rkm-esc { height:24cqh; background:linear-gradient(180deg,#fbbf24,#d97706); }
        .rkm-plaza.p2 .rkm-esc { height:16cqh; background:linear-gradient(180deg,#cbd5e1,#64748b); }
        .rkm-plaza.p3 .rkm-esc { height:10cqh; background:linear-gradient(180deg,#fdba74,#b45309); }
        .rkm-vacio { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#4b5865; font-size:.78rem; }
        .rkm-vacio[hidden] { display:none; }

        /* ── Enlaces: una fila por enlace con latido (latencia de la última
           hora), franja de 4 h en bloques de 5 min y métricas. Va en absoluto
           para no empujar la grilla; las filas se reparten el alto. ───────── */
        .enl-caja { flex:1; position:relative; min-height:0; }
        .enl { position:absolute; inset:0; display:flex; flex-direction:column; }
        .enl-cab { display:flex; align-items:center; gap:1rem; padding:.25rem .8rem; border-bottom:1px solid var(--line); font-size:.72rem; color:var(--muted); }
        .enl-cab .disp { font-weight:700; color:var(--txt); font-variant-numeric:tabular-nums; }
        .enl-cab .cont { margin-left:auto; display:flex; gap:.5rem; }
        .enl-cab .cont span { display:flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:999px; padding:0 .5rem; }
        .enl-cab .cont i { width:.5rem; height:.5rem; border-radius:50%; background:var(--c); }
        .enl-cab .cont b { color:var(--txt); font-variant-numeric:tabular-nums; }
        .enl-lista { flex:1; min-height:0; display:grid; grid-auto-rows:minmax(0, 1fr); position:relative; }
        .enl-f { --c:var(--ok); position:relative; display:grid; grid-template-columns:.8rem minmax(7rem, 1fr) 6.6rem minmax(5rem, 1fr) minmax(10rem, 3fr) 4.6rem minmax(7rem, 1.2fr);
                 gap:.8rem; align-items:center; padding:0 .8rem; border-bottom:1px solid rgba(255,255,255,.04); cursor:pointer; min-height:0; overflow:hidden; }
        .enl-f:hover { background:rgba(255,255,255,.03); }
        .enl-f[data-e="intermitente"] { --c:var(--warn); background:linear-gradient(90deg, rgba(255,215,3,.10), transparent 55%); }
        .enl-f[data-e="perdida"] { --c:var(--unknown); }
        .enl-f[data-e="caido"] { --c:var(--crit); background:linear-gradient(90deg, rgba(200,50,50,.28), transparent 60%); animation:enl-latir 1.1s ease-in-out infinite; }
        .enl-f[data-e="sin_datos"] { --c:var(--pending); }
        .enl-f[data-e="intermitente"]::before, .enl-f[data-e="caido"]::before { content:""; position:absolute; left:0; top:0; bottom:0; width:.2rem; background:var(--c); }
        .enl-f[data-e="intermitente"]::after { content:""; position:absolute; top:0; bottom:0; width:25%; left:-30%; pointer-events:none;
                                               background:linear-gradient(90deg, transparent, rgba(255,215,3,.12), transparent); animation:enl-barrer 3.4s linear infinite; }
        .enl-f.recupera { animation:enl-recupera 1.8s ease-out; }
        @keyframes enl-latir { 50% { background-color:rgba(200,50,50,.16); } }
        @keyframes enl-barrer { to { left:110%; } }
        @keyframes enl-recupera { from { background:rgba(19,211,137,.3); } }
        .enl-orbe { width:.65rem; height:.65rem; border-radius:50%; background:var(--c); box-shadow:0 0 0 .18rem color-mix(in srgb, var(--c) 25%, transparent), 0 0 .7rem var(--c); }
        .enl-f[data-e="caido"] .enl-orbe { animation:enl-orbe 1.1s ease-in-out infinite; }
        @keyframes enl-orbe { 50% { box-shadow:0 0 0 .45rem transparent, 0 0 1.1rem var(--c); } }
        .enl-nom { font-weight:700; font-size:.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .enl-etq { justify-self:start; font-weight:700; font-size:.62rem; letter-spacing:.12em; color:var(--c); border:1px solid color-mix(in srgb, var(--c) 50%, transparent);
                   padding:.05rem .4rem; border-radius:2px; white-space:nowrap; }
        .enl-f[data-e="estable"] .enl-etq { opacity:.7; }
        .enl-pulso { width:100%; height:1.7rem; display:block; }
        .enl-franja { display:grid; grid-template-columns:repeat(48, 1fr); gap:2px; height:1.15rem; }
        .enl-franja i { border-radius:1px; background:var(--b, #24313a); }
        .enl-lat { text-align:right; font-weight:700; font-size:1.05rem; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .enl-lat small { font-size:.62rem; color:var(--muted); font-weight:400; margin-left:.15rem; }
        .enl-pl { font-size:.72rem; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .enl-pl.mal { color:var(--c); }
        .enl-ejes { display:flex; justify-content:space-between; font-size:.58rem; color:var(--muted); padding:0 .8rem .15rem; }

        /* Modal de un enlace (usa el mismo marco que el de los hexágonos). */
        .enl-kpis { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:1px; background:var(--line); border-bottom:1px solid var(--line); }
        .enl-kpis div { background:var(--panel); padding:.8em 1.1em; }
        .enl-kpis small { display:block; color:var(--muted); font-size:.72em; letter-spacing:.08em; text-transform:uppercase; }
        .enl-kpis b { font-size:1.6em; font-variant-numeric:tabular-nums; }
        .enl-kpis b small { display:inline; font-size:.5em; letter-spacing:0; text-transform:none; }
        .enl-graf { position:relative; height:16em; margin:1em 1.1em .4em; }
        .enl-cortes { padding:.4em 1.1em 1.1em; }
        .enl-cortes h4 { font-size:.75em; letter-spacing:.12em; color:var(--muted); margin:.6em 0; }
        .enl-corte { display:grid; grid-template-columns:9em 1fr 7em 6em; gap:1em; align-items:center; font-size:.9em; padding:.45em .8em; border:1px solid var(--line); border-radius:3px; margin-bottom:.35em; font-variant-numeric:tabular-nums; }
        .enl-corte .tira { height:.4em; border-radius:.2em; background:linear-gradient(90deg, var(--warn), var(--crit)); }
        .md-rango { border:1px solid var(--line); background:transparent; color:var(--muted); font:inherit; font-size:.85em; font-weight:700; padding:.25em .8em; border-radius:3px; }
        .md-rango.on { border-color:var(--hud); color:var(--hud); background:rgba(var(--hud-rgb),.12); }

        /* ── Servicios críticos: mosaico que se arma en una columna si el
           panel es angosto y en dos si es ancho; mismo criterio de tamaño que
           el podio (absoluto y en unidades del contenedor). ───────────────── */
        .svc-caja { flex:1; position:relative; min-height:0; container-type:size; }
        .svc { position:absolute; inset:0; display:grid; grid-template-columns:repeat(auto-fit, minmax(11rem, 1fr));
               grid-auto-rows:minmax(0, 1fr); gap:.35rem; padding:.4rem; }
        .svc-t { --c:#5b6b78; display:flex; flex-direction:column; justify-content:center; min-width:0; min-height:0; overflow:hidden; padding:0 .6rem;
                 border-left:.3rem solid var(--c); background:color-mix(in srgb, var(--c) 14%, transparent); border-radius:2px; }
        /* Clases con prefijo: «.aviso» ya es el cartel de caídas (fijo arriba)
           y un recuadro en amarillo salía volando del panel. */
        .svc-t.svc-ok { --c:var(--ok); } .svc-t.svc-aviso { --c:var(--warn); } .svc-t.svc-critico { --c:var(--crit); } .svc-t.svc-mant { --c:#64b5f6; }
        .svc-t.svc-critico { animation:latido-svc 1.2s ease-in-out infinite; }
        @keyframes latido-svc { 50% { background:color-mix(in srgb, var(--crit) 38%, transparent); } }
        .svc-t b { font-size:min(1.1rem, 34cqh); line-height:1.15; display:flex; align-items:center; gap:.35em; min-width:0; }
        .svc-t b i { width:.55em; height:.55em; border-radius:50%; background:var(--c); flex-shrink:0; box-shadow:0 0 .4em var(--c); }
        .svc-t b em { font-style:normal; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .svc-t span { font-size:min(.78rem, 22cqh); line-height:1.2; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

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

        /* ══ Capa HUD ═══════════════════════════════════════════════════════
           Todo vive bajo body.hud y se apaga con el botón «HUD». Lo que se
           anima es opacidad, transform o la posición de un fondo: barato
           para una TV encendida todo el día. */
        .btn-hud { border:1px solid rgba(var(--hud-rgb),.55); background:transparent; border-radius:3px; padding:.3rem .7rem;
                   font-size:.8rem; color:var(--hud); letter-spacing:.08em; }
        .btn-hud.off { border-color:var(--line); color:var(--muted); }

        #lluvia { position:fixed; inset:0; z-index:0; pointer-events:none; opacity:0; transition:opacity 1.2s; }
        body.hud #lluvia { opacity:.14; }
        .cab, .muro { position:relative; z-index:1; }

        body.hud { background:radial-gradient(ellipse at 50% -10%, #0d2a20 0%, var(--bg) 62%) fixed; }
        /* Grilla holográfica de fondo */
        body.hud .muro::before { content:""; position:fixed; inset:0; z-index:-1; pointer-events:none;
            background-image:linear-gradient(rgba(var(--hud-rgb),.04) 1px, transparent 1px), linear-gradient(90deg, rgba(var(--hud-rgb),.04) 1px, transparent 1px);
            background-size:2.4rem 2.4rem; }
        /* Líneas de barrido, como un monitor de tubo: por encima de los paneles, por debajo de las caídas */
        body.hud::after { content:""; position:fixed; inset:0; z-index:45; pointer-events:none;
            background:repeating-linear-gradient(0deg, rgba(0,0,0,.10) 0 1px, transparent 1px 3px); }

        /* Cabecera */
        body.hud .cab { border-bottom-color:rgba(var(--hud-rgb),.35); background:linear-gradient(180deg, rgba(var(--hud-rgb),.07), transparent);
                        box-shadow:0 1px 1.2rem rgba(var(--hud-rgb),.15); }
        body.hud .cab h1 { letter-spacing:.06em; text-shadow:0 0 .8rem rgba(var(--hud-rgb),.7); }
        body.hud .cab .ruta { color:#7fdcb4; letter-spacing:.12em; text-transform:uppercase; }
        body.hud .reloj { color:#e0fff2; text-shadow:0 0 .8rem rgba(var(--hud-rgb),.85); }

        /* Paneles: vidrio oscuro con esquinas de mira que respiran */
        body.hud .panel:not(.vacio) { background:rgba(16,27,35,.86); border-color:var(--hud-suave);
            box-shadow:inset 0 0 1.8rem rgba(var(--hud-rgb),.05), 0 0 .8rem rgba(0,0,0,.45); }
        body.hud .panel:not(.vacio)::before { content:""; position:absolute; inset:-1px; pointer-events:none; z-index:3;
            --l:.9rem; --g:2px;
            background:
                linear-gradient(var(--hud), var(--hud)) top left     / var(--l) var(--g),
                linear-gradient(var(--hud), var(--hud)) top left     / var(--g) var(--l),
                linear-gradient(var(--hud), var(--hud)) top right    / var(--l) var(--g),
                linear-gradient(var(--hud), var(--hud)) top right    / var(--g) var(--l),
                linear-gradient(var(--hud), var(--hud)) bottom left  / var(--l) var(--g),
                linear-gradient(var(--hud), var(--hud)) bottom left  / var(--g) var(--l),
                linear-gradient(var(--hud), var(--hud)) bottom right / var(--l) var(--g),
                linear-gradient(var(--hud), var(--hud)) bottom right / var(--g) var(--l);
            background-repeat:no-repeat; filter:drop-shadow(0 0 3px var(--hud));
            animation:respirar 4.5s ease-in-out infinite; animation-delay:calc(var(--i, 0) * -.35s); }
        @keyframes respirar { 50% { opacity:.4; } }
        body.hud .panel > h2 { background:linear-gradient(90deg, transparent, rgba(var(--hud-rgb),.15), transparent); color:#dcffef;
            letter-spacing:.09em; text-transform:uppercase; font-size:.72rem; text-shadow:0 0 .5rem rgba(var(--hud-rgb),.6);
            border-bottom:1px solid rgba(var(--hud-rgb),.16); }
        body.hud .panel > h2::before, body.hud .panel > h2::after { content:"◢"; color:var(--hud); opacity:.7; margin:0 .55rem; font-size:.55rem; vertical-align:middle; }
        body.hud .panel > h2::after { content:"◣"; }

        /* Encendido: los paneles aparecen uno tras otro, como un sistema que arranca */
        body.hud.encendiendo .panel:not(.vacio) { animation:encender .75s cubic-bezier(.2,.8,.2,1) both; animation-delay:calc(var(--i, 0) * 45ms); }
        @keyframes encender {
            0%   { opacity:0; clip-path:inset(0 0 100% 0); filter:brightness(2.6); }
            55%  { opacity:1; filter:brightness(1.7); }
            100% { clip-path:inset(0 0 0 0); filter:none; }
        }

        /* Gráficos: escáner que barre y ondas que corren por la base */
        body.hud .graf::before { content:""; position:absolute; inset:0; z-index:1; pointer-events:none;
            background:linear-gradient(90deg, transparent 0 47%, rgba(var(--hud-rgb),.3) 50%, transparent 53%);
            background-size:300% 100%; animation:escaneo 9s linear infinite; animation-delay:calc(var(--i, 0) * -.7s); }
        @keyframes escaneo { from { background-position:100% 0; } to { background-position:0% 0; } }
        body.hud .graf::after { content:""; position:absolute; left:0; right:0; bottom:0; height:42%; z-index:1; pointer-events:none;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 40' preserveAspectRatio='none'%3E%3Cpath d='M0 22 Q25 8 50 22 T100 22 T150 22 T200 22 V40 H0Z' fill='%2313d389' fill-opacity='.5'/%3E%3C/svg%3E") repeat-x,
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 40' preserveAspectRatio='none'%3E%3Cpath d='M0 26 Q25 14 50 26 T100 26 T150 26 T200 26' fill='none' stroke='%2313d389' stroke-width='1.2'/%3E%3C/svg%3E") repeat-x;
            background-size:18rem 100%, 12rem 85%; background-position:0 100%, 0 100%;
            opacity:.12; animation:olas 16s linear infinite; }
        @keyframes olas { to { background-position:18rem 100%, -12rem 100%; } }

        /* Último valor de cada gráfico: un punto que late */
        .pulso { position:absolute; width:.5rem; height:.5rem; margin:-.25rem 0 0 -.25rem; border-radius:50%; z-index:2; pointer-events:none;
                 background:currentColor; box-shadow:0 0 .6rem currentColor; display:none; }
        body.hud .pulso { display:block; }
        .pulso[hidden] { display:none !important; }
        .pulso::after { content:""; position:absolute; inset:-.35rem; border-radius:50%; border:1px solid currentColor; animation:pulso-punto 1.8s ease-out infinite; }
        @keyframes pulso-punto { from { transform:scale(.4); opacity:1; } to { transform:scale(2.3); opacity:0; } }

        /* Medidores y hexágonos: anillos que giran, estilo reactor */
        .m-anillo, .hex-anillo { display:none; }
        body.hud .m-anillo, body.hud .hex-anillo { display:inline; transform-box:fill-box; transform-origin:center; animation:girar 20s linear infinite; }
        body.hud .m-anillo.inv { animation-duration:28s; animation-direction:reverse; }
        body.hud .hex-anillo { animation-duration:24s; }
        @keyframes girar { to { transform:rotate(360deg); } }
        body.hud .medidor svg { filter:drop-shadow(0 0 .35rem rgba(var(--hud-rgb),.35)); }
        body.hud .hex-in { transform-box:fill-box; transform-origin:center; animation:latido-suave 3.2s ease-in-out infinite; }
        @keyframes latido-suave { 50% { transform:scale(.84); opacity:.8; } }
        body.hud .numero b { color:#eafff5; text-shadow:0 0 .9rem rgba(var(--hud-rgb),.55); }
        body.hud .numero span { color:#8fe2bd; }

        /* Modo navidad (noviembre y diciembre): el trineo cruza la pantalla cada ~1 min. */
        .noel { position:fixed; left:0; top:0; z-index:48; width:clamp(320px, 30vw, 620px); pointer-events:none;
                display:none; will-change:transform; }
        .noel.vuela { display:block; }
        .noel svg { display:block; width:100%; height:auto; overflow:visible;
                    filter:drop-shadow(0 0 .5rem rgba(255,255,255,.25)); animation:noel-flota 1.6s ease-in-out infinite; }
        .noel.izq svg { transform:scaleX(-1); }
        @keyframes noel-flota { 50% { translate:0 -6%; } }
        .noel .pata { transform-box:fill-box; transform-origin:top center; animation:noel-galope .45s ease-in-out infinite alternate; }
        .noel .pata.b { animation-delay:-.22s; }
        @keyframes noel-galope { from { transform:rotate(-28deg); } to { transform:rotate(24deg); } }
        .noel .brazo { transform-box:fill-box; transform-origin:left bottom; animation:noel-saludo .7s ease-in-out infinite alternate; }
        @keyframes noel-saludo { from { transform:rotate(-12deg); } to { transform:rotate(22deg); } }
        .noel .nariz { animation:noel-nariz 1s ease-in-out infinite; }
        @keyframes noel-nariz { 50% { opacity:.45; } }
        /* Duende que se asoma por un borde de la pantalla. */
        .duende { position:fixed; z-index:48; width:clamp(80px, 6.5vw, 140px); aspect-ratio:100/120; overflow:hidden;
                  pointer-events:none; display:none; }
        .duende.asoma { display:block; }
        .duende > div { width:100%; height:100%; transform:translateY(105%); }
        .duende svg { display:block; width:100%; height:100%; filter:drop-shadow(0 0 .4rem rgba(255,255,255,.2)); }
        .duende .ojo { transform-box:fill-box; transform-origin:center; animation:duende-parpadeo 3s infinite; }
        @keyframes duende-parpadeo { 0%, 92%, 100% { transform:scaleY(1); } 95% { transform:scaleY(.1); } }
        .duende .cascabel { transform-box:fill-box; transform-origin:top center; animation:duende-cascabel .5s ease-in-out infinite alternate; }
        @keyframes duende-cascabel { from { transform:rotate(-15deg); } to { transform:rotate(15deg); } }
        /* Monstruito verde que aparece bailando en cualquier parte. */
        .grunon { position:fixed; z-index:48; width:clamp(100px, 7.5vw, 160px); pointer-events:none; display:none; }
        .grunon.baila { display:block; }
        .grunon svg { display:block; width:100%; height:auto; overflow:visible;
                      filter:drop-shadow(0 0 .7rem rgba(120,220,60,.45)); }
        .grunon .cuerpo { transform-box:view-box; transform-origin:50px 135px; animation:gr-baile .5s ease-in-out infinite alternate; }
        @keyframes gr-baile { from { transform:translateY(0) rotate(-9deg); } to { transform:translateY(-7px) rotate(9deg); } }
        .grunon .brazo { transform-box:fill-box; animation:gr-brazo .25s ease-in-out infinite alternate; }
        .grunon .brazo.i { transform-origin:right bottom; }
        .grunon .brazo.d { transform-origin:left bottom; animation-delay:-.12s; }
        @keyframes gr-brazo { from { transform:rotate(-30deg); } to { transform:rotate(30deg); } }
        .grunon .pierna { transform-box:fill-box; transform-origin:top center; animation:gr-pierna .5s ease-in-out infinite alternate; }
        .grunon .pierna.d { animation-direction:alternate-reverse; }
        @keyframes gr-pierna { from { transform:rotate(-18deg); } to { transform:rotate(18deg); } }
        #nieve { position:fixed; inset:0; z-index:46; pointer-events:none; display:none; }
        #nieve.activa { display:block; }
        .chispa { position:fixed; z-index:47; width:var(--t); height:var(--t); margin:calc(var(--t) / -2) 0 0 calc(var(--t) / -2);
                  pointer-events:none; border-radius:50%;
                  background:radial-gradient(circle, #fff 0 30%, #fff3b0 50%, rgba(255,215,90,.6) 65%, transparent 72%);
                  box-shadow:0 0 .6rem .15rem rgba(255,230,140,.85), 0 0 1.6rem .3rem rgba(255,200,80,.45);
                  animation:chispa var(--d) ease-out forwards; }
        @keyframes chispa {
            0%   { transform:translate(0, 0) scale(1); opacity:1; }
            60%  { opacity:.85; }
            100% { transform:translate(var(--dx), var(--dy)) scale(.15); opacity:0; }
        }

        /* ══ Piel 2.0 ═══════════════════════════════════════════════════════
           Un solo lenguaje para todo el muro: tarjetas con el título a la
           izquierda, orbes de estado en vez de bloques de color, barras
           finas y tipografía de consola para los datos. Va al final para
           mandar sobre la réplica de CheckMK y sobre la capa HUD. */
        /* Sin overflow:hidden: encajar() mide lo que desborda para elegir el
           tamaño de letra; si se recorta, cree que todo cabe y agranda de más. */
        .muro .panel:not(.vacio) { border-radius:.35rem; background:linear-gradient(180deg, #0f1c20, #0b1417 3.2rem); border-color:#16282d; }
        .muro .panel:not(.vacio):not(.crece) { flex-shrink:0; }
        body.hud .muro .panel:not(.vacio) { background:linear-gradient(180deg, rgba(15,28,32,.94), rgba(11,20,23,.94) 3.2rem); border-color:rgba(var(--hud-rgb),.16); }
        body .muro .panel > h2 { text-align:left; font-family:var(--f-display); font-weight:700; font-size:.76rem; letter-spacing:.1em; text-transform:uppercase;
                                 padding:.38rem .7rem; background:linear-gradient(90deg, rgba(var(--hud-rgb),.10), transparent 70%); border-bottom:1px solid rgba(255,255,255,.06);
                                 color:#e8f1f2; text-shadow:none; }
        body .muro .panel > h2::before, body .muro .panel > h2::after { content:none; }
        body .muro .panel.abre > h2::after { content:"⤢"; float:right; margin:0; opacity:1; font-size:.8rem; color:var(--muted); font-family:var(--f-body); }

        /* Tablas: orbe + nombre en vez del bloque de color, W/C como chips y barras finas. */
        .muro .t-cab { font-family:var(--f-display); letter-spacing:.08em; text-transform:uppercase; font-size:.64rem; height:1.5rem; }
        .muro .t-fila { height:1.7rem; border-bottom:1px solid rgba(255,255,255,.035); border-radius:0; }
        .muro .t-fila .c-host { background:transparent; color:#e8f1f2; font-family:var(--f-mono); font-weight:600; font-size:.74rem; padding-left:1.15rem; position:relative; height:auto; }
        .muro .t-fila .c-host::before { content:""; position:absolute; left:.3rem; top:50%; width:.55rem; height:.55rem; margin-top:-.275rem; border-radius:50%;
                                        background:var(--hc, var(--pending)); box-shadow:0 0 0 .14rem color-mix(in srgb, var(--hc, var(--pending)) 22%, transparent), 0 0 .5rem var(--hc, var(--pending)); }
        .t-fila.up, .l-fila.up { --hc:var(--ok); } .t-fila.down, .l-fila.down { --hc:var(--crit); }
        .t-fila.unreach, .l-fila.unreach { --hc:var(--unknown); } .t-fila.mant, .l-fila.mant { --hc:var(--dt); } .t-fila.pend, .l-fila.pend { --hc:var(--pending); }
        .muro .t-fila.down:not(.alarma), .muro .l-fila.down:not(.alarma) { background:linear-gradient(90deg, rgba(200,50,50,.28), transparent 65%); }
        .muro .t-fila.down .c-host { color:#ffb4b4; }
        .muro .c-n { height:1.1rem; border-radius:2px; font-family:var(--f-mono); font-size:.7rem; color:#4f6168; }
        .muro .c-n.hay { color:#000; }
        .muro .perf { height:1.05rem; background:#13252a; color:#e8f1f2; border-radius:2px; font-family:var(--f-mono); font-size:.64rem; }
        .muro .perf > span { text-shadow:0 0 .2rem #000, 0 0 .2rem #000; font-weight:400; }
        .muro .perf > b { opacity:.85; border-radius:2px; }
        .muro .perf.vacia { background:transparent; }

        /* Listas: el bloque «UP» pasa a ser un orbe. */
        .muro .l-cab, .muro .l-fila { grid-template-columns:.9rem 1fr; }
        .muro .l-cab span:first-child { visibility:hidden; }
        .muro .l-cab { font-family:var(--f-display); letter-spacing:.08em; text-transform:uppercase; font-size:.64rem; }
        .muro .l-fila { height:1.45rem; font-family:var(--f-mono); font-size:.74rem; }
        .muro .l-estado { width:.55rem; height:.55rem; border-radius:50%; font-size:0; background:var(--hc, var(--pending));
                          box-shadow:0 0 0 .14rem color-mix(in srgb, var(--hc, var(--pending)) 22%, transparent), 0 0 .5rem var(--hc, var(--pending)); justify-self:center; }

        /* Anillos (antes hexágonos). */
        .an-svg { width:5.6rem; height:5.6rem; flex-shrink:0; overflow:visible; transition:transform .45s cubic-bezier(.2,.8,.2,1.15); }
        .an-svg .seg { transition:stroke-dasharray .8s, stroke-dashoffset .8s; }
        .an-svg text { font-family:var(--f-mono); fill:#e8f1f2; }
        .panel.alarma-hex .an-svg { animation:latido-hex 1.15s ease-in-out infinite; filter:drop-shadow(0 0 1rem rgba(255,50,50,.8)); }
        .muro .hex-ley { font-family:var(--f-body); font-size:.7rem; line-height:1.35; min-width:0; }
        .muro .hex-ley div { grid-template-columns:1.6rem .65rem minmax(0, 1fr); gap:.35rem; }
        .muro .hex-ley span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .muro .hex-cuerpo { gap:.7rem; padding:.45rem .7rem; }
        .muro .hex-ley b { font-family:var(--f-mono); }
        .muro .hex-ley i { border-radius:2px; width:.65rem; height:.65rem; }

        /* ── Mesa de ayuda (GLPI): cifras, últimos tickets y eventos ──────── */
        .mesa-c { flex:1; min-height:0; display:flex; flex-direction:column; }
        .mesa-k { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:1px; background:rgba(255,255,255,.05); border-bottom:1px solid rgba(255,255,255,.06); }
        .mk { --c:var(--muted); position:relative; background:#0b1417; padding:.45rem .65rem .4rem; overflow:hidden; }
        .mk::before { content:""; position:absolute; left:0; top:0; bottom:0; width:.18rem; background:var(--c); opacity:.8; }
        .mk small { display:block; font-family:var(--f-display); font-weight:700; font-size:.58rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mk b { display:block; font-family:var(--f-mono); font-weight:600; font-size:1.55rem; line-height:1.1; font-variant-numeric:tabular-nums; color:#e8f1f2; }
        .mk span { display:block; font-family:var(--f-mono); font-size:.6rem; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mk.alerta b { color:var(--c); }
        .mk.mas  { animation:mk-mas 2.2s ease-out; }
        .mk.menos { animation:mk-menos 2.2s ease-out; }
        @keyframes mk-mas   { 0%, 20% { background:color-mix(in srgb, var(--fx, var(--warn)) 34%, #0b1417); box-shadow:inset 0 0 0 1px var(--fx, var(--warn)); } 100% { background:#0b1417; box-shadow:none; } }
        @keyframes mk-menos { 0%, 20% { background:color-mix(in srgb, var(--fx, var(--ok)) 30%, #0b1417); box-shadow:inset 0 0 0 1px var(--fx, var(--ok)); } 100% { background:#0b1417; box-shadow:none; } }
        .mk .delta { position:absolute; right:.5rem; top:.35rem; font-family:var(--f-mono); font-weight:700; font-size:.85rem; color:var(--fx); animation:mk-delta 2.4s ease-out forwards; pointer-events:none; }
        @keyframes mk-delta { 0% { opacity:0; transform:translateY(.6rem) scale(.8); } 15% { opacity:1; transform:none; } 70% { opacity:1; } 100% { opacity:0; transform:translateY(-.8rem); } }
        .mesa-t { display:flex; justify-content:space-between; align-items:center; padding:.3rem .65rem .15rem; font-family:var(--f-display); font-weight:700; font-size:.6rem; letter-spacing:.12em; color:var(--muted); text-transform:uppercase; }
        .mesa-l { flex:1; min-height:0; overflow:hidden; position:relative; }
        .mt { --c:var(--muted); display:grid; grid-template-columns:3.4rem minmax(0, 1fr) 6.2rem 4.4rem; gap:.6rem; align-items:center; padding:.32rem .65rem; border-bottom:1px solid rgba(255,255,255,.035); font-size:.74rem; }
        .mt .id { font-family:var(--f-mono); color:var(--muted); font-size:.7rem; }
        .mt .ti { min-width:0; }
        .mt .ti b { display:block; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mt .ti span { display:block; font-size:.62rem; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mt .es { justify-self:start; font-family:var(--f-display); font-weight:700; font-size:.56rem; letter-spacing:.1em; color:var(--c); border:1px solid color-mix(in srgb, var(--c) 50%, transparent); padding:.02rem .35rem; border-radius:2px; white-space:nowrap; }
        .mt .ha { font-family:var(--f-mono); font-size:.64rem; color:var(--muted); text-align:right; white-space:nowrap; }
        .mt.nuevo { animation:mt-nuevo 3s ease-out; }
        .mt.cambia { animation:mt-cambia 2.6s ease-out; }
        @keyframes mt-nuevo { 0% { transform:translateX(-1.2rem); opacity:0; background:rgba(255,215,3,.35); } 15% { transform:none; opacity:1; } 100% { background:transparent; } }
        @keyframes mt-cambia { 0%, 15% { background:rgba(19,211,137,.32); } 100% { background:transparent; } }
        .mesa-ev { display:flex; gap:1rem; align-items:center; padding:.3rem .65rem; border-top:1px solid rgba(255,255,255,.06); font-family:var(--f-mono); font-size:.64rem; color:var(--muted); white-space:nowrap; overflow:hidden; }
        .mesa-ev .rot { font-family:var(--f-display); font-weight:700; letter-spacing:.12em; color:var(--hud); font-size:.58rem; }
        .mesa-ev .ev b { color:#e8f1f2; font-weight:400; }
        .mesa-ev .ev.nuevo { animation:mt-nuevo 1.2s ease-out; }

        /* Hueco reservado (tipo «libre»). */
        .libre { border:1px dashed rgba(var(--hud-rgb),.32); border-radius:.35rem; display:grid; place-items:center; text-align:center;
                 color:rgba(var(--hud-rgb),.7); font-family:var(--f-display); font-weight:600; letter-spacing:.16em; font-size:.72rem; text-transform:uppercase;
                 background:repeating-linear-gradient(135deg, rgba(var(--hud-rgb),.025) 0 .7rem, transparent .7rem 1.4rem); }
        .libre small { display:block; font-family:var(--f-body); letter-spacing:0; text-transform:none; color:var(--muted); font-size:.66rem; margin-top:.2rem; font-weight:400; }

        /* Conteo de estados a la derecha del título. */
        .h2-res { float:right; display:flex; gap:.45rem; font-family:var(--f-mono); font-weight:400; letter-spacing:0; font-size:.66rem; color:var(--muted); margin-left:.5rem; }
        .h2-res span { display:inline-flex; align-items:center; gap:.25rem; }
        .h2-res i { width:.42rem; height:.42rem; border-radius:50%; background:var(--c); }

        /* Uptime como texto, igual que la muestra: la barra no aportaba. */
        .muro .perf.uptime { background:transparent; }
        .muro .perf.uptime > b { display:none; }
        .muro .perf.uptime > span { justify-content:flex-end; padding-right:.2rem; color:var(--muted); text-shadow:none; }

        /* Tarjeta de servidor. */
        .srv { --c:var(--ok); }
        .srv[data-e="warn"] { --c:var(--warn); } .srv[data-e="crit"], .srv[data-e="caido"] { --c:var(--crit); }
        .srv > h2 { box-shadow:inset .22rem 0 0 var(--c); }
        .srv-meta { display:flex; align-items:center; gap:.6rem; padding:.3rem .7rem; font-family:var(--f-mono); font-size:.68rem; color:var(--muted); border-bottom:1px solid rgba(255,255,255,.05); }
        .srv-meta .wc { margin-left:auto; display:flex; gap:.35rem; }
        .srv-pill { font-family:var(--f-display); font-weight:700; font-size:.58rem; letter-spacing:.12em; color:var(--c); border:1px solid color-mix(in srgb, var(--c) 50%, transparent); padding:.02rem .4rem; border-radius:2px; }
        .srv-v { flex:none; height:5.2rem; display:grid; grid-template-columns:4.6rem minmax(0, 1fr); gap:.7rem; align-items:center; padding:.35rem .7rem; border-bottom:1px solid rgba(255,255,255,.04); }
        .srv-g { position:relative; width:4.6rem; height:4.6rem; }
        .srv-g svg { width:100%; height:100%; transform:rotate(135deg); overflow:visible; }
        .srv-g circle { fill:none; stroke-width:6; stroke-linecap:round; }
        .srv-g .pista { stroke:#16282d; }
        .srv-g .valor { transition:stroke-dasharray .8s, stroke .4s; filter:drop-shadow(0 0 .25rem currentColor); }
        .srv-gv { position:absolute; inset:0; display:grid; place-content:center; text-align:center; font-family:var(--f-mono); line-height:1.05; }
        .srv-gv b { font-size:1.05rem; }
        .srv-gv small { font-family:var(--f-display); font-size:.52rem; letter-spacing:.14em; color:var(--muted); }
        .srv-s { min-width:0; height:100%; display:flex; flex-direction:column; }
        .srv-tt { display:flex; justify-content:space-between; gap:.5rem; font-family:var(--f-mono); font-size:.62rem; color:var(--muted); padding:.1rem 0; white-space:nowrap; overflow:hidden; }
        .srv-tt b { color:#e8f1f2; font-weight:400; }
        .srv-cv { flex:1; min-height:1.8rem; position:relative; }
        .srv-cv canvas { position:absolute; inset:0; display:block; }
        .srv-red { flex:none; height:3.8rem; display:grid; grid-template-columns:auto auto minmax(0, 1fr); gap:.9rem; align-items:center; padding:.35rem .7rem; }
        .srv-red .srv-cv { height:100%; }
        .srv-n { font-family:var(--f-mono); line-height:1.1; white-space:nowrap; }
        .srv-n small { display:block; font-family:var(--f-display); font-size:.55rem; letter-spacing:.12em; color:var(--muted); }
        .srv-n b { font-size:1.15rem; font-variant-numeric:tabular-nums; }
        .srv-n b em { font-style:normal; font-size:.62rem; color:var(--muted); font-weight:400; }
        .srv-n .sub { color:#3fd6d0; } .srv-n .baj { color:#5a9be6; }
        .srv-kpis { grid-template-columns:repeat(5, minmax(0, 1fr)); }
        .srv-graficos { display:grid; grid-template-columns:1fr 1fr; gap:1em; padding:1em 1.1em; }
        .srv-graficos figure { margin:0; border:1px solid var(--line); border-radius:4px; padding:.6em .8em; }
        .srv-graficos figcaption { font-size:.75em; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin-bottom:.4em; }
        .srv-graficos .ancho { grid-column:1 / -1; }
        .srv-mg { position:relative; height:11em; }

        @media (prefers-reduced-motion: reduce) {
            .noel, .chispa, #nieve, .duende, .grunon { display:none !important; }
            .t-fila.alarma, .l-fila.alarma, .panel.alarma-hex .hex, .aviso .ico, .alarma::after { animation:none; }
            #lluvia { display:none; }
            body.hud .graf::before, body.hud .graf::after, .pulso::after, body.hud .m-anillo, body.hud .hex-anillo,
            body.hud .hex-in, body.hud .panel::before, body.hud.encendiendo .panel { animation:none; }
        }
    </style>
</head>
<body class="{{ $modoTv ? 'tv' : '' }} hud encendiendo">
<canvas id="lluvia" aria-hidden="true"></canvas>

{{-- Viejito pascuero, duende y nieve: solo en noviembre y diciembre (ver el script). --}}
<canvas id="nieve" aria-hidden="true"></canvas>
<div class="grunon" id="grunon" aria-hidden="true">
    <svg viewBox="0 0 100 140" xmlns="http://www.w3.org/2000/svg">
        <g class="cuerpo">
            {{-- piernas --}}
            <g class="pierna i"><path d="M40 108 L36 130" stroke="#5fa82a" stroke-width="7" stroke-linecap="round"/><ellipse cx="33" cy="132" rx="8" ry="4" fill="#3d6e1a"/></g>
            <g class="pierna d"><path d="M60 108 L64 130" stroke="#5fa82a" stroke-width="7" stroke-linecap="round"/><ellipse cx="67" cy="132" rx="8" ry="4" fill="#3d6e1a"/></g>
            {{-- brazos --}}
            <path class="brazo i" d="M30 80 Q18 72 12 58" stroke="#5fa82a" stroke-width="6" fill="none" stroke-linecap="round"/>
            <path class="brazo d" d="M70 80 Q82 72 88 58" stroke="#5fa82a" stroke-width="6" fill="none" stroke-linecap="round"/>
            {{-- cuerpo peludo con chaleco rojo --}}
            <path d="M28 76 Q24 112 40 112 L60 112 Q76 112 72 76 Q50 66 28 76 Z" fill="#6cbf2e"/>
            <path d="M38 74 L44 112 L56 112 L62 74 Q50 70 38 74 Z" fill="#c62828"/>
            <path d="M44 112 h12" stroke="#fafafa" stroke-width="3"/>
            {{-- cabeza --}}
            <path d="M26 52 Q22 30 40 26 Q50 22 60 26 Q78 30 74 52 Q74 72 50 74 Q26 72 26 52 Z" fill="#6cbf2e"/>
            <path d="M27 46 l-5 -2 l5 -2 M73 46 l5 -2 l-5 -2 M30 62 l-5 1 l4 -4 M70 62 l5 1 l-4 -4" stroke="#4c9420" stroke-width="2" fill="none"/>
            {{-- cejas pícaras, ojos y sonrisa ladina --}}
            <path d="M33 38 L45 42 M67 38 L55 42" stroke="#2f5c12" stroke-width="3" stroke-linecap="round"/>
            <ellipse cx="40" cy="47" rx="5.5" ry="4.5" fill="#f4e04d"/><circle cx="41.5" cy="47.5" r="2.2" fill="#1a1a1a"/>
            <ellipse cx="60" cy="47" rx="5.5" ry="4.5" fill="#f4e04d"/><circle cx="58.5" cy="47.5" r="2.2" fill="#1a1a1a"/>
            <path d="M34 58 Q50 72 68 56" stroke="#2f5c12" stroke-width="2.6" fill="#3d6e1a" stroke-linecap="round"/>
            <path d="M44 63 l2 3 l2 -3 M54 63 l2 3 l2 -3" stroke="#fafafa" stroke-width="1.5" fill="none"/>
            {{-- gorro navideño ladeado --}}
            <path d="M30 30 Q44 2 74 8 Q86 12 90 26 Q78 18 70 28 Z" fill="#c62828"/>
            <path d="M27 31 Q50 20 72 29 L72 35 Q50 26 27 37 Z" fill="#fafafa"/>
            <circle cx="91" cy="28" r="5" fill="#fafafa"/>
        </g>
    </svg>
</div>
<div class="duende" id="duende" aria-hidden="true"><div>
    <svg viewBox="0 0 100 120" xmlns="http://www.w3.org/2000/svg">
        {{-- cuerpo --}}
        <path d="M14 120 Q16 92 50 90 Q84 92 86 120 Z" fill="#2e7d32"/>
        <path d="M38 92 L50 104 L62 92" fill="#c62828"/>
        {{-- orejas puntiagudas --}}
        <path d="M24 60 L4 46 L26 72 Z" fill="#f2c9a0"/>
        <path d="M76 60 L96 46 L74 72 Z" fill="#f2c9a0"/>
        {{-- cara --}}
        <circle cx="50" cy="64" r="26" fill="#f6d2ad"/>
        <circle cx="36" cy="72" r="5" fill="#ff8a80" opacity=".6"/>
        <circle cx="64" cy="72" r="5" fill="#ff8a80" opacity=".6"/>
        <ellipse class="ojo" cx="41" cy="62" rx="3.2" ry="4" fill="#1a1a1a"/>
        <ellipse class="ojo" cx="59" cy="62" rx="3.2" ry="4" fill="#1a1a1a"/>
        <circle cx="42" cy="60.5" r="1.1" fill="#fff"/><circle cx="60" cy="60.5" r="1.1" fill="#fff"/>
        <ellipse cx="50" cy="69" rx="3" ry="2.4" fill="#e8a27c"/>
        <path d="M41 77 Q50 85 59 77" stroke="#8d3b2a" stroke-width="2.2" fill="none" stroke-linecap="round"/>
        {{-- gorro --}}
        <path d="M24 46 Q30 10 66 6 Q84 4 92 18 Q72 14 76 44 Z" fill="#2e7d32"/>
        <path d="M22 44 Q50 34 78 44 L78 51 Q50 41 22 51 Z" fill="#c62828"/>
        <g class="cascabel"><path d="M92 18 v4" stroke="#e0b23a" stroke-width="1.5"/><circle cx="92" cy="25" r="4.5" fill="#ffd34d"/></g>
        {{-- manos agarradas del borde --}}
        <ellipse cx="22" cy="112" rx="9" ry="7" fill="#f6d2ad"/>
        <ellipse cx="78" cy="112" rx="9" ry="7" fill="#f6d2ad"/>
    </svg>
</div></div>
<div class="noel" id="noel" aria-hidden="true">
    <svg viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <g id="noel-reno">
                <path class="pata" d="M40 31 l5 14" stroke="#6b4423" stroke-width="3.2" stroke-linecap="round"/>
                <path class="pata b" d="M35 32 l-3 13" stroke="#6b4423" stroke-width="3.2" stroke-linecap="round"/>
                <path class="pata b" d="M15 31 l-7 12" stroke="#6b4423" stroke-width="3.2" stroke-linecap="round"/>
                <path class="pata" d="M19 32 l2 13" stroke="#6b4423" stroke-width="3.2" stroke-linecap="round"/>
                <ellipse cx="26" cy="25" rx="20" ry="9" fill="#8b5a2b"/>
                <circle cx="6" cy="21" r="3.2" fill="#f4ead9"/>
                <path d="M37 21 L45 7 L52 10 L44 27 Z" fill="#8b5a2b"/>
                <ellipse cx="52" cy="8" rx="8" ry="5.2" fill="#8b5a2b"/>
                <ellipse cx="46" cy="3" rx="3.2" ry="1.6" fill="#6b4423" transform="rotate(-30 46 3)"/>
                <path d="M49 3 L45 -8 M47 -2 L41 -4 M53 2 L56 -9 M54.5 -3 L60 -5" stroke="#e0c08f" stroke-width="2" stroke-linecap="round" fill="none"/>
                <circle cx="54" cy="6.5" r="1.1" fill="#1a1a1a"/>
                <path d="M20 23 Q30 30 40 22" stroke="#c62828" stroke-width="3" fill="none"/>
                <circle cx="30" cy="27.5" r="1.8" fill="#ffd34d"/>
            </g>
        </defs>
        {{-- riendas --}}
        <path d="M150 50 Q215 40 265 47 M150 50 Q260 52 335 40" stroke="#e0b23a" stroke-width="1.4" fill="none"/>
        <use href="#noel-reno" x="222" y="32"/>
        <use href="#noel-reno" x="292" y="24"/>
        <circle class="nariz" cx="351" cy="33" r="3" fill="#ff3b3b" style="filter:drop-shadow(0 0 4px #ff3b3b)"/>
        {{-- saco de regalos --}}
        <ellipse cx="62" cy="44" rx="17" ry="14" fill="#2e7d32"/>
        <rect x="52" y="24" width="12" height="12" rx="1.5" fill="#1e88e5"/><path d="M58 24 v12" stroke="#ffd34d" stroke-width="2"/>
        <rect x="64" y="20" width="11" height="13" rx="1.5" fill="#e53935"/><path d="M69.5 20 v13" stroke="#fff" stroke-width="2"/>
        {{-- viejito --}}
        <circle cx="100" cy="45" r="15" fill="#d32f2f"/>
        <rect x="86" y="46" width="28" height="4" fill="#212121"/><rect x="97" y="45.5" width="5" height="5" fill="#ffd34d"/>
        <path class="brazo" d="M108 40 L122 27" stroke="#d32f2f" stroke-width="6" stroke-linecap="round"/>
        <circle cx="123" cy="25" r="3.6" fill="#f4ead9"/>
        <circle cx="106" cy="27" r="8" fill="#f2c9a0"/>
        <path d="M98 28 Q100 44 108 42 Q116 40 114 28 Q106 34 98 28 Z" fill="#fafafa"/>
        <circle cx="109" cy="25" r="1.1" fill="#1a1a1a"/>
        <path d="M98 22 Q104 8 120 12 L112 22 Z" fill="#d32f2f"/>
        <rect x="96" y="20" width="18" height="4.5" rx="2.2" fill="#fafafa"/>
        <circle cx="121" cy="12" r="3.4" fill="#fafafa"/>
        {{-- trineo --}}
        <path d="M44 34 L56 34 L62 54 L140 54 Q154 54 157 44 L164 44 Q164 68 140 70 L62 70 Q46 70 44 34 Z"
              fill="#c62828" stroke="#e0b23a" stroke-width="2"/>
        <path d="M70 62 h60" stroke="#e0b23a" stroke-width="1.2" stroke-dasharray="3 4"/>
        <path d="M66 70 v10 M136 70 v10" stroke="#e0b23a" stroke-width="2.4"/>
        <path d="M34 80 L148 80 Q168 80 172 66" stroke="#e0b23a" stroke-width="3" fill="none" stroke-linecap="round"/>
    </svg>
</div>

<header class="cab">
    <div>
        <h1>Main Dashboard for all</h1>
        <div class="ruta">Muro de monitoreo · CheckMK</div>
    </div>
    <div class="der">
        <button type="button" class="btn-hud" id="btn-hud" title="Activar o desactivar los efectos visuales">HUD ●</button>
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

<div class="md-fondo" id="md" hidden>
    <div class="md-caja" role="dialog" aria-modal="true" aria-labelledby="md-t">
        <div class="md-cab">
            <h3 id="md-t"></h3>
            <div class="md-chips" id="md-chips"></div>
            <input type="search" class="md-buscar" id="md-buscar" placeholder="Buscar host, IP o servicio…" autocomplete="off">
            <button type="button" class="md-x" id="md-x" title="Cerrar (Esc)">✕</button>
        </div>
        <div class="md-cuerpo" id="md-cuerpo"></div>
        <div class="md-pie"><span id="md-pie-i"></span><span id="md-pie-d"></span></div>
    </div>
</div>

<div class="aviso" id="aviso" role="alert" aria-live="assertive">
    <div class="ico">▼</div>
    <div><b id="aviso-t"></b><span id="aviso-s"></span></div>
</div>

<script>
(() => {
    const AREAS      = @json($areas);
    const URL_ESTADO = @json($urlEstado);
    const URL_GRAFICOS = @json($urlGraficos);
    const URL_RANKING  = @json($urlRanking);
    const URL_SERVICIOS = @json($urlServicios);
    const URL_CARPETA  = @json($urlCarpeta);
    const URL_CHECKMK  = @json($urlCheckmk);
    const URL_ENLACES  = @json($urlEnlaces);
    const URL_MESA     = @json($urlMesa);
    const URL_ENLACE   = @json($urlEnlace);
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
            if (p.crece || p.paneles.some(x => x.tipo === 'grafico')) f.classList.add('crece');
            return f;
        }
        if (p.tipo === 'vacio') return el('div', 'panel vacio');
        // Hueco reservado para paneles futuros: ocupa el alto libre de su columna.
        if (p.tipo === 'libre') return el('div', 'libre crece', `<div>${esc(p.titulo || 'Espacio libre')}<small>${esc(p.nota || 'Para el próximo panel')}</small></div>`);

        const panel = el('section', 'panel');
        if (p.ancho) panel.style.flex = p.ancho;
        panel.appendChild(el('h2', '', esc(p.titulo)));
        const fabrica = { tabla: crearTabla, lista: crearLista, hexagono_hosts: crearHexHosts,
                          hexagono_servicios: crearHexServicios, medidor: crearMedidor, numero: crearNumero, grafico: crearGrafico,
                          ranking: crearRanking, servicios: crearServicios, enlaces: crearEnlaces, servidor: crearServidor,
                          mesa: crearMesa }[p.tipo];
        if (!fabrica) return panel;
        const actualizar = fabrica(panel, p);
        if (actualizar) paneles.push({ p, actualizar, panel });
        if (['grafico', 'servicios', 'enlaces', 'mesa'].includes(p.tipo)) panel.classList.add('crece');
        if (p.alto) { panel.style.height = p.alto; panel.style.flexShrink = '0'; }
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
            resumenTitulo(panel, hosts.map(([, h]) => h));
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

    /* Conteo de estados en el título: verde sin novedad, amarillo con
       avisos, rojo caídos o críticos. Solo se muestran los que hay. */
    function resumenTitulo(panel, hosts) {
        const h2 = panel.querySelector('h2');
        let r = h2.querySelector('.h2-res');
        if (!r) { r = el('span', 'h2-res'); h2.prepend(r); }
        const c = { ok: 0, warn: 0, crit: 0 };
        hosts.forEach(h => {
            if (h.estado === 'down' || h.estado === 'unreach' || h.cr > 0) c.crit++;
            else if (h.wa > 0) c.warn++;
            else c.ok++;
        });
        r.innerHTML = [['ok', 'var(--ok)'], ['warn', 'var(--warn)'], ['crit', 'var(--crit)']]
            .filter(([k]) => c[k] || k === 'ok').map(([k, col]) => `<span style="--c:${col}"><i></i>${c[k]}</span>`).join('');
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
            // En las listas solo cuenta si el host responde: sus servicios no se muestran.
            resumenTitulo(panel, hosts.map(([, h]) => ({ estado: h.estado, wa: 0, cr: 0 })));
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
    /* Piel 2.0: el hexágono pasa a ser un anillo partido por estado, con el
       total al centro. Se conserva el aro punteado que gira con el HUD. */
    const R_AN = 38, L_AN = 2 * Math.PI * R_AN;
    function hexSvg() {
        return `<svg class="an-svg" viewBox="0 0 100 100">
            <circle class="hex-anillo" cx="50" cy="50" r="48" fill="none" stroke="var(--hud)" stroke-width=".8" stroke-dasharray="3 6" opacity=".6"/>
            <circle cx="50" cy="50" r="${R_AN}" fill="none" stroke="#16282d" stroke-width="10"/>
            <g class="segs" transform="rotate(-90 50 50)"></g>
            <text class="an-n" x="50" y="53" text-anchor="middle" font-size="20" font-weight="600">—</text>
            <text class="an-t" x="50" y="68" text-anchor="middle" font-size="7.5" fill="#8a96a1" style="fill:#8a96a1; letter-spacing:.1em">TOTAL</text></svg>`;
    }
    // segs: [[cantidad, color], …] en el orden en que se dibujan.
    function pintarAnillo(cuerpo, segs, total, centro) {
        let off = 0;
        cuerpo.querySelector('.segs').innerHTML = segs.filter(([n]) => n > 0).map(([n, col]) => {
            const largo = total ? L_AN * n / total : 0;
            const s = `<circle class="seg" cx="50" cy="50" r="${R_AN}" fill="none" stroke="${col}" stroke-width="10"
                stroke-dasharray="${Math.max(0, largo - 1.2)} ${L_AN}" stroke-dashoffset="${-off}" style="filter:drop-shadow(0 0 2px ${col})"/>`;
            off += largo;
            return s;
        }).join('');
        cuerpo.querySelector('.an-n').textContent = centro;
    }
    function leyenda(filas) {
        return filas.map(([n, color, txt]) => `<div><b>${n}</b><i style="background:${color}"></i><span>${txt}</span></div>`).join('');
    }
    function crearHexHosts(panel, p) {
        const cuerpo = el('div', 'hex-cuerpo', hexSvg() + '<div class="hex-ley"></div>');
        panel.appendChild(cuerpo);
        hexagonos.push({ p, panel });
        hacerAbrible(panel, p);
        return d => {
            const hs = Object.values(d.hosts).filter(h => enCarpeta(h, p.carpeta));
            const c = { up: 0, mant: 0, unreach: 0, down: 0 };
            hs.forEach(h => { if (h.mant) c.mant++; else if (c[h.estado] !== undefined) c[h.estado]++; });
            pintarAnillo(cuerpo, [[c.up, 'var(--ok)'], [c.mant, 'var(--dt)'], [c.unreach, 'var(--unknown)'], [c.down, 'var(--crit)']], hs.length, hs.length);
            cuerpo.querySelector('.hex-ley').innerHTML = leyenda([
                [c.up, 'var(--ok)', 'Up'], [c.mant, 'var(--dt)', 'Mant.'], [c.unreach, 'var(--unknown)', 'Inalc.'],
                [c.down, 'var(--crit)', 'Down'],
            ]);
        };
    }
    function crearHexServicios(panel, p) {
        const cuerpo = el('div', 'hex-cuerpo', hexSvg() + '<div class="hex-ley"></div>');
        panel.appendChild(cuerpo);
        hacerAbrible(panel, p);
        return d => {
            const c = d.servicios[p.carpeta] || { ok: 0, mant: 0, host_caido: 0, warn: 0, unknown: 0, crit: 0, total: 0 };
            pintarAnillo(cuerpo, [[c.ok, 'var(--ok)'], [c.mant, 'var(--dt)'], [c.host_caido, 'var(--pending)'], [c.warn, 'var(--warn)'], [c.unknown, 'var(--unknown)'], [c.crit, 'var(--crit)']], c.total, c.total);
            cuerpo.querySelector('.hex-ley').innerHTML = leyenda([
                [c.ok, 'var(--ok)', 'OK'], [c.mant, 'var(--dt)', 'Mant.'], [c.host_caido, 'var(--pending)', 'H. caído'],
                [c.warn, 'var(--warn)', 'Warn'], [c.unknown, 'var(--unknown)', 'Unkn.'], [c.crit, 'var(--crit)', 'Crit'],
            ]);
        };
    }

    /* ── Modal de detalle de un hexágono ──────────────────────────────────
       Al pulsar un hexágono se abre la lista de sus hosts (o servicios, en
       el de SSL) con IP, desde cuándo están en su estado, servicios con
       problemas y la salida del chequeo. Se refresca cada 15 s mientras está
       abierto y se cierra solo a los 2 min sin tocarlo, para que la TV no
       quede tapada si alguien lo deja abierto. */
    const md = {
        fondo: $('md'), titulo: $('md-t'), chips: $('md-chips'), buscar: $('md-buscar'),
        cuerpo: $('md-cuerpo'), pieI: $('md-pie-i'), pieD: $('md-pie-d'),
        p: null, datos: null, filtro: null, abiertos: new Set(), tRefresco: null, tCierre: null,
    };
    const ORDEN_HOST = { down: 0, unreach: 1, pend: 2, mant: 3, up: 4 };
    const ORDEN_SVC  = { crit: 0, unknown: 1, warn: 2, mant: 3, ok: 4 };
    const ETQ = { up: 'UP', down: 'DOWN', unreach: 'UNREACH', pend: 'PEND', mant: 'MANT',
                  ok: 'OK', warn: 'WARN', crit: 'CRIT', unknown: 'UNKN' };
    const NOMBRE_EST = { up: 'Up', down: 'Down', unreach: 'Unreachable', pend: 'Pendiente', mant: 'En mantención',
                         ok: 'OK', warn: 'Warning', crit: 'Critical', unknown: 'Unknown' };

    function hace(ts) {
        if (!ts) return '—';
        const s = Math.max(0, Date.now() / 1000 - ts);
        const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
        if (d >= 1) return `${d} d${h ? ` ${h} h` : ''}`;
        if (h >= 1) return `${h} h${m ? ` ${m} min` : ''}`;
        return m >= 1 ? `${m} min` : `${Math.floor(s)} s`;
    }
    const fecha = ts => ts ? new Date(ts * 1000).toLocaleString('es-CL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
    const enlaceCmk = host => URL_CHECKMK
        ? `<a href="${esc(URL_CHECKMK + 'index.py?start_url=' + encodeURIComponent('view.py?view_name=host&host=' + host))}" target="_blank" rel="noopener">Abrir en CheckMK ↗</a>` : '';

    function hacerAbrible(panel, p) {
        if (!URL_CARPETA) return;
        panel.classList.add('abre');
        panel.title = 'Ver el detalle';
        panel.addEventListener('click', () => abrirDetalle(p));
    }

    function abrirDetalle(p) {
        Object.assign(md, { p, datos: null, filtro: null, enlace: null, servidor: null });
        if (md.chart) { md.chart.destroy(); md.chart = null; }
        (md.chartsSrv || []).forEach(c => c.destroy()); md.chartsSrv = [];
        md.abiertos.clear();
        md.buscar.value = '';
        md.buscar.hidden = false;
        md.titulo.innerHTML = `${esc(p.titulo)}<small>CARPETA ${esc(p.carpeta)} · CHECKMK</small>`;
        md.chips.innerHTML = '';
        md.cuerpo.innerHTML = '<div class="md-vacio">cargando…</div>';
        md.pieI.textContent = md.pieD.textContent = '';
        md.fondo.hidden = false;
        cargarDetalle();
        clearInterval(md.tRefresco);
        md.tRefresco = setInterval(cargarDetalle, 15000);
        tocarDetalle();
    }
    function cerrarDetalle() {
        md.fondo.hidden = true;
        md.p = null;
        md.enlace = null;
        md.servidor = null;
        if (md.chart) { md.chart.destroy(); md.chart = null; }
        (md.chartsSrv || []).forEach(c => c.destroy()); md.chartsSrv = [];
        clearInterval(md.tRefresco);
        clearTimeout(md.tCierre);
    }
    function tocarDetalle() {
        clearTimeout(md.tCierre);
        md.tCierre = setTimeout(cerrarDetalle, 120000);
    }

    async function cargarDetalle() {
        const p = md.p;
        if (!p) return;
        try {
            const sep = URL_CARPETA.includes('?') ? '&' : '?';
            const r = await fetch(`${URL_CARPETA}${sep}carpeta=${encodeURIComponent(p.carpeta)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (md.p !== p) return;   // se cerró o se abrió otro mientras llegaba
            if (!d.ok) throw new Error(d.error || 'CheckMK no responde');
            md.datos = d;
            pintarDetalle();
        } catch (e) {
            if (md.p !== p) return;
            if (!md.datos) md.cuerpo.innerHTML = `<div class="md-vacio">No se pudo leer CheckMK: ${esc(e.message)}</div>`;
            md.pieD.textContent = 'sin conexión con CheckMK · reintentando';
        }
    }

    // Estado efectivo para ordenar y filtrar: la mantención manda sobre el estado.
    const estHost = h => h.mant && h.estado !== 'down' ? 'mant' : h.estado;
    const estSvc  = s => s.mant ? 'mant' : s.estado;

    function pintarDetalle() {
        const d = md.datos, esHosts = d.tipo === 'hosts';
        const lista = esHosts ? d.hosts : d.servicios;
        const est = esHosts ? estHost : estSvc;
        const orden = esHosts ? ORDEN_HOST : ORDEN_SVC;

        // Chips con el conteo por estado; pulsar uno filtra (y otra vez lo quita).
        const conteo = {};
        lista.forEach(x => { conteo[est(x)] = (conteo[est(x)] || 0) + 1; });
        md.chips.innerHTML = Object.keys(orden).filter(k => conteo[k]).map(k =>
            `<button type="button" class="md-chip md-${k} ${md.filtro === k ? 'on' : ''}" data-f="${k}"><i></i>${NOMBRE_EST[k]} <b>${conteo[k]}</b></button>`
        ).join('') + `<span class="md-chip" style="border:none">Total <b>${lista.length}</b></span>`;

        const q = md.buscar.value.trim().toLowerCase();
        const visibles = lista
            .filter(x => !md.filtro || est(x) === md.filtro)
            .filter(x => !q || (esHosts
                ? [x.nombre, x.alias, x.ip, x.salida, ...x.problemas.map(s => s.servicio)]
                : [x.host, x.servicio, x.salida]).some(v => v && String(v).toLowerCase().includes(q)))
            .sort((a, b) => (orden[est(a)] - orden[est(b)]) || String(esHosts ? a.nombre : a.servicio).localeCompare(esHosts ? b.nombre : b.servicio, 'es'));

        if (!visibles.length) {
            md.cuerpo.innerHTML = '<div class="md-vacio">Nada coincide con el filtro.</div>';
        } else if (esHosts) {
            md.cuerpo.innerHTML = `<table class="md-tabla"><thead><tr>
                <th>Estado</th><th>Host</th><th>IP</th><th title="Desde el último cambio de estado firme">En este estado</th>
                <th>Latencia</th><th>Servicios</th><th>Salida del chequeo</th></tr></thead><tbody>${visibles.map(filaHost).join('')}</tbody></table>`;
        } else {
            md.cuerpo.innerHTML = `<table class="md-tabla"><thead><tr>
                <th>Estado</th><th>Servicio</th><th>Host</th><th>En este estado</th><th>Salida</th></tr></thead><tbody>${visibles.map(filaSvc).join('')}</tbody></table>`;
        }

        md.pieI.textContent = esHosts ? 'Pulsa un host para ver sus servicios con problemas' : 'Pulsa un servicio para ver la salida completa';
        md.pieD.textContent = 'actualizado ' + new Date(d.generado * 1000).toLocaleTimeString('es-CL');
    }

    function filaHost(h) {
        const e = estHost(h), s = h.servicios, abierta = md.abiertos.has(h.nombre);
        const svc = `<span class="${s.crit ? 'crit' : ''}" title="Critical">${s.crit}</span><span class="${s.warn ? 'warn' : ''}" title="Warning">${s.warn}</span><span class="ok" title="OK">${s.ok}</span>`;
        const fila = `<tr class="md-fila md-${e} ${abierta ? 'md-abierta' : ''}" data-k="${esc(h.nombre)}">
            <td><span class="md-est">${ETQ[e]}</span></td>
            <td><b>${esc(h.nombre)}</b>${h.alias ? `<div class="tenue">${esc(h.alias)}</div>` : ''}</td>
            <td class="num">${esc(h.ip || '—')}</td>
            <td class="num" title="${esc(fecha(h.desde))}">${hace(h.desde)}</td>
            <td class="num">${h.rta_ms != null ? `${h.rta_ms} ms` : '<span class="tenue">—</span>'}${h.perdida ? ` <span class="tenue">· ${h.perdida}% pérdida</span>` : ''}</td>
            <td><span class="md-svc">${svc}</span></td>
            <td class="salida" title="${esc(h.salida)}">${esc(h.salida)}</td></tr>`;
        if (!abierta) return fila;
        const probs = h.problemas.length
            ? `<ul>${h.problemas.map(p => `<li class="md-${p.estado}"><span class="md-est">${ETQ[p.estado]}</span><b>${esc(p.servicio)}</b>
                   <span>${esc(p.salida)} <span class="tenue">· hace ${hace(p.desde)}</span></span></li>`).join('')}</ul>`
            : '<div class="tenue">Todos sus servicios están OK.</div>';
        return fila + `<tr class="md-det"><td colspan="7">
            <div class="md-salida">${esc(h.salida)}  ·  último chequeo hace ${hace(h.ultimo)}</div>
            ${probs}
            <div style="margin-top:.6em">${enlaceCmk(h.nombre)}</div></td></tr>`;
    }

    function filaSvc(s) {
        const e = estSvc(s), k = s.host + '|' + s.servicio, abierta = md.abiertos.has(k);
        const fila = `<tr class="md-fila md-${e} ${abierta ? 'md-abierta' : ''}" data-k="${esc(k)}">
            <td><span class="md-est">${ETQ[e]}</span></td>
            <td><b>${esc(s.servicio)}</b>${s.host_caido ? ' <span class="tenue">· host caído</span>' : ''}</td>
            <td>${esc(s.host)}</td>
            <td class="num" title="${esc(fecha(s.desde))}">${hace(s.desde)}</td>
            <td class="salida" title="${esc(s.salida)}">${esc(s.salida)}</td></tr>`;
        return abierta ? fila + `<tr class="md-det"><td colspan="5"><div class="md-salida">${esc(s.salida)}</div>${enlaceCmk(s.host)}</td></tr>` : fila;
    }

    md.chips.addEventListener('click', e => {
        const r = e.target.closest('[data-r]');   // rango del modal de un enlace
        if (r && md.enlace) { md.rango = +r.dataset.r; pintarCabEnlace(); cargarSerieEnlace(); return; }
        const b = e.target.closest('[data-f]');
        if (!b) return;
        md.filtro = md.filtro === b.dataset.f ? null : b.dataset.f;
        pintarDetalle();
    });
    md.cuerpo.addEventListener('click', e => {
        if (e.target.closest('a')) return;
        const f = e.target.closest('tr.md-fila');
        if (!f) return;
        const k = f.dataset.k;
        md.abiertos.has(k) ? md.abiertos.delete(k) : md.abiertos.add(k);
        pintarDetalle();
    });
    md.buscar.addEventListener('input', () => md.datos && pintarDetalle());
    $('md-x').addEventListener('click', cerrarDetalle);
    md.fondo.addEventListener('click', e => { if (e.target === md.fondo) cerrarDetalle(); });
    md.fondo.addEventListener('pointermove', tocarDetalle);
    md.fondo.addEventListener('keydown', tocarDetalle);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !md.fondo.hidden) cerrarDetalle(); });

    /* Medidor: arco de 270°, con el valor al centro. */
    function arco(r, desde, hasta) {
        const pt = a => { const rad = (a - 90) * Math.PI / 180; return [(60 + r * Math.cos(rad)).toFixed(2), (60 + r * Math.sin(rad)).toFixed(2)]; };
        const [x1, y1] = pt(desde), [x2, y2] = pt(hasta);
        return `M${x1} ${y1} A${r} ${r} 0 ${hasta - desde > 180 ? 1 : 0} 1 ${x2} ${y2}`;
    }
    function crearMedidor(panel, p) {
        const caja = el('div', 'medidor', `<svg viewBox="0 0 120 100" overflow="visible">
            <circle class="m-anillo" cx="60" cy="60" r="55" fill="none" stroke="var(--hud)" stroke-width=".8" stroke-dasharray="1.5 5" opacity=".8"/>
            <circle class="m-anillo inv" cx="60" cy="60" r="35" fill="none" stroke="var(--hud)" stroke-width=".6" stroke-dasharray="10 4 2 4" opacity=".45"/>
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
            decodificar(caja.querySelector('.m-txt'), v == null ? '—' : `${v.toFixed(2)}%`);
            caja.querySelector('.m-svc-t').textContent = svc;
        };
    }

    function crearNumero(panel, p) {
        const caja = el('div', 'numero', '<b>—</b><span></span>');
        panel.appendChild(caja);
        return d => {
            const v = (d.metricas[p.host] || {})[p.metrica];
            const [num, unidad] = v == null ? ['—', ''] : bits(v).split(' ');
            decodificar(caja.querySelector('b'), num);
            caja.querySelector('span').textContent = unidad;
        };
    }

    /* Efecto Matrix: al cambiar, un número muestra caracteres al azar que se
       van fijando de izquierda a derecha hasta quedar en el valor real. */
    const GLIFOS = '0123456789ABCDEF#%&@$';
    function decodificar(nodo, final) {
        if (nodo._final === final) return;
        const primera = nodo._final === undefined;
        nodo._final = final;
        clearInterval(nodo._dec);
        if (!conHud() || primera) { nodo.textContent = final; return; }

        const fijo = c => c === ' ' || c === '.' || c === '%' || c === '—';
        let paso = 0;
        const pasos = 14;
        nodo._dec = setInterval(() => {
            paso++;
            nodo.textContent = [...final].map((c, i) => fijo(c) || i < final.length * paso / pasos ? c : GLIFOS[Math.floor(Math.random() * GLIFOS.length)]).join('');
            if (paso >= pasos) { clearInterval(nodo._dec); nodo.textContent = final; }
        }, 45);
    }

    /* Gráficos históricos: se crean vacíos y se llenan con cargarGraficos(),
       que corre aparte del sondeo de estado (los datos avanzan por minuto). */
    const graficos = new Map();   // título → { p, caja, ley, chart }
    function crearGrafico(panel, p) {
        const caja = el('div', 'graf', '<canvas></canvas><div class="sin-datos">cargando…</div>');
        panel.appendChild(caja);
        const ley = p.clase === 'perdida' ? el('div', 'graf-ley') : null;
        if (ley) panel.appendChild(ley);
        const g = { p, caja, ley, chart: null };
        graficos.set(p.titulo, g);
        // Lienzo devuelto tras un reinicio de la tarjeta gráfica: se rehace al tiro (ver ajustarGraficos).
        caja.querySelector('canvas').addEventListener('contextrestored', () => g.chart && rehacerGrafico(g));
        return null;
    }

    // Colores de las series, como los pinta CheckMK (rgb del relleno y su opacidad sin HUD).
    const ESTILO_GRAFICO = {
        cpu:         [{ borde: '#9ad85a', rgb: '126,200,64', alfa: .55 }],
        ram:         [{ borde: '#5a9be6', rgb: '61,120,216', alfa: .7 }],
        ancho_banda: [{ borde: '#2ecc71', rgb: '46,204,113', alfa: .18 }, { borde: '#5a9be6', rgb: '61,120,216', alfa: .7 }],
        perdida:     [{ borde: '#e5b52a', rgb: '229,181,42', alfa: .25 }],
    };
    const conHud = () => document.body.classList.contains('hud');

    /* Relleno: con HUD, un degradado que se desvanece hacia el eje (en la
       salida de red, que va hacia abajo, se invierte); sin HUD, color plano. */
    function relleno(estilo, haciaAbajo) {
        return ctx => {
            const { chart } = ctx, area = chart.chartArea;
            if (!conHud() || !area) return `rgba(${estilo.rgb},${estilo.alfa})`;
            const g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
            const fuerte = `rgba(${estilo.rgb},.75)`, tenue = `rgba(${estilo.rgb},.04)`;
            g.addColorStop(0, haciaAbajo ? tenue : fuerte);
            g.addColorStop(1, haciaAbajo ? fuerte : tenue);
            return g;
        };
    }

    if (window.Chart) {
        Chart.register({
            // Brillo de neón en las líneas.
            id: 'brillo',
            beforeDatasetDraw(chart, args) {
                if (!conHud()) return;
                chart.ctx.save();
                chart.ctx.shadowColor = chart.data.datasets[args.index].borderColor;
                chart.ctx.shadowBlur = 10;
                chart._brillo = true;
            },
            afterDatasetDraw(chart) {
                if (chart._brillo) { chart.ctx.restore(); chart._brillo = false; }
            },
        }, {
            // Punto que late sobre el último valor: es un elemento de la página
            // (anima con CSS sin redibujar el gráfico) y se reubica en cada dibujo.
            id: 'pulso',
            afterDraw(chart) {
                const caja = chart.canvas.parentNode, ds = chart.data.datasets[0];
                let punto = caja.querySelector('.pulso');
                if (!punto) { punto = el('span', 'pulso'); caja.appendChild(punto); }
                let k = ds ? ds.data.length - 1 : -1;
                while (k >= 0 && ds.data[k] == null) k--;
                const p = k >= 0 ? chart.getDatasetMeta(0).data[k] : null;
                punto.hidden = !p;
                if (p) { punto.style.left = `${p.x}px`; punto.style.top = `${p.y}px`; punto.style.color = ds.borderColor; }
            },
        });
    }
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
        g.d = d;   // para poder rehacerlo si el navegador pierde el lienzo
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
            backgroundColor: relleno(ESTILO_GRAFICO[g.p.clase][i], g.p.clase === 'ancho_banda' && i === 1),
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
        ajustarGraficos();
    }

    /* Chart.js se redimensiona solo, pero lo hace en el siguiente cuadro de
       animación: si la pantalla cambia de tamaño mientras la página no se
       pinta (TV apagada o en reposo, pestaña de fondo), el aviso se pierde y
       el gráfico queda chico dentro de su marco. Se compara a mano y se corrige.

       Además, cuando la TV entra en reposo la tarjeta gráfica puede reiniciarse
       y el navegador devuelve el lienzo con su estado de fábrica: conserva el
       tamaño pero pierde la escala de pantalla (devicePixelRatio), y el gráfico
       se dibuja a la mitad, en la esquina. Chart.js no se entera, así que se
       revisa la escala del lienzo y, si no calza, el gráfico se arma de nuevo. */
    function rehacerGrafico(g) {
        g.chart.destroy();
        g.chart = null;
        if (g.d) pintarGrafico(g, g.d);
    }
    function ajustarGraficos() {
        graficos.forEach(g => {
            if (!g.chart) return;
            const cv = g.chart.canvas, ctx = g.chart.ctx;
            const escala = ctx.getTransform ? ctx.getTransform().a : g.chart.currentDevicePixelRatio;
            if (Math.abs(escala - g.chart.currentDevicePixelRatio) > .01) return rehacerGrafico(g);
            if (Math.abs(cv.clientWidth - g.caja.clientWidth) > 1 || Math.abs(cv.clientHeight - g.caja.clientHeight) > 1
                || g.chart.currentDevicePixelRatio !== (window.devicePixelRatio || 1)) g.chart.resize();
        });
    }
    setInterval(ajustarGraficos, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) ajustarGraficos(); });

    /* Ranking de técnicos: el podio del mes, que va alternando de país. Los
       datos vienen del GLPI de Unifrutti y cambian lento: se piden cada 5 min. */
    const NOMBRE_PAIS = { chile: 'Chile', peru: 'Perú' };
    const rankings = [];
    let datosRanking = null;
    function crearRanking(panel, p) {
        const caja = el('div', 'rkm-caja', '<div class="rkm"></div><div class="rkm-vacio">cargando…</div>');
        panel.appendChild(caja);
        const r = { p, panel, caja, i: 0, paises: (p.paises && p.paises.length) ? p.paises : ['chile', 'peru'] };
        rankings.push(r);
        if (r.paises.length > 1) {
            setInterval(() => {
                const podio = caja.querySelector('.rkm');
                podio.classList.add('cambia');
                setTimeout(() => { r.i = (r.i + 1) % r.paises.length; pintarRanking(r); podio.classList.remove('cambia'); }, 400);
            }, (p.cada || 20) * 1000);
        }
        return null;
    }
    function pintarRanking(r) {
        if (!datosRanking) return;
        const pais = r.paises[r.i % r.paises.length];
        const d = datosRanking.paises[pais] || { error: true, tecnicos: [] };
        r.panel.querySelector('h2').textContent = `${r.p.titulo} · ${NOMBRE_PAIS[pais] || pais} · ${datosRanking.mes}`;
        const podio = r.caja.querySelector('.rkm'), vacio = r.caja.querySelector('.rkm-vacio');
        vacio.hidden = d.tecnicos.length > 0;
        vacio.textContent = d.error ? 'sin conexión con GLPI' : 'sin tickets resueltos este mes';
        podio.innerHTML = [1, 0, 2].filter(k => d.tecnicos[k]).map(k => {
            const t = d.tecnicos[k], n = k + 1;
            return `<div class="rkm-plaza p${n}">
                ${n === 1 ? '<span class="rkm-corona">👑</span>' : ''}
                <div class="rkm-av" style="background:${esc(t.color)}">${esc(t.iniciales)}</div>
                <div class="rkm-nom">${esc(t.nombre)}</div>
                <div class="rkm-n">${t.total}<small>tickets</small></div>
                <div class="rkm-esc">${t.puesto}º</div>
            </div>`;
        }).join('');
    }
    async function cargarRanking() {
        if (!rankings.length || !URL_RANKING) return;
        try {
            const r = await fetch(URL_RANKING, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!r.ok) throw new Error(r.status);
            datosRanking = await r.json();
            rankings.forEach(pintarRanking);
        } catch (e) {
            // Se deja el último podio; si nunca cargó, queda «cargando…».
        }
    }

    /* Servicios críticos externos (Microsoft 365, SII, Claude, ChatGPT): el
       servidor los consulta con caché de 2 min; la pantalla pide cada minuto. */
    const cajasServicios = [];
    function crearServicios(panel) {
        const caja = el('div', 'svc-caja', '<div class="svc"></div>');
        panel.appendChild(caja);
        cajasServicios.push(caja.firstChild);
        return null;
    }
    async function cargarServicios() {
        if (!cajasServicios.length || !URL_SERVICIOS) return;
        try {
            const r = await fetch(URL_SERVICIOS, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!r.ok) throw new Error(r.status);
            const lista = await r.json();
            const html = lista.map(s => `<div class="svc-t svc-${esc(s.estado)}" title="${esc(s.nombre)}: ${esc(s.detalle)}">
                <b><i></i><em>${esc(s.nombre)}</em></b><span>${esc(s.detalle)}</span></div>`).join('');
            cajasServicios.forEach(c => { c.innerHTML = html; });
        } catch (e) {
            // Se deja el último estado visto.
        }
    }

    /* ── Enlaces ───────────────────────────────────────────────────────────
       Las series (pérdida y latencia de PING) llegan cada minuto desde
       cargarEnlaces(); si el host está caído lo dice el sondeo de estado
       cada 5 s, así que una caída sube la fila al tiro sin esperar al minuto.
       El orden lo decide la salud: caído, intermitente, con pérdida, estable. */
    const enl = { cajas: [], filas: new Map(), datos: [], hosts: {} };
    const ORDEN_ENL = { caido: 0, intermitente: 1, perdida: 2, sin_datos: 3, estable: 4 };
    const ETQ_ENL = { caido: 'CAÍDO', intermitente: 'INTERMITENTE', perdida: 'PÉRDIDA', sin_datos: 'SIN DATOS', estable: 'ESTABLE' };
    const COLOR_ENL = { caido: 'var(--crit)', intermitente: 'var(--warn)', perdida: 'var(--unknown)', sin_datos: 'var(--pending)', estable: 'var(--ok)' };

    function crearEnlaces(panel) {
        const caja = el('div', 'enl-caja', `<div class="enl">
            <div class="enl-cab"><span>Disponibilidad 4 h <span class="disp">—</span></span><span>Franja: últimas 4 h en bloques de 5 min · latido: latencia de la última hora</span><span class="cont"></span></div>
            <div class="enl-lista"></div></div>`);
        panel.appendChild(caja);
        enl.cajas.push(caja);
        // El estado UP/DOWN viene en cada sondeo (5 s).
        return d => {
            const antes = enl.datos.map(estadoEnlace).join();
            enl.hosts = d.hosts || {};
            if (enl.datos.map(estadoEnlace).join() !== antes) pintarEnlaces();
        };
    }

    function estadoEnlace(e) {
        const h = enl.hosts[e.host];
        if (h && (h.estado === 'down' || h.estado === 'unreach')) return 'caido';
        if (e.sin_datos) return 'sin_datos';
        const malos = e.bloques.slice(-12).filter(v => v > 0).length;
        return malos >= 3 ? 'intermitente' : malos >= 1 ? 'perdida' : 'estable';
    }
    const colorBloque = v => v == null ? '#24313a' : v === 0 ? '#0f5c43' : v < 20 ? 'var(--warn)' : v < 100 ? 'var(--unknown)' : 'var(--crit)';

    async function cargarEnlaces() {
        if (!enl.cajas.length || !URL_ENLACES) return;
        try {
            const r = await fetch(URL_ENLACES, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error);
            enl.datos = d.enlaces;
            pintarEnlaces();
        } catch (e) {
            // Se deja lo último dibujado; la conexión con CheckMK la avisa el sondeo.
        }
    }

    function pintarEnlaces() {
        enl.cajas.forEach(caja => {
            const lista = caja.querySelector('.enl-lista');
            const antes = new Map([...lista.children].map(f => [f.dataset.host, f.getBoundingClientRect().top]));

            enl.datos.forEach(e => {
                let f = lista.querySelector(`[data-host="${CSS.escape(e.host)}"]`);
                if (!f) {
                    f = el('div', 'enl-f', `<span class="enl-orbe"></span><span class="enl-nom"></span><span class="enl-etq"></span>
                        <canvas class="enl-pulso"></canvas><div class="enl-franja">${'<i></i>'.repeat(48)}</div>
                        <span class="enl-lat"></span><span class="enl-pl"></span>`);
                    f.dataset.host = e.host;
                    f.addEventListener('click', () => abrirEnlace(f.dataset.host));
                    lista.appendChild(f);
                }
                const est = estadoEnlace(e);
                if (f.dataset.e === 'caido' && est !== 'caido') { f.classList.remove('recupera'); void f.offsetWidth; f.classList.add('recupera'); }
                f.dataset.e = est;
                f.title = `${e.nombre} · ${e.host}${e.ip ? ' · ' + e.ip : ''} — ver detalle`;
                f.querySelector('.enl-nom').textContent = e.nombre;
                f.querySelector('.enl-etq').textContent = ETQ_ENL[est];
                f.querySelectorAll('.enl-franja i').forEach((c, i) => {
                    const v = e.bloques[i];
                    c.style.setProperty('--b', colorBloque(v));
                    c.title = v == null ? 'sin datos' : `${v}% de pérdida`;
                });
                f.querySelector('.enl-lat').innerHTML = est === 'caido' || e.lat_ult == null ? '<span style="color:var(--crit)">—</span>' : `${Math.round(e.lat_ult)}<small>ms</small>`;
                const malos = e.bloques.slice(-12).filter(v => v > 0), pl = f.querySelector('.enl-pl');
                pl.classList.toggle('mal', est === 'caido' || malos.length > 0);
                pl.textContent = est === 'caido' ? `sin respuesta · ${hace(enl.hosts[e.host]?.cambio)}`
                    : malos.length ? `${malos.length} con pérdida en 1 h · máx ${Math.max(...malos)}%`
                    : e.sin_datos ? 'CheckMK sin datos de PING' : '0% de pérdida en 1 h';
            });

            // Reordenar por salud, con animación: el que falla sube.
            const orden = [...enl.datos].sort((a, b) => (ORDEN_ENL[estadoEnlace(a)] - ORDEN_ENL[estadoEnlace(b)])
                || (b.bloques.slice(-12).reduce((s, v) => s + (v || 0), 0) - a.bloques.slice(-12).reduce((s, v) => s + (v || 0), 0)));
            orden.forEach(e => lista.appendChild(lista.querySelector(`[data-host="${CSS.escape(e.host)}"]`)));
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                [...lista.children].forEach(f => {
                    const d = (antes.get(f.dataset.host) ?? f.getBoundingClientRect().top) - f.getBoundingClientRect().top;
                    if (Math.abs(d) > 2) f.animate([{ transform: `translateY(${d}px)` }, { transform: 'none' }], { duration: 700, easing: 'cubic-bezier(.2,.8,.2,1)' });
                });
            }

            // Cabecera: disponibilidad media y conteo por estado.
            const disp = enl.datos.map(e => e.disponible).filter(v => v != null);
            caja.querySelector('.disp').textContent = disp.length ? `${(disp.reduce((a, b) => a + b, 0) / disp.length).toFixed(2).replace('.', ',')}%` : '—';
            const n = {}; enl.datos.forEach(e => { const s = estadoEnlace(e); n[s] = (n[s] || 0) + 1; });
            caja.querySelector('.cont').innerHTML = Object.keys(ORDEN_ENL).filter(k => n[k])
                .map(k => `<span style="--c:${COLOR_ENL[k]}"><i></i>${ETQ_ENL[k].charAt(0) + ETQ_ENL[k].slice(1).toLowerCase()} <b>${n[k]}</b></span>`).join('');
        });
        pintarPulsos();
    }

    /* Latido: la latencia de la última hora como línea de monitor, con el
       último punto marcado. Los minutos sin respuesta quedan como cortes rojos. */
    function pintarPulsos() {
        const colores = { caido: '#ff3232', intermitente: '#ffd703', perdida: '#ff8400', sin_datos: '#838383', estable: '#13d389' };
        enl.cajas.forEach(caja => caja.querySelectorAll('.enl-f').forEach(f => {
            const e = enl.datos.find(x => x.host === f.dataset.host), cv = f.querySelector('canvas');
            if (!e || !cv.offsetWidth) return;
            const dpr = devicePixelRatio || 1, W = cv.offsetWidth, H = cv.offsetHeight;
            cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
            const x = cv.getContext('2d'); x.setTransform(dpr, 0, 0, dpr, 0, 0);
            const col = colores[estadoEnlace(e)], vals = e.latencia || [];
            if (vals.length < 2) return;
            const val = vals.filter(v => v != null), max = Math.max(1, ...val) * 1.3, min = Math.min(...val, max) * .8, paso = W / (vals.length - 1);
            const py = v => H - 3 - (v - min) / (max - min || 1) * (H - 6);
            x.lineWidth = 1.4; x.strokeStyle = col; x.shadowColor = col; x.shadowBlur = conHud() ? 6 : 0;
            x.beginPath(); let abierto = false;
            vals.forEach((v, i) => {
                if (v == null) {
                    if (abierto) x.stroke(); abierto = false;
                    // Un minuto sin dato al final suele ser el que CheckMK aún no consolida: no se marca.
                    if (i < vals.length - 2) { x.save(); x.shadowBlur = 0; x.fillStyle = '#ff3232'; x.fillRect(i * paso - 1, H - 6, 2, 5); x.restore(); }
                    x.beginPath(); return;
                }
                abierto ? x.lineTo(i * paso, py(v)) : x.moveTo(i * paso, py(v)); abierto = true;
            });
            if (abierto) x.stroke();
            x.shadowBlur = 0;
            const k = vals.map(v => v != null).lastIndexOf(true);
            if (k >= 0) { x.fillStyle = col; x.beginPath(); x.arc(Math.min(W - 3, k * paso), py(vals[k]), 2.4, 0, 7); x.fill(); }
        }));
    }
    let tPulsos;
    window.addEventListener('resize', () => { clearTimeout(tPulsos); tPulsos = setTimeout(pintarPulsos, 300); });

    /* Modal de un enlace: KPIs, gráfico de latencia y pérdida, y cortes. */
    async function abrirEnlace(host) {
        const e = enl.datos.find(x => x.host === host);
        if (!e || !URL_ENLACE) return;
        clearInterval(md.tRefresco);
        Object.assign(md, { p: null, datos: null, filtro: null, servidor: null, enlace: host, rango: md.enlace === host ? md.rango : 4 });
        (md.chartsSrv || []).forEach(c => c.destroy()); md.chartsSrv = [];
        md.buscar.hidden = true;
        md.cuerpo.innerHTML = `<div class="enl-kpis"></div><div class="enl-graf"><canvas></canvas></div><div class="enl-cortes"></div>`;
        md.fondo.hidden = false;
        pintarCabEnlace();
        await cargarSerieEnlace();
        md.tRefresco = setInterval(cargarSerieEnlace, 60000);
        tocarDetalle();
    }
    function pintarCabEnlace() {
        const e = enl.datos.find(x => x.host === md.enlace); if (!e) return;
        const est = estadoEnlace(e);
        md.titulo.innerHTML = `<span style="color:${COLOR_ENL[est]}">●</span> ${esc(e.nombre)} <span class="enl-etq" style="--c:${COLOR_ENL[est]}; font-size:.5em; vertical-align:middle">${ETQ_ENL[est]}</span>
            <small>${esc(e.host)}${e.ip ? ' · ' + esc(e.ip) : ''}${e.proveedor ? ' · ' + esc(e.proveedor) : ''}${e.desde ? ' · en este estado hace ' + hace(e.desde) : ''}</small>`;
        md.chips.innerHTML = [[4, '4 H'], [24, '24 H'], [168, '7 D']]
            .map(([h, t]) => `<button type="button" class="md-rango ${md.rango === h ? 'on' : ''}" data-r="${h}">${t}</button>`).join('');
    }
    async function cargarSerieEnlace() {
        const host = md.enlace, rango = md.rango;
        if (!host) return;
        try {
            const r = await fetch(`${URL_ENLACE}${URL_ENLACE.includes('?') ? '&' : '?'}host=${encodeURIComponent(host)}&horas=${rango}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (md.enlace !== host || md.rango !== rango) return;
            if (!d.ok) throw new Error(d.error || 'CheckMK no responde');
            pintarSerieEnlace(d);
            md.pieI.textContent = 'Barras: pérdida de paquetes · línea: latencia';
            md.pieD.textContent = `paso de ${Math.round(d.paso / 60)} min · actualizado ${new Date().toLocaleTimeString('es-CL')}`;
        } catch (err) {
            if (md.enlace !== host) return;
            md.pieD.textContent = 'sin conexión con CheckMK · reintentando';
        }
    }
    function pintarSerieEnlace(d) {
        const e = enl.datos.find(x => x.host === md.enlace);
        // Los últimos puntos suelen venir vacíos (sin consolidar): se recortan.
        let n = d.pl.length; while (n > 0 && d.pl[n - 1] == null && (d.rta[n - 1] ?? null) == null) n--;
        const pl = d.pl.slice(0, n), rta = d.rta.slice(0, n);
        const plv = pl.filter(v => v != null), rtv = rta.filter(v => v != null);
        const largo = d.horas > 24;
        const etiquetas = pl.map((_, i) => {
            const t = new Date((d.inicio + i * d.paso) * 1000);
            return largo ? t.toLocaleString('es-CL', { weekday: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : t.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
        });
        md.cuerpo.querySelector('.enl-kpis').innerHTML = [
            ['Disponibilidad', plv.length ? `${(100 - plv.reduce((a, b) => a + b, 0) / plv.length).toFixed(2).replace('.', ',')}<small>%</small>` : '—'],
            ['Latencia ahora', e?.lat_ult != null && estadoEnlace(e) !== 'caido' ? `${Math.round(e.lat_ult)}<small> ms</small>` : '—'],
            ['Latencia media', rtv.length ? `${Math.round(rtv.reduce((a, b) => a + b, 0) / rtv.length)}<small> ms</small>` : '—'],
            ['Puntos con pérdida', `${plv.filter(v => v > 0).length}<small> de ${plv.length}</small>`],
        ].map(([k, v]) => `<div><small>${k}</small><b>${v}</b></div>`).join('');

        const cv = md.cuerpo.querySelector('.enl-graf canvas');
        if (md.chart) md.chart.destroy();
        md.chart = window.Chart ? new Chart(cv, {
            data: { labels: etiquetas, datasets: [
                { type: 'line', label: 'Latencia (ms)', data: rta, yAxisID: 'ms', borderColor: '#3fd6d0', backgroundColor: 'rgba(63,214,208,.15)', fill: 'origin',
                  borderWidth: 1.6, pointRadius: 0, tension: .25, spanGaps: false, order: 1 },
                { type: 'bar', label: 'Pérdida (%)', data: pl, yAxisID: 'pl', order: 2, barPercentage: 1, categoryPercentage: .9,
                  backgroundColor: pl.map(v => v >= 100 ? 'rgba(255,50,50,.85)' : v >= 20 ? 'rgba(255,132,0,.8)' : 'rgba(255,215,3,.75)') },
            ] },
            options: {
                animation: { duration: 500 }, responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { color: '#c3cbd1', boxWidth: 12 } } },
                scales: {
                    x: { ticks: { color: '#8a96a1', maxTicksLimit: 8, maxRotation: 0 }, grid: { color: 'rgba(255,255,255,.05)' } },
                    ms: { position: 'left', beginAtZero: true, ticks: { color: '#3fd6d0', callback: v => `${v} ms` }, grid: { color: 'rgba(255,255,255,.06)' } },
                    pl: { position: 'right', min: 0, max: 100, ticks: { color: '#ffd703', callback: v => `${v}%` }, grid: { display: false } },
                },
            },
        }) : null;

        // Cortes: tramos seguidos con pérdida, del más reciente al más antiguo.
        const cortes = []; let cur = null;
        pl.forEach((v, i) => {
            if (v > 0) { if (!cur) cur = { ini: d.inicio + i * d.paso, n: 0, max: 0 }; cur.n++; cur.max = Math.max(cur.max, v); }
            else if (v === 0 && cur) { cortes.push(cur); cur = null; }
        });
        if (cur) { cur.vigente = true; cortes.push(cur); }
        md.cuerpo.querySelector('.enl-cortes').innerHTML = '<h4>CORTES Y PÉRDIDAS DEL PERIODO</h4>' + (cortes.length
            ? cortes.reverse().slice(0, 10).map(c => {
                const t = new Date(c.ini * 1000);
                return `<div class="enl-corte"><span>${t.toLocaleString('es-CL', largo ? { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' } : { hour: '2-digit', minute: '2-digit' })}</span>
                    <div class="tira" style="width:${Math.max(4, Math.round(c.max))}%"></div>
                    <span>${c.vigente ? '<b style="color:var(--crit)">en curso</b>' : Math.round(c.n * d.paso / 60) + ' min'}</span><span>${Math.round(c.max)}% máx</span></div>`;
            }).join('') + (cortes.length > 10 ? `<div class="tenue" style="color:var(--muted)">y ${cortes.length - 10} más</div>` : '')
            : '<div style="color:var(--muted)">Sin pérdida en el periodo.</div>');
    }

    /* ── Mesa de ayuda (GLPI) ───────────────────────────────────────────────
       Cifras de la cola y del día, los últimos tickets y una línea de
       eventos. Cada lectura (cada 30 s) se compara con la anterior: la cifra
       que cambia destella y muestra «+1»/«−1», un ticket nuevo entra
       deslizándose en amarillo y uno que se soluciona destella en verde. */
    const MESA_EST = { 1: ['NUEVO', 'var(--warn)'], 2: ['ASIGNADO', 'var(--dt)'], 3: ['PLANIFICADO', 'var(--dt)'],
                       4: ['EN ESPERA', 'var(--unknown)'], 5: ['SOLUCIONADO', 'var(--ok)'], 6: ['CERRADO', 'var(--pending)'] };
    // [clave, etiqueta, color de la franja, color del destello al subir, al bajar]
    const MESA_KPI = [
        ['total', 'En cola', 'var(--hud)', 'var(--warn)', 'var(--ok)'],
        ['nuevos', 'Nuevos', 'var(--warn)', 'var(--warn)', 'var(--ok)'],
        ['asignados', 'Asignados', 'var(--dt)', 'var(--dt)', 'var(--ok)'],
        ['espera', 'En espera', 'var(--unknown)', 'var(--unknown)', 'var(--ok)'],
        ['vencidos', 'Vencidos', 'var(--crit)', 'var(--crit)', 'var(--ok)'],
        ['creados', 'Creados hoy', 'var(--warn)', 'var(--warn)', 'var(--muted)'],
        ['solucionados', 'Solucionados hoy', 'var(--ok)', 'var(--ok)', 'var(--muted)'],
        ['cerrados', 'Cerrados hoy', 'var(--pending)', 'var(--ok)', 'var(--muted)'],
    ];
    const mesa = { cajas: [], antes: null, eventos: [] };

    function crearMesa(panel) {
        const caja = el('div', 'mesa-c', `<div class="mesa-k">${MESA_KPI.map(([k, t, c]) =>
                `<div class="mk" data-k="${k}" style="--c:${c}"><small>${t}</small><b>—</b><span>&nbsp;</span></div>`).join('')}</div>
            <div class="mesa-t"><span>Últimos tickets</span><span class="act"></span></div>
            <div class="mesa-l"></div>
            <div class="mesa-ev"><span class="rot">EVENTOS</span><span class="evs">esperando novedades…</span></div>`);
        panel.appendChild(caja);
        mesa.cajas.push(caja);
        return null;
    }

    async function cargarMesa() {
        if (!mesa.cajas.length || !URL_MESA) return;
        try {
            const r = await fetch(URL_MESA, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error);
            pintarMesa(d);
        } catch (e) {
            mesa.cajas.forEach(c => { c.querySelector('.act').textContent = 'sin conexión con GLPI'; });
        }
    }

    // Cuenta del valor anterior al nuevo, para que el cambio se vea.
    function contar(nodo, desde, hasta) {
        if (desde == null || desde === hasta || matchMedia('(prefers-reduced-motion: reduce)').matches) { nodo.textContent = hasta; return; }
        const t0 = performance.now(), dur = 900;
        const paso = t => { const k = Math.min(1, (t - t0) / dur); nodo.textContent = Math.round(desde + (hasta - desde) * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(paso); };
        requestAnimationFrame(paso);
    }

    function eventoMesa(txt) {
        mesa.eventos.unshift({ t: new Date(), txt });
        mesa.eventos.length = Math.min(mesa.eventos.length, 4);
        mesa.cajas.forEach(c => c.querySelector('.evs').innerHTML = mesa.eventos.map((e, i) =>
            `<span class="ev ${i === 0 ? 'nuevo' : ''}">${e.t.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })} <b>${esc(e.txt)}</b></span>`).join(' · '));
    }

    function pintarMesa(d) {
        const v = { ...d.cola, ...d.hoy }, a = mesa.antes;
        const subt = {
            total: `CL ${d.cola.por_pais.chile} · PE ${d.cola.por_pais.peru}`,
            nuevos: `${d.cola.sin_tecnico} sin técnico`,
            asignados: d.cola.planificados ? `+${d.cola.planificados} planificados` : 'en curso',
            espera: 'esperando al usuario',
            vencidos: 'fuera de plazo',
            creados: 'desde las 00:00',
            solucionados: 'esperan aprobación',
            cerrados: 'aprobados por el usuario',
        };
        mesa.cajas.forEach(caja => {
            MESA_KPI.forEach(([k, , , fxMas, fxMenos]) => {
                const t = caja.querySelector(`[data-k="${k}"]`), b = t.querySelector('b');
                contar(b, a ? a.v[k] : null, v[k]);
                t.querySelector('span').textContent = subt[k];
                t.classList.toggle('alerta', k === 'vencidos' ? v[k] > 0 : k === 'nuevos' && d.cola.sin_tecnico > 0);
                if (a && a.v[k] !== v[k]) {
                    const sube = v[k] > a.v[k];
                    t.style.setProperty('--fx', sube ? fxMas : fxMenos);
                    t.classList.remove('mas', 'menos'); void t.offsetWidth; t.classList.add(sube ? 'mas' : 'menos');
                    t.querySelector('.delta')?.remove();
                    t.insertAdjacentHTML('beforeend', `<span class="delta">${sube ? '+' : '−'}${Math.abs(v[k] - a.v[k])}</span>`);
                }
            });

            const lista = caja.querySelector('.mesa-l');
            lista.innerHTML = d.ultimos.map(t => {
                const [txt, col] = MESA_EST[t.estado] || ['—', 'var(--muted)'];
                const previo = a?.tickets.get(t.id);
                const clase = !a ? '' : previo == null ? 'nuevo' : previo !== t.estado ? 'cambia' : '';
                return `<div class="mt ${clase}" style="--c:${col}"><span class="id">#${t.id}</span>
                    <div class="ti"><b title="${esc(t.titulo)}">${esc(t.titulo)}</b><span>${esc(t.categoria || 'Sin categoría')} · ${t.tecnico ? esc(t.tecnico) : '<span style="color:var(--warn);display:inline">sin asignar</span>'}</span></div>
                    <span class="es">${txt}</span><span class="ha">hace ${hace(t.fecha)}</span></div>`;
            }).join('');
            caja.querySelector('.act').textContent = 'actualizado ' + new Date(d.generado * 1000).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        });

        // Eventos: tickets que entran y tickets que cambian de estado.
        if (a) {
            [...d.ultimos].reverse().forEach(t => {
                const previo = a.tickets.get(t.id);
                if (previo == null && t.id > a.maxId) eventoMesa(`#${t.id} nuevo · ${t.titulo}`);
                else if (previo != null && previo !== t.estado) eventoMesa(`#${t.id} ${(MESA_EST[t.estado] || ['cambió'])[0].toLowerCase()}${t.tecnico ? ' · ' + t.tecnico : ''}`);
            });
            if (v.solucionados > a.v.solucionados && !d.ultimos.some(t => a.tickets.get(t.id) != null && a.tickets.get(t.id) !== t.estado && t.estado === 5)) {
                eventoMesa(`${v.solucionados - a.v.solucionados} ticket${v.solucionados - a.v.solucionados > 1 ? 's' : ''} solucionado${v.solucionados - a.v.solucionados > 1 ? 's' : ''}`);
            }
        }
        mesa.antes = { v, tickets: new Map(d.ultimos.map(t => [t.id, t.estado])), maxId: Math.max(0, ...d.ultimos.map(t => t.id)) };
    }

    /* ── Servidor ───────────────────────────────────────────────────────────
       Una tarjeta por host con lo que antes eran 9 paneles: CPU y RAM (anillo
       con el valor actual y gráfico de 8 h con los últimos 30 min
       resaltados) y red (subida y bajada, con la última hora en espejo).
       Los valores actuales llegan con el sondeo (5 s); las series, con
       cargarGraficos() cada minuto. Al pulsar se abren los gráficos grandes. */
    const srv = { tarjetas: [], series: {}, metricas: {}, hosts: {} };
    const umbralColor = v => v == null ? 'var(--pending)' : v >= 90 ? 'var(--crit)' : v >= 80 ? 'var(--warn)' : 'var(--ok)';
    const COLOR_CANVAS = { cpu: '#9ad85a', ram: '#5a9be6', sub: '#3fd6d0', baj: '#5a9be6', warn: '#ffd703', crit: '#ff3232' };

    function crearServidor(panel, p) {
        panel.classList.add('abre', 'srv');
        panel.title = 'Ver los gráficos de detalle';
        panel.addEventListener('click', () => abrirServidor(p));
        const vital = m => `<div class="srv-v" data-m="${m}">
            <div class="srv-g"><svg viewBox="0 0 68 68"><circle class="pista" cx="34" cy="34" r="28"/><circle class="valor" cx="34" cy="34" r="28"/></svg>
                <div class="srv-gv"><b>—</b><small>${m.toUpperCase()}</small></div></div>
            <div class="srv-s"><div class="srv-tt"><span>${m.toUpperCase()} · 8 h</span><span class="st"></span></div><div class="srv-cv"><canvas></canvas></div></div></div>`;
        panel.insertAdjacentHTML('beforeend', `<div class="srv-meta"><span class="up">uptime —</span><span class="wc"></span></div>
            ${vital('cpu')}${vital('ram')}
            <div class="srv-red"><div class="srv-n"><small>↑ SUBIDA</small><b class="sub">—</b></div><div class="srv-n"><small>↓ BAJADA</small><b class="baj">—</b></div><div class="srv-cv"><canvas></canvas></div></div>`);
        const t = { p, panel };
        srv.tarjetas.push(t);   // la caída del host ya la avisa su fila en la tabla de servidores
        return d => {
            srv.metricas = d.metricas || {}; srv.hosts = d.hosts || {};
            pintarValoresServidor(t);
        };
    }

    function pintarValoresServidor(t) {
        const m = srv.metricas[t.p.host] || {}, h = srv.hosts[t.p.host] || {};
        t.panel.dataset.e = h.estado === 'down' || h.estado === 'unreach' ? 'caido' : (h.cr ? 'crit' : h.wa ? 'warn' : 'ok');
        t.panel.querySelector('.up').textContent = m.uptime ? `uptime ${uptime(m.uptime)}` : 'uptime —';
        t.panel.querySelector('.wc').innerHTML = h.estado === 'down' ? '<span class="srv-pill" style="--c:var(--crit)">CAÍDO</span>'
            : (h.cr ? `<span class="srv-pill" style="--c:var(--crit)">${h.cr} CRÍTICO${h.cr > 1 ? 'S' : ''}</span>` : '')
            + (h.wa ? `<span class="srv-pill" style="--c:var(--warn)">${h.wa} AVISO${h.wa > 1 ? 'S' : ''}</span>` : '')
            + (!h.cr && !h.wa && h.estado ? '<span class="srv-pill" style="--c:var(--ok)">OK</span>' : '');
        [['cpu', m.cpu], ['ram', m.ram]].forEach(([k, v]) => {
            const box = t.panel.querySelector(`[data-m="${k}"]`), r = 28, L = 2 * Math.PI * r, arco = L * .75;
            const val = box.querySelector('.valor');
            val.style.strokeDasharray = `${arco * Math.max(0, Math.min(100, v ?? 0)) / 100} ${L}`;
            val.style.stroke = val.style.color = umbralColor(v);
            box.querySelector('.pista').style.strokeDasharray = `${arco} ${L}`;
            box.querySelector('.srv-gv b').textContent = v == null ? '—' : `${Math.round(v)}%`;
        });
        t.panel.querySelector('.sub').innerHTML = m.out_bps == null ? '—' : bits(m.out_bps).replace(/ (\S+)$/, '<em> $1</em>');
        t.panel.querySelector('.baj').innerHTML = m.in_bps == null ? '—' : bits(m.in_bps).replace(/ (\S+)$/, '<em> $1</em>');
    }

    // Serie de un servidor ya en las unidades de la pantalla (RAM en % si se conoce el total).
    function serieServidor(host, clave) {
        const g = srv.series[`srv:${host}:${clave}`];
        if (!g || !g.series.length) return null;
        let datos = g.series[0].datos.slice();
        while (datos.length && datos[datos.length - 1] == null) datos.pop();
        if (clave.startsWith('ram')) {
            const m = srv.metricas[host] || {};
            const total = m.ram_total || (m.ram_bytes && m.ram ? m.ram_bytes / (m.ram / 100) : null);
            datos = datos.map(v => v == null ? null : total ? v / total * 100 : v / 1073741824);
            return { datos, pct: !!total, inicio: g.inicio, paso: g.paso };
        }
        return { datos, pct: true, inicio: g.inicio, paso: g.paso };
    }

    function lienzoSrv(cv) {
        const caja = cv.parentNode, dpr = devicePixelRatio || 1, W = caja.clientWidth, H = caja.clientHeight;
        if (!W || !H) return null;
        cv.style.width = W + 'px'; cv.style.height = H + 'px';
        cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
        const x = cv.getContext('2d'); x.setTransform(dpr, 0, 0, dpr, 0, 0); x.clearRect(0, 0, W, H);
        return { x, W, H };
    }

    // 8 h en tenue y los últimos 30 min resaltados, en el mismo gráfico.
    function dibujarDosEscalas(cv, s, color) {
        const L = lienzoSrv(cv); if (!L || !s || s.datos.length < 2) return; const { x, W, H } = L;
        const val = s.datos.filter(v => v != null);
        const max = s.pct ? 100 : Math.max(1, ...val) * 1.15, py = v => H - 2 - v / max * (H - 6), px = i => i / (s.datos.length - 1) * W;
        const corte = Math.max(0, s.datos.length - Math.round(1800 / (s.paso || 60)));
        x.fillStyle = 'rgba(255,255,255,.045)'; x.fillRect(px(corte), 0, W - px(corte), H);
        x.strokeStyle = 'rgba(255,255,255,.06)'; x.lineWidth = 1;
        [.25, .5, .75].forEach(k => { x.beginPath(); x.moveTo(0, py(max * k)); x.lineTo(W, py(max * k)); x.stroke(); });
        const camino = (desde) => { x.beginPath(); let a = false; s.datos.forEach((v, i) => { if (i < desde) return; if (v == null) { a = false; return; } a ? x.lineTo(px(i), py(v)) : x.moveTo(px(i), py(v)); a = true; }); };
        const g = x.createLinearGradient(0, 0, 0, H); g.addColorStop(0, color + '55'); g.addColorStop(1, color + '05');
        x.beginPath(); x.moveTo(0, H);
        s.datos.forEach((v, i) => x.lineTo(px(i), py(v ?? 0)));
        x.lineTo(W, H); x.closePath(); x.fillStyle = g; x.fill();
        camino(0); x.strokeStyle = color + '70'; x.lineWidth = 1; x.stroke();
        camino(corte); x.strokeStyle = color; x.lineWidth = 2; x.shadowColor = color; x.shadowBlur = conHud() ? 8 : 0; x.stroke(); x.shadowBlur = 0;
        const k = s.datos.length - 1; if (s.datos[k] != null) { x.fillStyle = color; x.beginPath(); x.arc(W - 2.5, py(s.datos[k]), 2.8, 0, 7); x.fill(); }
        x.fillStyle = '#8a96a1'; x.font = `${Math.min(11, Math.max(9, H * .16))}px ${getComputedStyle(document.body).fontFamily}`; x.textAlign = 'right'; x.fillText('30 min', W - 6, 12);
    }

    // Red en espejo: salida arriba, entrada abajo, en escala logarítmica.
    function dibujarEspejo(cv, sub, baj) {
        const L = lienzoSrv(cv); if (!L || !sub || !baj) return; const { x, W, H } = L, m = H / 2;
        const lg = v => Math.log10(1 + (v || 0)), max = Math.max(1, ...sub.map(lg), ...baj.map(lg)), n = Math.max(sub.length, baj.length), px = i => i / Math.max(1, n - 1) * W;
        x.strokeStyle = 'rgba(255,255,255,.14)'; x.beginPath(); x.moveTo(0, m); x.lineTo(W, m); x.stroke();
        [[sub, COLOR_CANVAS.sub, -1], [baj, COLOR_CANVAS.baj, 1]].forEach(([s, col, dir]) => {
            const y = v => m + dir * lg(v) / max * (m - 2);
            x.beginPath(); x.moveTo(0, m); s.forEach((v, i) => x.lineTo(px(i), y(v))); x.lineTo(px(s.length - 1), m); x.closePath(); x.fillStyle = col + '40'; x.fill();
            x.beginPath(); s.forEach((v, i) => i ? x.lineTo(px(i), y(v)) : x.moveTo(px(i), y(v))); x.strokeStyle = col; x.lineWidth = 1.4; x.stroke();
        });
    }

    function pintarServidores() {
        srv.tarjetas.forEach(t => {
            const host = t.p.host;
            [['cpu', COLOR_CANVAS.cpu], ['ram', COLOR_CANVAS.ram]].forEach(([k, col]) => {
                const box = t.panel.querySelector(`[data-m="${k}"]`), s = serieServidor(host, `${k}480`), s30 = serieServidor(host, `${k}30`);
                const v30 = s30 ? s30.datos.filter(v => v != null) : [], v8 = s ? s.datos.filter(v => v != null) : [];
                const u = s && s.pct ? '%' : ' GiB', f = v => s && s.pct ? Math.round(v) : v.toFixed(1);
                box.querySelector('.st').innerHTML = v8.length ? `pico <b>${f(Math.max(...v8))}${u}</b> · prom 30 min <b>${v30.length ? f(v30.reduce((a, b) => a + b, 0) / v30.length) : '—'}${u}</b>` : 'sin datos';
                dibujarDosEscalas(box.querySelector('canvas'), s, col);
            });
            const bw = srv.series[`srv:${host}:bw60`];
            const serie = nombre => bw?.series.find(x => x.nombre === nombre)?.datos.map(v => (v || 0) * 8) || null;
            dibujarEspejo(t.panel.querySelector('.srv-red canvas'), serie('Salida'), serie('Entrada'));
        });
    }
    let tSrv;
    window.addEventListener('resize', () => { clearTimeout(tSrv); tSrv = setTimeout(pintarServidores, 300); });

    /* Modal del servidor: los gráficos grandes de antes, en un solo lugar. */
    function abrirServidor(p) {
        clearInterval(md.tRefresco);
        Object.assign(md, { p: null, enlace: null, servidor: p.host });
        if (md.chart) { md.chart.destroy(); md.chart = null; }
        md.buscar.hidden = true; md.chips.innerHTML = '';
        md.titulo.innerHTML = `${esc(p.titulo)}<small>SERVIDOR · CHECKMK</small>`;
        md.cuerpo.innerHTML = `<div class="enl-kpis srv-kpis"></div><div class="srv-graficos">
            ${[['cpu30', 'CPU · 30 minutos'], ['cpu480', 'CPU · 8 horas'], ['ram30', 'RAM · 30 minutos'], ['ram480', 'RAM · 8 horas']]
                .map(([k, t]) => `<figure><figcaption>${t}</figcaption><div class="srv-mg"><canvas data-k="${k}"></canvas></div></figure>`).join('')}
            <figure class="ancho"><figcaption>Ancho de banda · 60 minutos · Interface 1</figcaption><div class="srv-mg"><canvas data-k="bw60"></canvas></div></figure></div>`;
        md.fondo.hidden = false;
        pintarModalServidor();
        md.pieI.textContent = 'Los gráficos se actualizan cada minuto';
        tocarDetalle();
    }
    function pintarModalServidor() {
        const host = md.servidor, m = srv.metricas[host] || {}, h = srv.hosts[host] || {};
        const c8 = serieServidor(host, 'cpu480'), v8 = c8 ? c8.datos.filter(v => v != null) : [];
        md.cuerpo.querySelector('.srv-kpis').innerHTML = [
            ['CPU ahora', m.cpu == null ? '—' : `${Math.round(m.cpu)}<small>%</small>`],
            ['RAM ahora', m.ram == null ? '—' : `${Math.round(m.ram)}<small>%</small>`],
            ['Pico CPU 8 h', v8.length ? `${Math.round(Math.max(...v8))}<small>%</small>` : '—'],
            ['↑ Subida', m.out_bps == null ? '—' : esc(bits(m.out_bps))],
            ['↓ Bajada', m.in_bps == null ? '—' : esc(bits(m.in_bps))],
        ].map(([k, v]) => `<div><small>${k}</small><b>${v}</b></div>`).join('');
        md.pieD.textContent = `${h.wa || 0} servicios en aviso · ${h.cr || 0} críticos · uptime ${m.uptime ? uptime(m.uptime) : '—'}`;
        (md.chartsSrv || []).forEach(c => c.destroy());
        md.chartsSrv = [];
        if (!window.Chart) return;
        md.cuerpo.querySelectorAll('canvas[data-k]').forEach(cv => {
            const k = cv.dataset.k, g = srv.series[`srv:${host}:${k}`];
            if (!g || !g.series.length) return;
            const etq = g.series[0].datos.map((_, i) => hhmm(g.inicio + i * g.paso));
            let datasets;
            if (k === 'bw60') {
                datasets = g.series.map(s => ({ label: s.nombre === 'Salida' ? '↑ Salida' : '↓ Entrada', data: s.datos.map(v => v == null ? null : v * 8 / 1e6 * (s.nombre === 'Entrada' ? -1 : 1)),
                    borderColor: s.nombre === 'Salida' ? COLOR_CANVAS.sub : COLOR_CANVAS.baj, backgroundColor: (s.nombre === 'Salida' ? COLOR_CANVAS.sub : COLOR_CANVAS.baj) + '33', fill: 'origin', borderWidth: 1.4, pointRadius: 0 }));
            } else {
                const s = serieServidor(host, k), col = k.startsWith('cpu') ? COLOR_CANVAS.cpu : COLOR_CANVAS.ram;
                datasets = [{ label: k.startsWith('cpu') ? 'CPU' : 'RAM', data: s.datos, borderColor: col, backgroundColor: col + '33', fill: 'origin', borderWidth: 1.6, pointRadius: 0, tension: .2 }];
            }
            const pct = !k.startsWith('bw') && (serieServidor(host, k)?.pct ?? true);
            md.chartsSrv.push(new Chart(cv, { type: 'line', data: { labels: etq, datasets }, options: {
                animation: false, responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: k === 'bw60', labels: { color: '#c3cbd1', boxWidth: 12 } } },
                scales: { x: { ticks: { color: '#8a96a1', maxTicksLimit: 6, maxRotation: 0 }, grid: { color: 'rgba(255,255,255,.05)' } },
                          y: { beginAtZero: true, max: pct ? 100 : undefined, ticks: { color: '#8a96a1', callback: v => k === 'bw60' ? `${Math.abs(v)} Mb` : pct ? `${v}%` : `${(+v).toFixed(1)} GiB` }, grid: { color: 'rgba(255,255,255,.06)' } } } } }));
        });
    }

    async function cargarGraficos() {
        if (!window.Chart) return;
        try {
            const r = await fetch(URL_GRAFICOS, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error);
            graficos.forEach((g, titulo) => pintarGrafico(g, d.graficos[titulo]));
            ajustarGraficos();
            srv.series = d.graficos;
            pintarServidores();
            if (md.servidor) pintarModalServidor();
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
    // Se mide dónde terminan los paneles, no scrollHeight: las decoraciones del
    // HUD (anillos, esquinas, el punto que late) sobresalen a propósito, y
    // scrollHeight las contaría como desborde y achicaría todo de más.
    function fondoDe(cont) {
        return Math.max(...[...cont.children].map(c => c.getBoundingClientRect().bottom), cont.getBoundingClientRect().top);
    }
    function cabe() {
        if (fondoDe(muro) > muro.getBoundingClientRect().bottom + 1) return false;
        return [...document.querySelectorAll('.area')].every(a => fondoDe(a) <= a.getBoundingClientRect().bottom + 1);
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

    // Orden de encendido: cada panel arranca un poco después del anterior.
    document.querySelectorAll('.panel').forEach((p, i) => p.style.setProperty('--i', i));
    setTimeout(() => document.body.classList.remove('encendiendo'), 3200);

    /* Botón HUD: los efectos se pueden apagar, y la pantalla lo recuerda. */
    let hud = (() => { try { return localStorage.getItem('muro-hud') !== '0'; } catch (e) { return true; } })();
    function aplicarHud() {
        document.body.classList.toggle('hud', hud);
        $('btn-hud').classList.toggle('off', !hud);
        $('btn-hud').textContent = hud ? 'HUD ●' : 'HUD ○';
        graficos.forEach(g => g.chart && g.chart.update('none'));   // degradado y brillo van en el dibujo
    }
    $('btn-hud').addEventListener('click', () => {
        hud = !hud;
        try { localStorage.setItem('muro-hud', hud ? '1' : '0'); } catch (e) {}
        aplicarHud();
    });
    aplicarHud();

    /* Lluvia de caracteres de fondo, muy tenue (la opacidad la da el CSS).
       Se dibuja a ~14 cuadros por segundo y se detiene sin HUD o con la
       pestaña oculta: en una TV encendida todo el día, eso importa. */
    (function lluvia() {
        const cv = $('lluvia'), ctx = cv.getContext('2d');
        const glifos = 'アカサタナハマヤラワ0123456789ABCDEF<>/\\|=+-*:';
        let tam = 16, gotas = [];
        function medir() {
            cv.width = innerWidth; cv.height = innerHeight;
            tam = Math.max(12, Math.round(parseFloat(getComputedStyle(raiz).fontSize) * 1.25));
            gotas = Array.from({ length: Math.ceil(cv.width / tam) }, () => ({ y: Math.random() * -60, v: .35 + Math.random() * .65 }));
        }
        medir();
        window.addEventListener('resize', medir);
        setInterval(() => {
            if (!conHud() || document.hidden) return;
            ctx.fillStyle = 'rgba(17,24,29,.2)';
            ctx.fillRect(0, 0, cv.width, cv.height);
            ctx.font = `${tam}px monospace`;
            gotas.forEach((g, i) => {
                ctx.fillStyle = Math.random() < .05 ? '#d6ffe9' : '#13d389';
                ctx.fillText(glifos[Math.floor(Math.random() * glifos.length)], i * tam, g.y * tam);
                g.y = g.y * tam > cv.height && Math.random() > .97 ? 0 : g.y + g.v;
            });
        }, 70);
    })();

    /* Modo navidad: en noviembre y diciembre el viejito pascuero cruza la
       pantalla en trineo, a una altura y en un sentido al azar, más o menos
       cada 30 segundos. ?navidad=1 lo fuerza fuera de temporada (pruebas). */
    (function navidad() {
        const forzado = new URLSearchParams(location.search).get('navidad') === '1';
        const enTemporada = () => forzado || [10, 11].includes(new Date().getMonth());
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const noel = $('noel');

        function chispa(x, y) {
            const c = document.createElement('i');
            c.className = 'chispa';
            c.style.left = x + 'px'; c.style.top = y + 'px';
            const dur = 2.4 + Math.random() * 1.4;                // estela larga: cada chispa dura 2,4–3,8 s
            c.style.setProperty('--t',  (.4 + Math.random() * .7).toFixed(2) + 'rem');
            c.style.setProperty('--dx', (Math.random() * 3 - 1.5).toFixed(2) + 'rem');
            c.style.setProperty('--dy', (3 + Math.random() * 4).toFixed(2) + 'rem');
            c.style.setProperty('--d',  dur.toFixed(2) + 's');
            document.body.appendChild(c);
            setTimeout(() => c.remove(), dur * 1000 + 100);
        }

        function volar() {
            if (!enTemporada() || document.hidden) return;
            const izq  = Math.random() < .5;                     // de derecha a izquierda
            noel.style.transform = 'translate(-200vw, 0)';      // medirlo sin que asome
            noel.classList.add('vuela');
            const ancho = noel.offsetWidth || 300;
            const y    = innerHeight * (.06 + Math.random() * .5);
            const ida  = [-ancho - 20, innerWidth + 20];
            const [x0, x1] = izq ? [ida[1], ida[0]] : ida;
            const ola  = innerHeight * .04;
            noel.classList.toggle('izq', izq);
            const anim = noel.animate([
                { transform: `translate(${x0}px, ${y}px)` },
                { transform: `translate(${x0 + (x1 - x0) * .25}px, ${y - ola}px)` },
                { transform: `translate(${x0 + (x1 - x0) * .5}px, ${y + ola}px)` },
                { transform: `translate(${x0 + (x1 - x0) * .75}px, ${y - ola}px)` },
                { transform: `translate(${x1}px, ${y - ola * 2}px)` },
            ], { duration: 9000 + Math.random() * 4000, easing: 'linear' });
            // Estela de polvo de estrellas que sale del trineo.
            const estela = setInterval(() => {
                const r = noel.getBoundingClientRect();
                const x = izq ? r.right - r.width * .12 : r.left + r.width * .12;
                for (let k = 0; k < 2; k++) chispa(x + (Math.random() - .5) * r.width * .06, r.top + r.height * (.55 + Math.random() * .2));
            }, 50);
            anim.onfinish = anim.oncancel = () => { clearInterval(estela); noel.classList.remove('vuela'); };
        }

        /* Nieve sutil sobre toda la pantalla: copos chicos, lentos y casi
           transparentes, a ~30 cuadros por segundo; se detiene con la
           pestaña oculta o fuera de temporada. */
        (function nevar() {
            const cv = $('nieve'), ctx = cv.getContext('2d');
            let copos = [];
            function medir() {
                cv.width = innerWidth; cv.height = innerHeight;
                const n = Math.round(innerWidth * innerHeight / 14000);   // ~150 en 1920×1080
                copos = Array.from({ length: n }, () => ({
                    x: Math.random() * cv.width, y: Math.random() * cv.height,
                    r: 1.1 + Math.random() * 2.6, v: .25 + Math.random() * .7,
                    a: .25 + Math.random() * .45, f: Math.random() * Math.PI * 2,
                }));
            }
            medir();
            window.addEventListener('resize', medir);
            setInterval(() => {
                const activa = enTemporada() && !document.hidden;
                cv.classList.toggle('activa', activa);
                if (!activa) return;
                ctx.clearRect(0, 0, cv.width, cv.height);
                copos.forEach(c => {
                    c.f += .02;
                    c.y += c.v;
                    c.x += Math.sin(c.f) * .35 + .1;
                    if (c.y > cv.height + 4) { c.y = -4; c.x = Math.random() * cv.width; }
                    if (c.x > cv.width + 4) c.x = -4;
                    ctx.beginPath();
                    ctx.arc(c.x, c.y, c.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(255,255,255,${c.a})`;
                    ctx.fill();
                });
            }, 33);
        })();

        /* Duende: cada ~15 s se asoma por un borde al azar (abajo, arriba,
           izquierda o derecha), mira un momento y se esconde. El dibujo
           siempre "sube desde abajo"; para los otros bordes se gira la caja. */
        (function duende() {
            const d = $('duende'), dentro = d.firstElementChild;
            function asomar() {
                if (!enTemporada() || document.hidden) return;
                d.classList.add('asoma');
                const W = d.offsetWidth, H = d.offsetHeight;
                const borde = ['abajo', 'arriba', 'izquierda', 'derecha'][Math.floor(Math.random() * 4)];
                const azar = (max) => max * (.1 + Math.random() * .8);
                let cx, cy, giro;
                if (borde === 'abajo')     { cx = azar(innerWidth);  cy = innerHeight - H / 2; giro = 0; }
                if (borde === 'arriba')    { cx = azar(innerWidth);  cy = H / 2;               giro = 180; }
                if (borde === 'izquierda') { cx = H / 2;              cy = azar(innerHeight);  giro = 90; }
                if (borde === 'derecha')   { cx = innerWidth - H / 2; cy = azar(innerHeight);  giro = -90; }
                d.style.left = (cx - W / 2) + 'px';
                d.style.top  = (cy - H / 2) + 'px';
                d.style.transform = `rotate(${giro}deg)`;
                const ladeo = Math.random() < .5 ? -8 : 8;
                const anim = dentro.animate([
                    { transform: 'translateY(105%)' },
                    { transform: 'translateY(6%) rotate(0deg)',          offset: .18 },
                    { transform: `translateY(6%) rotate(${ladeo}deg)`,   offset: .35 },
                    { transform: `translateY(6%) rotate(${-ladeo}deg)`,  offset: .55 },
                    { transform: 'translateY(6%) rotate(0deg)',          offset: .75 },
                    { transform: 'translateY(105%)' },
                ], { duration: 4200, easing: 'ease-in-out' });
                anim.onfinish = anim.oncancel = () => d.classList.remove('asoma');
            }
            (function programar(espera) {
                setTimeout(() => { asomar(); programar(12000 + Math.random() * 6000); }, espera);
            })(forzado ? 1500 : 8000);
        })();

        /* Monstruito verde: cada ~30 s aparece en un lugar al azar de la
           pantalla, baila 5 segundos y desaparece. */
        (function grunon() {
            const g = $('grunon');
            function bailar() {
                if (!enTemporada() || document.hidden) return;
                g.classList.add('baila');
                const W = g.offsetWidth, H = g.offsetHeight;
                g.style.left = (Math.random() * (innerWidth - W)) + 'px';
                g.style.top  = (Math.random() * (innerHeight - H)) + 'px';
                const anim = g.animate([
                    { transform: 'scale(0) rotate(-180deg)', opacity: 0 },
                    { transform: 'scale(1.15) rotate(8deg)', opacity: 1, offset: .07 },
                    { transform: 'scale(1) rotate(0deg)',    opacity: 1, offset: .1 },
                    { transform: 'scale(1) rotate(0deg)',    opacity: 1, offset: .9 },
                    { transform: 'scale(0) rotate(180deg)',  opacity: 0 },
                ], { duration: 5000, easing: 'ease-in-out' });
                anim.onfinish = anim.oncancel = () => g.classList.remove('baila');
            }
            (function programar(espera) {
                setTimeout(() => { bailar(); programar(25000 + Math.random() * 10000); }, espera);
            })(forzado ? 2500 : 20000);
        })();

        (function programar(espera) {
            setTimeout(() => { volar(); programar(25000 + Math.random() * 10000); }, espera);
        })(forzado ? 3000 : 15000);
    })();

    const reloj = () => { $('reloj').textContent = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }); };
    reloj(); setInterval(reloj, 10000);
    sondear(); setInterval(sondear, INTERVALO);
    // Los gráficos avanzan de a un punto por minuto en CheckMK: basta con refrescarlos cada minuto.
    cargarGraficos(); setInterval(cargarGraficos, 60000);
    cargarRanking(); setInterval(cargarRanking, 300000);
    cargarServicios(); setInterval(cargarServicios, 60000);
    cargarEnlaces(); setInterval(cargarEnlaces, 60000);
    cargarMesa(); setInterval(cargarMesa, 30000);
})();
</script>
</body>
</html>
