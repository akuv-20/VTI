@extends('layouts.app')

@section('content')
<style>
    /* ── Split layout ────────────────────────────────────────────────────── */
    .eid-split {
        display: flex;
        height: calc(100vh - var(--topbar-h) - 2.5rem);
        gap: 0;
        margin: -4px -4px 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }

    /* ── Panel izquierdo ─────────────────────────────────────────────────── */
    .eid-left {
        width: 320px;
        min-width: 280px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .eid-left-header {
        padding: .75rem .75rem .5rem;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
    }
    .eid-search-wrap {
        position: relative;
    }
    .eid-search-wrap .bi-search {
        position: absolute;
        left: 9px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: .85rem;
        pointer-events: none;
    }
    .eid-search-wrap input {
        padding-left: 28px;
        padding-right: 28px;
        font-size: .82rem;
        background: #fff;
        border-color: #cbd5e1;
        border-radius: 7px;
    }
    .eid-search-wrap input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
    .eid-search-clear {
        position: absolute;
        right: 6px; top: 50%;
        transform: translateY(-50%);
        border: none; background: none;
        color: #94a3b8; cursor: pointer;
        font-size: .9rem; line-height: 1;
        display: none;
        padding: 2px;
    }
    .eid-search-clear:hover { color: #475569; }

    .eid-filters {
        display: flex;
        gap: 4px;
        margin-top: .45rem;
    }
    .eid-filter-btn {
        flex: 1;
        font-size: .72rem;
        font-weight: 600;
        padding: 3px 6px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        cursor: pointer;
        transition: all .12s;
        text-align: center;
    }
    .eid-filter-btn:hover { border-color: #94a3b8; color: #334155; }
    .eid-filter-btn.active-all    { background: #f1f5f9; border-color: #94a3b8; color: #334155; }
    .eid-filter-btn.active-on     { background: #dcfce7; border-color: #86efac; color: #16a34a; }
    .eid-filter-btn.active-off    { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }

    .eid-counter {
        font-size: .7rem;
        color: #94a3b8;
        padding: .3rem .75rem .25rem;
        flex-shrink: 0;
    }

    .eid-list {
        flex: 1;
        overflow-y: auto;
        padding: 3px 6px 8px;
    }
    .eid-list::-webkit-scrollbar { width: 4px; }
    .eid-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    .eid-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 7px 8px;
        border-radius: 8px;
        cursor: pointer;
        transition: background .1s;
        border: 1px solid transparent;
        margin-bottom: 1px;
    }
    .eid-item:hover  { background: #f1f5f9; }
    .eid-item.active { background: #eff6ff; border-color: #bfdbfe; }

    .eid-avatar {
        width: 34px; height: 34px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 700;
        color: #fff; flex-shrink: 0;
        letter-spacing: .02em;
    }
    .eid-item-info { flex: 1; min-width: 0; }
    .eid-item-name {
        font-size: .82rem; font-weight: 600;
        color: #1e293b;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eid-item-email {
        font-size: .73rem; color: #94a3b8;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eid-dot {
        width: 7px; height: 7px;
        border-radius: 50%; flex-shrink: 0;
    }

    /* ── Panel derecho ───────────────────────────────────────────────────── */
    .eid-right {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    .eid-right::-webkit-scrollbar { width: 5px; }
    .eid-right::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

    /* Estado vacío */
    .eid-empty-state {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        gap: .6rem;
        padding: 2rem;
    }
    .eid-empty-state i { font-size: 2.5rem; opacity: .4; }
    .eid-empty-state p { font-size: .85rem; margin: 0; }

    /* ── Ficha de usuario ────────────────────────────────────────────────── */
    .eid-profile { padding: 0 0 2rem; }

    /* Hero oscuro */
    .eid-hero {
        padding: 1.5rem 1.5rem 1.2rem;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
        display: flex; align-items: center; gap: 1.1rem;
    }
    .eid-profile-avatar {
        width: 72px; height: 72px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; font-weight: 800; color: #fff; flex-shrink: 0;
        border: 3px solid rgba(255,255,255,.2);
        box-shadow: 0 4px 16px rgba(0,0,0,.35);
        letter-spacing: .02em;
    }
    .eid-hero-info { flex: 1; min-width: 0; }
    .eid-hero-name {
        font-size: 1.12rem; font-weight: 700; color: #fff;
        line-height: 1.2; margin-bottom: .15rem;
    }
    .eid-hero-upn {
        font-size: .72rem; color: #7dd3fc;
        font-family: monospace; word-break: break-all; margin-bottom: .55rem;
    }
    .eid-badges { display: flex; gap: .35rem; flex-wrap: wrap; }

    /* Secciones */
    .eid-section {
        padding: .9rem 1.25rem .65rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .eid-section:last-child { border-bottom: none; }
    .eid-section-title {
        font-size: .65rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .09em;
        color: #94a3b8; margin-bottom: .65rem;
        display: flex; align-items: center; gap: .4rem;
    }
    .eid-section-title::after {
        content: ''; flex: 1; height: 1px; background: #f1f5f9;
    }

    /* Grid de tarjetas de campo */
    .eid-fields-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .45rem;
    }
    .eid-fields-grid.cols-1 { grid-template-columns: 1fr; }

    .eid-field {
        background: #f8fafc; border: 1px solid #e9eef4;
        border-radius: 8px; padding: .5rem .7rem;
        display: flex; align-items: flex-start; gap: .55rem;
        min-width: 0; transition: border-color .12s, background .12s;
    }
    .eid-field:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .eid-field.span2 { grid-column: 1 / -1; }

    .eid-field-icon {
        font-size: .9rem; color: #94a3b8; flex-shrink: 0; margin-top: 2px;
    }
    .eid-field-body { flex: 1; min-width: 0; }
    .eid-field-val {
        font-size: .84rem; font-weight: 600; color: #1e293b;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eid-field-val.empty {
        color: #d1d5db; font-weight: 400; font-style: italic; font-size: .76rem;
    }
    .eid-field-label-es {
        font-size: .7rem; color: #64748b; margin-top: 1px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eid-field-key {
        font-size: .62rem; color: #c4cdd6; font-family: monospace;
        margin-top: 1px;
    }
    .eid-field-actions { flex-shrink: 0; align-self: center; }

    .eid-copy-btn {
        border: none; background: none; color: #d1d5db; cursor: pointer;
        font-size: .8rem; padding: 2px 5px; border-radius: 5px;
        transition: color .1s, background .1s; line-height: 1;
    }
    .eid-copy-btn:hover { color: #3b82f6; background: #eff6ff; }

    /* Loading skeleton */
    .eid-skeleton {
        background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
        background-size: 200% 100%;
        animation: shimmer 1.2s infinite;
        border-radius: 5px;
        height: 12px;
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    /* ── Responsive ──────────────────────────────────────────────────────── */
    @media (max-width: 640px) {
        .eid-split { flex-direction: column; height: auto; min-height: calc(100vh - var(--topbar-h) - 2.5rem); }
        .eid-left  { width: 100%; border-right: none; border-bottom: 1px solid #e2e8f0; max-height: 45vh; }
        .eid-right { min-height: 55vh; }
    }
</style>

<div class="eid-split">

    {{-- ── Panel izquierdo ──────────────────────────────────────────────── --}}
    <div class="eid-left">
        <div class="eid-left-header">
            {{-- Título --}}
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold" style="font-size:.82rem;color:#334155">
                    <i class="bi bi-microsoft me-1" style="color:#0078d4"></i>Entra ID
                </span>
                <div class="d-flex gap-1">
                    <a href="{{ route('admin.entra_id.dashboard') }}"
                       class="btn btn-outline-success btn-sm py-0 px-2"
                       style="font-size:.72rem" title="Salud de datos">
                        <i class="bi bi-heart-pulse"></i>
                    </a>
                    <a href="{{ route('admin.entra_id.inspector') }}"
                       class="btn btn-outline-primary btn-sm py-0 px-2"
                       style="font-size:.72rem">
                        <i class="bi bi-clipboard2-data me-1"></i>Inspector
                    </a>
                </div>
            </div>

            {{-- Buscador --}}
            <div class="eid-search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" id="eid-search" class="form-control form-control-sm"
                       placeholder="Nombre, email, área, cargo…" autocomplete="off">
                <button class="eid-search-clear" id="eid-search-clear" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            {{-- Filtros --}}
            <div class="eid-filters">
                <button class="eid-filter-btn active-on" data-filter="on">Habilitados</button>
                <button class="eid-filter-btn" data-filter="off">Deshabilitados</button>
                <button class="eid-filter-btn" data-filter="all">Todos</button>
            </div>
        </div>

        <div class="eid-counter" id="eid-counter">Cargando…</div>
        <div class="eid-list" id="eid-list">
            {{-- skeleton mientras carga --}}
            @for($i=0;$i<12;$i++)
            <div class="eid-item" style="pointer-events:none">
                <div class="eid-skeleton" style="width:34px;height:34px;border-radius:50%;flex-shrink:0"></div>
                <div style="flex:1;min-width:0">
                    <div class="eid-skeleton mb-1" style="width:70%"></div>
                    <div class="eid-skeleton" style="width:90%;height:10px"></div>
                </div>
            </div>
            @endfor
        </div>
    </div>

    {{-- ── Panel derecho ────────────────────────────────────────────────── --}}
    <div class="eid-right" id="eid-right">
        <div class="eid-empty-state" id="eid-empty">
            <i class="bi bi-person-circle"></i>
            <p>Selecciona una cuenta para ver su ficha</p>
        </div>
        <div class="eid-profile d-none" id="eid-profile"></div>
    </div>

</div>

<script>
(function () {
    const DATOS_URL   = @json(route('admin.entra_id.datos'));
    const INSPECTOR   = @json(route('admin.entra_id.inspector'));

    let allUsers      = [];
    let filtered      = [];
    let activeFilter  = 'on';
    let selectedId    = null;
    let searchTimeout = null;

    // ── Fetch datos ───────────────────────────────────────────────────────
    fetch(DATOS_URL)
        .then(r => {
            if (!r.ok) return r.json().then(d => { throw new Error(d.error || 'Error Graph API'); });
            return r.json();
        })
        .then(data => {
            allUsers = data;
            applyFilters();
        })
        .catch(err => {
            document.getElementById('eid-list').innerHTML =
                `<div class="p-3 text-danger" style="font-size:.82rem">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>${err.message}
                </div>`;
            document.getElementById('eid-counter').textContent = '';
        });

    // ── Filtrar y renderizar lista ────────────────────────────────────────
    function applyFilters() {
        const q = document.getElementById('eid-search').value.trim().toLowerCase();

        filtered = allUsers.filter(u => {
            // filtro estado
            if (activeFilter === 'on'  && u.accountEnabled === false) return false;
            if (activeFilter === 'off' && u.accountEnabled !== false)  return false;
            // filtro texto
            if (q) {
                const haystack = [
                    u.displayName, u.givenName, u.surname,
                    u.userPrincipalName, u.mail,
                    u.department, u.jobTitle, u.companyName
                ].map(v => (v || '').toLowerCase()).join(' ');
                return haystack.includes(q);
            }
            return true;
        });

        renderList();
        updateCounter();
    }

    function renderList() {
        const list = document.getElementById('eid-list');
        if (filtered.length === 0) {
            list.innerHTML = `<div class="text-center py-4 text-muted" style="font-size:.8rem">
                <i class="bi bi-person-x d-block mb-1" style="font-size:1.6rem;opacity:.35"></i>
                Sin resultados
            </div>`;
            return;
        }

        list.innerHTML = filtered.map(u => {
            const enabled  = u.accountEnabled !== false;
            const nombre   = u.displayName || u.userPrincipalName || '—';
            const email    = u.mail || u.userPrincipalName || '';
            const initials = nombre.split(' ').slice(0, 2).map(p => p[0] || '').join('').toUpperCase();
            const color    = enabled ? '#0078d4' : '#94a3b8';
            const dot      = enabled ? '#22c55e' : '#ef4444';
            const active   = u.id === selectedId ? 'active' : '';

            return `<div class="eid-item ${active}" data-id="${u.id}" onclick="selectUser('${u.id}')">
                <div class="eid-avatar" style="background:${color}">${initials}</div>
                <div class="eid-item-info">
                    <div class="eid-item-name">${esc(nombre)}</div>
                    <div class="eid-item-email">${esc(email)}</div>
                </div>
                <div class="eid-dot" style="background:${dot}" title="${enabled ? 'Habilitado' : 'Deshabilitado'}"></div>
            </div>`;
        }).join('');
    }

    function updateCounter() {
        const total  = allUsers.length;
        const shown  = filtered.length;
        const q      = document.getElementById('eid-search').value.trim();
        const label  = shown === total
            ? `${total.toLocaleString()} cuentas`
            : `${shown.toLocaleString()} de ${total.toLocaleString()}`;
        document.getElementById('eid-counter').textContent = label;
    }

    // ── Seleccionar usuario ───────────────────────────────────────────────
    window.selectUser = function(id) {
        selectedId = id;
        const u = allUsers.find(x => x.id === id);
        if (!u) return;

        // marcar activo en lista
        document.querySelectorAll('.eid-item').forEach(el => {
            el.classList.toggle('active', el.dataset.id === id);
        });

        renderProfile(u);
    };

    function renderProfile(u) {
        const enabled  = u.accountEnabled !== false;
        const nombre   = u.displayName || u.userPrincipalName || '—';
        const initials = nombre.split(' ').slice(0, 2).map(p => p[0] || '').join('').toUpperCase();
        const avatarBg = enabled ? '#0078d4' : '#64748b';

        const creado = u.createdDateTime
            ? new Date(u.createdDateTime).toLocaleDateString('es-CL', {day:'2-digit', month:'long', year:'numeric'})
            : null;

        const badgeEstado = enabled
            ? `<span class="badge bg-success" style="font-size:.7rem;opacity:.9">
                <i class="bi bi-check-circle-fill me-1"></i>Habilitado</span>`
            : `<span class="badge bg-danger" style="font-size:.7rem;opacity:.9">
                <i class="bi bi-x-circle-fill me-1"></i>Deshabilitado</span>`;

        const badgeTipo = u.userType === 'Guest'
            ? `<span class="badge bg-warning text-dark" style="font-size:.7rem;opacity:.9">
                <i class="bi bi-person-badge me-1"></i>Invitado</span>`
            : `<span class="badge" style="font-size:.7rem;opacity:.9;background:rgba(255,255,255,.2);color:#e0f2fe">
                <i class="bi bi-person-check me-1"></i>Miembro</span>`;

        document.getElementById('eid-empty').classList.add('d-none');
        const panel = document.getElementById('eid-profile');
        panel.classList.remove('d-none');

        panel.innerHTML = `
            <div class="eid-hero">
                <div class="eid-profile-avatar" style="background:${avatarBg}">${initials}</div>
                <div class="eid-hero-info">
                    <div class="eid-hero-name">${esc(nombre)}</div>
                    <div class="eid-hero-upn">${esc(u.userPrincipalName || '')}</div>
                    <div class="eid-badges">${badgeEstado}${badgeTipo}</div>
                </div>
            </div>

            <div class="eid-section">
                <div class="eid-section-title"><i class="bi bi-person-vcard"></i>Identidad</div>
                <div class="eid-fields-grid">
                    ${field('bi-alphabet-uppercase', u.givenName,  'Nombre de pila',  'givenName')}
                    ${field('bi-alphabet',           u.surname,    'Apellido',         'surname')}
                    ${field('bi-person-fill',        u.displayName,'Nombre completo',  'displayName', false, true)}
                </div>
            </div>

            <div class="eid-section">
                <div class="eid-section-title"><i class="bi bi-envelope-at"></i>Acceso</div>
                <div class="eid-fields-grid cols-1">
                    ${field('bi-at',          u.userPrincipalName, 'Usuario principal (login)',  'userPrincipalName', true)}
                    ${field('bi-envelope',    u.mail,              'Correo electrónico',          'mail',              true)}
                </div>
            </div>

            <div class="eid-section">
                <div class="eid-section-title"><i class="bi bi-building-fill"></i>Organización</div>
                <div class="eid-fields-grid">
                    ${field('bi-briefcase',       u.jobTitle,    'Cargo / Puesto',       'jobTitle')}
                    ${field('bi-diagram-3',       u.department,  'Área / Departamento',  'department')}
                </div>
            </div>

            <div class="eid-section">
                <div class="eid-section-title"><i class="bi bi-geo-alt-fill"></i>Ubicación</div>
                <div class="eid-fields-grid">
                    ${field('bi-building',      u.city,          'Ciudad',               'city')}
                    ${field('bi-map',           u.state,         'Región / Estado',      'state')}
                    ${field('bi-flag',          u.country,       'País',                 'country')}
                    ${field('bi-globe2',        u.usageLocation, 'Ubicación de uso (licencias)', 'usageLocation')}
                </div>
            </div>

            <div class="eid-section">
                <div class="eid-section-title"><i class="bi bi-telephone-fill"></i>Contacto</div>
                <div class="eid-fields-grid cols-1">
                    ${field('bi-phone', u.mobilePhone, 'Teléfono móvil', 'mobilePhone')}
                </div>
            </div>

            <div class="eid-section">
                <div class="eid-section-title"><i class="bi bi-shield-lock-fill"></i>Cuenta</div>
                <div class="eid-fields-grid">
                    ${field('bi-person-badge', u.userType,        'Tipo de cuenta',   'userType')}
                    ${field('bi-calendar-plus',creado,            'Fecha de creación','createdDateTime')}
                    ${field('bi-fingerprint',  u.id,              'Identificador único','id', true, true)}
                </div>
            </div>
        `;
    }

    // field(icon, value, labelEs, fieldKey, copyable, spanFull)
    function field(icon, value, labelEs, fieldKey, copyable = false, spanFull = false) {
        const empty   = !value;
        const display = empty ? '—' : esc(value);
        const copyBtn = copyable && !empty
            ? `<div class="eid-field-actions">
                <button class="eid-copy-btn" data-copy="${esc(value)}" title="Copiar">
                    <i class="bi bi-clipboard"></i>
                </button>
               </div>`
            : '';
        return `<div class="eid-field${spanFull ? ' span2' : ''}">
            <i class="bi ${icon} eid-field-icon"></i>
            <div class="eid-field-body">
                <div class="eid-field-val${empty ? ' empty' : ''}">${display}</div>
                <div class="eid-field-label-es">${labelEs}</div>
                <div class="eid-field-key">${fieldKey}</div>
            </div>
            ${copyBtn}
        </div>`;
    }

    // ── Copiar al portapapeles (delegado en el panel) ─────────────────────
    document.getElementById('eid-right').addEventListener('click', function(e) {
        const btn = e.target.closest('.eid-copy-btn');
        if (!btn) return;
        const text = btn.dataset.copy;
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check-lg';
            btn.style.color = '#22c55e';
            setTimeout(() => {
                icon.className = 'bi bi-clipboard';
                btn.style.color = '';
            }, 1500);
        });
    });

    // ── Escape HTML ───────────────────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Eventos UI ────────────────────────────────────────────────────────
    document.getElementById('eid-search').addEventListener('input', function () {
        document.getElementById('eid-search-clear').style.display = this.value ? 'block' : 'none';
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 150);
    });

    document.getElementById('eid-search-clear').addEventListener('click', function () {
        document.getElementById('eid-search').value = '';
        this.style.display = 'none';
        applyFilters();
    });

    document.querySelectorAll('.eid-filter-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            activeFilter = this.dataset.filter;
            document.querySelectorAll('.eid-filter-btn').forEach(b => {
                b.className = 'eid-filter-btn';
            });
            this.classList.add(
                activeFilter === 'on'  ? 'active-on'  :
                activeFilter === 'off' ? 'active-off' : 'active-all'
            );
            applyFilters();
        });
    });
})();
</script>
@endsection
