<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $appNombre ?? config('app.name', 'Laravel') }}</title>
    @if(!empty($favicon))
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
    @endif

    <style>
        .navbar .dropdown:hover .dropdown-menu {
            display: block;
            margin-top: 0;
        }

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
            overflow: hidden;
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
            /* Reduce padding vertical del main */
            main.py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }

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
            /* Evitar que las celdas de acción queden muy angostas */
            .vti-table th, .vti-table td { white-space: nowrap; }

            /* Botones de acción: touch target más grande */
            .vti-btn-edit, .vti-btn-delete, .vti-btn-view {
                width: 36px; height: 36px; font-size: .82rem;
            }

            /* Footer: apilar total + paginación */
            .vti-footer { flex-direction: column; align-items: center; gap: .5rem; text-align: center; }

            /* Formularios de filtro: inputs al 100% */
            .form-select[style*="width"],
            .form-control[style*="width"],
            input.form-control[style*="width"] {
                width: 100% !important;
                min-width: 0 !important;
            }

            /* Cards de formulario: menos padding */
            .card-body.p-4 { padding: 1rem !important; }
            .card-body.p-3 { padding: .75rem !important; }

            /* Paginación más compacta */
            .pagination .page-link { padding: .3rem .55rem; font-size: .8rem; }
        }

        /* ── Barra de progreso superior (NProgress-style manual) ──── */
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

    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/home') }}">
                    @if(!empty($appLogo))
                        <img src="{{ $appLogo }}" height="28" alt="Logo"
                             style="object-fit:contain;max-width:120px">
                    @else
                        <span class="d-inline-flex align-items-center justify-content-center rounded-2"
                              style="width:28px;height:28px;background:linear-gradient(135deg,#1e3a5f,#2563eb);flex-shrink:0">
                            <i class="bi bi-building-check" style="font-size:.9rem;color:#fff"></i>
                        </span>
                    @endif
                    {{ $appNombre ?? config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    @guest
                        @if (Route::has('login'))
                            
                        @endif
                        @else
                        <!-- Left Side Of Navbar -->
                        <ul class="navbar-nav me-auto">
                            @if(auth()->user()->tieneAcceso('facturas.index'))
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="{{ route('facturas.index') }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-receipt-cutoff me-1"></i>Facturación
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item fw-semibold" href="{{ route('facturas.pendientes') }}">
                                        <i class="bi bi-hourglass-split me-1 text-warning"></i>Facturas Pendientes
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('facturas.index') }}">
                                        <i class="bi bi-receipt me-1"></i>Facturas
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('facturas.resumen') }}">
                                        <i class="bi bi-bar-chart-line me-1"></i>Resumen por Cuenta Contable
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('facturas.resumen_servicios') }}">
                                        <i class="bi bi-grid-3x3-gap me-1"></i>Resumen por Servicio
                                    </a></li>
                                    <li><a class="dropdown-item fw-semibold" href="{{ route('entregas_facturas.index') }}">
                                        <i class="bi bi-box-arrow-up-right me-1 text-success"></i>Entregas de Facturas
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('servicios.index') }}">
                                        <i class="bi bi-grid me-1"></i>Servicios
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('familias.index') }}">
                                        <i class="bi bi-collection me-1"></i>Familias
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('empresas.index') }}">
                                        <i class="bi bi-building me-1"></i>Empresas
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('companias.index') }}">
                                        <i class="bi bi-buildings me-1"></i>Compañías
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cuentas_contables.index') }}">
                                        <i class="bi bi-journal-bookmark me-1"></i>Cuentas Contables
                                    </a></li>
                                </ul>
                            </li>
                            @endif
                            @if(auth()->user()->tieneAcceso('lineas_telefonicas.index'))
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-phone me-1"></i>Telefonía
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('lineas_telefonicas.index') }}">
                                        <i class="bi bi-telephone-fill me-1"></i>Líneas Telefónicas
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('emisores.index') }}">
                                        <i class="bi bi-broadcast me-1"></i>Emisores
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('usuarios_telefonicos.index') }}">
                                        <i class="bi bi-person-fill me-1"></i>Usuarios
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('ubicaciones.index') }}">
                                        <i class="bi bi-geo-alt-fill me-1"></i>Ubicaciones
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('marcas.index') }}">
                                        <i class="bi bi-tag-fill me-1"></i>Marcas
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('aparatos.index') }}">
                                        <i class="bi bi-phone-fill me-1"></i>Aparatos
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('centros_costo.index') }}">
                                        <i class="bi bi-diagram-2-fill me-1"></i>Centros de Costo
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('importaciones_movistar.index') }}">
                                        <i class="bi bi-cloud-upload me-1"></i>Importaciones Movistar
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('importaciones_entel.index') }}">
                                        <i class="bi bi-cloud-upload me-1"></i>Importaciones Entel
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('importaciones_wom.index') }}">
                                        <i class="bi bi-cloud-upload me-1" style="color:#6f42c1"></i>Importaciones WOM
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item fw-semibold" href="{{ route('informes.telefonia') }}">
                                        <i class="bi bi-bar-chart-fill me-1"></i>Informe Telefonía
                                    </a></li>
                                </ul>
                            </li>
                            @endif
                            @can('acceso_ad')
                            <li class="nav-item">
                                <a class="nav-link fw-semibold" href="{{ route('admin.active_directory.index') }}"
                                   style="color:#6366f1">
                                    <i class="bi bi-diagram-3-fill me-1"></i>Active Directory
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fw-semibold" href="{{ route('admin.active_directory2.index') }}"
                                   style="color:#6366f1">
                                    <i class="bi bi-diagram-3-fill me-1"></i>AD Grupo Verfrut (Perú)
                                </a>
                            </li>
                            @endcan
                            @can('admin')
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-danger fw-semibold" href="#"
                                   role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-shield-lock-fill me-1"></i>Admin
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('admin.usuarios.index') }}">
                                        <i class="bi bi-people-fill me-1"></i>Usuarios
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.configuracion.index') }}">
                                        <i class="bi bi-gear-fill me-1"></i>Configuración
                                    </a></li>
                                </ul>
                            </li>
                            @endcan
                        </ul>
                    @endguest
                    
                    
                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                
                            @endif

                            @if (Route::has('register'))
                                
                            @endif
                        @else
                        {{-- <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                        </li> --}}
                        {{-- <li class="nav-item">
                            <b><a class="nav-link" href="{{ route('register') }}">{{ __('Registrar Usuario') }}</a></b>
                        </li> --}}
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    @can('admin')
                                    <a class="dropdown-item" href="{{ route('admin.usuarios.index') }}">
                                        <i class="bi bi-people-fill me-1"></i>{{ __('Gestión de Usuarios') }}
                                    </a>
                                    <hr class="dropdown-divider">
                                    @endcan

                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        <i class="bi bi-box-arrow-right me-1"></i>{{ __('Cerrar Sesión') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
                
            </div>
        </nav>

        <main class="py-4">
            <div class="container">
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
                         style="position:fixed;top:72px;right:1.25rem;z-index:1090;max-width:360px;animation:slideInRight .25s ease">
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
            // ── Fallback navbar hamburger (por si Bootstrap JS no carga) ───
            (function () {
                var toggler  = document.querySelector('.navbar-toggler');
                var collapse = document.getElementById('navbarSupportedContent');
                if (!toggler || !collapse) return;

                toggler.addEventListener('click', function () {
                    var open = collapse.classList.contains('show');
                    collapse.classList.toggle('show', !open);
                    toggler.setAttribute('aria-expanded', String(!open));
                });

                // Cerrar al hacer clic fuera del navbar
                document.addEventListener('click', function (e) {
                    if (!toggler.contains(e.target) && !collapse.contains(e.target)) {
                        collapse.classList.remove('show');
                        toggler.setAttribute('aria-expanded', 'false');
                    }
                });

                // Cerrar al hacer clic en un link del menú (navegación)
                collapse.querySelectorAll('a.nav-link:not(.dropdown-toggle)').forEach(function (link) {
                    link.addEventListener('click', function () {
                        collapse.classList.remove('show');
                        toggler.setAttribute('aria-expanded', 'false');
                    });
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
                    // Instancia lazy para evitar fallo si Bootstrap aún no cargó
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
                    if (!m) return; // Bootstrap no disponible, dejar pasar el submit normal
                    e.preventDefault();
                    pendingForm = form;

                    // Texto del objeto a confirmar
                    lblNom.textContent = form.dataset.confirm || 'este registro';

                    // Verbo y textos personalizables (por defecto: "eliminar")
                    const verb    = form.dataset.confirmVerb    || 'eliminar';
                    const title   = form.dataset.confirmTitle   || 'Confirmar eliminación';
                    const sub     = form.dataset.confirmSub     || 'Esta acción no se puede deshacer';
                    const btnLbl  = form.dataset.confirmBtn     || ('Sí, ' + verb);
                    const icon    = form.dataset.confirmIcon    || 'bi-trash3-fill';
                    const color   = form.dataset.confirmColor   || 'danger'; // danger | warning | success

                    if (lblVerb)  lblVerb.textContent  = verb;
                    if (lblTitle) lblTitle.textContent = title;
                    if (lblSub)   lblSub.textContent   = sub;
                    if (btnText)  btnText.textContent  = btnLbl;
                    if (btnIcon)  { btnIcon.className = ''; btnIcon.classList.add('bi', icon, 'me-1'); }

                    // Adaptar colores al tipo de acción
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

                // Rutas que NO deben activar el loader (misma página, anclas, JS, logout)
                const SKIP_SAME_PAGE = true;

                let barTimer = null;

                function startLoader() {
                    loader.classList.add('active');
                    bar.style.width = '0%';
                    // Simula avance progresivo de la barra
                    let pct = 0;
                    clearInterval(barTimer);
                    barTimer = setInterval(() => {
                        // Avanza rápido al principio, luego más lento
                        pct += pct < 30 ? 8 : pct < 60 ? 4 : pct < 85 ? 1.5 : 0.3;
                        if (pct > 92) pct = 92; // Nunca llega al 100 hasta que carga
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
                    // Anclas puras, javascript:, mailto:
                    if (!attr || attr.startsWith('#') || attr.startsWith('javascript') || attr.startsWith('mailto')) return true;
                    // Toggles de Bootstrap (dropdown, collapse, modal…)
                    if (anchor.dataset.bsToggle || anchor.dataset.bsDismiss) return true;
                    // Misma URL exacta
                    if (SKIP_SAME_PAGE && full === window.location.href) return true;
                    return false;
                }

                // Interceptar clics en <a> que naveguen a otra página
                document.addEventListener('click', function (e) {
                    const anchor = e.target.closest('a[href]');
                    if (!anchor) return;
                    if (anchor.target === '_blank') return;
                    if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                    if (shouldSkip(anchor)) return;

                    startLoader();
                });

                // Interceptar submit solo en formularios con data-loader explícito
                // (filtros de búsqueda, no formularios de datos donde puede haber validación)
                document.addEventListener('submit', function (e) {
                    const form = e.target;
                    if (form.dataset.loader === undefined) return;
                    startLoader();
                });

                // Interceptar radios y checkboxes con onchange="this.form.submit()"
                // .submit() programático NO dispara el evento submit, se captura aquí
                // SOLO si el elemento tiene un onchange que llama a submit (no checkboxes normales de formulario)
                document.addEventListener('change', function (e) {
                    const input = e.target;
                    if (input.type !== 'radio' && input.type !== 'checkbox') return;
                    if (!input.form) return;
                    if (input.form.dataset.noLoader !== undefined) return;
                    if (input.dataset.noLoader !== undefined) return;
                    // Solo disparar si el input auto-envía el formulario
                    const onchange = input.getAttribute('onchange') || '';
                    if (!onchange.includes('submit')) return;
                    startLoader();
                });

                // Ocultar al volver (botón atrás del navegador)
                window.addEventListener('pageshow', function (e) {
                    stopLoader();
                });

                // Por si la página ya terminó de cargar antes de que el script corra
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
