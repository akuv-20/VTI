@extends('layouts.app')

@push('styles')
<style>
    #resultado-busqueda .factura-resultado-item {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 6px;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: .84rem;
        transition: border-color .15s;
    }
    #resultado-busqueda .factura-resultado-item:hover { border-color: #93c5fd; }
    #resultado-busqueda .factura-resultado-item .info { flex: 1; min-width: 0; }
    #resultado-busqueda .factura-resultado-item .nro {
        font-weight: 700; font-size: .92rem; color: #1e293b;
    }
    #resultado-busqueda .factura-resultado-item .prov {
        color: #475569; font-size: .8rem; margin-top: 1px;
    }
    #resultado-busqueda .factura-resultado-item .meta {
        color: #94a3b8; font-size: .75rem; margin-top: 1px;
    }
    #resultado-busqueda .factura-resultado-item .monto {
        font-weight: 600; color: #0f172a; font-size: .88rem; white-space: nowrap;
    }
    #tabla-seleccionadas tbody tr td { font-size: .84rem; vertical-align: middle; }
    .spinner-busqueda { display: none; }
    .spinner-busqueda.activo { display: inline-block; }
</style>
@endpush

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-box-arrow-up-right me-2"></i>Nueva Entrega de Facturas</h4>
        <a href="{{ route('entregas_facturas.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible py-2 px-3 mb-3" style="font-size:.88rem">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        {{ $errors->first() }}
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4" style="max-width:1100px">

        {{-- ── Formulario principal ──────────────────────────────── --}}
        <div class="col-12">
            <form action="{{ route('entregas_facturas.store') }}" method="POST" id="form-entrega">
                @csrf

                {{-- Observación --}}
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header fw-bold border-0" style="background:#f8fafc;font-size:.88rem">
                        <i class="bi bi-pencil me-2 text-primary"></i>Observación (opcional)
                    </div>
                    <div class="card-body">
                        <textarea name="observacion" class="form-control form-control-sm"
                                  rows="2" maxlength="500"
                                  placeholder="Ej: Facturas período mayo 2026, área TI"
                                  >{{ old('observacion') }}</textarea>
                    </div>
                </div>

                {{-- Buscador de facturas --}}
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header fw-bold border-0" style="background:#f8fafc;font-size:.88rem">
                        <i class="bi bi-search me-2 text-primary"></i>Buscar y agregar facturas
                        <small class="text-muted fw-normal ms-2">Solo se muestran facturas sin entrega asignada</small>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 mb-3">
                            <div class="flex-grow-1 position-relative">
                                <input type="text" id="buscador-facturas"
                                       class="form-control form-control-sm"
                                       placeholder="Número de factura o nombre de proveedor…"
                                       autocomplete="off">
                                <span class="spinner-busqueda spinner-border spinner-border-sm text-secondary"
                                      style="position:absolute;right:10px;top:7px"></span>
                            </div>
                        </div>
                        <div id="resultado-busqueda"></div>
                    </div>
                </div>

                {{-- Facturas seleccionadas --}}
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header fw-bold border-0 d-flex align-items-center justify-content-between"
                         style="background:#f8fafc;font-size:.88rem">
                        <span><i class="bi bi-list-check me-2 text-success"></i>Facturas en esta entrega</span>
                        <span class="badge bg-success rounded-pill" id="badge-count">0</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                            <table class="vti-table" id="tabla-seleccionadas">
                                <thead>
                                    <tr>
                                        <th>Nro. Factura</th>
                                        <th>Proveedor</th>
                                        <th>Descripción</th>
                                        <th>Cuenta Contable</th>
                                        <th style="text-align:right">Total</th>
                                        <th style="text-align:right">Sin IVA</th>
                                        <th style="width:50px"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-seleccionadas">
                                    <tr id="tr-vacio" class="vti-empty">
                                        <td colspan="7">Aún no has agregado facturas. Usa el buscador de arriba.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Inputs ocultos de facturas seleccionadas --}}
                <div id="inputs-ocultos"></div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success" id="btn-guardar" disabled>
                        <i class="bi bi-check-lg me-1"></i>Registrar Entrega
                    </button>
                    <a href="{{ route('entregas_facturas.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>

            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const buscador      = document.getElementById('buscador-facturas');
    const resultadoDiv  = document.getElementById('resultado-busqueda');
    const tbodySel      = document.getElementById('tbody-seleccionadas');
    const trVacio       = document.getElementById('tr-vacio');
    const inputsOcultos = document.getElementById('inputs-ocultos');
    const badgeCount    = document.getElementById('badge-count');
    const btnGuardar    = document.getElementById('btn-guardar');
    const spinner       = document.querySelector('.spinner-busqueda');

    let seleccionadas = {};   // {id: data}
    let debounceTimer = null;

    // ── Formato chileno ──────────────────────────────────────────────────────
    function fmt(n) {
        return '$ ' + Number(n).toLocaleString('es-CL', {maximumFractionDigits: 0});
    }

    // ── Actualizar tabla de seleccionadas ────────────────────────────────────
    function renderSeleccionadas() {
        const ids = Object.keys(seleccionadas);
        badgeCount.textContent  = ids.length;
        btnGuardar.disabled     = ids.length === 0;
        inputsOcultos.innerHTML = ids.map(id =>
            `<input type="hidden" name="facturas[]" value="${id}">`
        ).join('');

        // Limpiar filas previas (excepto tr-vacio)
        Array.from(tbodySel.querySelectorAll('tr[data-id]')).forEach(r => r.remove());

        if (ids.length === 0) {
            trVacio.style.display = '';
            return;
        }
        trVacio.style.display = 'none';

        ids.forEach(id => {
            const f = seleccionadas[id];
            const tr = document.createElement('tr');
            tr.dataset.id = id;
            tr.innerHTML = `
                <td><strong>${escHtml(f.factura)}</strong></td>
                <td>
                    ${f.proveedor_rut ? `<span class="text-muted me-1" style="font-size:.75rem">${escHtml(f.proveedor_rut)}</span>` : ''}
                    ${escHtml(f.proveedor_nombre)}
                </td>
                <td class="text-muted">${escHtml(f.descripcion)}</td>
                <td class="text-muted" style="font-size:.78rem">${escHtml(f.cuenta_contable)}</td>
                <td style="text-align:right;font-weight:600">${fmt(f.total)}</td>
                <td style="text-align:right;color:#475569">${fmt(f.valor_neto)}</td>
                <td>
                    <button type="button" class="btn btn-link btn-sm text-danger p-0"
                            onclick="quitarFactura(${id})" title="Quitar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            `;
            tbodySel.appendChild(tr);
        });
    }

    // ── Agregar / Quitar ─────────────────────────────────────────────────────
    window.agregarFactura = function (data) {
        seleccionadas[data.id] = data;
        renderSeleccionadas();
        // Refrescar resultados para marcar el ítem como ya agregado
        renderResultados(Object.values(window._ultimoResultado || {}));
    };

    window.quitarFactura = function (id) {
        delete seleccionadas[id];
        renderSeleccionadas();
        renderResultados(Object.values(window._ultimoResultado || {}));
    };

    // ── Renderizar resultados de búsqueda ────────────────────────────────────
    function renderResultados(facturas) {
        if (!facturas.length) {
            resultadoDiv.innerHTML = '<p class="text-muted small mt-2 mb-0">No se encontraron facturas disponibles.</p>';
            return;
        }
        resultadoDiv.innerHTML = facturas.map(f => {
            const yaAgregada = seleccionadas[f.id] !== undefined;
            return `
            <div class="factura-resultado-item ${yaAgregada ? 'opacity-50' : ''}">
                <div class="info">
                    <div class="nro">Nro. ${escHtml(f.factura)}
                        ${f.fecha_emision ? `<span class="fw-normal text-muted ms-2" style="font-size:.75rem">${escHtml(f.fecha_emision)}</span>` : ''}
                    </div>
                    <div class="prov">
                        ${f.proveedor_rut ? `<span class="text-muted me-1">${escHtml(f.proveedor_rut)}</span>` : ''}
                        ${escHtml(f.proveedor_nombre)}
                    </div>
                    <div class="meta">${escHtml(f.descripcion)} · ${escHtml(f.cuenta_contable)}</div>
                </div>
                <div class="monto text-end">
                    <div>${fmt(f.total)}</div>
                    <div class="text-muted" style="font-size:.75rem">neto ${fmt(f.valor_neto)}</div>
                </div>
                ${yaAgregada
                    ? '<span class="badge bg-success" style="font-size:.72rem">Agregada</span>'
                    : `<button type="button" class="btn btn-outline-primary btn-sm"
                               onclick='agregarFactura(${JSON.stringify(f)})'>
                           <i class="bi bi-plus-lg"></i> Agregar
                       </button>`
                }
            </div>`;
        }).join('');
    }

    // ── Búsqueda con debounce ────────────────────────────────────────────────
    buscador.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            resultadoDiv.innerHTML = '';
            window._ultimoResultado = [];
            return;
        }
        debounceTimer = setTimeout(() => buscar(q), 350);
    });

    async function buscar(q) {
        spinner.classList.add('activo');
        resultadoDiv.innerHTML = '';
        try {
            const res  = await fetch(`{{ route('entregas_facturas.buscar') }}?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            window._ultimoResultado = data;
            renderResultados(data);
        } catch (e) {
            resultadoDiv.innerHTML = '<p class="text-danger small mt-2">Error al buscar. Intenta nuevamente.</p>';
        } finally {
            spinner.classList.remove('activo');
        }
    }

    function escHtml(str) {
        if (str == null) return '—';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Inicializar
    renderSeleccionadas();
})();
</script>
@endpush
