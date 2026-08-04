<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appNombre ?? 'Monitoreo' }} · Mapa de red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /*
         | Paleta tomada del propio CheckMK 2.3 (tema modern-dark): los estados
         | usan exactamente los mismos colores que ves en la consola, para que la
         | pantalla se lea igual sin tener que reaprender códigos.
         */
        :root {
            --bg:      #11181d;
            --panel:   #1c2228;
            --panel-2: #20272e;
            --line:    #303946;
            --line-2:  #353f4a;
            --txt:     #ffffff;
            --muted:   #7f8a94;

            --ok:      #13d389;
            --warn:    #ffd703;
            --crit:    #c83232;
            --unknown: #ff8400;
            --pending: #838383;
            --dt:      #3cc2ff;
            --alarm:   #ff3232;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        html, body {
            height:100%; background:var(--bg); color:var(--txt); overflow:hidden;
            font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
            cursor:none;                     /* modo kiosco: sin puntero en la TV */
            -webkit-font-smoothing:antialiased;
        }

        /* ── Cabecera ejecutiva ─────────────────────────────────────────── */
        .hd { height:82px; display:flex; align-items:stretch; gap:0;
              background:var(--panel); border-bottom:2px solid var(--line); }
        .hd > div { display:flex; flex-direction:column; justify-content:center; padding:0 1.4rem; }
        .hd .sep { border-left:1px solid var(--line); padding:0; }

        .marca { min-width:270px; }
        .marca .sitio { font-size:.66rem; letter-spacing:.18em; color:var(--muted); text-transform:uppercase; font-weight:700; }
        .marca .mapa  { font-size:1.42rem; font-weight:700; line-height:1.15; letter-spacing:.01em;
                        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:26ch; }
        .marca .mapa i { color:var(--ok); margin-right:.45rem; font-size:1.1rem; }

        /* Fichas de estado, con el bloque de color macizo típico de CheckMK */
        .fichas { flex-direction:row !important; align-items:center; gap:.55rem; }
        .fi { min-width:104px; border:1px solid var(--line); border-radius:3px; overflow:hidden; background:var(--panel-2); }
        .fi .n { font-size:1.72rem; font-weight:700; line-height:1; padding:.42rem .7rem .3rem;
                 font-variant-numeric:tabular-nums; text-align:center; }
        .fi .l { font-size:.6rem; letter-spacing:.14em; font-weight:700; text-align:center;
                 padding:3px 0 4px; text-transform:uppercase; }
        .fi.ok   .l { background:var(--ok);      color:#000; }
        .fi.crit .l { background:var(--crit);    color:#fff; }
        .fi.dt   .l { background:var(--dt);      color:#000; }
        .fi.pend .l { background:var(--pending); color:#fff; }
        .fi.crit.hay { border-color:var(--crit); box-shadow:0 0 0 1px var(--crit) inset; }
        .fi.crit.hay .n { color:var(--crit); }

        /* Barra de disponibilidad */
        .salud { min-width:210px; }
        .salud .pct { font-size:1.72rem; font-weight:700; line-height:1; font-variant-numeric:tabular-nums; }
        .salud .barra { height:7px; background:var(--panel-2); border:1px solid var(--line); border-radius:2px;
                        margin:.4rem 0 .3rem; overflow:hidden; }
        .salud .barra span { display:block; height:100%; background:var(--ok); transition:width .6s, background .3s; }
        .salud .l { font-size:.6rem; letter-spacing:.14em; color:var(--muted); font-weight:700; text-transform:uppercase; }

        .reloj { margin-left:auto; text-align:right; min-width:150px; }
        .reloj .h { font-size:1.95rem; font-weight:700; line-height:1; font-variant-numeric:tabular-nums; letter-spacing:.02em; }
        .reloj .f { font-size:.68rem; color:var(--muted); text-transform:capitalize; margin-top:3px; }

        .puntos { flex-direction:row !important; align-items:center; gap:7px; }
        .puntos span { width:8px; height:8px; border-radius:50%; background:var(--line-2); transition:background .3s; }
        .puntos span.on { background:var(--ok); box-shadow:0 0 6px var(--ok); }

        /* ── Franja de alarma ───────────────────────────────────────────── */
        .alarma { display:none; align-items:center; gap:.7rem; height:34px; padding:0 1.4rem;
                  background:var(--crit); color:#fff; font-weight:700; font-size:.85rem; letter-spacing:.06em; }
        .alarma.on { display:flex; animation:pulso 1.6s ease-in-out infinite; }
        .alarma .cuenta { background:rgba(0,0,0,.28); border-radius:3px; padding:1px 9px; font-variant-numeric:tabular-nums; }
        @keyframes pulso { 50% { background:#a52626; } }

        /* ── Lienzo del mapa ────────────────────────────────────────────── */
        .stage { position:relative; flex:1 1 auto; min-height:0;
                 background-image:linear-gradient(var(--panel-2) 1px, transparent 1px),
                                  linear-gradient(90deg, var(--panel-2) 1px, transparent 1px);
                 background-size:44px 44px; }
        .stage svg { position:absolute; inset:0; width:100%; height:100%; z-index:1; }
        .fondo { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; pointer-events:none; z-index:0; }

        .elabel { position:absolute; transform:translate(-50%,-50%); z-index:2; pointer-events:none; font-weight:700;
                  color:var(--txt); white-space:nowrap; background:rgba(17,24,29,.82); padding:1px 6px;
                  border:1px solid var(--line); border-radius:2px; }

        /* Nodo: bloque de estado macizo, como las celdas de estado de CheckMK */
        .nodo { position:absolute; width:130px; margin-left:-65px; text-align:center; z-index:2; }
        .nodo .chip { margin:0 auto; border-radius:4px; display:flex; align-items:center; justify-content:center;
                      background:var(--panel); border:2px solid var(--line-2); color:var(--muted);
                      transition:border-color .3s, background .3s, color .3s, box-shadow .3s; }
        .nodo .lbl { font-weight:700; margin-top:5px; color:var(--txt); line-height:1.15;
                     text-shadow:0 1px 3px var(--bg), 0 0 6px var(--bg); }
        .nodo .sub { color:var(--muted); font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
                     text-shadow:0 1px 3px var(--bg); }
        .nodo.portal .chip { border-style:double; border-width:4px; }

        .nodo.st-up .chip       { border-color:var(--ok);      background:#0d2b23; color:var(--ok); }
        .nodo.st-down .chip     { border-color:var(--crit);    background:#2b1414; color:#ff6b6b;
                                  box-shadow:0 0 0 4px rgba(200,50,50,.22); animation:late 1.1s infinite; }
        .nodo.st-downtime .chip { border-color:var(--dt);      background:#0d2733; color:var(--dt); }
        .nodo.st-na .chip       { border-color:var(--pending); background:var(--panel); color:var(--pending); }
        @keyframes late { 50% { box-shadow:0 0 0 11px rgba(200,50,50,0); } }

        /* ── Panel de incidencias ───────────────────────────────────────── */
        .inc { position:absolute; left:1.1rem; bottom:1.1rem; z-index:4; min-width:330px; max-width:34%;
               background:rgba(28,34,40,.96); border:1px solid var(--line); border-radius:3px; display:none; }
        .inc.on { display:block; }
        .inc h6 { font-size:.62rem; letter-spacing:.16em; text-transform:uppercase; color:var(--muted);
                  font-weight:700; padding:.5rem .8rem; border-bottom:1px solid var(--line); }
        .inc table { width:100%; border-collapse:collapse; font-size:.82rem; }
        .inc td { padding:.34rem .6rem; border-bottom:1px solid var(--panel-2); }
        .inc tr:last-child td { border-bottom:0; }
        .inc tr:nth-child(even) { background:rgba(255,255,255,.02); }
        .inc .est { background:var(--crit); color:#fff; font-weight:700; font-size:.64rem; letter-spacing:.1em;
                    text-align:center; width:56px; }
        .inc .hn { color:var(--muted); font-family:ui-monospace,monospace; font-size:.72rem; text-align:right; }
        .inc .mas { padding:.35rem .8rem; font-size:.7rem; color:var(--muted); }

        /* ── Pie ────────────────────────────────────────────────────────── */
        .ft { height:30px; display:flex; align-items:center; gap:1.4rem; padding:0 1.4rem;
              background:var(--panel); border-top:1px solid var(--line);
              font-size:.68rem; color:var(--muted); letter-spacing:.04em; }
        .ft .pill { display:inline-flex; align-items:center; gap:.35rem; }
        .ft .pill i { font-size:.72rem; }
        .ft .der { margin-left:auto; display:flex; gap:1.2rem; }
        .ft b { color:var(--txt); font-weight:600; }
        .ft .conn.ko { color:var(--warn); }

        .wrap { display:flex; flex-direction:column; height:100%; }

        /* La cabecera está pensada para 1080p; en pantallas menores va soltando
           lo accesorio antes que desbordarse. */
        .hd { overflow:hidden; }
        @media (max-width:1600px) {
            .hd > div { padding:0 1rem; }
            .marca .mapa { font-size:1.28rem; max-width:20ch; }
            .fi { min-width:90px; }
            .salud { min-width:170px; }
        }
        @media (max-width:1330px) {
            .fi.pend { display:none; }
            .marca { min-width:200px; }
            .marca .mapa { max-width:15ch; }
        }
        @media (max-width:1150px) {
            .puntos { display:none; }
            .salud  { display:none; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <header class="hd">
        <div class="marca">
            <div class="sitio">{{ $appNombre ?? 'Monitoreo' }} · Monitoreo de red</div>
            <div class="mapa"><i class="bi bi-diagram-3-fill"></i><span id="tvNombre">—</span></div>
        </div>

        <div class="sep fichas">
            <div class="fi ok"   id="fiOk">  <div class="n" id="tOk">–</div>  <div class="l">Up</div></div>
            <div class="fi crit" id="fiCrit"><div class="n" id="tCrit">–</div><div class="l">Down</div></div>
            <div class="fi dt"   id="fiDt">  <div class="n" id="tDt">–</div>  <div class="l">Downtime</div></div>
            <div class="fi pend" id="fiNa">  <div class="n" id="tNa">–</div>  <div class="l">Pending</div></div>
        </div>

        <div class="sep salud">
            <div class="pct" id="tvPct">–</div>
            <div class="barra"><span id="tvBarra" style="width:0"></span></div>
            <div class="l">Disponibilidad del mapa</div>
        </div>

        <div class="sep puntos" id="tvPuntos"></div>

        <div class="reloj">
            <div class="h" id="tvHora">--:--</div>
            <div class="f" id="tvFecha">—</div>
        </div>
    </header>

    <div class="alarma" id="tvAlarma">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span class="cuenta" id="tvAlarmaN">0</span>
        <span id="tvAlarmaTxt">HOSTS SIN RESPUESTA</span>
    </div>

    <div class="stage" id="stage">
        <svg id="svgEdges" viewBox="0 0 1600 900" preserveAspectRatio="none"></svg>
        <div class="inc" id="tvInc">
            <h6><i class="bi bi-exclamation-triangle-fill"></i> Incidencias activas</h6>
            <table id="tvIncTabla"></table>
            <div class="mas" id="tvIncMas" style="display:none"></div>
        </div>
    </div>

    <footer class="ft">
        <span class="pill conn" id="tvConn"><i class="bi bi-hdd-network"></i> CheckMK</span>
        <span class="pill"><i class="bi bi-arrow-repeat"></i> Actualiza cada <b>{{ $intervalo }}s</b></span>
        @if(count($mapasData) > 1)
        <span class="pill"><i class="bi bi-collection-play"></i> Rota cada <b>{{ $rotacion }}s</b></span>
        @endif
        <span class="der">
            <span class="pill"><i class="bi bi-clock-history"></i> Último dato: <b id="tvUpd">—</b></span>
            <span class="pill" id="tvContador"></span>
        </span>
    </footer>

</div>

<script>
(() => {
    const MAPAS = @json($mapasData);
    const URL_ESTADO = id => '{{ url('monitoreo/tv/' . $token . '/estado') }}/' + id;
    const ROTACION  = {{ $rotacion }} * 1000;
    const INTERVALO = {{ $intervalo }} * 1000;

    const stage = document.getElementById('stage');
    const svg   = document.getElementById('svgEdges');
    let idx = 0, estados = {};

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // Puntos de rotación entre mapas
    const puntos = $('tvPuntos');
    MAPAS.forEach((_, i) => { const s = document.createElement('span'); if (i === 0) s.classList.add('on'); puntos.appendChild(s); });
    if (MAPAS.length < 2) puntos.style.display = 'none';

    function renderMapa() {
        const m = MAPAS[idx];
        $('tvNombre').textContent = m.nombre;
        $('tvContador').innerHTML = MAPAS.length > 1
            ? '<i class="bi bi-map"></i> Mapa <b>' + (idx + 1) + '</b> de <b>' + MAPAS.length + '</b>' : '';
        puntos.querySelectorAll('span').forEach((s, i) => s.classList.toggle('on', i === idx));

        stage.querySelectorAll('.nodo, .fondo, .elabel').forEach(el => el.remove());
        svg.innerHTML = '';

        if (m.fondo) {
            const img = document.createElement('img');
            img.className = 'fondo';
            img.src = m.fondo;
            img.alt = '';
            img.style.opacity = (m.opacidad || 40) / 100;
            stage.prepend(img);
        }

        m.nodos.forEach(n => {
            // Los tamaños por nodo se amplifican para la distancia de la TV.
            const ipx = Math.round((n.icono_px || 48) * 1.15);
            const lpx = Math.round((n.letra_px || 11) * 1.15);
            const ancho = Math.max(130, ipx + 60);
            const el = document.createElement('div');
            el.id = 'nodo-' + n.id;
            el.className = 'nodo st-na' + (n.mapa_destino_id ? ' portal' : '');
            el.style.left = (n.x / 1600 * 100) + '%';
            el.style.top  = (n.y / 900 * 100) + '%';
            el.style.width = ancho + 'px';
            el.style.marginLeft = -(ancho / 2) + 'px';
            el.style.transform = 'translateY(-' + (ipx / 2) + 'px)';
            el.innerHTML =
                '<div class="chip" style="width:' + ipx + 'px;height:' + ipx + 'px;font-size:' + Math.round(ipx * 0.46) + 'px">' +
                    '<i class="bi ' + n.icono + '"></i>' +
                '</div>' +
                '<div class="lbl" style="font-size:' + lpx + 'px">' + esc(n.etiqueta) + '</div>' +
                '<div class="sub" style="font-size:' + Math.max(8, Math.round(lpx * 0.8)) + 'px">' + esc(n.host_name || '') + '</div>';
            stage.appendChild(el);
        });

        aplicarEstado();
    }

    // Ruta ortogonal del enlace (mismos codos definidos en el editor).
    function rutaEnlace(e, a, b) {
        const vs = e.puntos || [];
        if (!vs.length) return [{ x: a.x, y: a.y }, { x: b.x, y: b.y }];
        const pts = [{ x: a.x, y: a.y }];
        let prev = pts[0];
        const alineados = (p, q) => Math.abs(p.x - q.x) < 1 || Math.abs(p.y - q.y) < 1;
        [...vs, { x: b.x, y: b.y }].forEach(v => {
            if (!alineados(prev, v)) pts.push({ x: v.x, y: prev.y });
            pts.push({ x: v.x, y: v.y });
            prev = v;
        });
        return pts;
    }

    function medioRuta(pts) {
        let total = 0; const segs = [];
        for (let i = 1; i < pts.length; i++) {
            const d = Math.hypot(pts[i].x - pts[i-1].x, pts[i].y - pts[i-1].y);
            segs.push(d); total += d;
        }
        let meta = total / 2;
        for (let i = 0; i < segs.length; i++) {
            if (meta <= segs[i] || i === segs.length - 1) {
                const t = segs[i] ? meta / segs[i] : 0;
                return { x: pts[i].x + (pts[i+1].x - pts[i].x) * t,
                         y: pts[i].y + (pts[i+1].y - pts[i].y) * t };
            }
            meta -= segs[i];
        }
        return pts[0];
    }

    function drawEdges() {
        const m = MAPAS[idx];
        svg.innerHTML = '';
        stage.querySelectorAll('.elabel').forEach(el => el.remove());

        const COL = { up:'#13d389', down:'#c83232', downtime:'#3cc2ff', na:'#838383' };

        m.enlaces.forEach(e => {
            const a = m.nodos.find(n => n.id === e.nodo_a_id), b = m.nodos.find(n => n.id === e.nodo_b_id);
            if (!a || !b) return;
            const ea = estados[e.nodo_a_id]?.estado, eb = estados[e.nodo_b_id]?.estado;
            let est = 'na';
            if (ea === 'down' || eb === 'down') est = 'down';
            else if (ea === 'downtime' || eb === 'downtime') est = 'downtime';
            else if (ea === 'up' || eb === 'up') est = 'up';

            const pts = rutaEnlace(e, a, b);
            const l = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
            l.setAttribute('points', pts.map(p => p.x + ',' + p.y).join(' '));
            l.setAttribute('fill', 'none');
            l.setAttribute('stroke-linejoin', 'round');
            l.setAttribute('stroke-linecap', 'round');
            l.setAttribute('stroke', COL[est]);
            l.setAttribute('stroke-width', e.tipo === 'fibra' ? 4 : 2.5);
            l.setAttribute('vector-effect', 'non-scaling-stroke');
            l.setAttribute('opacity', est === 'na' ? '.5' : '1');
            if (e.tipo === 'inalambrico') l.setAttribute('stroke-dasharray', '8 7');
            if (e.tipo === 'starlink')    l.setAttribute('stroke-dasharray', '14 5 3 5');
            if (est === 'down') {
                l.setAttribute('stroke-dasharray', '8 7');
                const an = document.createElementNS('http://www.w3.org/2000/svg', 'animate');
                an.setAttribute('attributeName', 'stroke-dashoffset');
                an.setAttribute('from', '30'); an.setAttribute('to', '0');
                an.setAttribute('dur', '0.8s'); an.setAttribute('repeatCount', 'indefinite');
                l.appendChild(an);
            }
            svg.appendChild(l);

            if (e.etiqueta) {
                const c = medioRuta(pts);
                const lbl = document.createElement('div');
                lbl.className = 'elabel';
                lbl.textContent = e.etiqueta;
                lbl.style.left = c.x / 1600 * 100 + '%';
                lbl.style.top  = c.y / 900 * 100 + '%';
                lbl.style.fontSize = Math.round((e.etiqueta_px || 12) * 1.15) + 'px';
                if (e.etiqueta_color) lbl.style.color = e.etiqueta_color;
                stage.appendChild(lbl);
            }
        });
    }

    function aplicarEstado() {
        const m = MAPAS[idx];
        const cuenta = { up:0, down:0, downtime:0, na:0 };

        m.nodos.forEach(n => {
            const est = estados[n.id]?.estado || 'na';
            cuenta[est] = (cuenta[est] || 0) + 1;
            const el = $('nodo-' + n.id);
            if (el) el.className = 'nodo st-' + est + (n.mapa_destino_id ? ' portal' : '');
        });

        $('tOk').textContent   = cuenta.up;
        $('tCrit').textContent = cuenta.down;
        $('tDt').textContent   = cuenta.downtime;
        $('tNa').textContent   = cuenta.na;
        $('fiCrit').classList.toggle('hay', cuenta.down > 0);

        // Disponibilidad: los nodos en mantención no cuentan como caídos.
        const evaluables = cuenta.up + cuenta.down;
        const pct = evaluables ? (cuenta.up / evaluables * 100) : null;
        $('tvPct').textContent = pct === null ? '–' : pct.toFixed(1) + '%';
        $('tvPct').style.color = pct === null ? 'var(--muted)' : (pct === 100 ? 'var(--ok)' : (pct >= 90 ? 'var(--warn)' : 'var(--crit)'));
        const barra = $('tvBarra');
        barra.style.width = (pct === null ? 0 : pct) + '%';
        barra.style.background = pct === null ? 'var(--pending)' : (pct === 100 ? 'var(--ok)' : (pct >= 90 ? 'var(--warn)' : 'var(--crit)'));

        // Franja de alarma e incidencias
        const caidos = m.nodos.filter(n => estados[n.id]?.estado === 'down');
        const alarma = $('tvAlarma');
        alarma.classList.toggle('on', caidos.length > 0);
        $('tvAlarmaN').textContent = caidos.length;
        $('tvAlarmaTxt').textContent = caidos.length === 1 ? 'HOST SIN RESPUESTA' : 'HOSTS SIN RESPUESTA';

        const inc = $('tvInc');
        inc.classList.toggle('on', caidos.length > 0);
        $('tvIncTabla').innerHTML = caidos.slice(0, 7).map(n =>
            '<tr><td class="est">DOWN</td><td>' + esc(n.etiqueta) +
            '</td><td class="hn">' + esc(estados[n.id]?.desde || n.host_name || '') + '</td></tr>').join('');
        const mas = $('tvIncMas');
        mas.style.display = caidos.length > 7 ? '' : 'none';
        mas.textContent = caidos.length > 7 ? '+ ' + (caidos.length - 7) + ' más' : '';

        drawEdges();
    }

    async function refrescar() {
        const conn = $('tvConn');
        try {
            const r = await fetch(URL_ESTADO(MAPAS[idx].id), { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            if (!j.ok) throw new Error();
            estados = j.nodos;
            aplicarEstado();
            conn.className = 'pill conn';
            conn.innerHTML = '<i class="bi bi-hdd-network"></i> CheckMK conectado';
            $('tvUpd').textContent = j.ts;
        } catch {
            conn.className = 'pill conn ko';
            conn.innerHTML = '<i class="bi bi-plug"></i> Sin conexión a CheckMK';
        }
    }

    function reloj() {
        const d = new Date();
        $('tvHora').textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        $('tvFecha').textContent = d.toLocaleDateString('es-CL', { weekday:'long', day:'numeric', month:'long' });
    }

    renderMapa();
    refrescar();
    reloj();
    setInterval(reloj, 10000);
    setInterval(refrescar, INTERVALO);
    if (MAPAS.length > 1) {
        setInterval(() => { idx = (idx + 1) % MAPAS.length; renderMapa(); refrescar(); }, ROTACION);
    }
})();
</script>
</body>
</html>
