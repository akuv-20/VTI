@extends('layouts.app')

@section('content')
<style>
    .mapv-toolbar { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .mapv-status { font-size:.8rem; font-weight:700; border-radius:20px; padding:.25rem .8rem; }
    .mapv-status.ok   { background:#dcfce7; color:#166534; }
    .mapv-status.bad  { background:#fee2e2; color:#991b1b; }
    .mapv-status.warn { background:#fef3c7; color:#92400e; }
    .mapv-wrap { display:flex; gap:.75rem; align-items:stretch; }
    .mapv-stage { position:relative; flex:1 1 auto; aspect-ratio:16/9; background:#f8fafc;
                  border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; touch-action:none;
                  background-image:radial-gradient(#e2e8f0 1px, transparent 1px); background-size:26px 26px; }
    .mapv-stage:fullscreen { background-color:#0f172a; background-image:radial-gradient(#1e293b 1px, transparent 1px); border:none; border-radius:0; aspect-ratio:auto; }
    .mapv-stage:fullscreen .mapv-nodo .lbl { color:#cbd5e1; }
    .mapv-stage svg { position:absolute; inset:0; width:100%; height:100%; z-index:1; }
    .mapv-fondo { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; pointer-events:none; z-index:0; }
    .mapv-nodo { position:absolute; width:110px; margin-left:-55px; text-align:center; user-select:none; cursor:pointer; z-index:2; }
    .mapv-nodo .chip { position:relative; width:48px; height:48px; margin:0 auto; border-radius:12px; display:flex; align-items:center;
                       justify-content:center; font-size:22px; border:2.5px solid #94a3b8; background:#fff; color:#64748b;
                       transition:border-color .2s, background .2s, color .2s; }
    .mapv-nodo.portal .chip { outline:2px solid #7c3aed; outline-offset:3px; }
    .mapv-nodo .pbadge { display:none; position:absolute; right:-9px; bottom:-9px; width:20px; height:20px; border-radius:50%;
                         background:#7c3aed; color:#fff; align-items:center; justify-content:center; font-size:10px; }
    .mapv-nodo.portal .pbadge { display:flex; }
    .mapv-nodo .dest { font-size:.6rem; color:#7c3aed; font-weight:700; }
    .mapv-nodo .lbl { font-size:.68rem; line-height:1.15; margin-top:3px; color:#475569; font-weight:600; word-break:break-word; }
    .mapv-nodo .sub { font-size:.6rem; color:#94a3b8; font-family:ui-monospace,monospace; }
    .mapv-nodo.st-up .chip       { border-color:#16a34a; background:#f0fdf4; color:#16a34a; }
    .mapv-nodo.st-down .chip     { border-color:#dc2626; background:#fef2f2; color:#dc2626; animation:mapvBlink 1s infinite; }
    .mapv-nodo.st-downtime .chip { border-color:#d97706; background:#fffbeb; color:#d97706; }
    .mapv-nodo.sel .chip { box-shadow:0 0 0 3px #bfdbfe; }
    .mapv-nodo.link-first .chip { box-shadow:0 0 0 3px #c7d2fe; }
    .mapv-nodo.dragging { opacity:.75; cursor:grabbing; z-index:3; }
    @keyframes mapvBlink { 50% { opacity:.5; } }
    .mapv-panel { width:300px; flex:0 0 300px; background:#fff; border:1px solid #e2e8f0; border-radius:10px;
                  padding:.9rem 1rem; font-size:.8rem; overflow-y:auto; max-height:75vh; }
    .mapv-panel h6 { font-size:.72rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.6rem; }
    .mapv-icons { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
    .mapv-icons button { border:1px solid #e2e8f0; background:#fff; border-radius:7px; padding:.3rem 0; font-size:15px; color:#64748b; }
    .mapv-icons button.on { border-color:#0ea5e9; background:#f0f9ff; color:#0284c7; }
    .mapv-tip { position:fixed; z-index:50; background:#0f172a; color:#e2e8f0; border-radius:8px; padding:.5rem .7rem;
                font-size:.72rem; max-width:280px; pointer-events:none; display:none; }
    .mapv-tip .t { font-weight:700; font-size:.76rem; }
    .mapv-hint { position:absolute; left:12px; bottom:8px; font-size:.72rem; color:#94a3b8; pointer-events:none; z-index:4; }
    .mapv-crumbs { display:flex; align-items:center; gap:.4rem; font-size:.8rem; color:#94a3b8; margin-bottom:.5rem; flex-wrap:wrap; }
    .mapv-crumbs a { color:#0284c7; text-decoration:none; font-weight:600; }
    .mapv-crumbs a:hover { text-decoration:underline; }
    .mapv-crumbs .cur { color:#334155; font-weight:700; }
    .mapv-mini { position:fixed; z-index:60; width:264px; background:#fff; border:1px solid #cbd5e1; border-radius:10px;
                 box-shadow:0 8px 24px rgba(15,23,42,.15); padding:.6rem .7rem; display:none; }
    .mapv-mini .mm-t { font-size:.76rem; font-weight:700; color:#334155; margin-bottom:.4rem; }
    .mapv-mini .mm-t i { color:#7c3aed; margin-right:.3rem; }
    .mapv-mini .mm-stage { position:relative; height:132px; background:#f8fafc; border:1px solid #eef2f7; border-radius:8px; overflow:hidden; }
    .mapv-mini .mm-stage svg { position:absolute; inset:0; width:100%; height:100%; }
    .mapv-mini .mm-dot { position:absolute; width:11px; height:11px; margin:-5.5px 0 0 -5.5px; border-radius:3px; }
    .mapv-mini .mm-res { font-size:.7rem; color:#475569; margin-top:.4rem; display:flex; gap:.7rem; flex-wrap:wrap; }
    .mapv-mini .mm-go { font-size:.68rem; color:#94a3b8; margin-top:.25rem; }
    .mapv-stage.saliendo { transition:transform .3s ease, opacity .3s ease; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-diagram-3 me-2" style="color:#0ea5e9"></i>{{ $mapa->nombre }}</h4>
        <a href="{{ route('admin.monitoreo.mapas.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Mapas
        </a>
    </div>

    <div class="mapv-crumbs" id="crumbs"></div>

    <div class="mapv-toolbar">
        <span class="mapv-status warn" id="stStatus"><i class="bi bi-hourglass-split me-1"></i>Consultando…</span>
        <span style="font-size:.72rem;color:#94a3b8" id="stTs"></span>

        <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
            <select id="selIntervalo" class="form-select form-select-sm" style="width:auto" title="Frecuencia de actualización">
                <option value="10">Cada 10 s</option>
                <option value="30" selected>Cada 30 s</option>
                <option value="60">Cada 1 min</option>
                <option value="300">Cada 5 min</option>
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFull" title="Pantalla completa">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
            @if($tvUrlMapa)
            <a href="{{ $tvUrlMapa }}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Abrir en modo TV">
                <i class="bi bi-tv"></i>
            </a>
            @endif
            <button type="button" class="btn btn-primary btn-sm" id="btnModo" @if(!$puedeEditar) style="display:none" @endif>
                <i class="bi bi-pencil me-1"></i>Editar mapa
            </button>
        </div>
    </div>

    <div class="mapv-wrap">
        <div class="mapv-stage" id="stage">
            @if($mapa->fondo_url)
                <img class="mapv-fondo" src="{{ $mapa->fondo_url }}" alt="" style="opacity:{{ $mapa->fondo_opacidad / 100 }}">
            @endif
            <svg id="svgEdges" viewBox="0 0 1600 900" preserveAspectRatio="none"></svg>
            <div class="mapv-hint" id="hint"></div>
        </div>

        {{-- ── Panel de edición ───────────────────────────────────────────── --}}
        <div class="mapv-panel" id="panel" style="display:none">

            <div id="pAgregar">
                <h6><i class="bi bi-plus-square me-1"></i>Agregar nodo</h6>
                <label class="form-label" style="font-size:.72rem">Host CheckMK <span class="text-muted">(vacío = decorativo)</span></label>
                <input type="text" id="fHost" class="form-control form-control-sm mb-2" list="dlHosts" placeholder="Buscar host…">
                <datalist id="dlHosts"></datalist>
                <label class="form-label" style="font-size:.72rem">Etiqueta</label>
                <input type="text" id="fEtiqueta" class="form-control form-control-sm mb-2" placeholder="Se autocompleta con el host">
                <label class="form-label" style="font-size:.72rem">Icono</label>
                <div class="mapv-icons mb-2" id="fIconos"></div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:.72rem">Icono: <span id="fTamIconoVal">48</span> px</label>
                        <input type="range" class="form-range" id="fTamIcono" min="24" max="128" step="2" value="48"
                               oninput="document.getElementById('fTamIconoVal').textContent=this.value">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:.72rem">Letra: <span id="fTamLetraVal">11</span> px</label>
                        <input type="range" class="form-range" id="fTamLetra" min="8" max="28" step="1" value="11"
                               oninput="document.getElementById('fTamLetraVal').textContent=this.value">
                    </div>
                </div>
                <label class="form-label" style="font-size:.72rem">Al hacer clic abre el mapa… <span class="text-muted">(drill-down)</span></label>
                <select id="fDestino" class="form-select form-select-sm mb-2">
                    <option value="">— Ninguno —</option>
                    @foreach($otrosMapas as $om)
                        <option value="{{ $om->id }}">{{ $om->nombre }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-success btn-sm w-100" id="btnAddNodo">
                    <i class="bi bi-plus-lg me-1"></i>Agregar al mapa
                </button>
                <hr>
                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btnEnlazar">
                    <i class="bi bi-bezier2 me-1"></i>Enlazar: clic en 2 nodos
                </button>
                <div class="text-muted mt-2" style="font-size:.7rem">
                    Arrastra los nodos para ubicarlos (se guarda solo). Clic en un nodo o enlace para editarlo.
                </div>

                <hr>
                <h6><i class="bi bi-gear me-1"></i>Configurar mapa</h6>
                <form method="POST" action="{{ route('admin.monitoreo.mapas.update', $mapa) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    @if($esAdmin)
                    <input type="text" name="nombre" class="form-control form-control-sm mb-2" value="{{ $mapa->nombre }}" required>
                    <input type="text" name="descripcion" class="form-control form-control-sm mb-2" value="{{ $mapa->descripcion }}" placeholder="Descripción">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="en_tv" value="1" id="fEnTv" @checked($mapa->en_tv)>
                        <label class="form-check-label" for="fEnTv" style="font-size:.75rem">Incluir en la rotación del modo TV</label>
                    </div>
                    @endif
                    <label class="form-label" style="font-size:.72rem">
                        Imagen de fondo <span class="text-muted">(plano de la planta — JPG/PNG, máx 8 MB)</span>
                    </label>
                    <input type="file" name="imagen_fondo" class="form-control form-control-sm mb-2" accept="image/*">
                    <label class="form-label" style="font-size:.72rem">Opacidad del fondo: <span id="opVal">{{ $mapa->fondo_opacidad }}</span>%</label>
                    <input type="range" name="fondo_opacidad" class="form-range mb-1" min="10" max="100" step="5"
                           value="{{ $mapa->fondo_opacidad }}"
                           oninput="document.getElementById('opVal').textContent=this.value; document.querySelector('.mapv-fondo') && (document.querySelector('.mapv-fondo').style.opacity=this.value/100)">
                    @if($mapa->fondo_url)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="quitar_fondo" value="1" id="fQuitarFondo">
                        <label class="form-check-label" for="fQuitarFondo" style="font-size:.75rem">Quitar imagen de fondo actual</label>
                    </div>
                    @endif
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100 mb-2"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
                </form>
                @if($esAdmin)
                <form method="POST" action="{{ route('admin.monitoreo.mapas.destroy', $mapa) }}"
                      onsubmit="return confirm('¿Eliminar este mapa con todos sus nodos y enlaces?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i>Eliminar mapa</button>
                </form>
                @endif
            </div>

            <div id="pNodo" style="display:none">
                <h6><i class="bi bi-pencil me-1"></i>Editar nodo</h6>
                <label class="form-label" style="font-size:.72rem">Host CheckMK</label>
                <input type="text" id="eHost" class="form-control form-control-sm mb-2" list="dlHosts">
                <label class="form-label" style="font-size:.72rem">Etiqueta</label>
                <input type="text" id="eEtiqueta" class="form-control form-control-sm mb-2">
                <label class="form-label" style="font-size:.72rem">Icono</label>
                <div class="mapv-icons mb-2" id="eIconos"></div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:.72rem">Icono: <span id="eTamIconoVal">48</span> px</label>
                        <input type="range" class="form-range" id="eTamIcono" min="24" max="128" step="2" value="48">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:.72rem">Letra: <span id="eTamLetraVal">11</span> px</label>
                        <input type="range" class="form-range" id="eTamLetra" min="8" max="28" step="1" value="11">
                    </div>
                </div>
                <label class="form-label" style="font-size:.72rem">Al hacer clic abre el mapa…</label>
                <select id="eDestino" class="form-select form-select-sm mb-2">
                    <option value="">— Ninguno —</option>
                    @foreach($otrosMapas as $om)
                        <option value="{{ $om->id }}">{{ $om->nombre }}</option>
                    @endforeach
                </select>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="btnNodoSave"><i class="bi bi-check-lg"></i> Guardar</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnNodoDel" title="Eliminar nodo"><i class="bi bi-trash"></i></button>
                </div>
                <button type="button" class="btn btn-link btn-sm w-100 mt-1" id="btnNodoCerrar" style="font-size:.72rem">← Volver</button>
            </div>

            <div id="pEnlace" style="display:none">
                <h6><i class="bi bi-bezier2 me-1"></i>Editar enlace</h6>
                <div class="text-muted mb-2" style="font-size:.72rem" id="eEnlaceNombre"></div>
                <label class="form-label" style="font-size:.72rem">Tipo</label>
                <select id="eTipo" class="form-select form-select-sm mb-2">
                    @foreach($tipos as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
                <label class="form-label" style="font-size:.72rem">Etiqueta <span class="text-muted">(opcional)</span></label>
                <input type="text" id="eEnlaceEtiqueta" class="form-control form-control-sm mb-2" placeholder="PtP 5GHz Ubiquiti">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="btnEnlaceSave"><i class="bi bi-check-lg"></i> Guardar</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnEnlaceDel" title="Eliminar enlace"><i class="bi bi-trash"></i></button>
                </div>
                <button type="button" class="btn btn-link btn-sm w-100 mt-1" id="btnEnlaceCerrar" style="font-size:.72rem">← Volver</button>
            </div>
        </div>
    </div>
</div>

<div class="mapv-tip" id="tip"></div>
<div class="mapv-mini" id="mini"></div>

@push('scripts')
<script>
(() => {
    const CSRF   = '{{ csrf_token() }}';
    const ICONOS = @json($iconos);
    const TIPOS  = @json($tipos);
    const NOMBRES_MAPAS = @json($nombresMapas);
    const MAPA_ID = {{ $mapa->id }};
    const MAPA_NOMBRE = @json($mapa->nombre);
    const PUEDE_EDITAR = @json($puedeEditar);
    const MAPAS_VISIBLES = @json($mapasVisibles);
    // Portal solo si el usuario puede ver el mapa destino.
    const esPortal = n => !!n.mapa_destino_id && MAPAS_VISIBLES.includes(n.mapa_destino_id);
    let   nodos   = @json($nodosData);
    let   enlaces = @json($enlacesData);

    const URL_ESTADO  = '{{ route('admin.monitoreo.mapas.estado', $mapa) }}';
    const URL_HOSTS   = '{{ route('admin.monitoreo.mapas.hosts') }}';
    const URL_NODOS   = '{{ route('admin.monitoreo.mapas.nodos.store', $mapa) }}';
    const URL_NODO    = id => '{{ url('admin/monitoreo/nodos') }}/' + id;
    const URL_ENLACES = '{{ route('admin.monitoreo.mapas.enlaces.store', $mapa) }}';
    const URL_ENLACE  = id => '{{ url('admin/monitoreo/enlaces') }}/' + id;
    const URL_MAPA    = id => '{{ url('admin/monitoreo/mapas') }}/' + id;

    const stage = document.getElementById('stage');
    const svg   = document.getElementById('svgEdges');
    const tip   = document.getElementById('tip');
    const hint  = document.getElementById('hint');

    let modoEdicion = false, modoEnlace = false, enlacePrimero = null;
    let nodoSel = null, enlaceSel = null;
    let estados = {};
    let timer = null;

    const api = (url, method, body) => fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    }).then(async r => {
        const j = await r.json().catch(() => ({}));
        if (!r.ok || j.ok === false) throw new Error(j.error || (j.message || 'Error HTTP ' + r.status));
        return j;
    });

    /* ── Render de nodos ─────────────────────────────────────────────────── */

    const pct = (v, max) => (v / max * 100) + '%';

    function renderNodo(n) {
        let el = document.getElementById('nodo-' + n.id);
        if (!el) {
            el = document.createElement('div');
            el.id = 'nodo-' + n.id;
            el.className = 'mapv-nodo st-na';
            el.innerHTML = '<div class="chip"><i class="bi"></i><span class="pbadge"><i class="bi bi-arrows-angle-expand"></i></span></div>' +
                           '<div class="lbl"></div><div class="sub"></div><div class="dest"></div>';
            stage.appendChild(el);
            wireNodo(el, n.id);
        }
        const ipx = n.icono_px || 48, lpx = n.letra_px || 11;
        const ancho = Math.max(110, ipx + 50);
        el.style.left = pct(n.x, 1600);
        el.style.top  = pct(n.y, 900);
        el.style.width = ancho + 'px';
        el.style.marginLeft = -(ancho / 2) + 'px';
        el.style.transform = 'translateY(-' + (ipx / 2) + 'px)';
        el.classList.toggle('portal', esPortal(n));
        const chip = el.querySelector('.chip');
        chip.style.width = ipx + 'px';
        chip.style.height = ipx + 'px';
        chip.style.fontSize = Math.round(ipx * 0.46) + 'px';
        el.querySelector('.chip > i').className = 'bi ' + n.icono;
        const lbl = el.querySelector('.lbl');
        lbl.textContent = n.etiqueta;
        lbl.style.fontSize = lpx + 'px';
        el.querySelector('.sub').style.fontSize = Math.max(8, Math.round(lpx * 0.85)) + 'px';
        el.querySelector('.sub').textContent = n.host_name || '';
        el.querySelector('.dest').textContent = esPortal(n) ? '⌁ ' + (NOMBRES_MAPAS[n.mapa_destino_id] || 'mapa') : '';
    }

    function wireNodo(el, id) {
        let drag = null, moved = false;

        el.addEventListener('pointerdown', ev => {
            if (!modoEdicion || modoEnlace) return;
            const n = nodos.find(x => x.id === id);
            const r = stage.getBoundingClientRect();
            drag = { dx: n.x - (ev.clientX - r.left) / r.width * 1600,
                     dy: n.y - (ev.clientY - r.top)  / r.height * 900 };
            moved = false;
            el.setPointerCapture(ev.pointerId);
            el.classList.add('dragging');
        });

        el.addEventListener('pointermove', ev => {
            if (!drag) return;
            const n = nodos.find(x => x.id === id);
            const r = stage.getBoundingClientRect();
            n.x = Math.round(Math.min(1560, Math.max(40, (ev.clientX - r.left) / r.width * 1600 + drag.dx)) / 10) * 10;
            n.y = Math.round(Math.min(850, Math.max(40, (ev.clientY - r.top) / r.height * 900 + drag.dy)) / 10) * 10;
            moved = true;
            renderNodo(n);
            drawEdges();
        });

        el.addEventListener('pointerup', () => {
            el.classList.remove('dragging');
            if (drag && moved) {
                const n = nodos.find(x => x.id === id);
                api(URL_NODO(id), 'PUT', { x: n.x, y: n.y }).catch(e => alert(e.message));
            }
            drag = null;
        });

        el.addEventListener('click', ev => {
            ev.stopPropagation();
            if (moved) { moved = false; return; }
            const n = nodos.find(x => x.id === id);
            if (modoEnlace) { elegirParaEnlace(n); return; }
            if (modoEdicion) { seleccionarNodo(n); return; }
            if (esPortal(n)) irAlMapa(n);
        });

        el.addEventListener('mouseenter', ev => {
            const n = nodos.find(x => x.id === id);
            if (!modoEdicion && esPortal(n)) { programarMini(n); return; }
            mostrarTip(id, ev);
        });
        el.addEventListener('mousemove', ev => { if (tip.style.display === 'block') { tip.style.left = (ev.clientX + 14) + 'px'; tip.style.top = (ev.clientY + 14) + 'px'; } });
        el.addEventListener('mouseleave', () => { tip.style.display = 'none'; cancelarMini(); });
    }

    function mostrarTip(id, ev) {
        const n = nodos.find(x => x.id === id);
        const e = estados[id] || {};
        const est = { up: '🟢 En línea', down: '🔴 Caído', downtime: '🟠 Mantención programada', na: '⚪ Sin datos' }[e.estado || 'na'];
        tip.innerHTML = '<div class="t">' + esc(n.etiqueta) + '</div>' +
            (n.host_name ? '<div style="font-family:monospace">' + esc(n.host_name) + '</div>' : '') +
            '<div>' + est + (e.desde ? ' · desde ' + esc(e.desde) : '') + '</div>' +
            (e.detalle ? '<div style="color:#94a3b8;margin-top:2px">' + esc(e.detalle) + '</div>' : '') +
            (!modoEdicion && esPortal(n) ? '<div style="color:#7dd3fc;margin-top:2px">Clic para abrir el mapa ↗</div>' : '');
        tip.style.display = 'block';
        tip.style.left = (ev.clientX + 14) + 'px';
        tip.style.top  = (ev.clientY + 14) + 'px';
    }

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    /* ── Render de enlaces ───────────────────────────────────────────────── */

    function estadoEnlace(e) {
        const a = estados[e.nodo_a_id]?.estado, b = estados[e.nodo_b_id]?.estado;
        if (a === 'down' || b === 'down') return 'down';
        if (a === 'downtime' || b === 'downtime') return 'downtime';
        if (a === 'up' && b === 'up') return 'up';
        if (a === 'up' || b === 'up') return 'up';
        return 'na';
    }

    function drawEdges() {
        svg.innerHTML = '';
        const COLORES = { up:'#16a34a', down:'#dc2626', downtime:'#d97706', na:'#94a3b8' };
        enlaces.forEach(e => {
            const a = nodos.find(n => n.id === e.nodo_a_id), b = nodos.find(n => n.id === e.nodo_b_id);
            if (!a || !b) return;
            const est = estadoEnlace(e);
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');

            const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            l.setAttribute('x1', a.x); l.setAttribute('y1', a.y);
            l.setAttribute('x2', b.x); l.setAttribute('y2', b.y);
            l.setAttribute('stroke', COLORES[est]);
            l.setAttribute('stroke-width', e.tipo === 'fibra' ? 3.5 : 2);
            l.setAttribute('vector-effect', 'non-scaling-stroke');
            if (e.tipo === 'inalambrico') l.setAttribute('stroke-dasharray', '7 6');
            if (est === 'down') {
                l.setAttribute('stroke-dasharray', '7 6');
                const an = document.createElementNS('http://www.w3.org/2000/svg', 'animate');
                an.setAttribute('attributeName', 'stroke-dashoffset');
                an.setAttribute('from', '26'); an.setAttribute('to', '0');
                an.setAttribute('dur', '0.8s'); an.setAttribute('repeatCount', 'indefinite');
                l.appendChild(an);
            }
            g.appendChild(l);

            const hit = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            hit.setAttribute('x1', a.x); hit.setAttribute('y1', a.y);
            hit.setAttribute('x2', b.x); hit.setAttribute('y2', b.y);
            hit.setAttribute('stroke', 'transparent');
            hit.setAttribute('stroke-width', 16);
            hit.setAttribute('vector-effect', 'non-scaling-stroke');
            hit.style.cursor = 'pointer';
            hit.addEventListener('click', ev => { ev.stopPropagation(); if (modoEdicion) seleccionarEnlace(e); });
            g.appendChild(hit);

            if (e.etiqueta) {
                const t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                t.setAttribute('x', (a.x + b.x) / 2);
                t.setAttribute('y', (a.y + b.y) / 2 - 8);
                t.setAttribute('text-anchor', 'middle');
                t.setAttribute('font-size', '15');
                t.setAttribute('fill', '#64748b');
                t.textContent = e.etiqueta;
                g.appendChild(t);
            }
            svg.appendChild(g);
        });
    }

    /* ── Estado en vivo ──────────────────────────────────────────────────── */

    async function refrescar() {
        const st = document.getElementById('stStatus');
        try {
            const r = await fetch(URL_ESTADO, { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            if (!j.ok) throw new Error(j.error || 'Error');
            estados = j.nodos;
            nodos.forEach(n => {
                const el = document.getElementById('nodo-' + n.id);
                if (el) el.className = 'mapv-nodo st-' + (estados[n.id]?.estado || 'na') +
                    (esPortal(n) ? ' portal' : '') +
                    (nodoSel?.id === n.id ? ' sel' : '') + (enlacePrimero?.id === n.id ? ' link-first' : '');
            });
            drawEdges();
            if (j.caidos > 0) {
                st.className = 'mapv-status bad';
                st.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + j.caidos + (j.caidos === 1 ? ' host caído' : ' hosts caídos');
            } else {
                st.className = 'mapv-status ok';
                st.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Todo operativo';
            }
            document.getElementById('stTs').textContent = 'Actualizado ' + j.ts;
        } catch (e) {
            st.className = 'mapv-status warn';
            st.innerHTML = '<i class="bi bi-wifi-off me-1"></i>CheckMK sin conexión';
        }
    }

    function programar() {
        clearInterval(timer);
        const s = parseInt(document.getElementById('selIntervalo').value, 10);
        localStorage.setItem('mapv_intervalo', s);
        timer = setInterval(refrescar, s * 1000);
    }

    document.getElementById('selIntervalo').addEventListener('change', programar);
    const guardado = localStorage.getItem('mapv_intervalo');
    if (guardado) document.getElementById('selIntervalo').value = guardado;

    /* ── Modo edición ────────────────────────────────────────────────────── */

    function setModo(edit) {
        if (edit && !PUEDE_EDITAR) return;
        modoEdicion = edit;
        document.getElementById('panel').style.display = edit ? '' : 'none';
        const b = document.getElementById('btnModo');
        b.innerHTML = edit ? '<i class="bi bi-eye me-1"></i>Ver en vivo' : '<i class="bi bi-pencil me-1"></i>Editar mapa';
        b.className = edit ? 'btn btn-secondary btn-sm' : 'btn btn-primary btn-sm';
        hint.textContent = edit ? 'Modo edición: arrastra para mover · clic para editar' : '';
        if (!edit) { cancelarEnlace(); cerrarPaneles(); }
    }
    document.getElementById('btnModo').addEventListener('click', () => setModo(!modoEdicion));

    /* ── Iconos (grids) ──────────────────────────────────────────────────── */

    function gridIconos(cont, inicial, onPick) {
        cont.innerHTML = '';
        Object.entries(ICONOS).forEach(([cls, nombre]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.title = nombre;
            b.innerHTML = '<i class="bi ' + cls + '"></i>';
            if (cls === inicial) b.classList.add('on');
            b.addEventListener('click', () => {
                cont.querySelectorAll('button').forEach(x => x.classList.remove('on'));
                b.classList.add('on');
                onPick(cls);
            });
            cont.appendChild(b);
        });
    }

    let iconoNuevo = 'bi-hdd-network';
    gridIconos(document.getElementById('fIconos'), iconoNuevo, c => iconoNuevo = c);

    /* ── Agregar nodo ────────────────────────────────────────────────────── */

    fetch(URL_HOSTS, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) return;
            const dl = document.getElementById('dlHosts');
            j.hosts.forEach(h => { const o = document.createElement('option'); o.value = h; dl.appendChild(o); });
        }).catch(() => {});

    document.getElementById('fHost').addEventListener('change', ev => {
        const et = document.getElementById('fEtiqueta');
        if (!et.value) et.value = ev.target.value;
    });

    document.getElementById('btnAddNodo').addEventListener('click', () => {
        const host = document.getElementById('fHost').value.trim();
        const etiqueta = document.getElementById('fEtiqueta').value.trim() || host;
        if (!etiqueta) { alert('Indica un host o una etiqueta.'); return; }
        api(URL_NODOS, 'POST', {
            host_name: host || null,
            etiqueta,
            icono: iconoNuevo,
            icono_px: parseInt(document.getElementById('fTamIcono').value, 10),
            letra_px: parseInt(document.getElementById('fTamLetra').value, 10),
            x: 760 + Math.round(Math.random() * 80),
            y: 410 + Math.round(Math.random() * 80),
            mapa_destino_id: document.getElementById('fDestino').value || null,
        }).then(j => {
            nodos.push(j.nodo);
            renderNodo(j.nodo);
            drawEdges();
            refrescar();
            document.getElementById('fHost').value = '';
            document.getElementById('fEtiqueta').value = '';
            document.getElementById('fDestino').value = '';
        }).catch(e => alert(e.message));
    });

    /* ── Editar nodo ─────────────────────────────────────────────────────── */

    let iconoEdit = null, selBackup = null;

    function seleccionarNodo(n) {
        cerrarPaneles();
        nodoSel = n;
        selBackup = { icono_px: n.icono_px || 48, letra_px: n.letra_px || 11 };
        document.getElementById('nodo-' + n.id)?.classList.add('sel');
        document.getElementById('pAgregar').style.display = 'none';
        document.getElementById('pNodo').style.display = '';
        document.getElementById('eHost').value = n.host_name || '';
        document.getElementById('eEtiqueta').value = n.etiqueta;
        document.getElementById('eDestino').value = n.mapa_destino_id || '';
        iconoEdit = n.icono;
        gridIconos(document.getElementById('eIconos'), n.icono, c => iconoEdit = c);
        document.getElementById('eTamIcono').value = n.icono_px || 48;
        document.getElementById('eTamIconoVal').textContent = n.icono_px || 48;
        document.getElementById('eTamLetra').value = n.letra_px || 11;
        document.getElementById('eTamLetraVal').textContent = n.letra_px || 11;
    }

    // Vista previa en vivo de los tamaños mientras se mueve el slider.
    document.getElementById('eTamIcono').addEventListener('input', ev => {
        document.getElementById('eTamIconoVal').textContent = ev.target.value;
        if (nodoSel) { nodoSel.icono_px = parseInt(ev.target.value, 10); renderNodo(nodoSel); drawEdges(); }
    });
    document.getElementById('eTamLetra').addEventListener('input', ev => {
        document.getElementById('eTamLetraVal').textContent = ev.target.value;
        if (nodoSel) { nodoSel.letra_px = parseInt(ev.target.value, 10); renderNodo(nodoSel); }
    });

    document.getElementById('btnNodoSave').addEventListener('click', () => {
        if (!nodoSel) return;
        api(URL_NODO(nodoSel.id), 'PUT', {
            host_name: document.getElementById('eHost').value.trim() || null,
            etiqueta:  document.getElementById('eEtiqueta').value.trim(),
            icono:     iconoEdit,
            icono_px:  parseInt(document.getElementById('eTamIcono').value, 10),
            letra_px:  parseInt(document.getElementById('eTamLetra').value, 10),
            mapa_destino_id: document.getElementById('eDestino').value || null,
        }).then(j => {
            Object.assign(nodoSel, j.nodo);
            selBackup = null; // guardado: no revertir la vista previa
            renderNodo(nodoSel);
            refrescar();
            cerrarPaneles();
        }).catch(e => alert(e.message));
    });

    document.getElementById('btnNodoDel').addEventListener('click', () => {
        if (!nodoSel || !confirm('¿Eliminar este nodo y sus enlaces?')) return;
        api(URL_NODO(nodoSel.id), 'DELETE').then(() => {
            document.getElementById('nodo-' + nodoSel.id)?.remove();
            enlaces = enlaces.filter(e => e.nodo_a_id !== nodoSel.id && e.nodo_b_id !== nodoSel.id);
            nodos = nodos.filter(n => n.id !== nodoSel.id);
            drawEdges();
            cerrarPaneles();
        }).catch(e => alert(e.message));
    });

    document.getElementById('btnNodoCerrar').addEventListener('click', cerrarPaneles);

    /* ── Enlazar ─────────────────────────────────────────────────────────── */

    function elegirParaEnlace(n) {
        if (!enlacePrimero) {
            enlacePrimero = n;
            document.getElementById('nodo-' + n.id)?.classList.add('link-first');
            hint.textContent = 'Enlace: «' + n.etiqueta + '» → elige el segundo nodo (Esc cancela)';
            return;
        }
        if (enlacePrimero.id === n.id) return;
        api(URL_ENLACES, 'POST', { nodo_a_id: enlacePrimero.id, nodo_b_id: n.id, tipo: 'cable', etiqueta: null })
            .then(j => {
                enlaces.push(j.enlace);
                cancelarEnlace();
                drawEdges();
                seleccionarEnlace(enlaces[enlaces.length - 1]);
            }).catch(e => { alert(e.message); cancelarEnlace(); });
    }

    function cancelarEnlace() {
        if (enlacePrimero) document.getElementById('nodo-' + enlacePrimero.id)?.classList.remove('link-first');
        enlacePrimero = null;
        modoEnlace = false;
        document.getElementById('btnEnlazar').classList.remove('active');
        hint.textContent = modoEdicion ? 'Modo edición: arrastra para mover · clic para editar' : '';
    }

    document.getElementById('btnEnlazar').addEventListener('click', () => {
        modoEnlace = !modoEnlace;
        document.getElementById('btnEnlazar').classList.toggle('active', modoEnlace);
        if (modoEnlace) hint.textContent = 'Enlace: haz clic en el primer nodo';
        else cancelarEnlace();
    });

    document.addEventListener('keydown', ev => { if (ev.key === 'Escape') { cancelarEnlace(); cerrarPaneles(); } });

    /* ── Editar enlace ───────────────────────────────────────────────────── */

    function seleccionarEnlace(e) {
        cerrarPaneles();
        enlaceSel = e;
        const a = nodos.find(n => n.id === e.nodo_a_id), b = nodos.find(n => n.id === e.nodo_b_id);
        document.getElementById('pAgregar').style.display = 'none';
        document.getElementById('pEnlace').style.display = '';
        document.getElementById('eEnlaceNombre').textContent = (a?.etiqueta || '?') + '  ⟷  ' + (b?.etiqueta || '?');
        document.getElementById('eTipo').value = e.tipo;
        document.getElementById('eEnlaceEtiqueta').value = e.etiqueta || '';
    }

    document.getElementById('btnEnlaceSave').addEventListener('click', () => {
        if (!enlaceSel) return;
        api(URL_ENLACE(enlaceSel.id), 'PUT', {
            tipo: document.getElementById('eTipo').value,
            etiqueta: document.getElementById('eEnlaceEtiqueta').value.trim() || null,
        }).then(j => {
            Object.assign(enlaceSel, j.enlace);
            drawEdges();
            cerrarPaneles();
        }).catch(e => alert(e.message));
    });

    document.getElementById('btnEnlaceDel').addEventListener('click', () => {
        if (!enlaceSel || !confirm('¿Eliminar este enlace?')) return;
        api(URL_ENLACE(enlaceSel.id), 'DELETE').then(() => {
            enlaces = enlaces.filter(x => x.id !== enlaceSel.id);
            drawEdges();
            cerrarPaneles();
        }).catch(e => alert(e.message));
    });

    document.getElementById('btnEnlaceCerrar').addEventListener('click', cerrarPaneles);

    function cerrarPaneles() {
        if (nodoSel) {
            document.getElementById('nodo-' + nodoSel.id)?.classList.remove('sel');
            // Revertir vista previa de tamaños si se cerró sin guardar.
            if (selBackup) { Object.assign(nodoSel, selBackup); renderNodo(nodoSel); drawEdges(); }
        }
        nodoSel = null;
        selBackup = null;
        enlaceSel = null;
        document.getElementById('pNodo').style.display = 'none';
        document.getElementById('pEnlace').style.display = 'none';
        document.getElementById('pAgregar').style.display = '';
    }

    /* ── Portales: navegación, migas y miniatura ─────────────────────────── */

    function leerTrail() {
        try { return JSON.parse(sessionStorage.getItem('mapv_trail') || '[]'); } catch { return []; }
    }

    function irAlMapa(n) {
        const trail = leerTrail();
        trail.push({ id: MAPA_ID, nombre: MAPA_NOMBRE });
        sessionStorage.setItem('mapv_trail', JSON.stringify(trail));
        ocultarMini();
        // Transición: zoom hacia el nodo antes de entrar al mapa destino.
        stage.classList.add('saliendo');
        stage.style.transformOrigin = (n.x / 16) + '% ' + (n.y / 9) + '%';
        stage.style.transform = 'scale(1.6)';
        stage.style.opacity = '0.25';
        setTimeout(() => window.location = URL_MAPA(n.mapa_destino_id), 290);
    }

    function pintarCrumbs() {
        let trail = leerTrail();
        // Si volvimos a un mapa que ya estaba en la ruta (botón atrás), truncar.
        const yo = trail.findIndex(t => t.id === MAPA_ID);
        if (yo !== -1) {
            trail = trail.slice(0, yo);
            sessionStorage.setItem('mapv_trail', JSON.stringify(trail));
        }
        const box = document.getElementById('crumbs');
        box.innerHTML = '';
        const raiz = document.createElement('a');
        raiz.href = '{{ route('admin.monitoreo.mapas.index') }}';
        raiz.innerHTML = '<i class="bi bi-map me-1"></i>Mapas';
        raiz.addEventListener('click', () => sessionStorage.removeItem('mapv_trail'));
        box.appendChild(raiz);
        trail.forEach((t, k) => {
            box.insertAdjacentHTML('beforeend', '<i class="bi bi-chevron-right" style="font-size:.65rem"></i>');
            const a = document.createElement('a');
            a.href = URL_MAPA(t.id);
            a.textContent = t.nombre;
            a.addEventListener('click', ev => {
                ev.preventDefault();
                sessionStorage.setItem('mapv_trail', JSON.stringify(trail.slice(0, k)));
                window.location = URL_MAPA(t.id);
            });
            box.appendChild(a);
        });
        box.insertAdjacentHTML('beforeend', '<i class="bi bi-chevron-right" style="font-size:.65rem"></i><span class="cur">' + esc(MAPA_NOMBRE) + '</span>');
    }

    /* ── Miniatura en vivo (hover sobre nodo portal) ─────────────────────── */

    const mini = document.getElementById('mini');
    let miniTimer = null, miniNodo = null;
    const miniCache = {};

    function programarMini(n) {
        cancelarMini();
        miniTimer = setTimeout(() => mostrarMini(n), 220);
    }

    function cancelarMini() {
        clearTimeout(miniTimer);
        // pequeña gracia para poder mover el mouse hacia la tarjeta
        setTimeout(() => { if (!mini.matches(':hover')) ocultarMini(); }, 120);
    }

    function ocultarMini() { mini.style.display = 'none'; miniNodo = null; }
    mini.addEventListener('mouseleave', ocultarMini);
    mini.addEventListener('click', () => { const n = miniNodo; if (n) irAlMapa(n); });

    async function mostrarMini(n) {
        miniNodo = n;
        let d = miniCache[n.mapa_destino_id];
        if (!d || Date.now() - d.ts > 15000) {
            try {
                const r = await fetch(URL_MAPA(n.mapa_destino_id) + '/preview', { headers: { 'Accept': 'application/json' } });
                d = { ts: Date.now(), j: await r.json() };
                miniCache[n.mapa_destino_id] = d;
            } catch { return; }
        }
        if (miniNodo?.id !== n.id) return; // el mouse ya salió
        const j = d.j;
        if (!j.ok) return;

        const COL = { up:'#16a34a', down:'#dc2626', downtime:'#d97706', na:'#94a3b8' };
        let svgL = '', dots = '';
        j.enlaces.forEach(e => {
            const a = j.nodos.find(x => x.id === e.nodo_a_id), b = j.nodos.find(x => x.id === e.nodo_b_id);
            if (!a || !b) return;
            const ea = j.estados[e.nodo_a_id]?.estado, eb = j.estados[e.nodo_b_id]?.estado;
            const col = (ea === 'down' || eb === 'down') ? COL.down : COL.up;
            svgL += '<line x1="' + (a.x/16) + '%" y1="' + (a.y/9) + '%" x2="' + (b.x/16) + '%" y2="' + (b.y/9) + '%" stroke="' + col + '" stroke-width="1.4"/>';
        });
        let ok = 0, caidos = [], mant = 0;
        j.nodos.forEach(x => {
            const e = j.estados[x.id]?.estado || 'na';
            if (e === 'up') ok++;
            else if (e === 'down') caidos.push(x.etiqueta);
            else if (e === 'downtime') mant++;
            dots += '<span class="mm-dot" title="' + esc(x.etiqueta) + '" style="left:' + (x.x/16) + '%;top:' + (x.y/9) + '%;background:' + COL[e] + '"></span>';
        });

        mini.innerHTML =
            '<div class="mm-t"><i class="bi bi-map"></i>' + esc(j.nombre) + ' — vista previa en vivo</div>' +
            '<div class="mm-stage"><svg>' + svgL + '</svg>' + dots + '</div>' +
            '<div class="mm-res">' +
                '<span style="color:#16a34a"><i class="bi bi-check-circle-fill me-1"></i>' + ok + ' ok</span>' +
                (caidos.length ? '<span style="color:#dc2626"><i class="bi bi-x-octagon-fill me-1"></i>' + caidos.length + (caidos.length === 1 ? ' caído' : ' caídos') + ' · ' + esc(caidos.slice(0, 2).join(', ')) + (caidos.length > 2 ? '…' : '') + '</span>' : '') +
                (mant ? '<span style="color:#d97706"><i class="bi bi-cone-striped me-1"></i>' + mant + ' en mantención</span>' : '') +
            '</div>' +
            '<div class="mm-go">clic para entrar al detalle →</div>';

        // Posicionar junto al nodo (derecha; si no cabe, izquierda).
        const r = document.getElementById('nodo-' + n.id).getBoundingClientRect();
        mini.style.display = 'block';
        const w = mini.offsetWidth, h = mini.offsetHeight;
        let left = r.right + 12;
        if (left + w > window.innerWidth - 8) left = r.left - w - 12;
        mini.style.left = Math.max(8, left) + 'px';
        mini.style.top  = Math.max(8, Math.min(window.innerHeight - h - 8, r.top - 20)) + 'px';
    }

    /* ── Pantalla completa ───────────────────────────────────────────────── */

    document.getElementById('btnFull').addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen();
        else stage.requestFullscreen();
    });

    stage.addEventListener('click', () => { if (modoEdicion && !modoEnlace) cerrarPaneles(); });

    /* ── Init ────────────────────────────────────────────────────────────── */

    pintarCrumbs();
    nodos.forEach(renderNodo);
    drawEdges();
    refrescar();
    programar();

    if (nodos.length === 0 && PUEDE_EDITAR) setModo(true);
})();
</script>
@endpush
@endsection
