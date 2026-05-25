<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $appNombre ?? config('app.name', 'Laravel') }}</title>

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

    {{-- Modal de confirmación de eliminación --}}
    <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
            <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
                <div class="modal-header border-0 pb-0" style="background:#fff1f2">
                    <div class="d-flex align-items-center gap-3 w-100 px-1 pt-1">
                        <div style="width:44px;height:44px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-trash3-fill" style="font-size:1.2rem;color:#dc2626"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold" style="color:#1e293b">Confirmar eliminación</h6>
                            <small class="text-muted">Esta acción no se puede deshacer</small>
                        </div>
                    </div>
                </div>
                <div class="modal-body pt-3 pb-2">
                    <p class="mb-0 text-center" style="color:#475569;font-size:.9rem">
                        ¿Estás seguro de que deseas eliminar
                        <strong id="modal-confirm-nombre" class="text-dark"></strong>?
                    </p>
                </div>
                <div class="modal-footer border-0 pt-1 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                            data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-danger btn-sm px-4" id="modal-confirm-btn">
                        <i class="bi bi-trash3-fill me-1"></i>Sí, eliminar
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
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="{{ route('facturas.index') }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Facturación
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item fw-semibold" href="{{ route('facturas.pendientes') }}">Facturas Pendientes</a></li>
                                    <li><a class="dropdown-item" href="{{ route('facturas.index') }}">Facturas</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('servicios.index') }}">Servicios</a></li>
                                    <li><a class="dropdown-item" href="{{ route('familias.index') }}">Familias</a></li>
                                    <li><a class="dropdown-item" href="{{ route('empresas.index') }}">Empresas</a></li>
                                    <li><a class="dropdown-item" href="{{ route('companias.index') }}">Compañías</a></li>
                                    <li><a class="dropdown-item" href="{{ route('cuentas_contables.index') }}">Cuentas Contables</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Telefonía</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('lineas_telefonicas.index') }}">Líneas Telefónicas</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('emisores.index') }}">Emisores</a></li>
                                    <li><a class="dropdown-item" href="{{ route('usuarios_telefonicos.index') }}">Usuarios</a></li>
                                    <li><a class="dropdown-item" href="{{ route('ubicaciones.index') }}">Ubicaciones</a></li>
                                    <li><a class="dropdown-item" href="{{ route('marcas.index') }}">Marcas</a></li>
                                    <li><a class="dropdown-item" href="{{ route('aparatos.index') }}">Aparatos</a></li>
                                    <li><a class="dropdown-item" href="{{ route('centros_costo.index') }}">Centros de Costo</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('importaciones_movistar.index') }}">Importaciones Movistar</a></li>
                                    <li><a class="dropdown-item" href="{{ route('importaciones_entel.index') }}">Importaciones Entel</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item fw-semibold" href="{{ route('informes.telefonia') }}">📊 Informe Telefonía</a></li>
                                </ul>
                            </li>
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
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Por favor corrige los siguientes errores:</strong>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
            </div>

            @yield('content')
        </main>

        <script>
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

                const modalEl = document.getElementById('modalConfirmarEliminar');
                const btnOk   = document.getElementById('modal-confirm-btn');
                const lblNom  = document.getElementById('modal-confirm-nombre');
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
                    lblNom.textContent = form.dataset.confirm || 'este registro';
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
</body>
</html>
