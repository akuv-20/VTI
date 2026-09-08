{{-- Modales de creación rápida (usuario, ubicación, aparato) usados por los "+" de los formularios. --}}

{{-- Modal simple: un solo campo "nombre" (usuario / ubicación) --}}
<div class="modal fade" id="modalCrearSimple" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="crSimpleTitulo">Nuevo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold" id="crSimpleLabel">Nombre</label>
                <input type="text" class="form-control" id="crSimpleNombre" maxlength="255">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="crSimpleGuardar"><i class="bi bi-check-lg me-1"></i>Crear</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal aparato: marca (existente o nueva) + modelo --}}
<div class="modal fade" id="modalCrearAparato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-phone me-1"></i>Nuevo aparato (modelo)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label fw-semibold">Marca</label>
                    <select class="form-select" id="crAparatoMarca">
                        <option value="">-- Sin marca --</option>
                        @foreach(($marcas ?? []) as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">¿No está? <a href="#" id="crAparatoMarcaToggle">Escribir una marca nueva</a></div>
                    <input type="text" class="form-control mt-1 d-none" id="crAparatoMarcaNueva" placeholder="Nombre de la marca nueva" maxlength="255">
                </div>
                <div>
                    <label class="form-label fw-semibold">Modelo</label>
                    <input type="text" class="form-control" id="crAparatoModelo" maxlength="255" placeholder="Ej: Galaxy A26">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="crAparatoGuardar"><i class="bi bi-check-lg me-1"></i>Crear</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const rutas = {
        usuario:   @json(route('creacion_rapida.usuario')),
        ubicacion: @json(route('creacion_rapida.ubicacion')),
        marca:     @json(route('creacion_rapida.marca')),
        aparato:   @json(route('creacion_rapida.aparato')),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const titulos = { usuario: 'Nuevo usuario', ubicacion: 'Nueva ubicación' };

    let targetSelectId = null;   // select donde agregar la opción creada
    let tipoActual = null;

    async function post(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        if (!r.ok) {
            let msg = 'Error ' + r.status;
            try { const j = await r.json(); msg = Object.values(j.errors ?? {})[0]?.[0] || j.message || msg; } catch (e) {}
            throw new Error(msg);
        }
        return r.json();
    }

    function agregarOpcion(selectId, id, label) {
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.add(new Option(label, id, true, true));
    }

    function abrir(id) {
        const el = document.getElementById(id);
        try { bootstrap.Modal.getOrCreateInstance(el).show(); }
        catch (e) {
            el.classList.add('show'); el.style.display = 'block'; el.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            const bd = document.createElement('div'); bd.className = 'modal-backdrop fade show'; bd.dataset.for = id;
            document.body.appendChild(bd);
        }
    }
    function cerrar(id) {
        const el = document.getElementById(id);
        try { bootstrap.Modal.getInstance(el)?.hide(); } catch (e) {}
        el.classList.remove('show'); el.style.display = 'none';
        document.querySelector(`.modal-backdrop[data-for="${id}"]`)?.remove();
        if (!document.querySelector('.modal.show')) document.body.classList.remove('modal-open');
    }
    // cierre por X / cancelar en modo fallback
    document.querySelectorAll('#modalCrearSimple, #modalCrearAparato').forEach(m => {
        m.addEventListener('click', e => {
            if (document.querySelector(`.modal-backdrop[data-for="${m.id}"]`) &&
                (e.target.closest('[data-bs-dismiss="modal"]') || e.target === m)) cerrar(m.id);
        });
    });

    // Clic en cualquier "+" de creación rápida
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-crear-rapido');
        if (!btn) return;
        e.preventDefault();
        targetSelectId = btn.dataset.target;
        const tipo = btn.dataset.tipo;

        if (tipo === 'aparato') {
            document.getElementById('crAparatoModelo').value = '';
            document.getElementById('crAparatoMarcaNueva').value = '';
            document.getElementById('crAparatoMarcaNueva').classList.add('d-none');
            document.getElementById('crAparatoMarca').classList.remove('d-none');
            abrir('modalCrearAparato');
            setTimeout(() => document.getElementById('crAparatoModelo').focus(), 200);
        } else {
            tipoActual = tipo;
            document.getElementById('crSimpleTitulo').textContent = titulos[tipo] || 'Nuevo';
            document.getElementById('crSimpleLabel').textContent = 'Nombre';
            document.getElementById('crSimpleNombre').value = '';
            abrir('modalCrearSimple');
            setTimeout(() => document.getElementById('crSimpleNombre').focus(), 200);
        }
    });

    // Guardar simple (usuario / ubicación)
    document.getElementById('crSimpleGuardar')?.addEventListener('click', async function () {
        const nombre = document.getElementById('crSimpleNombre').value.trim();
        if (!nombre) return;
        this.disabled = true;
        try {
            const d = await post(rutas[tipoActual], { nombre });
            agregarOpcion(targetSelectId, d.id, d.label);
            cerrar('modalCrearSimple');
        } catch (err) { alert('No se pudo crear: ' + err.message); }
        finally { this.disabled = false; }
    });

    // Toggle "marca nueva" en el modal de aparato
    document.getElementById('crAparatoMarcaToggle')?.addEventListener('click', function (e) {
        e.preventDefault();
        const input = document.getElementById('crAparatoMarcaNueva');
        const select = document.getElementById('crAparatoMarca');
        input.classList.toggle('d-none');
        if (!input.classList.contains('d-none')) { input.focus(); } else { input.value = ''; select.classList.remove('d-none'); }
    });

    // Guardar aparato (crea marca nueva si corresponde, luego el aparato)
    document.getElementById('crAparatoGuardar')?.addEventListener('click', async function () {
        const modelo = document.getElementById('crAparatoModelo').value.trim();
        if (!modelo) { alert('Indica el modelo.'); return; }
        const marcaNueva = document.getElementById('crAparatoMarcaNueva');
        this.disabled = true;
        try {
            let idMarca = document.getElementById('crAparatoMarca').value || null;
            if (!marcaNueva.classList.contains('d-none') && marcaNueva.value.trim()) {
                const m = await post(rutas.marca, { nombre: marcaNueva.value.trim() });
                idMarca = m.id;
            }
            const d = await post(rutas.aparato, { modelo, id_marca: idMarca });
            agregarOpcion(targetSelectId, d.id, d.label);
            cerrar('modalCrearAparato');
        } catch (err) { alert('No se pudo crear: ' + err.message); }
        finally { this.disabled = false; }
    });
})();
</script>
@endpush
