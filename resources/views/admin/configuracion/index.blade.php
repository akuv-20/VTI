@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-gear-fill me-2"></i>Configuración del Sistema</h4>
    </div>

    <div style="max-width:780px">

        {{-- ── Tabs ──────────────────────────────────────────────────── --}}
        <ul class="nav nav-tabs mb-0" id="cfgTabs" role="tablist"
            style="border-bottom:2px solid #e2e8f0">
            <li class="nav-item">
                <button class="nav-link active fw-semibold" id="tab-apariencia"
                        data-bs-toggle="tab" data-bs-target="#pane-apariencia"
                        type="button" style="font-size:.88rem">
                    <i class="bi bi-palette me-1"></i>Apariencia
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="tab-ldap"
                        data-bs-toggle="tab" data-bs-target="#pane-ldap"
                        type="button" style="font-size:.88rem">
                    <i class="bi bi-diagram-3 me-1"></i>Active Directory
                    @if($ldapCfg['username'])
                        <span class="badge bg-success ms-1" style="font-size:.65rem">ON</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="tab-ldap2"
                        data-bs-toggle="tab" data-bs-target="#pane-ldap2"
                        type="button" style="font-size:.88rem">
                    <i class="bi bi-diagram-3 me-1"></i>AD Grupo Verfrut (Perú)
                    @if($ldap2Cfg['username'])
                        <span class="badge bg-success ms-1" style="font-size:.65rem">ON</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="tab-glpi"
                        data-bs-toggle="tab" data-bs-target="#pane-glpi"
                        type="button" style="font-size:.88rem">
                    <i class="bi bi-pc-display me-1"></i>BD GLPI
                    @if($glpiCfg['username'])
                        <span class="badge bg-success ms-1" style="font-size:.65rem">ON</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="tab-azure"
                        data-bs-toggle="tab" data-bs-target="#pane-azure"
                        type="button" style="font-size:.88rem">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 23 23" class="me-1" style="vertical-align:-.1em">
                        <path fill="#f3f3f3" d="M0 0h23v23H0z"/><path fill="#f35325" d="M1 1h10v10H1z"/>
                        <path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/>
                        <path fill="#ffba08" d="M12 12h10v10H12z"/>
                    </svg>
                    Microsoft 365
                    @if($azureCfg['enabled'])
                        <span class="badge bg-success ms-1" style="font-size:.65rem">ON</span>
                    @endif
                </button>
            </li>
        </ul>

        <div class="tab-content bg-white border border-top-0 rounded-bottom-3 shadow-sm p-4"
             style="border-color:#e2e8f0 !important">

            {{-- ══════════════════════════════════════════════════════════
                 Tab: Apariencia
            ══════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade show active" id="pane-apariencia">

                {{-- Nombre --}}
                <div class="mb-4 pb-4" style="border-bottom:1px solid #f1f5f9">
                    <div class="row align-items-start g-3">
                        <div class="col-md-4">
                            <div class="fw-bold" style="font-size:.88rem;color:#1e293b">
                                <i class="bi bi-fonts me-1 text-primary"></i>Nombre
                            </div>
                            <div class="text-muted" style="font-size:.78rem;margin-top:2px">
                                Aparece en la barra de navegación y en el login.
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                  class="d-flex gap-2 align-items-start" data-loader>
                                @csrf
                                <div class="flex-grow-1">
                                    <input type="text" name="app_nombre"
                                           class="form-control form-control-sm @error('app_nombre') is-invalid @enderror"
                                           value="{{ old('app_nombre', $appNombre) }}"
                                           maxlength="60" placeholder="{{ config('app.name') }}">
                                    @error('app_nombre')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">
                                    <i class="bi bi-check-lg"></i> Guardar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Logo --}}
                <div class="mb-4 pb-4" style="border-bottom:1px solid #f1f5f9">
                    <div class="row align-items-start g-3">
                        <div class="col-md-4">
                            <div class="fw-bold" style="font-size:.88rem;color:#1e293b">
                                <i class="bi bi-image-fill me-1 text-primary"></i>Logo
                            </div>
                            <div class="text-muted" style="font-size:.78rem;margin-top:2px">
                                PNG, JPG, WebP o SVG — máx. 2 MB.
                            </div>
                            {{-- Preview --}}
                            <div class="mt-2">
                                @if($appLogo)
                                    <img src="{{ $appLogo }}" alt="Logo"
                                         class="rounded-2 border"
                                         style="height:52px;object-fit:contain;background:#f8fafc;padding:6px;max-width:140px">
                                    <div class="mt-1">
                                        <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                              data-confirm="el logo de la aplicación">
                                            @csrf
                                            <input type="hidden" name="eliminar_logo" value="1">
                                            <button class="btn btn-link btn-sm text-danger p-0" style="font-size:.75rem">
                                                <i class="bi bi-trash3-fill me-1"></i>Quitar logo
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="rounded-2 d-inline-flex align-items-center justify-content-center"
                                         style="height:52px;width:52px;background:linear-gradient(135deg,#1e3a5f,#2563eb)">
                                        <i class="bi bi-building-check" style="font-size:1.4rem;color:#fff"></i>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:.74rem">Ícono por defecto</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                  enctype="multipart/form-data" data-loader>
                                @csrf
                                <input type="file" name="app_logo" class="form-control form-control-sm"
                                       accept="image/jpeg,image/png,image/webp,image/svg+xml">
                                @error('app_logo')
                                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
                                    </div>
                                @enderror
                                <button type="submit" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-upload me-1"></i>Subir logo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Favicon --}}
                <div class="mb-4 pb-4" style="border-bottom:1px solid #f1f5f9">
                    <div class="row align-items-start g-3">
                        <div class="col-md-4">
                            <div class="fw-bold" style="font-size:.88rem;color:#1e293b">
                                <i class="bi bi-globe me-1 text-primary"></i>Favicon
                            </div>
                            <div class="text-muted" style="font-size:.78rem;margin-top:2px">
                                ICO, PNG, SVG o WebP — máx. 512 KB.<br>
                                Recomendado: 32×32 px o 64×64 px.
                            </div>
                            {{-- Preview --}}
                            <div class="mt-2">
                                @php
                                    $faviconUrl = $favicon && \Illuminate\Support\Facades\Storage::disk('public')->exists($favicon)
                                        ? \Illuminate\Support\Facades\Storage::url($favicon)
                                        : null;
                                @endphp
                                @if($faviconUrl)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $faviconUrl }}" alt="Favicon"
                                             class="rounded border"
                                             style="width:40px;height:40px;object-fit:contain;background:#f8fafc;padding:4px">
                                        <div class="rounded border d-flex align-items-center gap-1 px-2 py-1"
                                             style="background:#f1f5f9;font-size:.72rem;color:#475569">
                                            <img src="{{ $faviconUrl }}" style="width:14px;height:14px;object-fit:contain">
                                            {{ $appNombre ?? config('app.name') }}
                                        </div>
                                    </div>
                                    <div class="mt-1">
                                        <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                              data-confirm="el favicon de la aplicación">
                                            @csrf
                                            <input type="hidden" name="eliminar_favicon" value="1">
                                            <button class="btn btn-link btn-sm text-danger p-0" style="font-size:.75rem">
                                                <i class="bi bi-trash3-fill me-1"></i>Quitar favicon
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded border d-flex align-items-center justify-content-center"
                                             style="width:40px;height:40px;background:#f8fafc">
                                            <i class="bi bi-globe" style="font-size:1.1rem;color:#94a3b8"></i>
                                        </div>
                                        <div class="text-muted" style="font-size:.74rem">Sin favicon</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                  enctype="multipart/form-data" data-loader>
                                @csrf
                                <input type="file" name="favicon" class="form-control form-control-sm"
                                       accept=".ico,image/x-icon,image/png,image/svg+xml,image/webp">
                                @error('favicon')
                                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
                                    </div>
                                @enderror
                                <button type="submit" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-upload me-1"></i>Subir favicon
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Fondo del login --}}
                <div>
                    <div class="row align-items-start g-3">
                        <div class="col-md-4">
                            <div class="fw-bold" style="font-size:.88rem;color:#1e293b">
                                <i class="bi bi-image me-1 text-primary"></i>Fondo del Login
                            </div>
                            <div class="text-muted" style="font-size:.78rem;margin-top:2px">
                                JPG, PNG o WebP — máx. 10 MB.<br>Recomendado: 1920×1080 px.
                            </div>
                            {{-- Preview --}}
                            <div class="mt-2">
                                @if($loginBackground)
                                    <img src="{{ $loginBackground }}" alt="Fondo"
                                         class="rounded-2 border"
                                         style="height:72px;object-fit:cover;width:140px">
                                    <div class="mt-1">
                                        <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                              data-confirm="la imagen de fondo del login">
                                            @csrf
                                            <input type="hidden" name="eliminar_fondo" value="1">
                                            <button class="btn btn-link btn-sm text-danger p-0" style="font-size:.75rem">
                                                <i class="bi bi-trash3-fill me-1"></i>Quitar imagen
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                                         style="height:72px;width:140px;background:linear-gradient(135deg,#1e3a5f,#2563eb)">
                                        <div class="text-center text-white">
                                            <i class="bi bi-image" style="font-size:1.3rem;opacity:.6"></i>
                                            <div style="font-size:.62rem;opacity:.75;margin-top:2px">Gradiente azul</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form action="{{ route('admin.configuracion.update') }}" method="POST"
                                  enctype="multipart/form-data" data-loader>
                                @csrf
                                <input type="file" name="login_background" class="form-control form-control-sm"
                                       accept="image/jpeg,image/png,image/webp">
                                @error('login_background')
                                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
                                    </div>
                                @enderror
                                <button type="submit" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-upload me-1"></i>Subir imagen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>{{-- /pane-apariencia --}}

            {{-- ══════════════════════════════════════════════════════════
                 Tab: Active Directory / LDAP
            ══════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-ldap">

                <form action="{{ route('admin.configuracion.update') }}" method="POST" data-loader id="formLdap">
                    @csrf
                    <input type="hidden" name="seccion" value="ldap">

                    <div class="row g-3 mb-3">
                        <div class="col-md-9">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Servidores (Domain Controllers)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="ldap_host"
                                   class="form-control form-control-sm font-monospace @error('ldap_host') is-invalid @enderror"
                                   value="{{ old('ldap_host', $ldapCfg['host']) }}"
                                   placeholder="vfrpdc01.verfrut.cl,vfrpdc02.verfrut.cl">
                            <div class="form-text">Separados por coma si hay más de uno. El primero es el principal.</div>
                            @error('ldap_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Puerto <span class="text-danger">*</span></label>
                            <input type="number" name="ldap_port"
                                   class="form-control form-control-sm @error('ldap_port') is-invalid @enderror"
                                   value="{{ old('ldap_port', $ldapCfg['port']) }}"
                                   min="1" max="65535">
                            <div class="form-text">389 LDAP · 636 LDAPS</div>
                            @error('ldap_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Base DN <span class="text-danger">*</span></label>
                            <input type="text" name="ldap_base_dn"
                                   class="form-control form-control-sm font-monospace @error('ldap_base_dn') is-invalid @enderror"
                                   value="{{ old('ldap_base_dn', $ldapCfg['base_dn']) }}"
                                   placeholder="DC=verfrut,DC=cl">
                            <div class="form-text">Raíz del directorio desde donde se buscarán los objetos.</div>
                            @error('ldap_base_dn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Usuario de servicio <span class="text-danger">*</span></label>
                            <input type="text" name="ldap_username"
                                   class="form-control form-control-sm font-monospace @error('ldap_username') is-invalid @enderror"
                                   value="{{ old('ldap_username', $ldapCfg['username']) }}"
                                   placeholder="usuario@verfrut.cl">
                            <div class="form-text">Formato UPN: usuario@verfrut.cl</div>
                            @error('ldap_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Contraseña</label>
                            <input type="password" name="ldap_password"
                                   class="form-control form-control-sm"
                                   autocomplete="new-password"
                                   placeholder="{{ $ldapCfg['username'] ? 'Dejar en blanco para no cambiar' : 'Ingresar contraseña' }}">
                        </div>
                    </div>

                    {{-- Info --}}
                    <div class="p-3 rounded-2 mb-3 d-flex align-items-start gap-2"
                         style="background:#f0f9ff;border:1px solid #bae6fd;font-size:.8rem">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1" style="color:#0284c7"></i>
                        <span>
                            Para <strong>resetear contraseñas</strong> de usuarios AD se requiere conexión
                            <strong>LDAPS (puerto 636)</strong> con certificado SSL válido.
                            La lectura y modificación de atributos funciona con LDAP estándar (389).
                        </span>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestLdap">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                        <span id="ldapTestResult" class="small ms-1"></span>
                    </div>
                </form>

            </div>{{-- /pane-ldap --}}

            {{-- ══════════════════════════════════════════════════════════
                 Tab: Active Directory — Grupo Verfrut (Perú)
            ══════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-ldap2">

                <form action="{{ route('admin.configuracion.update') }}" method="POST" data-loader id="formLdap2">
                    @csrf
                    <input type="hidden" name="seccion" value="ldap2">

                    <div class="row g-3 mb-3">
                        <div class="col-md-9">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Servidores (Domain Controllers) <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="ldap2_host"
                                   class="form-control form-control-sm font-monospace @error('ldap2_host') is-invalid @enderror"
                                   value="{{ old('ldap2_host', $ldap2Cfg['host']) }}"
                                   placeholder="dc01.dominio.pe,dc02.dominio.pe">
                            <div class="form-text">Separados por coma si hay más de uno.</div>
                            @error('ldap2_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Puerto <span class="text-danger">*</span></label>
                            <input type="number" name="ldap2_port"
                                   class="form-control form-control-sm @error('ldap2_port') is-invalid @enderror"
                                   value="{{ old('ldap2_port', $ldap2Cfg['port']) }}"
                                   min="1" max="65535">
                            <div class="form-text">389 LDAP · 636 LDAPS</div>
                            @error('ldap2_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Base DN <span class="text-danger">*</span></label>
                            <input type="text" name="ldap2_base_dn"
                                   class="form-control form-control-sm font-monospace @error('ldap2_base_dn') is-invalid @enderror"
                                   value="{{ old('ldap2_base_dn', $ldap2Cfg['base_dn']) }}"
                                   placeholder="DC=grupoVerfrut,DC=pe">
                            <div class="form-text">Raíz del directorio desde donde se buscarán los objetos.</div>
                            @error('ldap2_base_dn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Usuario de servicio <span class="text-danger">*</span></label>
                            <input type="text" name="ldap2_username"
                                   class="form-control form-control-sm font-monospace @error('ldap2_username') is-invalid @enderror"
                                   value="{{ old('ldap2_username', $ldap2Cfg['username']) }}"
                                   placeholder="usuario@dominio.pe">
                            <div class="form-text">Formato UPN: usuario@dominio.pe</div>
                            @error('ldap2_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Contraseña</label>
                            <input type="password" name="ldap2_password"
                                   class="form-control form-control-sm"
                                   autocomplete="new-password"
                                   placeholder="{{ $ldap2Cfg['username'] ? 'Dejar en blanco para no cambiar' : 'Ingresar contraseña' }}">
                        </div>
                    </div>

                    <div class="p-3 rounded-2 mb-3 d-flex align-items-start gap-2"
                         style="background:#f0f9ff;border:1px solid #bae6fd;font-size:.8rem">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1" style="color:#0284c7"></i>
                        <span>
                            Esta es la conexión secundaria para el dominio <strong>Grupo Verfrut (Perú)</strong>.
                            Para <strong>resetear contraseñas</strong> se requiere <strong>LDAPS (puerto 636)</strong>.
                        </span>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestLdap2">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                        <span id="ldap2TestResult" class="small ms-1"></span>
                    </div>
                </form>

            </div>{{-- /pane-ldap2 --}}

            {{-- ══════════════════════════════════════════════════════════
                 Tab: Microsoft 365
            ══════════════════════════════════════════════════════════ --}}
            {{-- ══════════════════ GLPI ══════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-glpi">

                <form method="POST" action="{{ route('admin.configuracion.update') }}">
                    @csrf
                    <input type="hidden" name="seccion" value="glpi">

                    <p class="text-muted mb-3" style="font-size:.83rem">
                        Configura la conexión a la base de datos GLPI para el módulo Inventario TI.
                        La contraseña solo se actualiza si ingresas una nueva.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Host / IP del servidor</label>
                            <input type="text" name="glpi_db_host" class="form-control form-control-sm"
                                   value="{{ old('glpi_db_host', $glpiCfg['host']) }}"
                                   placeholder="127.0.0.1 o nombre de host">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Puerto</label>
                            <input type="number" name="glpi_db_port" class="form-control form-control-sm"
                                   value="{{ old('glpi_db_port', $glpiCfg['port']) }}"
                                   min="1" max="65535">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Base de datos</label>
                            <input type="text" name="glpi_db_database" class="form-control form-control-sm"
                                   value="{{ old('glpi_db_database', $glpiCfg['database']) }}"
                                   placeholder="glpi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Usuario</label>
                            <input type="text" name="glpi_db_username" class="form-control form-control-sm"
                                   value="{{ old('glpi_db_username', $glpiCfg['username']) }}"
                                   placeholder="vti_readonly" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.83rem">
                                Contraseña <span class="text-muted fw-normal">(dejar en blanco para mantener la actual)</span>
                            </label>
                            <input type="password" name="glpi_db_password" class="form-control form-control-sm"
                                   placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    @error('glpi_db_host') <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestGlpi">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                    </div>

                    <div id="glpiTestResult" class="mt-2" style="font-size:.83rem;display:none"></div>
                </form>

            </div>{{-- /pane-glpi --}}

            <div class="tab-pane fade" id="pane-azure">

                <form action="{{ route('admin.configuracion.update') }}" method="POST" data-loader>
                    @csrf
                    <input type="hidden" name="seccion" value="azure">

                    {{-- Toggle activar --}}
                    <div class="d-flex align-items-center justify-content-between mb-4 pb-3"
                         style="border-bottom:1px solid #f1f5f9">
                        <div>
                            <div class="fw-bold" style="font-size:.88rem;color:#1e293b">Habilitar login con Microsoft 365</div>
                            <div class="text-muted" style="font-size:.78rem">Muestra el botón "Continuar con Microsoft 365" en la pantalla de inicio de sesión.</div>
                        </div>
                        <div class="form-check form-switch ms-3 mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="azure_enabled" id="azure_enabled" value="1"
                                   style="width:2.5em;height:1.3em"
                                   {{ $azureCfg['enabled'] ? 'checked' : '' }}>
                        </div>
                    </div>

                    {{-- Credenciales --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Application (Client) ID
                            </label>
                            <input type="text" name="azure_client_id"
                                   class="form-control form-control-sm font-monospace"
                                   value="{{ $azureCfg['client_id'] }}"
                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Client Secret
                            </label>
                            <input type="password" name="azure_client_secret"
                                   class="form-control form-control-sm font-monospace"
                                   value="{{ $azureCfg['client_secret'] }}"
                                   placeholder="Dejar en blanco para no cambiar">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Directory (Tenant) ID
                            </label>
                            <input type="text" name="azure_tenant_id"
                                   class="form-control form-control-sm font-monospace"
                                   value="{{ $azureCfg['tenant_id'] }}"
                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        </div>
                    </div>

                    {{-- Info URI --}}
                    <div class="p-3 rounded-2 mb-3 d-flex align-items-center gap-2"
                         style="background:#f0f9ff;border:1px solid #bae6fd;font-size:.8rem">
                        <i class="bi bi-info-circle-fill flex-shrink-0" style="color:#0284c7"></i>
                        <span>
                            <strong>URI de redirección</strong> registrada en Azure:
                            <code class="ms-1">{{ url('/auth/azure/callback') }}</code>
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar configuración
                    </button>
                </form>

            </div>{{-- /pane-azure --}}

        </div>{{-- /tab-content --}}
    </div>

</div>
@endsection

@push('scripts')
<script>
// Inicialización manual de pestañas (fallback por si Bootstrap JS no carga en producción)
(function () {
    var buttons = document.querySelectorAll('[data-bs-toggle="tab"]');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var targetId = btn.getAttribute('data-bs-target');
            if (!targetId) return;
            var target = document.querySelector(targetId);
            if (!target) return;

            // Desactivar todas las pestañas y paneles
            buttons.forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-pane').forEach(function (p) {
                p.classList.remove('show', 'active');
            });

            // Activar la pestaña y panel seleccionado
            btn.classList.add('active');
            target.classList.add('show', 'active');
        });
    });
})();

document.getElementById('btnTestLdap')?.addEventListener('click', function () {
    var btn = this;
    var result = document.getElementById('ldapTestResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Probando…';
    result.textContent = '';
    result.className = 'small ms-1';

    fetch('{{ route("admin.configuracion.test-ldap") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        result.textContent = (data.ok ? '✓ ' : '✗ ') + data.message;
        result.className   = 'small ms-1 ' + (data.ok ? 'text-success' : 'text-danger');
    })
    .catch(() => {
        result.textContent = '✗ Error al conectar con el servidor';
        result.className   = 'small ms-1 text-danger';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-1"></i>Probar conexión';
    });
});

document.getElementById('btnTestGlpi')?.addEventListener('click', function () {
    var btn    = this;
    var result = document.getElementById('glpiTestResult');
    var form   = btn.closest('form');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Probando…';
    result.style.display = 'none';

    var data = {
        host:     form.querySelector('[name=glpi_db_host]').value,
        port:     form.querySelector('[name=glpi_db_port]').value,
        database: form.querySelector('[name=glpi_db_database]').value,
        username: form.querySelector('[name=glpi_db_username]').value,
        password: form.querySelector('[name=glpi_db_password]').value,
    };

    fetch('{{ route("admin.configuracion.test-glpi") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    })
    .then(r => r.json())
    .then(d => {
        result.textContent   = (d.ok ? '✓ ' : '✗ ') + d.message;
        result.className     = 'mt-2 ' + (d.ok ? 'text-success' : 'text-danger');
        result.style.display = 'block';
    })
    .catch(() => {
        result.textContent   = '✗ Error al conectar con el servidor';
        result.className     = 'mt-2 text-danger';
        result.style.display = 'block';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-1"></i>Probar conexión';
    });
});

document.getElementById('btnTestLdap2')?.addEventListener('click', function () {
    var btn    = this;
    var result = document.getElementById('ldap2TestResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Probando…';
    result.textContent = '';
    result.className = 'small ms-1';

    fetch('{{ route("admin.configuracion.test-ldap2") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        result.textContent = (data.ok ? '✓ ' : '✗ ') + data.message;
        result.className   = 'small ms-1 ' + (data.ok ? 'text-success' : 'text-danger');
    })
    .catch(() => {
        result.textContent = '✗ Error al conectar con el servidor';
        result.className   = 'small ms-1 text-danger';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-1"></i>Probar conexión';
    });
});
</script>
@endpush
