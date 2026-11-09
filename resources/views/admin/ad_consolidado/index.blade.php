@extends('layouts.app')
@section('content')
@php
    // Datos planos para el script (@json no admite closures).
    $listaConexiones = [];
    foreach ($conexiones as $nombre => $meta) {
        $listaConexiones[] = ['clave' => $nombre, 'grupo' => $meta['grupo'], 'url' => route('admin.ad_consolidado.datos', $nombre)];
    }
    $colsEntorno = ['Grupo empresarial', 'Bosque (Forest)', 'Dominio DNS', 'País / sede', 'N° controladores de dominio',
        'Windows Server (versiones)', 'Nivel funcional AD', 'Sincronización Entra ID / M365', 'Relaciones de confianza',
        'Responsable técnico', 'Observaciones'];
    $colsCuentas = ['Grupo empresarial', 'Dominio', 'Tipo de cuenta / objeto', 'Usuario de inicio de sesión (sAMAccountName)',
        'Correo corporativo', 'Nombre / descripción de la cuenta', 'Estado (habilitada/deshabilitada)', 'Área / sistema asociado',
        'OU / ubicación en AD', 'Grupos / privilegios relevantes', 'Último inicio de sesión', 'Responsable / propietario',
        '¿Requiere migración?', 'Observaciones'];
    $tipos = ['Usuario', 'Administrador', 'Servicio', 'Genérica/Compartida', 'Otro', 'Equipo', 'Grupo'];
@endphp

