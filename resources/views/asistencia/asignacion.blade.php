@extends('layouts.app')

@section('content')
<style>
    .as-col { height:calc(100vh - 235px); min-height:480px; display:flex; flex-direction:column; }
    .as-col > .card-body { overflow-y:auto; flex:1; }
    .as-col-head { border-bottom:1px solid #e2e8f0; padding:.6rem .75rem; background:#fff; border-radius:.5rem .5rem 0 0; }
    .as-col-head .titulo { font-weight:600; font-size:.88rem; }

    /* Tarjetas de ticket */
    .as-ticket { border:1px solid #e2e8f0; border-left:4px solid #94a3b8; border-radius:.5rem; padding:.55rem .7rem;
                 margin-bottom:.5rem; background:#fff; cursor:pointer; transition:box-shadow .1s, transform .08s; }
    .as-ticket:hover { box-shadow:0 2px 10px rgba(0,0,0,.08); }
    .as-ticket.activo { border-color:#2563eb; box-shadow:0 0 0 2px #bfdbfe; }
    .as-ticket.arrastrando { opacity:.45; }
    .as-ticket.recien { animation:as-flash 2.5s ease-out; }
    @keyframes as-flash { from { background:#dbeafe; } to { background:#fff; } }
    .as-ticket.edad-verde   { border-left-color:#16a34a; }
    .as-ticket.edad-naranja { border-left-color:#f59e0b; }
    .as-ticket.edad-rojo    { border-left-color:#dc2626; }
    .as-ticket .t-titulo { font-weight:600; font-size:.85rem; line-height:1.25; }
    .as-ticket .t-meta { font-size:.72rem; color:#64748b; }
    .as-ticket .t-resumen { font-size:.75rem; color:#475569; margin-top:.2rem;
                            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .as-edad { font-size:.7rem; font-weight:700; border-radius:999px; padding:.05rem .45rem; white-space:nowrap; }
    .as-edad.verde   { background:#dcfce7; color:#15803d; }
    .as-edad.naranja { background:#fef3c7; color:#b45309; }
    .as-edad.rojo    { background:#fee2e2; color:#b91c1c; }
    .as-chip { display:inline-block; font-size:.7rem; background:#f1f5f9; color:#334155; border-radius:.3rem;
               padding:.1rem .4rem; margin:0 .2rem .2rem 0; }

    /* Detalle */
    .as-desc { white-space:pre-wrap; font-size:.86rem; background:#f8fafc; border:1px solid #e2e8f0;
               border-radius:.5rem; padding:.75rem; word-break:break-word; }
    .as-seccion { font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; font-weight:700; margin:1rem 0 .35rem; }
    .as-adjunto-img { width:110px; height:80px; object-fit:cover; border-radius:.4rem; border:1px solid #e2e8f0; }
    .as-vacio { color:#94a3b8; text-align:center; padding:3rem 1rem; }
    .as-vacio i { font-size:2.4rem; display:block; margin-bottom:.4rem; }

    /* Técnicos */
    .as-tec { border:1px solid #e2e8f0; border-radius:.5rem; padding:.5rem .6rem; margin-bottom:.45rem;
              display:flex; align-items:center; gap:.6rem; background:#fff; transition:background .1s, border-color .1s; }
    .as-tec.drop { background:#eff6ff; border-color:#2563eb; border-style:dashed; }
    .as-tec.sugerido { border-color:#fbbf24; background:#fffbeb; }
    .as-avatar { border-radius:50%; color:#fff; font-weight:700; display:flex; align-items:center; justify-content:center;
                 flex-shrink:0; width:34px; height:34px; font-size:.75rem; }
    .as-tec .nombre { font-weight:600; font-size:.84rem; line-height:1.2; }
    .as-tec .carga { font-size:.7rem; color:#64748b; }
    .as-tec .motivo { font-size:.7rem; color:#92400e; }
    .as-carga-alta { color:#dc2626; font-weight:700; }

    .as-tabla td, .as-tabla th { vertical-align:middle; font-size:.82rem; }
    .as-tabla th { font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; white-space:nowrap; }

    .as-m-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-radius:.4rem; padding:.45rem .7rem; }
    .as-pais { font-size:.62rem; font-weight:700; border-radius:.25rem; padding:.05rem .3rem; background:#e0e7ff; color:#3730a3; vertical-align:middle; }
    .as-btn-pais { font-size:.65rem; padding:.05rem .4rem; line-height:1.3; }
    .as-separador { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; font-weight:700; margin:.6rem 0 .35rem .2rem; }

    .as-toasts { position:fixed; right:1rem; bottom:1rem; z-index:1080; display:flex; flex-direction:column; gap:.5rem; max-width:380px; }
    .as-toast { border-radius:.5rem; padding:.6rem .8rem; color:#fff; font-size:.84rem; box-shadow:0 6px 20px rgba(0,0,0,.18); }
    .as-toast.ok { background:#15803d; } .as-toast.error { background:#b91c1c; } .as-toast.aviso { background:#b45309; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header mb-2 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h4 class="mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Asignación de tickets</h4>
            <small class="text-muted">
                Asignando como <strong>{{ $glpi['nombre'] }}</strong>
                ({{ implode(', ', array_intersect($glpi['perfiles'], \App\Services\AsignacionTickets::PERFILES_ASIGNADORES)) }})
                &nbsp;·&nbsp; <span id="as-actualizado">cargando…</span>
            </small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="as-refrescar" title="Actualizar ahora"><i class="bi bi-arrow-clockwise"></i></button>
            <a href="{{ $urlGlpi }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>GLPI</a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-key"></i></button>
                <div class="dropdown-menu dropdown-menu-end p-2 small" style="min-width:260px">
                    <div class="text-muted mb-2">Tu token personal de GLPI está guardado (cifrado).</div>
                    <form method="POST" action="{{ route('asistencia.asignacion.token.olvidar') }}"
                          onsubmit="return confirm('¿Eliminar tu token de GLPI? Tendrás que ingresarlo de nuevo para asignar.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i>Eliminar o cambiar token</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#as-pane-nuevos" type="button" data-tab="nuevos">
                <i class="bi bi-inbox me-1"></i>Nuevos <span class="badge text-bg-danger ms-1" id="as-cnt-nuevos">…</span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#as-pane-asignados" type="button" data-tab="asignados">
                <i class="bi bi-people me-1"></i>Asignados <span class="badge text-bg-secondary ms-1" id="as-cnt-asignados">…</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- ════════════════ Nuevos ════════════════ --}}
        <div class="tab-pane fade show active" id="as-pane-nuevos">
            <div class="row g-3">

                {{-- Cola --}}
                <div class="col-xl-3 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 as-col">
                        <div class="as-col-head">
                            <div class="titulo mb-2"><i class="bi bi-inbox me-1"></i>Tickets sin técnico</div>
                            <input type="search" class="form-control form-control-sm mb-2" id="as-buscar" placeholder="Buscar título, solicitante, #id…">
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="as-filtro-grupo"><option value="">Todos los grupos</option></select>
                                <select class="form-select form-select-sm" id="as-filtro-pais" style="max-width:110px">
                                    <option value="">País</option>
                                    <option value="Chile">Chile</option>
                                    <option value="Perú">Perú</option>
                                    <option value="-">Sin país</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <select class="form-select form-select-sm" id="as-orden">
                                    <option value="nuevos">Más nuevos primero</option>
                                    <option value="viejos">Más antiguos primero</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body p-2" id="as-cola"></div>
                    </div>
                </div>

                {{-- Detalle --}}
                <div class="col-xl-5 col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 as-col">
                        <div class="card-body" id="as-detalle">
                            <div class="as-vacio"><i class="bi bi-hand-index"></i>Elige un ticket de la cola para ver el detalle.<br>
                                <small>Puedes arrastrarlo directo sobre un técnico.</small></div>
                        </div>
                    </div>
                </div>

                {{-- Técnicos --}}
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-3 as-col">
                        <div class="as-col-head">
                            <div class="titulo mb-2"><i class="bi bi-people me-1"></i>Técnicos</div>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="as-tec-grupo"><option value="">Todos los técnicos</option></select>
                                <input type="search" class="form-control form-control-sm" id="as-tec-buscar" placeholder="Buscar…" style="max-width:130px">
                            </div>
                        </div>
                        <div class="card-body p-2" id="as-tecnicos"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ════════════════ Asignados ════════════════ --}}
        <div class="tab-pane fade" id="as-pane-asignados">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center border-bottom">
                    <input type="search" class="form-control form-control-sm" id="as-a-buscar" placeholder="Buscar título, solicitante, #id…" style="max-width:260px">
                    <select class="form-select form-select-sm" id="as-a-tecnico" style="max-width:220px"><option value="">Todos los técnicos</option></select>
                    <select class="form-select form-select-sm" id="as-a-grupo" style="max-width:220px"><option value="">Todos los grupos</option></select>
                    <select class="form-select form-select-sm" id="as-a-estado" style="max-width:200px">
                        <option value="">Todos los estados</option>
                        <option value="2">En curso (asignado)</option>
                        <option value="3">En curso (planificado)</option>
                        <option value="4">En espera</option>
                    </select>
                    <small class="text-muted ms-auto" id="as-a-total"></small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 as-tabla">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Ticket</th><th>Solicitante</th><th>Técnico</th><th>Estado</th>
                                <th title="Desde que se abrió">Antigüedad</th><th>Última act.</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="as-a-cuerpo"><tr><td colspan="8" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════ Modal de asignación ════════════════ --}}
<div class="modal fade" id="as-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="as-m-titulo">Asignar ticket</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="fw-semibold mb-1" id="as-m-ticket"></div>
                <div class="small text-muted mb-3" id="as-m-actual"></div>

                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label small text-muted mb-1" for="as-m-grupo">Grupo</label>
                        <select class="form-select form-select-sm" id="as-m-grupo"><option value="">Todos</option></select>
                    </div>
                    <div class="col-7">
                        <label class="form-label small text-muted mb-1" for="as-m-tecnico">Técnico</label>
                        <select class="form-select form-select-sm" id="as-m-tecnico"></select>
                    </div>
                </div>

                <label class="form-label small text-muted mb-1" for="as-m-nota">Nota para el técnico <span class="fw-normal">(opcional, queda como seguimiento en GLPI)</span></label>
                <textarea class="form-control form-control-sm" id="as-m-nota" rows="3" maxlength="2000"></textarea>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="as-m-privada" checked>
                    <label class="form-check-label small" for="as-m-privada">Nota privada (el solicitante no la ve)</label>
                </div>

                {{-- Sin la clase .alert: el layout borra todas las .alert a los pocos segundos
                     (es lo que oculta los mensajes flash) y este recuadro debe seguir existiendo. --}}
                <div class="as-m-error small mt-3 d-none" id="as-m-error"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="as-m-confirmar">
                    <span class="spinner-border spinner-border-sm me-1 d-none"></span>Asignar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="as-toasts" id="as-toasts"></div>

{{-- Para «Ingresar otro token» desde un aviso, sin la confirmación del menú. --}}
<form method="POST" action="{{ route('asistencia.asignacion.token.olvidar') }}" id="as-form-token" class="d-none">
    @csrf @method('DELETE')
</form>

@push('scripts')
<script>
// En DOMContentLoaded, no antes: `bootstrap` (para el modal) lo publica el
// bundle de Vite, que es un módulo y se ejecuta en diferido.
document.addEventListener('DOMContentLoaded', () => {
    const RUTAS = {
        nuevos:    @json(route('asistencia.asignacion.nuevos')),
        asignados: @json(route('asistencia.asignacion.asignados')),
        tecnicos:  @json(route('asistencia.asignacion.tecnicos')),
        detalle:   @json(route('asistencia.asignacion.detalle', ['ticket' => '__ID__'])),
        asignar:   @json(route('asistencia.asignacion.asignar', ['ticket' => '__ID__'])),
        pais:      @json(route('asistencia.asignacion.pais', ['tecnico' => '__ID__'])),
    };
    const URL_GLPI = @json($urlGlpi);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const REFRESCO_MS = 60000;

    const estado = {
        nuevos: [], asignados: [], grupos: [], tecnicos: [], paises: [],
        seleccionado: null,   // id del ticket abierto en el detalle
        detalle: null,        // detalle cargado del seleccionado
        vistos: null,         // ids de la carga anterior, para resaltar los que llegan
        modal: null,          // {ticket, origen: 'nuevos'|'asignados'}
    };

    /* ── Utilidades ─────────────────────────────────────────────────────── */

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const edad = h => h < 4 ? 'verde' : (h < 24 ? 'naranja' : 'rojo');
    const color = id => `hsl(${(id * 47) % 360}, 55%, 45%)`;
    const norm = s => String(s ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    async function pedir(url, opciones = {}) {
        const resp = await fetch(url, {
            ...opciones,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', ...(opciones.headers || {}) },
        });
        let datos = {};
        try { datos = await resp.json(); } catch (e) { /* respuesta vacía */ }
        if (!resp.ok) {
            const err = new Error(datos.error || `Error ${resp.status}`);
            Object.assign(err, { estado: resp.status, datos });
            throw err;
        }
        return datos;
    }

    function aviso(texto, tipo = 'ok', ms = 4500) {
        const div = document.createElement('div');
        div.className = `as-toast ${tipo}`;
        div.innerHTML = texto;
        $('as-toasts').appendChild(div);
        setTimeout(() => div.remove(), ms);
    }

    /** Token rechazado por GLPI: se ofrece borrarlo para volver a la pantalla de ingreso. */
    function avisoToken(mensaje) {
        aviso(`${esc(mensaje)} <a href="#" class="text-white fw-bold ms-1"
            onclick="event.preventDefault(); document.getElementById('as-form-token').submit();">Ingresar otro token</a>`, 'error', 12000);
    }

    /* ── Carga de datos ─────────────────────────────────────────────────── */

    async function cargarTodo() {
        try {
            const [n, t, a] = await Promise.all([pedir(RUTAS.nuevos), pedir(RUTAS.tecnicos), pedir(RUTAS.asignados)]);
            estado.grupos = t.grupos;
            estado.tecnicos = t.tecnicos;
            estado.paises = t.paises;
            estado.asignados = a.tickets;
            recibirNuevos(n.tickets);
            llenarFiltros();
            pintarCola(); pintarTecnicos(); pintarAsignados();
            $('as-actualizado').textContent = 'actualizado ' + new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            if (e.datos?.token_invalido) return avisoToken(e.message);
            aviso(esc(e.message), 'error');
        }
    }

    /** Guarda la cola nueva y marca los tickets que no estaban en la carga anterior. */
    function recibirNuevos(tickets) {
        const antes = estado.vistos;
        estado.nuevos = tickets.map(t => ({ ...t, recien: antes !== null && !antes.has(t.id) }));
        estado.vistos = new Set(tickets.map(t => t.id));
        const llegaron = estado.nuevos.filter(t => t.recien).length;
        if (llegaron) aviso(`<i class="bi bi-bell-fill me-1"></i>${llegaron} ticket${llegaron > 1 ? 's' : ''} nuevo${llegaron > 1 ? 's' : ''} en la cola`, 'aviso');

        // Si el ticket abierto ya no está (lo asignó otra persona), se avisa y se cierra.
        if (estado.seleccionado && !estado.vistos.has(estado.seleccionado)) {
            aviso(`El ticket #${estado.seleccionado} ya no está en la cola: alguien más lo asignó.`, 'aviso');
            cerrarDetalle();
        }
    }

    function llenarFiltros() {
        const opciones = (sel, items, primera) => {
            const actual = sel.value;
            sel.innerHTML = `<option value="">${primera}</option>` + items.map(([v, t]) => `<option value="${esc(v)}">${esc(t)}</option>`).join('');
            sel.value = [...sel.options].some(o => o.value === actual) ? actual : '';
        };
        const grupos = estado.grupos.map(g => [g.id, `${g.nombre} (${g.miembros})`]);
        opciones($('as-tec-grupo'), grupos, 'Todos los técnicos');
        opciones($('as-m-grupo'), estado.grupos.map(g => [g.id, g.nombre]), 'Todos');
        opciones($('as-a-grupo'), estado.grupos.map(g => [g.id, g.nombre]), 'Todos los grupos');
        opciones($('as-a-tecnico'), estado.tecnicos.map(t => [t.id, t.nombre]), 'Todos los técnicos');

        // Grupos de la cola: los que traen los tickets, aunque no sean asignables.
        const enCola = new Map();
        estado.nuevos.forEach(t => t.grupos.forEach(g => enCola.set(String(g.id), g.nombre)));
        opciones($('as-filtro-grupo'), [...enCola].sort((a, b) => a[1].localeCompare(b[1])), 'Todos los grupos');
    }

    /* ── Cola de nuevos ─────────────────────────────────────────────────── */

    function pintarCola() {
        const q = norm($('as-buscar').value);
        const grupo = $('as-filtro-grupo').value;
        const pais = $('as-filtro-pais').value;
        let lista = estado.nuevos.filter(t =>
            (!grupo || t.grupos.some(g => String(g.id) === grupo)) &&
            (!pais || (pais === '-' ? !t.pais : t.pais === pais)) &&
            (!q || norm(`#${t.id} ${t.titulo} ${t.resumen} ${t.categoria} ${t.solicitantes.map(s => s.nombre).join(' ')}`).includes(q))
        );
        if ($('as-orden').value === 'nuevos') lista = [...lista].reverse();

        $('as-cnt-nuevos').textContent = estado.nuevos.length;
        $('as-cola').innerHTML = lista.length ? lista.map(t => `
            <div class="as-ticket edad-${edad(t.horas)} ${t.id === estado.seleccionado ? 'activo' : ''} ${t.recien ? 'recien' : ''}"
                 draggable="true" data-id="${t.id}">
                <div class="d-flex justify-content-between gap-2">
                    <span class="t-titulo">${esc(t.titulo)}</span>
                    <span class="as-edad ${edad(t.horas)}" title="Abierto el ${esc(t.fecha)}">${esc(t.antiguedad)}</span>
                </div>
                <div class="t-meta mt-1">
                    #${t.id} · <i class="bi bi-person"></i> ${esc(t.solicitantes.map(s => s.nombre).join(', ') || '—')}
                    ${t.adjuntos ? ` · <i class="bi bi-paperclip"></i>${t.adjuntos}` : ''}
                </div>
                <div class="t-meta">${t.pais ? `<span class="as-pais">${esc(t.pais)}</span> ` : ''}${esc(t.categoria || 'Sin categoría')}${t.ubicacion ? ` · <i class="bi bi-geo-alt"></i> ${esc(t.ubicacion)}` : ''}</div>
                <div class="t-resumen">${esc(t.resumen)}</div>
            </div>`).join('')
            : `<div class="as-vacio"><i class="bi bi-check2-circle"></i>${estado.nuevos.length ? 'Ningún ticket coincide con el filtro.' : '¡No hay tickets sin asignar!'}</div>`;
    }

    $('as-cola').addEventListener('click', e => {
        const card = e.target.closest('.as-ticket');
        if (card) abrirDetalle(+card.dataset.id);
    });
    $('as-cola').addEventListener('dragstart', e => {
        const card = e.target.closest('.as-ticket');
        if (!card) return;
        e.dataTransfer.setData('text/plain', card.dataset.id);
        e.dataTransfer.effectAllowed = 'move';
        card.classList.add('arrastrando');
        if (+card.dataset.id !== estado.seleccionado) abrirDetalle(+card.dataset.id);
    });
    $('as-cola').addEventListener('dragend', e => e.target.closest('.as-ticket')?.classList.remove('arrastrando'));
    ['as-buscar', 'as-filtro-grupo', 'as-filtro-pais', 'as-orden'].forEach(id => $(id).addEventListener('input', pintarCola));

    /* ── Detalle ────────────────────────────────────────────────────────── */

    async function abrirDetalle(id) {
        estado.seleccionado = id;
        estado.detalle = null;
        pintarCola();
        $('as-detalle').innerHTML = '<div class="as-vacio"><div class="spinner-border text-primary"></div></div>';

        try {
            const { ticket } = await pedir(RUTAS.detalle.replace('__ID__', id));
            if (estado.seleccionado !== id) return; // se eligió otro mientras cargaba
            estado.detalle = ticket;

            // El panel de técnicos salta al grupo del ticket; se puede cambiar a mano.
            const grupo = ticket.grupos.find(g => estado.grupos.some(x => x.id === g.id));
            $('as-tec-grupo').value = grupo ? String(grupo.id) : '';

            pintarDetalle(ticket);
            pintarTecnicos();
        } catch (e) {
            $('as-detalle').innerHTML = `<div class="as-vacio text-danger"><i class="bi bi-exclamation-triangle"></i>${esc(e.message)}</div>`;
        }
    }

    function cerrarDetalle() {
        estado.seleccionado = null;
        estado.detalle = null;
        $('as-detalle').innerHTML = '<div class="as-vacio"><i class="bi bi-hand-index"></i>Elige un ticket de la cola para ver el detalle.</div>';
        pintarTecnicos();
    }

    function pintarDetalle(t) {
        const s = t.solicitante;
        const imagenes = t.adjuntos_lista.filter(a => a.imagen);
        const archivos = t.adjuntos_lista.filter(a => !a.imagen);

        $('as-detalle').innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="small text-muted">#${t.id} · abierto el ${esc(t.fecha)}
                        <span class="as-edad ${edad(t.horas)} ms-1">hace ${esc(t.antiguedad)}</span></div>
                    <h5 class="mb-1 mt-1">${esc(t.titulo)}</h5>
                </div>
                <a href="${esc(t.url)}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm flex-shrink-0" title="Abrir en GLPI">
                    <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div class="mb-2">
                <span class="as-chip"><i class="bi bi-tag me-1"></i>${esc(t.categoria || 'Sin categoría')}</span>
                ${t.grupos.map(g => `<span class="as-chip"><i class="bi bi-people me-1"></i>${esc(g.nombre)}</span>`).join('')}
                ${t.ubicacion || s?.ubicacion ? `<span class="as-chip"><i class="bi bi-geo-alt me-1"></i>${esc(t.ubicacion || s.ubicacion)}</span>` : ''}
                ${t.pais ? `<span class="as-chip"><i class="bi bi-flag me-1"></i>${esc(t.pais)}</span>` : '<span class="as-chip text-muted"><i class="bi bi-flag me-1"></i>Sin país</span>'}
            </div>

            ${s ? `
            <div class="as-seccion">Solicitante</div>
            <div class="d-flex flex-wrap gap-3 small">
                <span><i class="bi bi-person-fill me-1 text-primary"></i><strong>${esc(s.nombre)}</strong></span>
                ${s.correo ? `<a href="mailto:${esc(s.correo)}"><i class="bi bi-envelope me-1"></i>${esc(s.correo)}</a>` : ''}
                ${s.telefonos.map(f => `<span><i class="bi bi-telephone me-1"></i>${esc(f)}</span>`).join('')}
            </div>
            ${s.equipos.length ? `<div class="small mt-1"><i class="bi bi-pc-display me-1 text-muted"></i>${s.equipos.map(e =>
                `<span class="as-chip" title="Serie ${esc(e.serial || '—')}">${esc(e.nombre)}${e.modelo ? ' · ' + esc(e.modelo) : ''}</span>`).join('')}</div>` : ''}
            ` : ''}

            <div class="as-seccion">Descripción</div>
            <div class="as-desc">${esc(t.descripcion) || '<span class="text-muted">Sin descripción.</span>'}</div>

            ${t.adjuntos_lista.length ? `
            <div class="as-seccion">Adjuntos (${t.adjuntos_lista.length})</div>
            <div class="d-flex flex-wrap gap-2">
                ${imagenes.map(a => `<a href="${esc(a.url)}" target="_blank" rel="noopener" title="${esc(a.nombre)}">
                    <img src="${esc(a.url)}" class="as-adjunto-img" alt="${esc(a.nombre)}" loading="lazy"
                         onerror="this.replaceWith(Object.assign(document.createElement('span'), {className:'as-chip', textContent:'🖼 ' + this.alt}))"></a>`).join('')}
            </div>
            <div class="mt-1">${archivos.map(a => `<a href="${esc(a.url)}" target="_blank" rel="noopener" class="as-chip text-decoration-none">
                <i class="bi bi-file-earmark me-1"></i>${esc(a.nombre)}</a>`).join('')}</div>
            <div class="small text-muted">Los adjuntos se abren en GLPI con tu sesión de GLPI del navegador.</div>
            ` : ''}

            ${s?.recientes.length ? `
            <div class="as-seccion">Tickets recientes de ${esc(s.nombre.split(' ')[0])}</div>
            <ul class="list-unstyled small mb-0">
                ${s.recientes.map(r => `<li class="py-1 border-bottom">
                    <a href="${esc(URL_GLPI)}/front/ticket.form.php?id=${r.id}" target="_blank" rel="noopener">#${r.id}</a>
                    ${esc(r.titulo)} <span class="text-muted">· ${esc(r.fecha)} · ${esc(r.estado)}${r.tecnicos.length ? ' · ' + esc(r.tecnicos.join(', ')) : ''}</span>
                </li>`).join('')}
            </ul>` : ''}
        `;
    }

    /* ── Técnicos ───────────────────────────────────────────────────────── */

    /**
     * Puntaje de sugerencia (ver AsignacionTickets::sugerencias): experiencia
     * en la categoría y con el solicitante, menos la carga actual.
     */
    function puntaje(tec) {
        const s = estado.detalle?.sugerencias?.[tec.id];
        if (!s) return null;
        return { puntos: s.categoria + 3 * s.solicitante - tec.abiertos, ...s };
    }

    /**
     * Nivel de un técnico según el país del ticket: 0 = mismo país, 1 = sin
     * país asignado en GLPI, 2 = otro país. Los de otro país siguen
     * apareciendo (a veces se atiende remoto), pero al final.
     */
    function nivelPais(tec, paisTicket) {
        if (!paisTicket) return 0;
        if (tec.pais === paisTicket) return 0;
        return tec.pais ? 2 : 1;
    }

    /** Ordena técnicos: por país del ticket, luego por sugerencia, luego por nombre. */
    function ordenarTecnicos(lista, paisTicket, conSugerencia) {
        return lista.map(t => ({ t, p: conSugerencia ? puntaje(t) : null, nivel: nivelPais(t, paisTicket) }))
            .sort((a, b) => a.nivel - b.nivel || (b.p?.puntos ?? -999) - (a.p?.puntos ?? -999) || a.t.nombre.localeCompare(b.t.nombre));
    }

    function pintarTecnicos() {
        const grupo = $('as-tec-grupo').value;
        const q = norm($('as-tec-buscar').value);
        const lista = estado.tecnicos.filter(t => (!grupo || t.grupos.includes(+grupo)) && (!q || norm(t.nombre + ' ' + t.login).includes(q)));
        const paisTicket = estado.detalle?.pais || null;

        // Con un ticket abierto: primero su país, y dentro de cada país los sugeridos.
        const ordenados = ordenarTecnicos(lista, paisTicket, !!estado.detalle);

        // Candidato a ★ es quien tiene experiencia (en la categoría o con el
        // solicitante), aunque la carga deje su puntaje bajo cero: entre los
        // candidatos manda el puntaje. Las estrellas van al mejor nivel de
        // país que tenga candidatos: si nadie es del país del ticket, se
        // sugiere entre los sin país.
        const candidato = x => x.p && (x.p.categoria + x.p.solicitante) > 0;
        const nivelSugerido = Math.min(...ordenados.filter(candidato).map(x => x.nivel), 9);
        const sugeridos = new Set(ordenados.filter(x => x.nivel === nivelSugerido && candidato(x)).slice(0, 3).map(x => x.t.id));
        const hay = !!estado.seleccionado;
        const titulos = paisTicket ? [`Técnicos de ${paisTicket}`, 'Sin país asignado en GLPI', 'Otro país'] : null;
        let nivelActual = -1;

        $('as-tecnicos').innerHTML = ordenados.length ? ordenados.map(({ t, p, nivel }) => {
            const motivos = p ? [p.categoria ? `${p.categoria} de esta categoría` : '', p.solicitante ? `atendió ${p.solicitante} vez${p.solicitante > 1 ? 'es' : ''} a este solicitante` : '']
                .filter(Boolean).join(' · ') : '';
            const separador = titulos && nivel !== nivelActual ? `<div class="as-separador">${esc(titulos[nivel])}</div>` : '';
            nivelActual = nivel;
            return `${separador}
            <div class="as-tec ${sugeridos.has(t.id) ? 'sugerido' : ''}" data-id="${t.id}">
                <div class="as-avatar" style="background:${color(t.id)}">${esc(t.iniciales)}</div>
                <div class="flex-grow-1 min-w-0">
                    <div class="nombre">${sugeridos.has(t.id) ? '<i class="bi bi-star-fill text-warning me-1" title="Sugerido"></i>' : ''}${esc(t.nombre)}
                        ${t.pais ? `<span class="as-pais ms-1">${esc(t.pais)}</span>` : ''}</div>
                    <div class="carga">
                        <span class="${t.abiertos >= 10 ? 'as-carga-alta' : ''}">${t.abiertos} abiertos</span>
                        ${t.en_espera ? ` · ${t.en_espera} en espera` : ''} · ${t.hoy} resueltos hoy
                    </div>
                    ${motivos ? `<div class="motivo">${esc(motivos)}</div>` : ''}
                    ${!t.pais && estado.paises.length ? `<div class="mt-1 d-flex align-items-center gap-1">
                        <span class="carga">Sin país:</span>
                        ${estado.paises.map(p => `<button class="btn btn-outline-secondary as-btn-pais" data-pais="${esc(p.nombre)}" data-tecnico="${t.id}"
                            title="Dejar a ${esc(t.corto)} en ${esc(p.nombre)} (ubicación en GLPI)">${esc(p.nombre)}</button>`).join('')}
                    </div>` : ''}
                </div>
                <button class="btn btn-sm ${sugeridos.has(t.id) ? 'btn-warning' : 'btn-outline-primary'} flex-shrink-0" data-asignar="${t.id}"
                        ${hay ? '' : 'disabled'} title="${hay ? 'Asignar el ticket abierto' : 'Elige un ticket primero'}">Asignar</button>
            </div>`;
        }).join('') : '<div class="as-vacio"><i class="bi bi-person-x"></i>Ningún técnico coincide.</div>';
    }

    $('as-tecnicos').addEventListener('click', e => {
        const pais = e.target.closest('[data-pais]');
        if (pais) return fijarPais(+pais.dataset.tecnico, pais.dataset.pais, pais);
        const btn = e.target.closest('[data-asignar]');
        if (btn && estado.seleccionado) abrirModal(estado.seleccionado, 'nuevos', +btn.dataset.asignar);
    });

    /** Da país a un técnico sin ubicación en GLPI y lo reordena en el acto. */
    async function fijarPais(tecnicoId, pais, boton) {
        const tec = estado.tecnicos.find(t => t.id === tecnicoId);
        if (!tec || !confirm(`¿Dejar a ${tec.nombre} en ${pais}?\n\nSe guarda como su ubicación en GLPI.`)) return;

        boton.closest('div').querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const r = await pedir(RUTAS.pais.replace('__ID__', tecnicoId), { method: 'POST', body: JSON.stringify({ pais }) });
            tec.pais = r.pais;
            aviso(`<i class="bi bi-flag-fill me-1"></i>${esc(r.mensaje)}`);
        } catch (e) {
            if (e.datos?.token_invalido) return avisoToken(e.message);
            if (e.datos?.pais) tec.pais = e.datos.pais; // ya lo tenía: se muestra el real
            aviso(esc(e.message), 'error', 8000);
        }
        pintarTecnicos();
    }
    $('as-tecnicos').addEventListener('dragover', e => {
        const tec = e.target.closest('.as-tec');
        if (!tec) return;
        e.preventDefault();
        document.querySelectorAll('.as-tec.drop').forEach(x => x !== tec && x.classList.remove('drop'));
        tec.classList.add('drop');
    });
    $('as-tecnicos').addEventListener('dragleave', e => {
        const tec = e.target.closest('.as-tec');
        if (tec && !tec.contains(e.relatedTarget)) tec.classList.remove('drop');
    });
    $('as-tecnicos').addEventListener('drop', e => {
        const tec = e.target.closest('.as-tec');
        if (!tec) return;
        e.preventDefault();
        tec.classList.remove('drop');
        const id = +e.dataTransfer.getData('text/plain');
        if (id) abrirModal(id, 'nuevos', +tec.dataset.id);
    });
    ['as-tec-grupo', 'as-tec-buscar'].forEach(id => $(id).addEventListener('input', pintarTecnicos));

    /* ── Asignados ──────────────────────────────────────────────────────── */

    function pintarAsignados() {
        const q = norm($('as-a-buscar').value);
        const tec = $('as-a-tecnico').value, grupo = $('as-a-grupo').value, est = $('as-a-estado').value;
        const lista = estado.asignados.filter(t =>
            (!tec || t.tecnicos.some(x => String(x.id) === tec)) &&
            (!grupo || t.grupos.some(g => String(g.id) === grupo)) &&
            (!est || String(t.estado) === est) &&
            (!q || norm(`#${t.id} ${t.titulo} ${t.categoria} ${t.solicitantes.map(s => s.nombre).join(' ')}`).includes(q))
        );

        $('as-cnt-asignados').textContent = estado.asignados.length;
        $('as-a-total').textContent = `${lista.length} de ${estado.asignados.length} tickets`;
        $('as-a-cuerpo').innerHTML = lista.length ? lista.map(t => `
            <tr>
                <td><a href="${esc(URL_GLPI)}/front/ticket.form.php?id=${t.id}" target="_blank" rel="noopener">${t.id}</a></td>
                <td><div class="fw-semibold">${esc(t.titulo)}</div><small class="text-muted">${t.pais ? `<span class="as-pais">${esc(t.pais)}</span> ` : ''}${esc(t.categoria || 'Sin categoría')}</small></td>
                <td>${esc(t.solicitantes.map(s => s.nombre).join(', ') || '—')}</td>
                <td>${t.tecnicos.map(x => `<div class="d-flex align-items-center gap-1"><span class="as-avatar" style="width:22px;height:22px;font-size:.6rem;background:${color(x.id || 0)}">${esc((x.nombre.match(/\b\p{L}/gu) || []).slice(0, 2).join(''))}</span>${esc(x.nombre)}</div>`).join('')}</td>
                <td><span class="badge ${t.estado === 4 ? 'text-bg-warning' : 'text-bg-light border'}">${esc(t.estado_texto)}</span></td>
                <td><span class="as-edad ${edad(t.horas)}">${esc(t.antiguedad)}</span></td>
                <td class="small text-muted">${esc(t.actualizado || '')}</td>
                <td><button class="btn btn-outline-primary btn-sm" data-reasignar="${t.id}"><i class="bi bi-arrow-left-right me-1"></i>Reasignar</button></td>
            </tr>`).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-4">No hay tickets que coincidan.</td></tr>';
    }

    $('as-a-cuerpo').addEventListener('click', e => {
        const btn = e.target.closest('[data-reasignar]');
        if (btn) abrirModal(+btn.dataset.reasignar, 'asignados');
    });
    ['as-a-buscar', 'as-a-tecnico', 'as-a-grupo', 'as-a-estado'].forEach(id => $(id).addEventListener('input', pintarAsignados));

    /* ── Modal de asignación ────────────────────────────────────────────── */

    const modal = new bootstrap.Modal($('as-modal'));

    function opcionesTecnico(grupo, elegido, excluir = []) {
        const lista = estado.tecnicos.filter(t => (!grupo || t.grupos.includes(+grupo)) && !excluir.includes(t.id));
        const paisTicket = estado.modal?.ticket.pais || null;
        $('as-m-tecnico').innerHTML = ordenarTecnicos(lista, paisTicket, false).map(({ t }) =>
            `<option value="${t.id}" ${t.id === elegido ? 'selected' : ''}>${esc(t.nombre)}${t.pais ? ` (${esc(t.pais)})` : ''} — ${t.abiertos} abiertos</option>`).join('');
    }

    function abrirModal(ticketId, origen, tecnicoId = null) {
        const t = (origen === 'nuevos' ? estado.nuevos : estado.asignados).find(x => x.id === ticketId);
        if (!t) return aviso('Ese ticket ya no está en la lista; actualiza.', 'aviso');

        const actuales = t.tecnicos.map(x => x.id).filter(Boolean);
        estado.modal = { ticket: t, origen };

        $('as-m-titulo').textContent = origen === 'nuevos' ? 'Asignar ticket' : 'Reasignar ticket';
        $('as-m-confirmar').lastChild.textContent = origen === 'nuevos' ? 'Asignar' : 'Reasignar';
        $('as-m-ticket').textContent = `#${t.id} · ${t.titulo}`;
        $('as-m-actual').innerHTML = actuales.length
            ? `Hoy lo tiene <strong>${esc(t.tecnicos.map(x => x.nombre).join(', '))}</strong>. Al reasignar, sale del ticket.`
            : esc(t.categoria || '');

        // Grupo inicial: el del técnico elegido por arrastre, o el del ticket.
        const tec = estado.tecnicos.find(x => x.id === tecnicoId);
        const grupoTicket = t.grupos.find(g => estado.grupos.some(x => x.id === g.id))?.id;
        const grupo = tec ? (tec.grupos.includes(grupoTicket) ? grupoTicket : '') : (grupoTicket ?? '');
        $('as-m-grupo').value = grupo ? String(grupo) : '';
        opcionesTecnico($('as-m-grupo').value, tecnicoId, actuales);

        $('as-m-nota').value = '';
        $('as-m-privada').checked = true;
        $('as-m-error').classList.add('d-none');
        modal.show();
    }

    $('as-m-grupo').addEventListener('change', () => {
        const actuales = estado.modal?.ticket.tecnicos.map(x => x.id).filter(Boolean) || [];
        opcionesTecnico($('as-m-grupo').value, +$('as-m-tecnico').value || null, actuales);
    });

    $('as-m-confirmar').addEventListener('click', async () => {
        const m = estado.modal;
        const tecnico = +$('as-m-tecnico').value;
        if (!m || !tecnico) return;

        const btn = $('as-m-confirmar');
        btn.disabled = true;
        btn.querySelector('.spinner-border').classList.remove('d-none');
        $('as-m-error').classList.add('d-none');

        try {
            const r = await pedir(RUTAS.asignar.replace('__ID__', m.ticket.id), {
                method: 'POST',
                body: JSON.stringify({
                    tecnico,
                    esperados: m.ticket.tecnicos.map(x => x.id).filter(Boolean),
                    nota: $('as-m-nota').value,
                    nota_privada: $('as-m-privada').checked,
                }),
            });
            modal.hide();
            aviso(`<i class="bi bi-check-circle-fill me-1"></i>${esc(r.mensaje)}`);
            despuesDeAsignar(m);
        } catch (e) {
            if (e.datos?.token_invalido) { modal.hide(); return avisoToken(e.message); }
            $('as-m-error').textContent = e.message;
            $('as-m-error').classList.remove('d-none');
            if (e.datos?.cambiado) cargarTodo();
        } finally {
            btn.disabled = false;
            btn.querySelector('.spinner-border').classList.add('d-none');
        }
    });

    /** Saca el ticket de la cola, pasa al siguiente y refresca cargas y asignados. */
    function despuesDeAsignar(m) {
        if (m.origen === 'nuevos') {
            const visibles = [...document.querySelectorAll('#as-cola .as-ticket')].map(x => +x.dataset.id);
            const siguiente = visibles[visibles.indexOf(m.ticket.id) + 1] ?? visibles[visibles.indexOf(m.ticket.id) - 1];
            estado.nuevos = estado.nuevos.filter(t => t.id !== m.ticket.id);
            estado.vistos?.delete(m.ticket.id);
            pintarCola();
            if (estado.seleccionado === m.ticket.id) siguiente ? abrirDetalle(siguiente) : cerrarDetalle();
        }
        cargarTodo();
    }

    /* ── Arranque y refresco ────────────────────────────────────────────── */

    $('as-refrescar').addEventListener('click', cargarTodo);
    cargarTodo();
    // Refresco automático; se pausa mientras el modal está abierto o la pestaña no se ve.
    setInterval(() => {
        if (!document.hidden && !$('as-modal').classList.contains('show')) cargarTodo();
    }, REFRESCO_MS);
});
</script>
@endpush
@endsection
