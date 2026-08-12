{{-- Mantenedor de zonas.

     Habla por JSON en vez de postear y redirigir porque se abre encima de la
     ficha del sitio: un redirect se llevaría lo que el técnico lleve escrito y
     todavía no haya guardado. Al crear una zona se agrega sola a TODOS los
     <select name="zona_id"> de la página y queda elegida, que es el motivo de
     abrir el modal desde la edición.

     Se incluye una sola vez por página; el listado y la ficha lo comparten. --}}

@once
@push('styles')
<style>
    .zn-fila { display:flex; align-items:center; gap:.4rem; padding:.35rem .5rem;
               border:1px solid #e2e8f0; border-radius:7px; margin-bottom:.35rem; }
    .zn-fila input.nombre { border:0; background:none; font-size:.82rem; font-weight:600;
                            color:#1e293b; flex:1 1 auto; min-width:0; padding:.15rem .2rem; }
    .zn-fila input.nombre:focus { outline:0; background:#f8fafc; border-radius:4px; }
    .zn-fila input.orden { width:52px; font-size:.72rem; text-align:center; padding:.15rem; border:1px solid #e2e8f0; border-radius:5px; }
    .zn-uso { font-size:.68rem; color:#94a3b8; white-space:nowrap; }
    .zn-fila button { border:0; background:none; color:#cbd5e1; padding:.1rem .3rem; line-height:1; }
    .zn-fila button:hover { color:#dc2626; }
    .zn-fila.sucia { border-color:#fbbf24; background:#fffbeb; }
    .zn-vacio { text-align:center; color:#94a3b8; font-size:.8rem; padding:1.2rem .5rem; }
    .zn-aviso { font-size:.75rem; }
</style>
@endpush

{{-- `recargar-al-cerrar` solo en el listado: ahí el filtro por zona se queda
     viejo si se agregó o borró alguna. En la ficha JAMÁS, porque recargar
     botaría lo que el técnico esté editando sin guardar. --}}
<div class="modal fade" id="modalZonas" tabindex="-1" aria-labelledby="tituloZonas" aria-hidden="true"
     data-recargar-al-cerrar="{{ ($recargarAlCerrar ?? false) ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="tituloZonas"><i class="bi bi-signpost-split me-1"></i>Zonas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="znNueva" class="form-control form-control-sm"
                           placeholder="Nombre de la zona nueva" maxlength="80" autocomplete="off">
                    <input type="number" id="znNuevaOrden" class="form-control form-control-sm"
                           style="width:70px" placeholder="Orden" min="0" max="9999" title="Orden en que se listan">
                    <button type="button" id="znAgregar" class="btn btn-success btn-sm text-nowrap">
                        <i class="bi bi-plus-lg me-1"></i>Agregar
                    </button>
                </div>

                <div id="znError" class="alert alert-danger py-2 zn-aviso d-none mb-2"></div>
                <div id="znOk" class="alert alert-success py-2 zn-aviso d-none mb-2"></div>

                <div id="znLista"></div>

                <p class="text-muted mb-0 mt-2" style="font-size:.72rem">
                    El <b>orden</b> manda sobre el alfabético: sirve para listarlas de norte a sur.
                    Cambia un nombre y sal del campo para guardarlo.
                </p>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// En DOMContentLoaded: `bootstrap` lo publica el bundle de Vite, que es un
// módulo y por lo tanto corre después de los scripts en línea.
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalZonas');
    if (!modal) return;

    const lista  = document.getElementById('znLista');
    const nueva  = document.getElementById('znNueva');
    const orden  = document.getElementById('znNuevaOrden');
    const elErr  = document.getElementById('znError');
    const elOk   = document.getElementById('znOk');
    const token  = document.querySelector('meta[name="csrf-token"]')?.content;
    const RUTA   = @json(route('admin.zonas.index'));

    const aviso = (el, texto) => {
        el.textContent = texto;
        el.classList.toggle('d-none', !texto);
        if (texto && el === elOk) setTimeout(() => elOk.classList.add('d-none'), 2500);
    };
    const limpiarAvisos = () => { aviso(elErr, ''); aviso(elOk, ''); };

    /** Se tocó el mantenedor: lo que la página ya pintó quedó viejo. */
    const marcarSucio = () => { modal.dataset.sucio = '1'; };

    // El nombre lo escribe una persona y termina dentro de un atributo: una
    // comilla partiría el HTML.
    const esc = s => String(s).replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function pedir(metodo, url, cuerpo) {
        const r = await fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined,
        });

        let datos = {};
        try { datos = await r.json(); } catch (e) {}

        if (!r.ok) {
            // 422 de Laravel trae los mensajes por campo; el resto, un `error`.
            const msg = datos.error
                || Object.values(datos.errors || {}).flat()[0]
                || `No se pudo completar (HTTP ${r.status}).`;
            throw Object.assign(new Error(msg), { estado: r.status, datos });
        }
        return datos;
    }

    /* ── Los <select> de la página se mantienen al día ───────────────────── */

    function selects() {
        return [...document.querySelectorAll('select[name="zona_id"]')];
    }

    function repintarSelects(zonas, seleccionar) {
        selects().forEach(sel => {
            const actual = seleccionar ?? sel.value;
            sel.innerHTML = '<option value="">— Sin zona —</option>'
                + zonas.map(z => `<option value="${z.id}">${esc(z.nombre)}</option>`).join('');
            sel.value = actual ?? '';
            // Si la zona elegida se borró, el select queda en "Sin zona".
            if (sel.value !== String(actual ?? '')) sel.value = '';
        });
    }

    /* ── Pintado de la lista ─────────────────────────────────────────────── */

    function pintar(zonas) {
        if (!zonas.length) {
            lista.innerHTML = '<div class="zn-vacio">Todavía no hay zonas. Crea la primera arriba.</div>';
            return;
        }

        lista.innerHTML = zonas.map(z => `
            <div class="zn-fila" data-id="${z.id}">
                <input type="number" class="orden" value="${z.orden}" min="0" max="9999" title="Orden">
                <input type="text" class="nombre" value="${esc(z.nombre)}" maxlength="80">
                <span class="zn-uso">${z.sitios} ${z.sitios === 1 ? 'sitio' : 'sitios'}</span>
                <button type="button" class="borrar" title="Eliminar"><i class="bi bi-trash"></i></button>
            </div>`).join('');
    }

    async function recargar(seleccionar) {
        const { zonas } = await pedir('GET', RUTA);
        pintar(zonas);
        repintarSelects(zonas, seleccionar);
        return zonas;
    }

    /* ── Crear ───────────────────────────────────────────────────────────── */

    async function agregar() {
        const nombre = nueva.value.trim();
        limpiarAvisos();
        if (!nombre) { nueva.focus(); return; }

        try {
            const r = await pedir('POST', RUTA, { nombre, orden: orden.value === '' ? 0 : Number(orden.value) });
            pintar(r.zonas);
            // Se deja elegida en toda la página: es para lo que se abrió el modal
            // desde la ficha de un sitio que no tenía su zona todavía.
            repintarSelects(r.zonas, String(r.zona.id));
            selects().forEach(s => s.dispatchEvent(new Event('change', { bubbles: true })));
            nueva.value = ''; orden.value = '';
            marcarSucio();
            aviso(elOk, `«${r.zona.nombre}» creada y asignada.`);
            nueva.focus();
        } catch (e) { aviso(elErr, e.message); }
    }

    document.getElementById('znAgregar').addEventListener('click', agregar);
    nueva.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); agregar(); } });

    /* ── Renombrar y reordenar (al salir del campo) ──────────────────────── */

    lista.addEventListener('focusin', e => {
        if (e.target.matches('.nombre, .orden')) e.target.dataset.previo = e.target.value;
    });

    lista.addEventListener('focusout', async e => {
        if (!e.target.matches('.nombre, .orden')) return;

        const fila = e.target.closest('.zn-fila');
        const previo = e.target.dataset.previo;
        if (previo === undefined || e.target.value === previo) return;

        limpiarAvisos();
        fila.classList.add('sucia');

        try {
            const r = await pedir('PUT', `${RUTA}/${fila.dataset.id}`, {
                nombre: fila.querySelector('.nombre').value.trim(),
                orden:  Number(fila.querySelector('.orden').value || 0),
            });
            pintar(r.zonas);
            repintarSelects(r.zonas);
            marcarSucio();
            aviso(elOk, 'Guardado.');
        } catch (err) {
            e.target.value = previo;          // se deja como estaba
            fila.classList.remove('sucia');
            aviso(elErr, err.message);
        }
    });

    /* ── Borrar ──────────────────────────────────────────────────────────── */

    lista.addEventListener('click', async e => {
        const btn = e.target.closest('.borrar');
        if (!btn) return;

        const fila = btn.closest('.zn-fila');
        const nombre = fila.querySelector('.nombre').value;
        limpiarAvisos();

        try {
            const r = await pedir('DELETE', `${RUTA}/${fila.dataset.id}`);
            pintar(r.zonas);
            repintarSelects(r.zonas);
            marcarSucio();
            aviso(elOk, `«${nombre}» eliminada.`);
        } catch (err) {
            if (err.estado !== 409) { aviso(elErr, err.message); return; }

            // En uso: se avisa a cuántos sitios afecta antes de dejarla sin zona.
            if (!confirm(`${err.message}\n\nSi la eliminas, esos sitios quedan sin zona.\n¿Eliminar igual?`)) return;

            try {
                const r = await pedir('DELETE', `${RUTA}/${fila.dataset.id}?forzar=1`);
                pintar(r.zonas);
                repintarSelects(r.zonas);
                marcarSucio();
                aviso(elOk, `«${nombre}» eliminada; ${r.liberados} ${r.liberados === 1 ? 'sitio quedó' : 'sitios quedaron'} sin zona.`);
            } catch (e2) { aviso(elErr, e2.message); }
        }
    });

    /* ── Ciclo del modal ─────────────────────────────────────────────────── */

    modal.addEventListener('show.bs.modal', () => {
        limpiarAvisos();
        lista.innerHTML = '<div class="zn-vacio">Cargando…</div>';
        recargar().catch(e => aviso(elErr, e.message));
    });

    modal.addEventListener('shown.bs.modal', () => nueva.focus());

    // Solo en el listado, y solo si de verdad cambió algo: es la única forma de
    // que el filtro por zona deje de estar viejo.
    modal.addEventListener('hidden.bs.modal', () => {
        if (modal.dataset.sucio === '1' && modal.dataset.recargarAlCerrar === '1') location.reload();
    });
});
</script>
@endpush
@endonce
