<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitoreo · Mapa de red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height:100%; background:#0b1220; color:#e2e8f0; font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; overflow:hidden; }
        .tv-top { height:64px; display:flex; align-items:center; gap:1.2rem; padding:0 1.5rem; background:#0f172a; border-bottom:1px solid #1e293b; }
        .tv-top .titulo { font-size:1.15rem; font-weight:700; letter-spacing:.02em; }
        .tv-top .titulo i { color:#38bdf8; margin-right:.5rem; }
        .tv-badge { font-size:1rem; font-weight:800; border-radius:24px; padding:.4rem 1.2rem; }
        .tv-badge.ok  { background:#052e16; color:#4ade80; border:1px solid #166534; }
        .tv-badge.bad { background:#450a0a; color:#f87171; border:1px solid #991b1b; animation:tvPulse 1.2s infinite; }
        .tv-badge.warn{ background:#451a03; color:#fbbf24; border:1px solid #92400e; }
        @keyframes tvPulse { 50% { opacity:.55; } }
        .tv-reloj { margin-left:auto; text-align:right; }
        .tv-reloj .h { font-size:1.35rem; font-weight:700; font-variant-numeric:tabular-nums; }
        .tv-reloj .f { font-size:.7rem; color:#64748b; }
        .tv-dots { display:flex; gap:6px; margin-left:1rem; }
        .tv-dots span { width:9px; height:9px; border-radius:50%; background:#334155; }
        .tv-dots span.on { background:#38bdf8; }
        .tv-stage { position:relative; height:calc(100% - 64px); background-image:radial-gradient(#1e293b 1px, transparent 1px); background-size:30px 30px; }
        .tv-stage svg { position:absolute; inset:0; width:100%; height:100%; z-index:1; }
        .tv-fondo { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; pointer-events:none; z-index:0; }
        .tv-nodo { position:absolute; width:130px; margin-left:-65px; text-align:center; transform:translateY(-28px); z-index:2; }
        .tv-nodo .chip { width:56px; height:56px; margin:0 auto; border-radius:14px; display:flex; align-items:center; justify-content:center;
                         font-size:26px; border:3px solid #475569; background:#0f172a; color:#64748b;
                         transition:border-color .3s, background .3s, color .3s; }
        .tv-nodo .lbl { font-size:.78rem; font-weight:700; margin-top:4px; color:#cbd5e1; line-height:1.15; }
        .tv-nodo .sub { font-size:.62rem; color:#475569; font-family:ui-monospace,monospace; }
        .tv-nodo.portal .chip { outline:2px solid #8b5cf6; outline-offset:3px; }
        .tv-nodo.st-up .chip       { border-color:#22c55e; background:#052e16; color:#4ade80; }
        .tv-nodo.st-down .chip     { border-color:#ef4444; background:#450a0a; color:#f87171; animation:tvBlink 1s infinite; }
        .tv-nodo.st-downtime .chip { border-color:#f59e0b; background:#451a03; color:#fbbf24; }
        @keyframes tvBlink { 50% { opacity:.45; } }
        .tv-caidos { position:absolute; left:1.2rem; bottom:1rem; z-index:4; font-size:.8rem; color:#f87171; max-width:40%; }
        .tv-caidos div { margin-top:.2rem; }
    </style>
</head>
<body>

<div class="tv-top">
    <div class="titulo"><i class="bi bi-broadcast-pin"></i><span id="tvNombre">—</span></div>
    <span class="tv-badge warn" id="tvBadge"><i class="bi bi-hourglass-split"></i> Consultando…</span>
    <div class="tv-dots" id="tvDots"></div>
    <div class="tv-reloj">
        <div class="h" id="tvHora">--:--</div>
        <div class="f" id="tvUpd">actualizando…</div>
    </div>
</div>

<div class="tv-stage" id="stage">
    <svg id="svgEdges" viewBox="0 0 1600 900" preserveAspectRatio="none"></svg>
    <div class="tv-caidos" id="tvCaidos"></div>
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

    // Puntos de rotación
    const dots = document.getElementById('tvDots');
    MAPAS.forEach((_, i) => { const s = document.createElement('span'); if (i === 0) s.classList.add('on'); dots.appendChild(s); });
    if (MAPAS.length < 2) dots.style.display = 'none';

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function renderMapa() {
        const m = MAPAS[idx];
        document.getElementById('tvNombre').textContent = m.nombre;
        dots.querySelectorAll('span').forEach((s, i) => s.classList.toggle('on', i === idx));
        stage.querySelectorAll('.tv-nodo, .tv-fondo').forEach(el => el.remove());
        svg.innerHTML = '';
        if (m.fondo) {
            const img = document.createElement('img');
            img.className = 'tv-fondo';
            img.src = m.fondo;
            img.alt = '';
            img.style.opacity = (m.opacidad || 40) / 100;
            stage.prepend(img);
        }
        m.nodos.forEach(n => {
            // Los tamaños por nodo se amplifican levemente para la distancia de la TV.
            const ipx = Math.round((n.icono_px || 48) * 1.15);
            const lpx = Math.round((n.letra_px || 11) * 1.15);
            const ancho = Math.max(130, ipx + 60);
            const el = document.createElement('div');
            el.id = 'nodo-' + n.id;
            el.className = 'tv-nodo st-na' + (n.mapa_destino_id ? ' portal' : '');
            el.style.left = (n.x / 1600 * 100) + '%';
            el.style.top  = (n.y / 900 * 100) + '%';
            el.style.width = ancho + 'px';
            el.style.marginLeft = -(ancho / 2) + 'px';
            el.style.transform = 'translateY(-' + (ipx / 2) + 'px)';
            el.innerHTML = '<div class="chip" style="width:' + ipx + 'px;height:' + ipx + 'px;font-size:' + Math.round(ipx * 0.46) + 'px"><i class="bi ' + n.icono + '"></i></div>' +
                           '<div class="lbl" style="font-size:' + lpx + 'px">' + esc(n.etiqueta) + '</div>' +
                           '<div class="sub" style="font-size:' + Math.max(8, Math.round(lpx * 0.8)) + 'px">' + esc(n.host_name || '') + '</div>';
            stage.appendChild(el);
        });
        aplicarEstado();
    }

    function drawEdges() {
        const m = MAPAS[idx];
        svg.innerHTML = '';
        const COL = { up:'#22c55e', down:'#ef4444', downtime:'#f59e0b', na:'#475569' };
        m.enlaces.forEach(e => {
            const a = m.nodos.find(n => n.id === e.nodo_a_id), b = m.nodos.find(n => n.id === e.nodo_b_id);
            if (!a || !b) return;
            const ea = estados[e.nodo_a_id]?.estado, eb = estados[e.nodo_b_id]?.estado;
            let est = 'na';
            if (ea === 'down' || eb === 'down') est = 'down';
            else if (ea === 'downtime' || eb === 'downtime') est = 'downtime';
            else if (ea === 'up' || eb === 'up') est = 'up';
            const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            l.setAttribute('x1', a.x); l.setAttribute('y1', a.y);
            l.setAttribute('x2', b.x); l.setAttribute('y2', b.y);
            l.setAttribute('stroke', COL[est]);
            l.setAttribute('stroke-width', e.tipo === 'fibra' ? 4 : 2.5);
            l.setAttribute('vector-effect', 'non-scaling-stroke');
            if (e.tipo === 'inalambrico') l.setAttribute('stroke-dasharray', '8 7');
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
                const t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                t.setAttribute('x', (a.x + b.x) / 2);
                t.setAttribute('y', (a.y + b.y) / 2 - 10);
                t.setAttribute('text-anchor', 'middle');
                t.setAttribute('font-size', '16');
                t.setAttribute('fill', '#64748b');
                t.textContent = e.etiqueta;
                svg.appendChild(t);
            }
        });
    }

    function aplicarEstado() {
        const m = MAPAS[idx];
        m.nodos.forEach(n => {
            const el = document.getElementById('nodo-' + n.id);
            if (el) el.className = 'tv-nodo st-' + (estados[n.id]?.estado || 'na') + (n.mapa_destino_id ? ' portal' : '');
        });
        drawEdges();
    }

    async function refrescar() {
        const badge = document.getElementById('tvBadge');
        try {
            const r = await fetch(URL_ESTADO(MAPAS[idx].id), { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            if (!j.ok) throw new Error();
            estados = j.nodos;
            aplicarEstado();
            if (j.caidos > 0) {
                badge.className = 'tv-badge bad';
                badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + j.caidos + (j.caidos === 1 ? ' HOST CAÍDO' : ' HOSTS CAÍDOS');
                const caidos = MAPAS[idx].nodos.filter(n => estados[n.id]?.estado === 'down');
                document.getElementById('tvCaidos').innerHTML = caidos.slice(0, 6)
                    .map(n => '<div><i class="bi bi-x-octagon-fill"></i> ' + esc(n.etiqueta) + '</div>').join('');
            } else {
                badge.className = 'tv-badge ok';
                badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> TODO OPERATIVO';
                document.getElementById('tvCaidos').innerHTML = '';
            }
            document.getElementById('tvUpd').textContent = 'actualizado ' + j.ts;
        } catch {
            badge.className = 'tv-badge warn';
            badge.innerHTML = '<i class="bi bi-wifi-off"></i> SIN CONEXIÓN A CHECKMK';
        }
    }

    function reloj() {
        const d = new Date();
        document.getElementById('tvHora').textContent =
            String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
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