<style>
    .cad-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
    .cad-chip { display:inline-flex; align-items:center; gap:.35rem; font-size:.78rem; padding:.25rem .6rem; border-radius:999px;
                border:1px solid #e2e8f0; background:#f8fafc; color:#475569; }
    .cad-chip i { font-size:.8rem; }
    .cad-chip.ok { border-color:#bbf7d0; background:#f0fdf4; color:#166534; }
    .cad-chip.err { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
    .cad-tabla-wrap { max-height:calc(100vh - 330px); min-height:260px; overflow:auto; border:1px solid #e2e8f0; border-radius:.4rem; }
    .cad-tabla { font-size:.74rem; margin:0; white-space:nowrap; }
    .cad-tabla thead th { position:sticky; top:0; background:#f1f5f9; z-index:1; font-weight:600; color:#334155;
                          border-bottom:1px solid #cbd5e1; }
    .cad-tabla td { max-width:340px; overflow:hidden; text-overflow:ellipsis; }
    .cad-tabla td.envolver { white-space:normal; min-width:260px; }
    .cad-filtros .form-check-inline { margin-right:.6rem; font-size:.8rem; }
    .cad-badge-tipo { font-size:.68rem; font-weight:600; }
    .cad-aviso { font-size:.78rem; color:#64748b; }
    .cad-copiado { animation:cad-ok 1.6s ease; }
    @keyframes cad-ok { 0%,60% { box-shadow:0 0 0 .25rem rgba(34,197,94,.35); } 100% { box-shadow:none; } }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-table me-2"></i>Consolidado AD</h4>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="cad-actualizar">
            <i class="bi bi-arrow-clockwise me-1"></i>Volver a leer los dominios
        </button>
    </div>

    @if(empty($listaConexiones))
        <div class="alert alert-warning">No hay dominios AD configurados. Configúralos en Admin → Configuración.</div>
    @else

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="cad-aviso">Dominios:</span>
        <div class="cad-chips" id="cad-chips"></div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cad-entorno" type="button">
            <i class="bi bi-diagram-3 me-1"></i>01 · Entorno AD</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cad-cuentas" type="button">
            <i class="bi bi-people me-1"></i>02 · Cuentas AD <span class="badge bg-secondary ms-1" id="cad-total">0</span></button></li>
    </ul>

    <div class="tab-content">

        {{-- ── 01 · Entorno AD ─────────────────────────────────────────── --}}
        <div class="tab-pane fade show active" id="cad-entorno">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm" id="cad-copiar-entorno">
                    <i class="bi bi-clipboard me-1"></i>Copiar
                </button>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="cad-cab-entorno">
                    <label class="form-check-label small" for="cad-cab-entorno">Incluir encabezados</label>
                </div>
                <span class="cad-aviso">Pega en la celda <strong>A5</strong> de la hoja «Entorno AD». «Responsable técnico» queda en blanco.</span>
            </div>
            <div class="cad-tabla-wrap">
                <table class="table table-sm table-hover cad-tabla">
                    <thead><tr>@foreach($colsEntorno as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                    <tbody id="cad-tb-entorno"></tbody>
                </table>
            </div>
        </div>

        {{-- ── 02 · Cuentas AD ─────────────────────────────────────────── --}}
        <div class="tab-pane fade" id="cad-cuentas">
            <div class="cad-filtros d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="input-group input-group-sm" style="max-width:260px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="cad-buscar" placeholder="Buscar en todas las columnas…">
                </div>
                <select class="form-select form-select-sm" id="cad-dominio" style="max-width:190px">
                    <option value="">Todos los dominios</option>
                </select>
                <select class="form-select form-select-sm" id="cad-estado" style="max-width:170px">
                    <option value="">Todos los estados</option>
                    <option>Habilitada</option><option>Deshabilitada</option><option>Bloqueada</option><option>No aplica</option>
                </select>
                <div>
                    @foreach($tipos as $i => $t)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input cad-tipo" type="checkbox" id="cad-tipo-{{ $i }}" value="{{ $t }}"
                                   {{ in_array($t, ['Equipo', 'Grupo']) ? '' : 'checked' }}>
                            <label class="form-check-label" for="cad-tipo-{{ $i }}">{{ $t }} <span class="text-muted" data-cuenta="{{ $t }}"></span></label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm" id="cad-copiar-cuentas">
                    <i class="bi bi-clipboard me-1"></i>Copiar <span id="cad-n-filtradas">0</span> filas
                </button>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="cad-cab-cuentas">
                    <label class="form-check-label small" for="cad-cab-cuentas">Incluir encabezados</label>
                </div>
                <span class="cad-aviso" id="cad-mostrando"></span>
            </div>
            <div class="cad-tabla-wrap">
                <table class="table table-sm table-hover cad-tabla">
                    <thead><tr>@foreach($colsCuentas as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                    <tbody id="cad-tb-cuentas"></tbody>
                </table>
            </div>
            <p class="cad-aviso mt-2 mb-0">
                Pega en la celda <strong>A5</strong> de la hoja «Cuentas AD». El tipo, el área y «¿Requiere migración?» son una
                sugerencia automática para revisar: Sí = habilitada con inicio de sesión en los últimos 90 días; No = deshabilitada
                y sin uso hace más de 180; el resto, Por validar. «Responsable» solo se llena cuando AD tiene el jefe
                (<code>manager</code>) o el dueño (<code>managedBy</code>).
            </p>
        </div>
    </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const CONEXIONES = @json($listaConexiones);
    const COLS_ENTORNO = @json($colsEntorno);
    const COLS_CUENTAS = @json($colsCuentas);
    const URL_ACTUALIZAR = @json(route('admin.ad_consolidado.actualizar'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const MAX_VISIBLES = 400;
    if (!CONEXIONES.length) return;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const datos = {};          // clave de conexión => respuesta
    let filtradas = [];

    /* ── Carga: un pedido por dominio, en paralelo ─────────────────────── */
    function chip(c, estado, texto) {
        let el = $('cad-chip-' + c.clave);
        if (!el) {
            el = document.createElement('span');
            el.id = 'cad-chip-' + c.clave;
            $('cad-chips').appendChild(el);
        }
        const ico = { cargando: '<span class="spinner-border spinner-border-sm"></span>', ok: '<i class="bi bi-check-circle-fill"></i>', err: '<i class="bi bi-x-circle-fill"></i>' }[estado];
        el.className = 'cad-chip ' + estado;
        el.innerHTML = ico + '<span>' + esc(texto) + '</span>';
    }

    function cargar() {
        CONEXIONES.forEach(c => {
            chip(c, 'cargando', c.grupo + ' · leyendo…');
            const t0 = performance.now();
            fetch(c.url, { headers: { Accept: 'application/json' } })
                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                .then(d => {
                    datos[c.clave] = d;
                    if (d.error) chip(c, 'err', c.grupo + ': ' + d.error);
                    else {
                        const seg = ((performance.now() - t0) / 1000).toFixed(1);
                        chip(c, 'ok', d.entorno[2] + ' · ' + d.cuentas.length.toLocaleString('es-CL') + ' objetos · ' + seg + ' s');
                    }
                    pintar();
                })
                .catch(e => { chip(c, 'err', c.grupo + ': ' + e.message); });
        });
    }

    /* ── Pintado ────────────────────────────────────────────────────────── */
    const ordenadas = () => CONEXIONES.map(c => datos[c.clave]).filter(d => d && !d.error);

    function pintar() {
        // Entorno
        $('cad-tb-entorno').innerHTML = ordenadas().map(d =>
            '<tr>' + d.entorno.map((v, i) => `<td class="${i === 8 || i === 10 ? 'envolver' : ''}">${esc(v)}</td>`).join('') + '</tr>'
        ).join('');

        // Opciones de dominio y conteos por tipo
        const sel = $('cad-dominio'), actual = sel.value;
        sel.innerHTML = '<option value="">Todos los dominios</option>' +
            ordenadas().map(d => `<option ${d.entorno[2] === actual ? 'selected' : ''}>${esc(d.entorno[2])}</option>`).join('');
        const conteo = {};
        ordenadas().forEach(d => d.cuentas.forEach(f => conteo[f[2]] = (conteo[f[2]] || 0) + 1));
        document.querySelectorAll('[data-cuenta]').forEach(s => s.textContent = '(' + (conteo[s.dataset.cuenta] || 0).toLocaleString('es-CL') + ')');
        $('cad-total').textContent = Object.values(conteo).reduce((a, b) => a + b, 0).toLocaleString('es-CL');

        filtrar();
    }

    function filtrar() {
        const q = $('cad-buscar').value.trim().toLowerCase();
        const dom = $('cad-dominio').value, est = $('cad-estado').value;
        const tipos = new Set([...document.querySelectorAll('.cad-tipo:checked')].map(c => c.value));

        filtradas = [];
        ordenadas().forEach(d => d.cuentas.forEach(f => {
            if (!tipos.has(f[2])) return;
            if (dom && f[1] !== dom) return;
            if (est && f[6] !== est) return;
            if (q && !f.join(' ').toLowerCase().includes(q)) return;
            filtradas.push(f);
        }));

        const colorTipo = { 'Usuario': 'primary', 'Administrador': 'danger', 'Servicio': 'warning', 'Genérica/Compartida': 'info',
                            'Otro': 'secondary', 'Equipo': 'dark', 'Grupo': 'success' };
        const colorEstado = { 'Habilitada': 'text-success', 'Deshabilitada': 'text-secondary', 'Bloqueada': 'text-danger fw-semibold' };
        $('cad-tb-cuentas').innerHTML = filtradas.slice(0, MAX_VISIBLES).map(f => '<tr>' + f.map((v, i) => {
            if (i === 2) return `<td><span class="badge cad-badge-tipo bg-${colorTipo[v] || 'secondary'}-subtle text-${colorTipo[v] || 'secondary'}-emphasis">${esc(v)}</span></td>`;
            if (i === 6) return `<td class="${colorEstado[v] || ''}">${esc(v)}</td>`;
            return `<td title="${esc(v)}">${esc(v)}</td>`;
        }).join('') + '</tr>').join('');

        const n = filtradas.length;
        $('cad-n-filtradas').textContent = n.toLocaleString('es-CL');
        $('cad-mostrando').textContent = n > MAX_VISIBLES
            ? `En pantalla se muestran ${MAX_VISIBLES} de ${n.toLocaleString('es-CL')}; Copiar copia todas.` : '';
    }

    ['cad-buscar', 'cad-dominio', 'cad-estado'].forEach(id => $(id).addEventListener('input', filtrar));
    document.querySelectorAll('.cad-tipo').forEach(c => c.addEventListener('change', filtrar));

    /* ── Copiar como TSV, listo para pegar en Excel ─────────────────────── */
    function celda(v) {
        let s = String(v ?? '').replace(/[\t\r\n]+/g, ' ').trim();
        // Excel tomaría "=", "+", "-" o "@" al inicio como fórmula.
        if (/^[=+\-@]/.test(s)) s = "'" + s;
        return s;
    }
    async function copiar(filas, cabecera, boton) {
        const tsv = (cabecera ? [cabecera] : []).concat(filas).map(f => f.map(celda).join('\t')).join('\r\n');
        try {
            await navigator.clipboard.writeText(tsv);
        } catch (e) {
            // Sin HTTPS no hay API de portapapeles: se usa el método antiguo.
            const ta = document.createElement('textarea');
            ta.value = tsv; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
        }
        const html = boton.innerHTML;
        boton.innerHTML = `<i class="bi bi-check2 me-1"></i>${filas.length.toLocaleString('es-CL')} filas copiadas`;
        boton.classList.add('cad-copiado');
        setTimeout(() => { boton.innerHTML = html; boton.classList.remove('cad-copiado'); }, 1800);
    }

    $('cad-copiar-entorno').addEventListener('click', e =>
        copiar(ordenadas().map(d => d.entorno), $('cad-cab-entorno').checked ? COLS_ENTORNO : null, e.currentTarget));
    $('cad-copiar-cuentas').addEventListener('click', e =>
        copiar(filtradas, $('cad-cab-cuentas').checked ? COLS_CUENTAS : null, e.currentTarget));

    $('cad-actualizar').addEventListener('click', () => {
        fetch(URL_ACTUALIZAR, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' } })
            .then(() => { Object.keys(datos).forEach(k => delete datos[k]); pintar(); cargar(); });
    });

    cargar();
});
</script>
@endsection
