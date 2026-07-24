@extends('layouts.app')

@section('content')
<style>
    .kpis-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
    .kpis-card h6 { font-size:.82rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }
    .kpis-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .kpis-table th, .kpis-table td { padding:.5rem .6rem; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:middle; }
    .kpis-table th { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; font-weight:700; }
    .kpis-obj { font-family:ui-monospace,monospace; font-size:.74rem; color:#64748b; }
    .kpis-explorer-list { max-height:280px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px; }
    .kpis-explorer-list .item { padding:.35rem .6rem; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:.8rem; display:flex; align-items:center; gap:.5rem; }
    .kpis-explorer-list .item:hover { background:#f0fdf4; }
    .kpis-explorer-list .item.host { font-weight:600; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-hdd-stack me-2" style="color:#16a34a"></i>Servicios críticos · KPI Disponibilidad</h4>
        <a href="{{ route('admin.kpi.disponibilidad.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-activity me-1"></i>Dashboard
        </a>
    </div>

    @unless($configurado)
        <div class="alert alert-warning py-2" style="font-size:.83rem">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            CheckMK no está configurado, así que el explorador no funcionará. Ve a
            <a href="{{ route('admin.configuracion.index') }}">Admin → Configuración → CheckMK</a>.
            Igual puedes agregar servicios manualmente abajo.
        </div>
    @endunless

    <div class="row g-3">
        {{-- ── Explorador CheckMK ─────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="kpis-card">
                <h6><i class="bi bi-search me-1"></i>Explorar CheckMK</h6>
                <div class="d-flex gap-2 mb-2">
                    <button type="button" id="btnCargarHosts" class="btn btn-outline-primary btn-sm" {{ $configurado ? '' : 'disabled' }}>
                        <i class="bi bi-arrow-repeat me-1"></i>Cargar hosts
                    </button>
                    <input type="text" id="filtroExplorer" class="form-control form-control-sm" placeholder="Filtrar…">
                </div>
                <div id="explorerBreadcrumb" class="text-muted mb-1" style="font-size:.74rem;display:none"></div>
                <div class="kpis-explorer-list" id="explorerList">
                    <div class="text-muted text-center py-4" style="font-size:.8rem">Pulsa «Cargar hosts» para empezar.</div>
                </div>
                <div class="text-muted mt-2" style="font-size:.72rem">
                    Clic en un host para ver sus servicios. Clic en «(host)» o en un servicio para agregarlo como crítico.
                </div>
            </div>
        </div>

        {{-- ── Alta manual ─────────────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="kpis-card">
                <h6><i class="bi bi-plus-square me-1"></i>Agregar manualmente</h6>
                <form method="POST" action="{{ route('admin.kpi.disponibilidad.servicios.store') }}" id="formAgregar">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:.75rem">Host <span class="text-danger">*</span></label>
                            <input type="text" name="host_name" id="f_host" class="form-control form-control-sm" required placeholder="srv-datacenter01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:.75rem">Servicio <span class="text-muted">(vacío = host completo)</span></label>
                            <input type="text" name="service_description" id="f_service" class="form-control form-control-sm" placeholder="CPU load">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.75rem">Etiqueta <span class="text-danger">*</span></label>
                            <input type="text" name="etiqueta" id="f_etiqueta" class="form-control form-control-sm" required placeholder="Datacenter · CPU">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.75rem">Sector</label>
                            <select name="sector" class="form-select form-select-sm">
                                <option value="">— Sin asignar —</option>
                                @foreach(\App\Models\KpiServicioCritico::SECTORES as $val => $lbl)
                                    <option value="{{ $val }}" @selected(old('sector') === $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.75rem">Grupo</label>
                            <input type="text" name="grupo" id="f_grupo" class="form-control form-control-sm" placeholder="Datacenter" list="gruposExistentes">
                            <datalist id="gruposExistentes">
                                @foreach($servicios->pluck('grupo')->filter()->unique() as $g)<option value="{{ $g }}">@endforeach
                            </datalist>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" style="font-size:.75rem">Orden</label>
                            <input type="number" name="orden" class="form-control form-control-sm" value="0" min="0" max="9999">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm mt-3">
                        <i class="bi bi-plus-lg me-1"></i>Agregar servicio crítico
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Listado actual ─────────────────────────────────────────────────── --}}
    <div class="kpis-card">
        <h6><i class="bi bi-list-check me-1"></i>Servicios críticos definidos ({{ $servicios->count() }})</h6>
        @if($servicios->isEmpty())
            <div class="text-muted text-center py-4" style="font-size:.85rem">Aún no hay servicios críticos.</div>
        @else
        <div style="overflow-x:auto">
        <table class="kpis-table">
            <thead>
                <tr>
                    <th style="width:34px">#</th>
                    <th>Etiqueta</th>
                    <th>Objeto CheckMK</th>
                    <th style="width:90px">Sector</th>
                    <th>Grupo</th>
                    <th style="width:70px">Estado</th>
                    <th style="width:120px;text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($servicios as $s)
                <tr class="{{ $s->activo ? '' : 'text-muted' }}">
                    <td>{{ $s->orden }}</td>
                    <td class="fw-semibold">{{ $s->etiqueta }}</td>
                    <td class="kpis-obj">{{ $s->objeto }}</td>
                    <td>
                        @if($s->sector === 'planta')
                            <span class="badge" style="background:#0369a1;font-size:.65rem"><i class="bi bi-building-fill me-1"></i>Planta</span>
                        @elseif($s->sector === 'campo')
                            <span class="badge" style="background:#15803d;font-size:.65rem"><i class="bi bi-tree-fill me-1"></i>Campo</span>
                        @else
                            <span class="badge bg-warning text-dark" style="font-size:.65rem">Sin asignar</span>
                        @endif
                    </td>
                    <td>{{ $s->grupo ?: '—' }}</td>
                    <td>
                        @if($s->activo)
                            <span class="badge bg-success" style="font-size:.65rem">Activo</span>
                        @else
                            <span class="badge bg-secondary" style="font-size:.65rem">Inactivo</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 btn-edit"
                                data-id="{{ $s->id }}" data-etiqueta="{{ $s->etiqueta }}" data-grupo="{{ $s->grupo }}" data-orden="{{ $s->orden }}"
                                title="Editar"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('admin.kpi.disponibilidad.servicios.toggle', $s) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" title="{{ $s->activo ? 'Desactivar' : 'Activar' }}">
                                <i class="bi {{ $s->activo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.kpi.disponibilidad.servicios.destroy', $s) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar «{{ $s->etiqueta }}»? Se conservará el histórico ya capturado.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                {{-- Fila de edición inline (oculta) --}}
                <tr id="edit-{{ $s->id }}" style="display:none;background:#f8fafc">
                    <td></td>
                    <td colspan="6">
                        <form method="POST" action="{{ route('admin.kpi.disponibilidad.servicios.update', $s) }}" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf @method('PUT')
                            <div>
                                <label class="form-label" style="font-size:.72rem">Etiqueta</label>
                                <input type="text" name="etiqueta" class="form-control form-control-sm" value="{{ $s->etiqueta }}" required>
                            </div>
                            <div>
                                <label class="form-label" style="font-size:.72rem">Sector</label>
                                <select name="sector" class="form-select form-select-sm">
                                    <option value="">— Sin asignar —</option>
                                    @foreach(\App\Models\KpiServicioCritico::SECTORES as $val => $lbl)
                                        <option value="{{ $val }}" @selected($s->sector === $val)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" style="font-size:.72rem">Grupo</label>
                                <input type="text" name="grupo" class="form-control form-control-sm" value="{{ $s->grupo }}">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:.72rem">Orden</label>
                                <input type="number" name="orden" class="form-control form-control-sm" style="width:80px" value="{{ $s->orden }}" min="0" max="9999">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
const explorarUrl = '{{ route("admin.kpi.disponibilidad.explorar") }}';
const listEl   = document.getElementById('explorerList');
const crumbEl  = document.getElementById('explorerBreadcrumb');
const filtroEl = document.getElementById('filtroExplorer');
let itemsActuales = []; // {tipo:'host'|'host_root'|'service', host, service, label}

function renderItems() {
    const q = (filtroEl.value || '').toLowerCase();
    const filtrados = itemsActuales.filter(it => it.label.toLowerCase().includes(q));
    if (!filtrados.length) {
        listEl.innerHTML = '<div class="text-muted text-center py-4" style="font-size:.8rem">Sin resultados.</div>';
        return;
    }
    listEl.innerHTML = filtrados.map((it, i) =>
        `<div class="item ${it.tipo === 'host' ? 'host' : ''}" data-i="${i}">
            <i class="bi ${it.tipo === 'host' ? 'bi-hdd-network' : (it.tipo === 'host_root' ? 'bi-server' : 'bi-gear')}"></i>
            <span>${it.label}</span>
        </div>`
    ).join('');
    // Reasignar índices al set filtrado
    listEl.querySelectorAll('.item').forEach((el, idx) => {
        el.addEventListener('click', () => onItemClick(filtrados[idx]));
    });
}

function loading() {
    listEl.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';
}

function cargarHosts() {
    loading();
    crumbEl.style.display = 'none';
    fetch(explorarUrl).then(r => r.json()).then(d => {
        if (d.error) { listEl.innerHTML = `<div class="text-danger p-3" style="font-size:.8rem">${d.error}</div>`; return; }
        itemsActuales = (d.hosts || []).map(h => ({ tipo:'host', host:h.host_name, label:h.host_name }));
        filtroEl.value = '';
        renderItems();
    }).catch(() => { listEl.innerHTML = '<div class="text-danger p-3">Error de conexión.</div>'; });
}

function cargarServicios(host) {
    loading();
    fetch(explorarUrl + '?host=' + encodeURIComponent(host)).then(r => r.json()).then(d => {
        if (d.error) { listEl.innerHTML = `<div class="text-danger p-3" style="font-size:.8rem">${d.error}</div>`; return; }
        const root = [{ tipo:'host_root', host:host, service:'', label:host + ' (host completo)' }];
        const svcs = (d.servicios || []).map(s => ({ tipo:'service', host:s.host_name, service:s.service_description, label:s.service_description }));
        itemsActuales = root.concat(svcs);
        crumbEl.style.display = 'block';
        crumbEl.innerHTML = `<a href="#" id="volverHosts"><i class="bi bi-arrow-left"></i> Hosts</a> / <strong>${host}</strong>`;
        document.getElementById('volverHosts').addEventListener('click', e => { e.preventDefault(); cargarHosts(); });
        filtroEl.value = '';
        renderItems();
    }).catch(() => { listEl.innerHTML = '<div class="text-danger p-3">Error de conexión.</div>'; });
}

function onItemClick(it) {
    if (it.tipo === 'host') { cargarServicios(it.host); return; }
    // host_root o service → precargar formulario de alta
    document.getElementById('f_host').value    = it.host;
    document.getElementById('f_service').value = it.service || '';
    document.getElementById('f_etiqueta').value = it.service ? (it.host + ' · ' + it.service) : (it.host);
    document.getElementById('f_etiqueta').focus();
    document.getElementById('formAgregar').scrollIntoView({ behavior:'smooth', block:'center' });
}

document.getElementById('btnCargarHosts')?.addEventListener('click', cargarHosts);
filtroEl?.addEventListener('input', renderItems);

// Edición inline
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = document.getElementById('edit-' + btn.dataset.id);
        row.style.display = row.style.display === 'none' ? '' : 'none';
    });
});
</script>
@endpush
@endsection
