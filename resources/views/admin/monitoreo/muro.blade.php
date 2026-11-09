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
        return `<svg class="hex" viewBox="-4 -4 108 108" overflow="visible">
            <polygon class="hex-anillo" points="${HEX(52)}" fill="none" stroke="var(--hud)" stroke-width="1" stroke-dasharray="4 7" opacity=".7"/>
            <polygon points="${HEX(46)}" fill="#1a2a26" stroke="#1f6e52" stroke-width="2"/>
            <polygon class="hex-in" points="${HEX(20)}" fill="var(--ok)"/></svg>`;
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
        graficos.set(p.titulo, { p, caja, ley, chart: null });
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
       el gráfico queda chico dentro de su marco. Se compara a mano y se corrige. */
    function ajustarGraficos() {
        graficos.forEach(g => {
            if (!g.chart) return;
            const cv = g.chart.canvas;
            if (Math.abs(cv.clientWidth - g.caja.clientWidth) > 1 || Math.abs(cv.clientHeight - g.caja.clientHeight) > 1) g.chart.resize();
        });
    }
    setInterval(ajustarGraficos, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) ajustarGraficos(); });

    async function cargarGraficos() {
        if (!window.Chart) return;
        try {
            const r = await fetch(URL_GRAFICOS, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error);
            graficos.forEach((g, titulo) => pintarGrafico(g, d.graficos[titulo]));
            ajustarGraficos();
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
})();
</script>
</body>
</html>
