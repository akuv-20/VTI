@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-layout-text-window me-2"></i>Plantilla WOM</h4>
        <a href="{{ route('importaciones_wom.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver a Importaciones
        </a>
    </div>

    <p class="text-muted small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Define aquí las líneas WOM que se facturan habitualmente. El monto de referencia se pre-cargará
        al crear una nueva importación y podrás ajustarlo si varía ese mes.
    </p>

    <div class="row g-4">

        {{-- ── Líneas en plantilla ───────────────────────────────────────────── --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-2">
                    <span class="fw-semibold" style="font-size:.9rem">
                        <i class="bi bi-list-check me-1" style="color:#6f42c1"></i>Líneas en plantilla
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small" id="labelTotalPlantilla"></span>
                        <span class="badge" style="background:#6f42c1" id="badgeTotal">{{ $lineasPlantilla->count() }}</span>
                    </div>
                </div>
                <div class="card-body p-0" id="contenedorTabla">
                    @if($lineasPlantilla->isEmpty())
                        <div class="text-center text-muted py-5" id="msgVacio">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                            No hay líneas en la plantilla. Agrega líneas desde el panel de búsqueda.
                        </div>
                    @else
                    <table class="vti-table mb-0" style="font-size:.83rem" id="tablaPlantilla">
                        <thead>
                            <tr>
                                <th>Línea</th>
                                <th>Usuario</th>
                                <th>Empresa</th>
                                <th>CC</th>
                                <th>Ubicación</th>
                                <th class="text-end" style="min-width:130px">Monto ref.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPlantilla">
                            @foreach($lineasPlantilla as $p)
                            @php $l = $p->lineaTelefonica; @endphp
                            <tr id="row-{{ $p->id }}" data-monto="{{ $p->monto }}">
                                <td><strong style="color:#6f42c1">{{ $l->linea }}</strong></td>
                                <td>{{ $l->usuario->nombre ?? '—' }}</td>
                                <td style="font-size:.79rem">{{ $l->empresa->nombre ?? '—' }}</td>
                                <td style="font-size:.79rem">{{ $l->centroCosto->ccosto ?? '—' }}</td>
                                <td style="font-size:.79rem">{{ $l->ubicacion->nombre ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <input type="text"
                                               class="form-control form-control-sm text-end monto-plantilla"
                                               style="width:110px"
                                               value="{{ $p->monto > 0 ? number_format($p->monto, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               data-id="{{ $p->id }}"
                                               data-raw="{{ $p->monto }}"
                                               onblur="guardarMonto(this)"
                                               onkeydown="if(event.key==='Enter'){this.blur()}">
                                        <span class="saved-icon text-success" style="font-size:.8rem;width:14px;opacity:0">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger py-0 px-2"
                                            onclick="quitarLinea({{ $p->id }}, {{ $l->id }}, this)"
                                            title="Quitar de plantilla">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Buscador para agregar ─────────────────────────────────────────── --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-2">
                    <span class="fw-semibold" style="font-size:.9rem">
                        <i class="bi bi-search me-1"></i>Agregar línea
                    </span>
                </div>
                <div class="card-body">
                    <input type="text" id="buscarLinea" class="form-control form-control-sm mb-3"
                           placeholder="Buscar por número, usuario, empresa…"
                           autocomplete="off">
                    <div id="resultadosBuscar"></div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
    .resultado-card {
        display:flex; align-items:center; gap:.6rem;
        padding:.45rem .7rem; border:1px solid #e2e8f0; border-radius:8px;
        margin-bottom:.35rem; background:#fff; cursor:pointer;
        transition:border-color .12s, background .12s;
        font-size:.82rem;
    }
    .resultado-card:hover  { border-color:#6f42c1; background:#faf5ff; }
    .resultado-card.ya-en  { opacity:.5; cursor:default; pointer-events:none; }
    .resultado-card .lnum  { font-weight:700; color:#6f42c1; min-width:90px; }
    .monto-plantilla:focus { border-color:#6f42c1; box-shadow:0 0 0 2px rgba(111,66,193,.15); }
    .spin { animation:spin .6s linear infinite; display:inline-block; }
    @keyframes spin { to { transform:rotate(360deg); } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const buscarUrl      = @json(route('importaciones_wom.buscar_lineas'));
    const agregarUrl     = @json(route('importaciones_wom.plantilla_agregar'));
    const montoBaseUrl   = @json(url('importaciones_wom/plantilla'));
    const quitarBase     = @json(url('importaciones_wom/plantilla'));
    const csrfToken      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const enPlantilla    = new Set(@json($lineasPlantilla->pluck('id_linea_telefonica')->toArray()));
    const inputBuscar    = document.getElementById('buscarLinea');
    const resultadosDiv  = document.getElementById('resultadosBuscar');
    const badgeTotal     = document.getElementById('badgeTotal');
    const labelTotal     = document.getElementById('labelTotalPlantilla');
    let debounce;

    // ── Total plantilla ───────────────────────────────────────────────────────
    function actualizarTotal() {
        let sum = 0;
        document.querySelectorAll('.monto-plantilla').forEach(inp => {
            sum += parseFloat(inp.dataset.raw) || 0;
        });
        labelTotal.textContent = sum > 0 ? '$ ' + Math.round(sum).toLocaleString('es-CL') : '';
    }
    actualizarTotal();

    // ── Búsqueda ──────────────────────────────────────────────────────────────
    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { resultadosDiv.innerHTML = ''; return; }
        debounce = setTimeout(() => fetchLineas(q), 320);
    });

    async function fetchLineas(q) {
        const res  = await fetch(buscarUrl + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        resultadosDiv.innerHTML = data.length
            ? data.map(l => {
                const ya = enPlantilla.has(l.id);
                return `<div class="resultado-card ${ya ? 'ya-en' : ''}"
                             onclick="agregarLinea(${l.id}, '${esc(l.linea)}', '${esc(l.usuario)}', '${esc(l.empresa)}', '${esc(l.cc)}', '${esc(l.ubicacion)}')">
                    <span class="lnum"><i class="bi bi-phone me-1"></i>${l.linea}</span>
                    <span class="flex-fill text-muted small">${l.usuario} · ${l.empresa}</span>
                    ${ya ? '<span class="badge bg-secondary">En plantilla</span>'
                         : '<span class="badge" style="background:#6f42c1">+ Agregar</span>'}
                </div>`;
            }).join('')
            : '<p class="text-muted small">Sin resultados.</p>';
    }

    function esc(s) { return (s || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

    // ── Agregar ───────────────────────────────────────────────────────────────
    window.agregarLinea = async function (idLinea, linea, usuario, empresa, cc, ubicacion) {
        if (enPlantilla.has(idLinea)) return;

        const res  = await fetch(agregarUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ id_linea_telefonica: idLinea }),
        });
        const data = await res.json();
        if (!data.ok) return;

        enPlantilla.add(idLinea);
        badgeTotal.textContent = enPlantilla.size;

        // Crear tabla si estaba vacía
        let tbody = document.getElementById('tbodyPlantilla');
        if (!tbody) {
            document.getElementById('contenedorTabla').innerHTML = `
                <table class="vti-table mb-0" style="font-size:.83rem" id="tablaPlantilla">
                    <thead><tr>
                        <th>Línea</th><th>Usuario</th><th>Empresa</th>
                        <th>CC</th><th>Ubicación</th>
                        <th class="text-end" style="min-width:130px">Monto ref.</th><th></th>
                    </tr></thead>
                    <tbody id="tbodyPlantilla"></tbody>
                </table>`;
            tbody = document.getElementById('tbodyPlantilla');
        }

        const tr = document.createElement('tr');
        tr.id = 'row-' + data.id;
        tr.dataset.monto = 0;
        tr.innerHTML = `
            <td><strong style="color:#6f42c1">${linea}</strong></td>
            <td>${usuario}</td>
            <td style="font-size:.79rem">${empresa}</td>
            <td style="font-size:.79rem">${cc}</td>
            <td style="font-size:.79rem">${ubicacion}</td>
            <td class="text-end">
                <div class="d-flex align-items-center justify-content-end gap-1">
                    <input type="text" class="form-control form-control-sm text-end monto-plantilla"
                           style="width:110px" placeholder="0"
                           data-id="${data.id}" data-raw="0"
                           onblur="guardarMonto(this)"
                           onkeydown="if(event.key==='Enter'){this.blur()}">
                    <span class="saved-icon text-success" style="font-size:.8rem;width:14px;opacity:0">
                        <i class="bi bi-check-lg"></i>
                    </span>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                        onclick="quitarLinea(${data.id}, ${idLinea}, this)" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);

        // Marcar en resultados como "en plantilla"
        resultadosDiv.querySelectorAll(`[onclick*="agregarLinea(${idLinea},"]`).forEach(c => {
            c.classList.add('ya-en');
            const badge = c.querySelector('.badge');
            if (badge) { badge.textContent = 'En plantilla'; badge.style.background = ''; badge.className = 'badge bg-secondary'; }
        });

        // Limpiar y enfocar buscador
        inputBuscar.value = '';
        resultadosDiv.innerHTML = '';
        inputBuscar.focus();
    };

    // ── Guardar monto ─────────────────────────────────────────────────────────
    window.guardarMonto = async function (input) {
        const raw    = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        const pId    = input.dataset.id;
        const icono  = input.closest('td').querySelector('.saved-icon');

        // Formatear visual
        input.value     = raw > 0 ? Math.round(raw).toLocaleString('es-CL') : '';
        input.dataset.raw = raw;

        // Guardar via AJAX
        await fetch(`${montoBaseUrl}/${pId}/monto`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ monto: raw }),
        });

        // Mostrar ✓ brevemente
        icono.style.opacity = '1';
        setTimeout(() => { icono.style.opacity = '0'; }, 1500);

        actualizarTotal();
    };

    // ── Quitar ────────────────────────────────────────────────────────────────
    window.quitarLinea = async function (plantillaId, idLinea, btn) {
        const tr  = btn.closest('tr');
        const res = await fetch(`${quitarBase}/${plantillaId}/quitar`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!data.ok) return;

        enPlantilla.delete(idLinea);
        tr.remove();
        badgeTotal.textContent = parseInt(badgeTotal.textContent) - 1;
        actualizarTotal();

        // Si no quedan filas, mostrar vacío
        const tbody = document.getElementById('tbodyPlantilla');
        if (tbody && !tbody.children.length) {
            document.getElementById('contenedorTabla').innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                    No hay líneas en la plantilla. Agrega líneas desde el panel de búsqueda.
                </div>`;
        }
    };
})();
</script>
@endpush
