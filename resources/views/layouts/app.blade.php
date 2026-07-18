<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        // Estado contraído del sidebar antes del primer paint (evita parpadeo)
        try { if (localStorage.getItem('vti_sb_collapsed') === '1') document.documentElement.classList.add('sb-collapsed'); } catch (e) {}
    </script>

    <title>{{ $appNombre ?? config('app.name', 'Laravel') }}</title>
    @if(!empty($favicon))
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
    @endif

    <style>
        /* ── Loading overlay ────────────────────────────────────────────── */
        #page-loader {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 14px;
        }
        #page-loader.active { display: flex; }

        .loader-spinner {
            width: 48px;
            height: 48px;
            border: 5px solid #dee2e6;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
        }
        .loader-text {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            letter-spacing: 0.03em;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Layout: sidebar + topbar ──────────────────────────────────── */
        :root {
            --sidebar-w: 250px;
            --topbar-h: 52px;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #283548;
            --sidebar-active: #2563eb;
        }

        body { background: #f1f5f9; }

        /* Sidebar */
        .vti-sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            z-index: 1040;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }

        .vti-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: .95rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
            flex-shrink: 0;
        }
        .vti-sidebar-brand:hover { color: #fff; }
        .vti-sidebar-brand img { object-fit: contain; max-width: 140px; }

        .vti-sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 10px 8px 20px;
        }
        .vti-sidebar-nav::-webkit-scrollbar { width: 5px; }
        .vti-sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 4px; }

        /* Grupo (botón que colapsa) */
        .vti-nav-group { margin-bottom: 2px; }
        .vti-nav-group-toggle {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 8px 12px;
            background: none;
            border: none;
            border-radius: 8px;
            color: #cbd5e1;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: background .12s, color .12s;
        }
        .vti-nav-group-toggle:hover { background: var(--sidebar-hover); color: #fff; }
        .vti-nav-group-toggle .bi-chevron-down {
            margin-left: auto;
            font-size: .68rem;
            transition: transform .2s ease;
        }
        .vti-nav-group.open .vti-nav-group-toggle .bi-chevron-down { transform: rotate(180deg); }
        .vti-nav-group.open .vti-nav-group-toggle { color: #fff; }

        .vti-nav-group-items {
            display: none;
            padding: 2px 0 4px;
        }
        .vti-nav-group.open .vti-nav-group-items { display: block; }

        /* Links */
        .vti-nav-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 6px 12px 6px 34px;
            border-radius: 8px;
            color: #94a3b8;
            font-size: .82rem;
            text-decoration: none;
            transition: background .12s, color .12s;
            margin: 1px 0;
        }
        .vti-nav-link:hover { background: var(--sidebar-hover); color: #fff; }
        .vti-nav-link.active {
            background: var(--sidebar-active);
            color: #fff;
            font-weight: 600;
        }
        .vti-nav-link i { font-size: .8rem; width: 16px; text-align: center; flex-shrink: 0; }

        /* Link de primer nivel (sin grupo) */
        .vti-nav-link.top-level { padding-left: 12px; font-weight: 600; color: #cbd5e1; font-size: .84rem; }
        .vti-nav-link.top-level:hover { color: #fff; }
        .vti-nav-link.top-level.active { color: #fff; }

        .vti-nav-divider {
            height: 1px;
            background: rgba(255,255,255,.07);
            margin: 6px 12px;
        }

        /* Topbar */
        .vti-topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 18px;
            z-index: 1030;
        }

        .vti-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .84rem;
            color: #64748b;
            flex-wrap: wrap;
            min-width: 0;
        }
        .vti-breadcrumb a {
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .vti-breadcrumb a:hover { color: #2563eb; }
        .vti-breadcrumb .sep { color: #cbd5e1; font-size: .7rem; }
        .vti-breadcrumb .current { color: #1e293b; font-weight: 600; }

        /* Botón hamburguesa (solo móvil) */
        .vti-burger {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #475569;
            padding: 4px 8px;
            cursor: pointer;
            border-radius: 6px;
        }
        .vti-burger:hover { background: #f1f5f9; }

        /* Usuario (derecha del topbar) */
        .vti-user-menu { margin-left: auto; position: relative; }
        .vti-user-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: background .12s;
        }
        .vti-user-btn:hover { background: #f1f5f9; }
        .vti-user-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .vti-user-name { font-size: .83rem; font-weight: 600; color: #334155; }

        .vti-user-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,.14);
            border: 1px solid #e2e8f0;
            min-width: 200px;
            padding: 6px;
            display: none;
            z-index: 1050;
        }
        .vti-user-dropdown.show { display: block; }
        .vti-user-dropdown a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 7px;
            font-size: .84rem;
            color: #334155;
            text-decoration: none;
        }
        .vti-user-dropdown a:hover { background: #f1f5f9; }
        .vti-user-dropdown hr { margin: 5px 4px; border-color: #e2e8f0; }

        /* Contenido */
        .vti-main {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh;
        }
        .vti-main-inner { padding: 1.25rem 1.25rem 2rem; }

        /* Botón contraer (parte inferior del sidebar) */
        .vti-collapse-btn {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 11px 20px;
            background: none;
            border: none;
            border-top: 1px solid rgba(255,255,255,.08);
            color: #94a3b8;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .12s, color .12s;
            flex-shrink: 0;
        }
        .vti-collapse-btn:hover { background: var(--sidebar-hover); color: #fff; }
        .vti-collapse-btn i { font-size: .85rem; transition: transform .25s ease; }

        /* ── Sidebar contraído (solo desktop) ───────────────────────── */
        @media (min-width: 992px) {
            html.sb-collapsed .vti-sidebar { width: 72px; }
            html.sb-collapsed .vti-topbar  { left: 72px; }
            html.sb-collapsed .vti-main    { margin-left: 72px; }

            /* Expandir al pasar el cursor (flota sobre el contenido) */
            html.sb-collapsed .vti-sidebar:hover {
                width: var(--sidebar-w);
                box-shadow: 8px 0 30px rgba(0,0,0,.25);
            }

            /* Ocultar textos y submenús cuando está contraído sin hover */
            html.sb-collapsed .vti-sidebar:not(:hover) .sb-text,
            html.sb-collapsed .vti-sidebar:not(:hover) .vti-nav-group-items,
            html.sb-collapsed .vti-sidebar:not(:hover) .vti-nav-group-toggle .bi-chevron-down {
                display: none;
            }

            /* Centrar iconos cuando está contraído */
            html.sb-collapsed .vti-sidebar:not(:hover) .vti-nav-link.top-level,
            html.sb-collapsed .vti-sidebar:not(:hover) .vti-nav-group-toggle,
            html.sb-collapsed .vti-sidebar:not(:hover) .vti-collapse-btn {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
            html.sb-collapsed .vti-sidebar:not(:hover) .vti-sidebar-brand {
                justify-content: center;
                padding-left: 8px;
                padding-right: 8px;
            }

            /* Rotar flecha del botón contraer */
            html.sb-collapsed .vti-collapse-btn i { transform: rotate(180deg); }
        }

        /* Backdrop móvil */
        .vti-sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.5);
            z-index: 1039;
        }

        /* ── Responsive ─────────────────────────────────────────────── */
        @media (max-width: 991.98px) {
            .vti-sidebar { transform: translateX(-100%); }
            .vti-sidebar.show { transform: translateX(0); }
            .vti-sidebar-backdrop.show { display: block; }
            .vti-topbar { left: 0; }
            .vti-main { margin-left: 0; }
            .vti-burger { display: inline-flex; }
            .vti-user-name { display: none; }
        }

        /* ── VTI Design System ──────────────────────────────────────── */
        .vti-page { padding: 0 4px; }

        .vti-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .vti-page-header h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            white-space: nowrap;
        }

        /* Search */
        .vti-search { display: flex; gap: 6px; align-items: center; }
        .vti-search .form-control {
            border-radius: 20px;
            padding-left: 14px;
            border-color: #cbd5e1;
            font-size: 0.83rem;
        }
        .vti-search .form-control:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }

        /* Table card wrapper */
        .vti-table-wrapper {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 20px rgba(0,0,0,.05);
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            margin-bottom: .75rem;
        }

        /* Table */
        .vti-table { margin: 0; font-size: 0.82rem; width: 100%; border-collapse: collapse; }

        .vti-table thead th {
            background: #1e293b;
            color: #cbd5e1;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 10px 14px;
            border: none;
            white-space: nowrap;
        }

        .vti-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            border-left: 3px solid transparent;
            transition: background .1s ease, border-left-color .1s ease;
        }
        .vti-table tbody tr:nth-child(even) { background: #f8fafc; }
        .vti-table tbody tr:hover { background: #eff6ff !important; border-left-color: #3b82f6; }
        .vti-table tbody tr:last-child { border-bottom: none; }
        .vti-table tbody td {
            padding: 7px 14px;
            vertical-align: middle;
            border: none;
            color: #334155;
        }

        /* Empty state */
        .vti-table .vti-empty td {
            text-align: center;
            padding: 2.5rem;
            color: #94a3b8;
            font-style: italic;
        }

        /* Action buttons */
        .vti-actions { display: flex; gap: 5px; align-items: center; }
        .vti-btn-edit, .vti-btn-delete, .vti-btn-view {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            transition: transform .1s, box-shadow .15s;
            text-decoration: none;
        }
        .vti-btn-edit  { background: #fef3c7; color: #92400e; }
        .vti-btn-edit:hover  { background: #f59e0b; color: #fff; box-shadow: 0 2px 8px rgba(245,158,11,.45); transform: translateY(-1px); }
        .vti-btn-delete { background: #fee2e2; color: #991b1b; }
        .vti-btn-delete:hover { background: #ef4444; color: #fff; box-shadow: 0 2px 8px rgba(239,68,68,.45); transform: translateY(-1px); }
        .vti-btn-view  { background: #dbeafe; color: #1e40af; }
        .vti-btn-view:hover  { background: #3b82f6; color: #fff; box-shadow: 0 2px 8px rgba(59,130,246,.45); transform: translateY(-1px); }

        /* Footer */
        .vti-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2px 2px 8px;
            font-size: 0.8rem;
            color: #64748b;
        }
        .vti-footer .pagination { margin: 0; }

        /* ── Responsive mobile ─────────────────────────────────────── */
        @media (max-width: 767.98px) {
            .vti-main-inner { padding: .85rem .75rem 1.5rem; }

            /* Page header: apilar título + acciones */
            .vti-page-header { flex-direction: column; align-items: stretch; gap: .6rem; }
            .vti-page-header h4 { white-space: normal; }
            .vti-page-header > a.btn,
            .vti-page-header > button.btn { width: 100%; justify-content: center; }
            .vti-page-header > .d-flex { width: 100%; flex-wrap: wrap; }
            .vti-page-header > .d-flex .btn { flex: 1 1 auto; justify-content: center; }

            /* Tablas: scroll horizontal */
            .vti-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 10px;
            }
            .vti-table th, .vti-table td { white-space: nowrap; }

            .vti-btn-edit, .vti-btn-delete, .vti-btn-view {
                width: 36px; height: 36px; font-size: .82rem;
            }

            .vti-footer { flex-direction: column; align-items: center; gap: .5rem; text-align: center; }

            .form-select[style*="width"],
            .form-control[style*="width"],
            input.form-control[style*="width"] {
                width: 100% !important;
                min-width: 0 !important;
            }

            .card-body.p-4 { padding: 1rem !important; }
            .card-body.p-3 { padding: .75rem !important; }

            .pagination .page-link { padding: .3rem .55rem; font-size: .8rem; }

            /* Breadcrumb: ocultar segmentos intermedios en pantallas chicas */
            .vti-breadcrumb .crumb-mid { display: none; }
        }

        /* ── Barra de progreso superior ─────────────────────────────── */
        #page-bar {
            position: fixed;
            top: 0; left: 0;
            height: 3px;
            width: 0%;
            background: #0d6efd;
            z-index: 10000;
            transition: width 0.3s ease;
            border-radius: 0 2px 2px 0;
            box-shadow: 0 0 6px rgba(13,110,253,0.5);
        }
    </style>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    {{-- Loading overlay global --}}
    <div id="page-bar"></div>
    <div id="page-loader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Cargando…</div>
    </div>

    {{-- Modal de confirmación (genérico: eliminar, deshabilitar, habilitar, etc.) --}}
    <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
            <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
                <div class="modal-header border-0 pb-0" id="modal-confirm-header" style="background:#fff1f2">
                    <div class="d-flex align-items-center gap-3 w-100 px-1 pt-1">
                        <div id="modal-confirm-icon-wrap" style="width:44px;height:44px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i id="modal-confirm-icon" class="bi bi-trash3-fill" style="font-size:1.2rem;color:#dc2626"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold" style="color:#1e293b" id="modal-confirm-title">Confirmar eliminación</h6>
                            <small class="text-muted" id="modal-confirm-subtitle">Esta acción no se puede deshacer</small>
                        </div>
                    </div>
                </div>
                <div class="modal-body pt-3 pb-2">
                    <p class="mb-0 text-center" style="color:#475569;font-size:.9rem">
                        ¿Estás seguro de que deseas
                        <span id="modal-confirm-verb">eliminar</span>
                        <strong id="modal-confirm-nombre" class="text-dark"></strong>?
                    </p>
                </div>
                <div class="modal-footer border-0 pt-1 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                            data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-danger btn-sm px-4" id="modal-confirm-btn">
                        <i id="modal-confirm-btn-icon" class="bi bi-trash3-fill me-1"></i>
                        <span id="modal-confirm-btn-text">Sí, eliminar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @auth
    {{-- ════════════════════════════ SIDEBAR ════════════════════════════ --}}
    <div class="vti-sidebar-backdrop" id="sidebarBackdrop"></div>
    <aside class="vti-sidebar" id="vtiSidebar">

        <a class="vti-sidebar-brand" href="{{ url('/home') }}">
            @if(!empty($appLogo))
                <img src="{{ $appLogo }}" height="28" alt="Logo">
            @else
                <span class="d-inline-flex align-items-center justify-content-center rounded-2"
                      style="width:28px;height:28px;background:linear-gradient(135deg,#1e3a5f,#2563eb);flex-shrink:0">
                    <i class="bi bi-building-check" style="font-size:.9rem;color:#fff"></i>
                </span>
            @endif
            <span class="sb-text">{{ $appNombre ?? config('app.name', 'Laravel') }}</span>
        </a>

        @php $ta = fn(string $r) => auth()->user()->tieneAcceso($r); @endphp
        <nav class="vti-sidebar-nav">

            <a href="{{ url('/home') }}" class="vti-nav-link top-level {{ request()->is('home') || request()->is('/') ? 'active' : '' }}">
                <i class="bi bi-house-fill"></i><span class="sb-text">Inicio</span>
            </a>

            <div class="vti-nav-divider"></div>

            {{-- ── Facturación ── --}}
            @if($ta('facturas.index') || $ta('entregas_facturas.index') || $ta('servicios.index') || $ta('familias.index') || $ta('empresas.index') || $ta('companias.index') || $ta('cuentas_contables.index'))
            <div class="vti-nav-group" data-group="facturacion">
                <button type="button" class="vti-nav-group-toggle">
                    <i class="bi bi-receipt-cutoff"></i><span class="sb-text">Facturación</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="vti-nav-group-items">
                    @if($ta('facturas.index'))
                    <a href="{{ route('facturas.pendientes') }}" class="vti-nav-link {{ request()->routeIs('facturas.pendientes') ? 'active' : '' }}">
                        <i class="bi bi-hourglass-split"></i>Facturas Pendientes
                    </a>
                    <a href="{{ route('facturas.index') }}" class="vti-nav-link {{ request()->routeIs('facturas.index', 'facturas.create', 'facturas.edit', 'facturas.show') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i>Facturas
                    </a>
                    <a href="{{ route('facturas.resumen') }}" class="vti-nav-link {{ request()->routeIs('facturas.resumen') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-line"></i>Resumen por Cuenta
                    </a>
                    <a href="{{ route('facturas.resumen_servicios') }}" class="vti-nav-link {{ request()->routeIs('facturas.resumen_servicios') ? 'active' : '' }}">
                        <i class="bi bi-grid-3x3-gap"></i>Resumen por Servicio
                    </a>
                    @endif
                    @if($ta('entregas_facturas.index'))
                    <a href="{{ route('entregas_facturas.index') }}" class="vti-nav-link {{ request()->routeIs('entregas_facturas.*') ? 'active' : '' }}">
                        <i class="bi bi-box-arrow-up-right"></i>Entregas de Facturas
                    </a>
                    @endif
                    @if($ta('servicios.index') || $ta('familias.index') || $ta('empresas.index') || $ta('companias.index') || $ta('cuentas_contables.index'))
                    <div class="vti-nav-divider"></div>
                    @endif
                    @if($ta('servicios.index'))
                    <a href="{{ route('servicios.index') }}" class="vti-nav-link {{ request()->routeIs('servicios.*') ? 'active' : '' }}">
                        <i class="bi bi-grid"></i>Servicios
                    </a>
                    @endif
                    @if($ta('familias.index'))
                    <a href="{{ route('familias.index') }}" class="vti-nav-link {{ request()->routeIs('familias.*') ? 'active' : '' }}">
                        <i class="bi bi-collection"></i>Familias
                    </a>
                    @endif
                    @if($ta('empresas.index'))
                    <a href="{{ route('empresas.index') }}" class="vti-nav-link {{ request()->routeIs('empresas.*') ? 'active' : '' }}">
                        <i class="bi bi-building"></i>Empresas
                    </a>
                    @endif
                    @if($ta('companias.index'))
                    <a href="{{ route('companias.index') }}" class="vti-nav-link {{ request()->routeIs('companias.*') ? 'active' : '' }}">
                        <i class="bi bi-buildings"></i>Compañías
                    </a>
                    @endif
                    @if($ta('cuentas_contables.index'))
                    <a href="{{ route('cuentas_contables.index') }}" class="vti-nav-link {{ request()->routeIs('cuentas_contables.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-bookmark"></i>Cuentas Contables
                    </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- ── Telefonía ── --}}
            @if($ta('lineas_telefonicas.index') || $ta('roamings.index') || $ta('emisores.index') || $ta('usuarios_telefonicos.index') || $ta('ubicaciones.index') || $ta('marcas.index') || $ta('aparatos.index') || $ta('centros_costo.index') || $ta('importaciones_movistar.index') || $ta('importaciones_entel.index') || $ta('importaciones_wom.index') || $ta('informes.telefonia') || $ta('actas_entrega_telefono.index') || $ta('actas_devolucion_telefono.index'))
            <div class="vti-nav-group" data-group="telefonia">
                <button type="button" class="vti-nav-group-toggle">
                    <i class="bi bi-phone"></i><span class="sb-text">Telefonía</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="vti-nav-group-items">
                    @if($ta('lineas_telefonicas.index'))
                    <a href="{{ route('lineas_telefonicas.index') }}" class="vti-nav-link {{ request()->routeIs('lineas_telefonicas.*') ? 'active' : '' }}">
                        <i class="bi bi-telephone-fill"></i>Líneas Telefónicas
                    </a>
                    @endif
                    @if($ta('roamings.index'))
                    <a href="{{ route('roamings.index') }}" class="vti-nav-link {{ request()->routeIs('roamings.*') ? 'active' : '' }}">
                        <i class="bi bi-globe-americas"></i>Roamings
                    </a>
                    @endif
                    @if($ta('lineas_telefonicas.index') || $ta('roamings.index'))
                    <div class="vti-nav-divider"></div>
                    @endif
                    @if($ta('emisores.index'))
                    <a href="{{ route('emisores.index') }}" class="vti-nav-link {{ request()->routeIs('emisores.*') ? 'active' : '' }}">
                        <i class="bi bi-broadcast"></i>Emisores
                    </a>
                    @endif
                    @if($ta('usuarios_telefonicos.index'))
                    <a href="{{ route('usuarios_telefonicos.index') }}" class="vti-nav-link {{ request()->routeIs('usuarios_telefonicos.*') ? 'active' : '' }}">
                        <i class="bi bi-person-fill"></i>Usuarios
                    </a>
                    @endif
                    @if($ta('ubicaciones.index'))
                    <a href="{{ route('ubicaciones.index') }}" class="vti-nav-link {{ request()->routeIs('ubicaciones.*') ? 'active' : '' }}">
                        <i class="bi bi-geo-alt-fill"></i>Ubicaciones
                    </a>
                    @endif
                    @if($ta('marcas.index'))
                    <a href="{{ route('marcas.index') }}" class="vti-nav-link {{ request()->routeIs('marcas.*') ? 'active' : '' }}">
                        <i class="bi bi-tag-fill"></i>Marcas
                    </a>
                    @endif
                    @if($ta('aparatos.index'))
                    <a href="{{ route('aparatos.index') }}" class="vti-nav-link {{ request()->routeIs('aparatos.*') ? 'active' : '' }}">
                        <i class="bi bi-phone-fill"></i>Aparatos
                    </a>
                    @endif
                    @if($ta('centros_costo.index'))
                    <a href="{{ route('centros_costo.index') }}" class="vti-nav-link {{ request()->routeIs('centros_costo.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-2-fill"></i>Centros de Costo
                    </a>
                    @endif
                    @if($ta('importaciones_movistar.index') || $ta('importaciones_entel.index') || $ta('importaciones_wom.index'))
                    <div class="vti-nav-divider"></div>
                    @endif
                    @if($ta('importaciones_movistar.index'))
                    <a href="{{ route('importaciones_movistar.index') }}" class="vti-nav-link {{ request()->routeIs('importaciones_movistar.*') ? 'active' : '' }}">
                        <i class="bi bi-cloud-upload"></i>Imp. Movistar
                    </a>
                    @endif
                    @if($ta('importaciones_entel.index'))
                    <a href="{{ route('importaciones_entel.index') }}" class="vti-nav-link {{ request()->routeIs('importaciones_entel.*') ? 'active' : '' }}">
                        <i class="bi bi-cloud-upload"></i>Imp. Entel
                    </a>
                    @endif
                    @if($ta('importaciones_wom.index'))
                    <a href="{{ route('importaciones_wom.index') }}" class="vti-nav-link {{ request()->routeIs('importaciones_wom.*') ? 'active' : '' }}">
                        <i class="bi bi-cloud-upload" style="color:#a78bfa"></i>Imp. WOM
                    </a>
                    @endif
                    @if($ta('informes.telefonia') || $ta('actas_entrega_telefono.index') || $ta('actas_devolucion_telefono.index'))
                    <div class="vti-nav-divider"></div>
                    @endif
                    @if($ta('informes.telefonia'))
                    <a href="{{ route('informes.telefonia') }}" class="vti-nav-link {{ request()->routeIs('informes.telefonia') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-fill"></i>Informe Telefonía
                    </a>
                    @endif
                    @if($ta('actas_entrega_telefono.index'))
                    <a href="{{ route('actas_entrega_telefono.index') }}" class="vti-nav-link {{ request()->routeIs('actas_entrega_telefono.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text-fill"></i>Actas de Entrega
                    </a>
                    @endif
                    @if($ta('actas_devolucion_telefono.index'))
                    <a href="{{ route('actas_devolucion_telefono.index') }}" class="vti-nav-link {{ request()->routeIs('actas_devolucion_telefono.*') ? 'active' : '' }}">
                        <i class="bi bi-box-arrow-in-left"></i>Actas de Devolución
                    </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- ── Inventario TI ── --}}
            @if($ta('inventario_ti.index'))
            <div class="vti-nav-group" data-group="inventario">
                <button type="button" class="vti-nav-group-toggle">
                    <i class="bi bi-pc-display"></i><span class="sb-text">Inventario TI</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="vti-nav-group-items">
                    <a href="{{ route('inventario_ti.dashboard') }}" class="vti-nav-link {{ request()->routeIs('inventario_ti.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                    <a href="{{ route('inventario_ti.index') }}" class="vti-nav-link {{ request()->routeIs('inventario_ti.index', 'inventario_ti.show') ? 'active' : '' }}">
                        <i class="bi bi-display-fill"></i>Equipos
                    </a>
                    <a href="{{ route('inventario_ti.actas') }}" class="vti-nav-link {{ request()->routeIs('inventario_ti.actas*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text-fill"></i>Actas de Entrega
                    </a>
                </div>
            </div>
            @endif

            {{-- ── Active Directory ── --}}
            @if(auth()->user()->can('acceso_ad') || auth()->user()->can('acceso_ad2') || auth()->user()->can('acceso_entra'))
            <div class="vti-nav-group" data-group="ad">
                <button type="button" class="vti-nav-group-toggle">
                    <i class="bi bi-diagram-3-fill"></i><span class="sb-text">AD | EntraID</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="vti-nav-group-items">
                    @can('acceso_ad')
                    <a href="{{ route('admin.active_directory.index') }}" class="vti-nav-link {{ request()->routeIs('admin.active_directory.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3"></i>AD Verfrut
                    </a>
                    @endcan
                    @can('acceso_ad2')
                    <a href="{{ route('admin.active_directory2.index') }}" class="vti-nav-link {{ request()->routeIs('admin.active_directory2.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3"></i>AD Grupo Verfrut (Perú)
                    </a>
                    @endcan
                    @can('acceso_entra')
                    <a href="{{ route('admin.entra_id.index') }}" class="vti-nav-link {{ request()->routeIs('admin.entra_id.*') ? 'active' : '' }}">
                        <i class="bi bi-microsoft"></i>Entra ID
                    </a>
                    @endcan
                </div>
            </div>
            @endif

            {{-- ── Admin ── --}}
            @can('admin')
            <div class="vti-nav-divider"></div>
            <div class="vti-nav-group" data-group="admin">
                <button type="button" class="vti-nav-group-toggle" style="color:#fca5a5">
                    <i class="bi bi-shield-lock-fill"></i><span class="sb-text">Admin</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="vti-nav-group-items">
                    <a href="{{ route('admin.usuarios.index') }}" class="vti-nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
                        <i class="bi bi-people-fill"></i>Usuarios
                    </a>
                    <a href="{{ route('admin.configuracion.index') }}" class="vti-nav-link {{ request()->routeIs('admin.configuracion.*') ? 'active' : '' }}">
                        <i class="bi bi-gear-fill"></i>Configuración
                    </a>
                </div>
            </div>
            @endcan

        </nav>

        <button type="button" class="vti-collapse-btn d-none d-lg-flex" id="sidebarCollapseBtn" title="Contraer / expandir menú">
            <i class="bi bi-chevron-double-left"></i><span class="sb-text">Contraer menú</span>
        </button>
    </aside>

    {{-- ════════════════════════════ TOPBAR ════════════════════════════ --}}
    @php
        // ── Breadcrumb dinámico según ruta actual ──────────────────────
        $rn = Route::currentRouteName() ?? '';

        // [prefijo de ruta => [Grupo, Página]]
        $mapaBread = [
            'facturas.pendientes'         => ['Facturación', 'Facturas Pendientes'],
            'facturas.resumen_servicios'  => ['Facturación', 'Resumen por Servicio'],
            'facturas.resumen'            => ['Facturación', 'Resumen por Cuenta Contable'],
            'facturas'                    => ['Facturación', 'Facturas'],
            'entregas_facturas'           => ['Facturación', 'Entregas de Facturas'],
            'servicios'                   => ['Facturación', 'Servicios'],
            'familias'                    => ['Facturación', 'Familias'],
            'empresas'                    => ['Facturación', 'Empresas'],
            'companias'                   => ['Facturación', 'Compañías'],
            'cuentas_contables'           => ['Facturación', 'Cuentas Contables'],
            'lineas_telefonicas'          => ['Telefonía', 'Líneas Telefónicas'],
            'emisores'                    => ['Telefonía', 'Emisores'],
            'usuarios_telefonicos'        => ['Telefonía', 'Usuarios'],
            'ubicaciones'                 => ['Telefonía', 'Ubicaciones'],
            'marcas'                      => ['Telefonía', 'Marcas'],
            'aparatos'                    => ['Telefonía', 'Aparatos'],
            'centros_costo'               => ['Telefonía', 'Centros de Costo'],
            'importaciones_movistar'      => ['Telefonía', 'Importaciones Movistar'],
            'importaciones_entel'         => ['Telefonía', 'Importaciones Entel'],
            'importaciones_wom.plantilla' => ['Telefonía', 'Plantilla WOM'],
            'importaciones_wom'           => ['Telefonía', 'Importaciones WOM'],
            'informes.telefonia'          => ['Telefonía', 'Informe Telefonía'],
            'roamings'                    => ['Telefonía', 'Roamings'],
            'actas_entrega_telefono'      => ['Telefonía', 'Actas de Entrega'],
            'actas_devolucion_telefono'   => ['Telefonía', 'Actas de Devolución'],
            'inventario_ti.dashboard'     => ['Inventario TI', 'Dashboard'],
            'inventario_ti.actas'         => ['Inventario TI', 'Actas de Entrega'],
            'inventario_ti'               => ['Inventario TI', 'Equipos'],
            'admin.active_directory2'     => ['Active Directory', 'AD Grupo Verfrut (Perú)'],
            'admin.active_directory'      => ['Active Directory', 'AD Verfrut'],
            'admin.entra_id'              => ['Active Directory', 'Entra ID'],
            'admin.usuarios'              => ['Admin', 'Usuarios'],
            'admin.configuracion'         => ['Admin', 'Configuración'],
            'home'                        => [null, 'Inicio'],
        ];

        $breadGrupo  = null;
        $breadPagina = null;
        foreach ($mapaBread as $prefijo => [$g, $p]) {
            if ($rn === $prefijo || str_starts_with($rn, $prefijo . '.')) {
                $breadGrupo  = $g;
                $breadPagina = $p;
                break;
            }
        }

        // Sufijo de acción
        $breadAccion = null;
        if (str_ends_with($rn, '.create'))                 $breadAccion = 'Crear';
        elseif (str_ends_with($rn, '.edit'))               $breadAccion = 'Editar';
        elseif (str_ends_with($rn, '.show'))               $breadAccion = 'Detalle';
        elseif (str_ends_with($rn, '.importar_correos') || str_ends_with($rn, '.procesar_importacion')) $breadAccion = 'Importar correos';

        // URL del índice del módulo (para enlazar la página cuando hay acción)
        $breadPaginaUrl = null;
        if ($breadAccion) {
            $indexRoute = \Illuminate\Support\Str::beforeLast($rn, '.') . '.index';
            if (Route::has($indexRoute)) {
                try { $breadPaginaUrl = route($indexRoute); } catch (\Throwable) {}
            }
        }
    @endphp

    <header class="vti-topbar">
        <button type="button" class="vti-burger" id="sidebarToggle" aria-label="Abrir menú">
            <i class="bi bi-list"></i>
        </button>

        <nav class="vti-breadcrumb" aria-label="breadcrumb">
            <a href="{{ url('/home') }}"><i class="bi bi-house-door"></i>Inicio</a>
            @if($breadGrupo)
                <span class="sep crumb-mid"><i class="bi bi-chevron-right"></i></span>
                <span class="crumb-mid">{{ $breadGrupo }}</span>
            @endif
            @if($breadPagina && $breadPagina !== 'Inicio')
                <span class="sep"><i class="bi bi-chevron-right"></i></span>
                @if($breadAccion)
                    @if($breadPaginaUrl)
                        <a href="{{ $breadPaginaUrl }}">{{ $breadPagina }}</a>
                    @else
                        <span>{{ $breadPagina }}</span>
                    @endif
                    <span class="sep"><i class="bi bi-chevron-right"></i></span>
                    <span class="current">{{ $breadAccion }}</span>
                @else
                    <span class="current">{{ $breadPagina }}</span>
                @endif
            @endif
        </nav>

        {{-- Usuario --}}
        <div class="vti-user-menu" id="userMenu">
            @php
                $userIniciales = collect(explode(' ', Auth::user()->name))->take(2)->map(fn($p) => strtoupper(substr($p,0,1)))->join('');
            @endphp
            <button type="button" class="vti-user-btn" id="userMenuBtn">
                <span class="vti-user-avatar">{{ $userIniciales }}</span>
                <span class="vti-user-name">{{ Auth::user()->name }}</span>
                <i class="bi bi-chevron-down" style="font-size:.65rem;color:#94a3b8"></i>
            </button>
            <div class="vti-user-dropdown" id="userDropdown">
                @can('admin')
                <a href="{{ route('admin.usuarios.index') }}">
                    <i class="bi bi-people-fill"></i>{{ __('Gestión de Usuarios') }}
                </a>
                <hr>
                @endcan
                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="bi bi-box-arrow-right"></i>{{ __('Cerrar Sesión') }}
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </div>
    </header>
    @endauth

    {{-- ════════════════════════════ CONTENIDO ════════════════════════════ --}}
    <div id="app" class="@auth vti-main @endauth">
        <main class="@auth vti-main-inner @else py-4 @endauth">
            <div class="@guest container @endguest">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if ($errors->any())
                    {{-- Alert fijo: no desplaza el contenido de la página --}}
                    <div id="globalErrorAlert"
                         style="position:fixed;top:64px;right:1.25rem;z-index:1090;max-width:360px;animation:slideInRight .25s ease">
                        <div class="alert alert-danger alert-dismissible shadow mb-0" role="alert">
                            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Corrige los siguientes campos:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                    <style>
                        @keyframes slideInRight { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
                    </style>
                @endif
            </div>

            @yield('content')
        </main>

        <script>
            // ── Sidebar: toggle móvil + grupos colapsables ──────────────────
            (function () {
                const sidebar  = document.getElementById('vtiSidebar');
                const backdrop = document.getElementById('sidebarBackdrop');
                const toggle   = document.getElementById('sidebarToggle');
                if (!sidebar) return;

                // Toggle móvil
                function openSidebar()  { sidebar.classList.add('show');    backdrop.classList.add('show'); }
                function closeSidebar() { sidebar.classList.remove('show'); backdrop.classList.remove('show'); }

                toggle?.addEventListener('click', openSidebar);
                backdrop?.addEventListener('click', closeSidebar);

                // Contraer / expandir (desktop)
                const collapseBtn = document.getElementById('sidebarCollapseBtn');
                collapseBtn?.addEventListener('click', () => {
                    const contraido = document.documentElement.classList.toggle('sb-collapsed');
                    try { localStorage.setItem('vti_sb_collapsed', contraido ? '1' : '0'); } catch (e) {}
                });

                // Cerrar al navegar (móvil)
                sidebar.querySelectorAll('a.vti-nav-link').forEach(link => {
                    link.addEventListener('click', () => {
                        if (window.innerWidth < 992) closeSidebar();
                    });
                });

                // Grupos colapsables — modo acordeón (solo uno abierto a la vez)
                const KEY    = 'vti_sidebar_open_group';
                const grupos = sidebar.querySelectorAll('.vti-nav-group');
                let abierto  = null;
                try { abierto = localStorage.getItem(KEY); } catch (e) {}

                // Prioridad: grupo con link activo > último abierto por el usuario
                let grupoInicial = null;
                grupos.forEach(g => {
                    if (g.querySelector('.vti-nav-link.active')) grupoInicial = g.dataset.group;
                });
                if (!grupoInicial) grupoInicial = abierto;

                grupos.forEach(grupo => {
                    const id        = grupo.dataset.group;
                    const toggleBtn = grupo.querySelector('.vti-nav-group-toggle');

                    if (id === grupoInicial) grupo.classList.add('open');

                    toggleBtn.addEventListener('click', () => {
                        const estabaAbierto = grupo.classList.contains('open');
                        // Cerrar todos
                        grupos.forEach(g => g.classList.remove('open'));
                        // Abrir el clickeado (si estaba cerrado)
                        if (!estabaAbierto) grupo.classList.add('open');
                        try {
                            if (estabaAbierto) localStorage.removeItem(KEY);
                            else               localStorage.setItem(KEY, id);
                        } catch (e) {}
                    });
                });
            })();

            // ── Dropdown usuario ────────────────────────────────────────────
            (function () {
                const btn  = document.getElementById('userMenuBtn');
                const menu = document.getElementById('userDropdown');
                if (!btn || !menu) return;

                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    menu.classList.toggle('show');
                });
                document.addEventListener('click', (e) => {
                    if (!menu.contains(e.target)) menu.classList.remove('show');
                });
            })();

            // ── Auto-dismiss alertas ────────────────────────────────────────
            setTimeout(() => {
                document.querySelectorAll('.alert:not(.no-autodismiss)').forEach(el => {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 500);
                });
            }, 3000);

            // ── Modal de confirmación de eliminación ────────────────────────
            (function () {
                let pendingForm = null;
                let modal       = null;

                const modalEl   = document.getElementById('modalConfirmarEliminar');
                const btnOk     = document.getElementById('modal-confirm-btn');
                const lblNom    = document.getElementById('modal-confirm-nombre');
                const lblVerb   = document.getElementById('modal-confirm-verb');
                const lblTitle  = document.getElementById('modal-confirm-title');
                const lblSub    = document.getElementById('modal-confirm-subtitle');
                const btnIcon   = document.getElementById('modal-confirm-btn-icon');
                const btnText   = document.getElementById('modal-confirm-btn-text');
                const hdr       = document.getElementById('modal-confirm-header');
                const iconWrap  = document.getElementById('modal-confirm-icon-wrap');
                const iconEl    = document.getElementById('modal-confirm-icon');
                if (!modalEl || !btnOk || !lblNom) return;

                function getModal() {
                    if (!modal) {
                        try { modal = new bootstrap.Modal(modalEl); }
                        catch (e) { return null; }
                    }
                    return modal;
                }

                document.addEventListener('submit', function (e) {
                    const form = e.target;
                    if (!form.hasAttribute('data-confirm')) return;
                    const m = getModal();
                    if (!m) return;
                    e.preventDefault();
                    pendingForm = form;

                    lblNom.textContent = form.dataset.confirm || 'este registro';

                    const verb    = form.dataset.confirmVerb    || 'eliminar';
                    const title   = form.dataset.confirmTitle   || 'Confirmar eliminación';
                    const sub     = form.dataset.confirmSub     || 'Esta acción no se puede deshacer';
                    const btnLbl  = form.dataset.confirmBtn     || ('Sí, ' + verb);
                    const icon    = form.dataset.confirmIcon    || 'bi-trash3-fill';
                    const color   = form.dataset.confirmColor   || 'danger';

                    if (lblVerb)  lblVerb.textContent  = verb;
                    if (lblTitle) lblTitle.textContent = title;
                    if (lblSub)   lblSub.textContent   = sub;
                    if (btnText)  btnText.textContent  = btnLbl;
                    if (btnIcon)  { btnIcon.className = ''; btnIcon.classList.add('bi', icon, 'me-1'); }

                    const colors = { danger: ['#fff1f2','#fee2e2','#dc2626'], warning: ['#fffbeb','#fef3c7','#d97706'], success: ['#f0fdf4','#dcfce7','#16a34a'] };
                    const [bgHdr, bgIcon, clrIcon] = colors[color] || colors.danger;
                    if (hdr)      hdr.style.background      = bgHdr;
                    if (iconWrap) iconWrap.style.background = bgIcon;
                    if (iconEl)   iconEl.style.color        = clrIcon;
                    btnOk.className = `btn btn-${color} btn-sm px-4`;

                    m.show();
                });

                btnOk.addEventListener('click', function () {
                    const m = getModal();
                    if (m) m.hide();
                    if (pendingForm) {
                        pendingForm.removeAttribute('data-confirm');
                        pendingForm.submit();
                        pendingForm = null;
                    }
                });

                modalEl.addEventListener('hidden.bs.modal', function () {
                    pendingForm = null;
                });
            })();

            // ── Loading overlay global ──────────────────────────────────────
            (function () {
                const loader = document.getElementById('page-loader');
                const bar    = document.getElementById('page-bar');

                const SKIP_SAME_PAGE = true;

                let barTimer = null;

                function startLoader() {
                    loader.classList.add('active');
                    bar.style.width = '0%';
                    let pct = 0;
                    clearInterval(barTimer);
                    barTimer = setInterval(() => {
                        pct += pct < 30 ? 8 : pct < 60 ? 4 : pct < 85 ? 1.5 : 0.3;
                        if (pct > 92) pct = 92;
                        bar.style.width = pct + '%';
                    }, 120);
                }

                function stopLoader() {
                    clearInterval(barTimer);
                    bar.style.width = '100%';
                    setTimeout(() => {
                        loader.classList.remove('active');
                        bar.style.transition = 'none';
                        bar.style.width = '0%';
                        setTimeout(() => { bar.style.transition = 'width 0.3s ease'; }, 50);
                    }, 300);
                }

                function shouldSkip(anchor) {
                    const attr = anchor.getAttribute('href') || '';
                    const full = anchor.href || '';
                    if (!attr || attr.startsWith('#') || attr.startsWith('javascript') || attr.startsWith('mailto')) return true;
                    if (anchor.dataset.bsToggle || anchor.dataset.bsDismiss) return true;
                    if (SKIP_SAME_PAGE && full === window.location.href) return true;
                    return false;
                }

                document.addEventListener('click', function (e) {
                    const anchor = e.target.closest('a[href]');
                    if (!anchor) return;
                    if (anchor.target === '_blank') return;
                    if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                    if (shouldSkip(anchor)) return;

                    startLoader();
                });

                document.addEventListener('submit', function (e) {
                    const form = e.target;
                    if (form.dataset.loader === undefined) return;
                    startLoader();
                });

                document.addEventListener('change', function (e) {
                    const input = e.target;
                    if (input.type !== 'radio' && input.type !== 'checkbox') return;
                    if (!input.form) return;
                    if (input.form.dataset.noLoader !== undefined) return;
                    if (input.dataset.noLoader !== undefined) return;
                    const onchange = input.getAttribute('onchange') || '';
                    if (!onchange.includes('submit')) return;
                    startLoader();
                });

                window.addEventListener('pageshow', function (e) {
                    stopLoader();
                });

                if (document.readyState === 'complete') {
                    stopLoader();
                } else {
                    window.addEventListener('load', stopLoader);
                }
            })();
        </script>
    </div>
    @stack('scripts')
</body>
</html>
