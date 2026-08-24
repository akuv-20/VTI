@extends('layouts.app')
@section('content')
<style>
/* cfg- : pantalla Admin → Configuración */
.cfg-nav            { position:sticky; top:1rem }
.cfg-grupo          { font-size:.68rem; font-weight:700; letter-spacing:.06em;
                      text-transform:uppercase; color:#94a3b8; padding:0 .25rem .35rem;
                      margin-top:1rem }
.cfg-grupo:first-child { margin-top:0 }
.cfg-nav .nav-link  { display:flex; align-items:center; gap:.5rem; width:100%;
                      font-size:.84rem; font-weight:600; color:#475569;
                      border:1px solid transparent; border-radius:.5rem;
                      padding:.5rem .65rem; margin-bottom:.15rem; text-align:left }
.cfg-nav .nav-link:hover        { background:#f1f5f9; color:#1e293b }
.cfg-nav .nav-link.active       { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe }
.cfg-nav .nav-link .cfg-ico     { width:1.1rem; flex-shrink:0; text-align:center }
.cfg-nav .nav-link .cfg-txt     { flex:1; min-width:0; overflow:hidden;
                                  text-overflow:ellipsis; white-space:nowrap }

/* Semáforo de estado por conexión */
.cfg-dot            { width:9px; height:9px; border-radius:50%; flex-shrink:0;
                      background:#cbd5e1; box-shadow:0 0 0 2px #fff }
.cfg-dot[data-estado="ok"]       { background:#16a34a }
.cfg-dot[data-estado="error"]    { background:#dc2626 }
.cfg-dot[data-estado="probando"] { background:#f59e0b; animation:cfgLatido 1s ease-in-out infinite }
@keyframes cfgLatido { 0%,100% { opacity:1 } 50% { opacity:.25 } }

.cfg-resumen        { font-size:.75rem; color:#64748b; padding:.5rem .25rem 0 }
.cfg-estado-pane    { font-size:.83rem; border-radius:.5rem; padding:.6rem .8rem;
                      margin-bottom:1rem; display:none; align-items:flex-start; gap:.5rem }
.cfg-estado-pane.ok    { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d }
.cfg-estado-pane.error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c }
.cfg-estado-pane.info  { background:#f8fafc; border:1px solid #e2e8f0; color:#64748b }
</style>
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-gear-fill me-2"></i>Configuración del Sistema</h4>
    </div>

    <div style="max-width:1040px">

        <div class="row g-3">

        {{-- ── Navegación lateral ────────────────────────────────────── --}}
        <div class="col-lg-4">
        <div class="cfg-nav bg-white border rounded-3 shadow-sm p-3"
             style="border-color:#e2e8f0 !important">

            <div class="cfg-grupo">General</div>
            <ul class="nav flex-column mb-0" id="cfgTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="tab-apariencia"
                            data-bs-toggle="tab" data-bs-target="#pane-apariencia" type="button">
                        <i class="bi bi-palette cfg-ico"></i>
                        <span class="cfg-txt">Apariencia</span>
                    </button>
                </li>
            </ul>

            <div class="cfg-grupo">Conexiones</div>
            <ul class="nav flex-column mb-0" role="tablist">
                {{-- data-cfg-test  : endpoint del test
                     data-cfg-lista : si está configurada (si no, ni se prueba)
                     data-cfg-panel : dónde escribir el detalle del resultado --}}
                <li class="nav-item">
                    <button class="nav-link" id="tab-ldap"
                            data-bs-toggle="tab" data-bs-target="#pane-ldap" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-ldap') }}"
                            data-cfg-lista="{{ $ldapCfg['username'] ? '1' : '0' }}"
                            data-cfg-panel="ldapEstado">
                        <i class="bi bi-diagram-3 cfg-ico"></i>
                        <span class="cfg-txt">Active Directory</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-ldap2"
                            data-bs-toggle="tab" data-bs-target="#pane-ldap2" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-ldap2') }}"
                            data-cfg-lista="{{ $ldap2Cfg['username'] ? '1' : '0' }}"
                            data-cfg-panel="ldap2Estado">
                        <i class="bi bi-diagram-3 cfg-ico"></i>
                        <span class="cfg-txt">AD Verfrut Perú</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-ldap3"
                            data-bs-toggle="tab" data-bs-target="#pane-ldap3" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-ldap3') }}"
                            data-cfg-lista="{{ $ldap3Cfg['username'] ? '1' : '0' }}"
                            data-cfg-panel="ldap3Estado">
                        <i class="bi bi-diagram-3 cfg-ico"></i>
                        <span class="cfg-txt">AD Unifrutti</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-glpi"
                            data-bs-toggle="tab" data-bs-target="#pane-glpi" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-glpi') }}"
                            data-cfg-lista="{{ $glpiCfg['username'] ? '1' : '0' }}"
                            data-cfg-panel="glpiEstado">
                        <i class="bi bi-pc-display cfg-ico"></i>
                        <span class="cfg-txt">BD GLPI</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-glpiuni"
                            data-bs-toggle="tab" data-bs-target="#pane-glpiuni" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-glpiuni') }}"
                            data-cfg-lista="{{ $glpiUniCfg['username'] ? '1' : '0' }}"
                            data-cfg-panel="glpiUniEstado">
                        <i class="bi bi-pc-display-horizontal cfg-ico"></i>
                        <span class="cfg-txt">GLPI Unifrutti</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-azure"
                            data-bs-toggle="tab" data-bs-target="#pane-azure" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-azure') }}"
                            data-cfg-lista="{{ $azureCfg['enabled'] ? '1' : '0' }}"
                            data-cfg-panel="azureEstado">
                        <span class="cfg-ico">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 23 23" style="vertical-align:-.1em">
                                <path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/>
                                <path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/>
                            </svg>
                        </span>
                        <span class="cfg-txt">Microsoft 365</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-checkmk"
                            data-bs-toggle="tab" data-bs-target="#pane-checkmk" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-checkmk') }}"
                            data-cfg-lista="{{ $checkmkCfg['url'] && $checkmkCfg['site'] ? '1' : '0' }}"
                            data-cfg-panel="checkmkEstado">
                        <i class="bi bi-activity cfg-ico"></i>
                        <span class="cfg-txt">CheckMK</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-veeam"
                            data-bs-toggle="tab" data-bs-target="#pane-veeam" type="button"
                            data-cfg-test="{{ route('admin.configuracion.test-veeam') }}"
                            data-cfg-lista="{{ $veeamCfg['url'] && $veeamCfg['user'] ? '1' : '0' }}"
                            data-cfg-panel="veeamEstado">
                        <i class="bi bi-shield-check cfg-ico"></i>
                        <span class="cfg-txt">Veeam</span>
                        <span class="cfg-dot" data-estado="sin"></span>
                    </button>
                </li>
            </ul>

            <div class="cfg-resumen d-flex align-items-center justify-content-between">
                <span id="cfgResumen">Comprobando conexiones…</span>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                        id="btnProbarTodas" style="font-size:.75rem">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar
                </button>
            </div>

        </div>
        </div>

        {{-- ── Contenido ─────────────────────────────────────────────── --}}
        <div class="col-lg-8">
        <div class="tab-content bg-white border rounded-3 shadow-sm p-4"
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

                <div class="cfg-estado-pane" id="ldapEstado"></div>

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

                <div class="cfg-estado-pane" id="ldap2Estado"></div>

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
                 Tab: Active Directory — Unifrutti
            ══════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-ldap3">

                <div class="cfg-estado-pane" id="ldap3Estado"></div>

                <form action="{{ route('admin.configuracion.update') }}" method="POST" data-loader id="formLdap3">
                    @csrf
                    <input type="hidden" name="seccion" value="ldap3">

                    <div class="row g-3 mb-3">
                        <div class="col-md-9">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Servidores (Domain Controllers) <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="ldap3_host"
                                   class="form-control form-control-sm font-monospace @error('ldap3_host') is-invalid @enderror"
                                   value="{{ old('ldap3_host', $ldap3Cfg['host']) }}"
                                   placeholder="UFPEDC01.unifrutti.com">
                            <div class="form-text">Separados por coma si hay más de uno.</div>
                            @error('ldap3_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Puerto <span class="text-danger">*</span></label>
                            <input type="number" name="ldap3_port"
                                   class="form-control form-control-sm @error('ldap3_port') is-invalid @enderror"
                                   value="{{ old('ldap3_port', $ldap3Cfg['port']) }}"
                                   min="1" max="65535">
                            <div class="form-text">389 LDAP · 636 LDAPS</div>
                            @error('ldap3_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Base DN <span class="text-danger">*</span></label>
                            <input type="text" name="ldap3_base_dn"
                                   class="form-control form-control-sm font-monospace @error('ldap3_base_dn') is-invalid @enderror"
                                   value="{{ old('ldap3_base_dn', $ldap3Cfg['base_dn']) }}"
                                   placeholder="DC=unifrutti,DC=com">
                            <div class="form-text">Raíz del directorio desde donde se buscarán los objetos.</div>
                            @error('ldap3_base_dn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Usuario de servicio <span class="text-danger">*</span></label>
                            <input type="text" name="ldap3_username"
                                   class="form-control form-control-sm font-monospace @error('ldap3_username') is-invalid @enderror"
                                   value="{{ old('ldap3_username', $ldap3Cfg['username']) }}"
                                   placeholder="usuario@unifrutti.com">
                            <div class="form-text">Formato UPN: usuario@unifrutti.com</div>
                            @error('ldap3_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.82rem">Contraseña</label>
                            <input type="password" name="ldap3_password"
                                   class="form-control form-control-sm"
                                   autocomplete="new-password"
                                   placeholder="{{ $ldap3Cfg['username'] ? 'Dejar en blanco para no cambiar' : 'Ingresar contraseña' }}">
                        </div>
                    </div>

                    <div class="p-3 rounded-2 mb-3 d-flex align-items-start gap-2"
                         style="background:#f0f9ff;border:1px solid #bae6fd;font-size:.8rem">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1" style="color:#0284c7"></i>
                        <span>
                            Esta es la conexión terciaria para el dominio <strong>Unifrutti</strong>.
                            Para <strong>resetear contraseñas</strong> se requiere <strong>LDAPS (puerto 636)</strong>.
                        </span>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestLdap3">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                        <span id="ldap3TestResult" class="small ms-1"></span>
                    </div>
                </form>

            </div>{{-- /pane-ldap3 --}}

            {{-- ══════════════════════════════════════════════════════════
                 Tab: Microsoft 365
            ══════════════════════════════════════════════════════════ --}}
            {{-- ══════════════════ GLPI ══════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-glpi">

                <div class="cfg-estado-pane" id="glpiEstado"></div>

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

            {{-- ══════════════════════════════════════════════════════════
                 Tab: BD GLPI Unifrutti (Helpdesk)
            ══════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-glpiuni">

                <div class="cfg-estado-pane" id="glpiUniEstado"></div>

                <form method="POST" action="{{ route('admin.configuracion.update') }}">
                    @csrf
                    <input type="hidden" name="seccion" value="glpiuni">

                    <p class="text-muted mb-3" style="font-size:.83rem">
                        Conexión a la base de datos del GLPI de <strong>Unifrutti</strong> (helpdesk.unifrutti.com),
                        usada por el módulo <strong>Inventario Unifrutti</strong> y el cruce con el AD.
                        La contraseña solo se actualiza si ingresas una nueva.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Host / IP del servidor</label>
                            <input type="text" name="glpiuni_db_host" class="form-control form-control-sm"
                                   value="{{ old('glpiuni_db_host', $glpiUniCfg['host']) }}"
                                   placeholder="192.168.2.69">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Puerto</label>
                            <input type="number" name="glpiuni_db_port" class="form-control form-control-sm"
                                   value="{{ old('glpiuni_db_port', $glpiUniCfg['port']) }}"
                                   min="1" max="65535">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Base de datos</label>
                            <input type="text" name="glpiuni_db_database" class="form-control form-control-sm"
                                   value="{{ old('glpiuni_db_database', $glpiUniCfg['database']) }}"
                                   placeholder="glpi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Usuario</label>
                            <input type="text" name="glpiuni_db_username" class="form-control form-control-sm"
                                   value="{{ old('glpiuni_db_username', $glpiUniCfg['username']) }}"
                                   placeholder="vti_readonly" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.83rem">
                                Contraseña <span class="text-muted fw-normal">(dejar en blanco para mantener la actual)</span>
                            </label>
                            <input type="password" name="glpiuni_db_password" class="form-control form-control-sm"
                                   placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    @error('glpiuni_db_host') <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestGlpiuni">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                    </div>

                    <div id="glpiuniTestResult" class="mt-2" style="font-size:.83rem;display:none"></div>
                </form>

            </div>{{-- /pane-glpiuni --}}

            <div class="tab-pane fade" id="pane-azure">

                <div class="cfg-estado-pane" id="azureEstado"></div>

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

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestAzure">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                    </div>

                    <div id="azureTestResult" class="mt-2" style="font-size:.83rem;display:none"></div>
                </form>

            </div>{{-- /pane-azure --}}

            {{-- ══════════════════ CheckMK ═══════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-checkmk">

                <div class="cfg-estado-pane" id="checkmkEstado"></div>

                <form method="POST" action="{{ route('admin.configuracion.update') }}">
                    @csrf
                    <input type="hidden" name="seccion" value="checkmk">

                    <p class="text-muted mb-3" style="font-size:.83rem">
                        Conexión a la API REST de CheckMK (2.3+) para el KPI de disponibilidad.
                        Usa un <strong>usuario de automatización</strong> y su <em>secret</em>.
                        El secret solo se actualiza si ingresas uno nuevo.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" style="font-size:.83rem">URL del servidor</label>
                            <input type="text" name="checkmk_url" class="form-control form-control-sm"
                                   value="{{ old('checkmk_url', $checkmkCfg['url']) }}"
                                   placeholder="https://checkmk.verfrut.cl">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Site</label>
                            <input type="text" name="checkmk_site" class="form-control form-control-sm"
                                   value="{{ old('checkmk_site', $checkmkCfg['site']) }}"
                                   placeholder="cmk">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Usuario de automatización</label>
                            <input type="text" name="checkmk_user" class="form-control form-control-sm"
                                   value="{{ old('checkmk_user', $checkmkCfg['user']) }}"
                                   placeholder="automation" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">
                                Secret <span class="text-muted fw-normal">(dejar en blanco para mantener el actual)</span>
                            </label>
                            <input type="password" name="checkmk_secret" class="form-control form-control-sm"
                                   placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="p-3 rounded-2 mt-3 d-flex align-items-start gap-2"
                         style="background:#f0f9ff;border:1px solid #bae6fd;font-size:.8rem">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1" style="color:#0284c7"></i>
                        <span>
                            La API se consulta en
                            <code>{URL}/{site}/check_mk/api/1.0</code>.
                            Guarda antes de probar la conexión.
                        </span>
                    </div>

                    @error('checkmk_url')  <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror
                    @error('checkmk_site') <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror
                    @error('checkmk_user') <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestCheckmk">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                    </div>

                    <div id="checkmkTestResult" class="mt-2" style="font-size:.83rem;display:none"></div>
                </form>

            </div>{{-- /pane-checkmk --}}

            {{-- ══════════════════ Veeam B&R ═════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-veeam">

                <div class="cfg-estado-pane" id="veeamEstado"></div>

                <form method="POST" action="{{ route('admin.configuracion.update') }}">
                    @csrf
                    <input type="hidden" name="seccion" value="veeam">

                    <p class="text-muted mb-3" style="font-size:.83rem">
                        Conexión a la API REST de <strong>Veeam Backup &amp; Replication</strong> (v12+)
                        para el KPI de continuidad operacional: jobs configurados y sesiones ejecutadas.
                        Basta una cuenta de <strong>solo lectura</strong> (rol <em>Veeam Backup Viewer</em>).
                        La contraseña solo se actualiza si ingresas una nueva.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="font-size:.83rem">URL del servidor</label>
                            <input type="text" name="veeam_url" class="form-control form-control-sm"
                                   value="{{ old('veeam_url', $veeamCfg['url']) }}"
                                   placeholder="https://192.168.2.80:9419">
                            <div class="form-text" style="font-size:.75rem">
                                Incluye el puerto. El de la API REST de VBR es el <code>9419</code>.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Usuario</label>
                            <input type="text" name="veeam_user" class="form-control form-control-sm"
                                   value="{{ old('veeam_user', $veeamCfg['user']) }}"
                                   placeholder="VERFRUT\fhenriquez" autocomplete="off">
                            <div class="form-text" style="font-size:.75rem">
                                Cuenta de dominio (<code>DOMINIO\usuario</code> o <code>usuario@dominio</code>)
                                o local del servidor Veeam.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.83rem">
                                Contraseña <span class="text-muted fw-normal">(dejar en blanco para mantener la actual)</span>
                            </label>
                            <input type="password" name="veeam_password" class="form-control form-control-sm"
                                   placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="p-3 rounded-2 mt-3 d-flex align-items-start gap-2"
                         style="background:#f0f9ff;border:1px solid #bae6fd;font-size:.8rem">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1" style="color:#0284c7"></i>
                        <span>
                            La API se consulta en <code>{URL}/api/v1</code>.
                            La revisión de API (<code>x-api-version</code>) se detecta sola al probar la conexión
                            @if($veeamCfg['api_version'])
                                — actualmente <code>{{ $veeamCfg['api_version'] }}</code>.
                            @else
                                y queda guardada.
                            @endif
                            Puedes probar sin guardar: el test usa lo que esté escrito en el formulario.
                        </span>
                    </div>

                    @error('veeam_url')  <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror
                    @error('veeam_user') <div class="text-danger mt-2" style="font-size:.8rem">{{ $message }}</div> @enderror

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestVeeam">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                        </button>
                    </div>

                    <div id="veeamTestResult" class="mt-2" style="font-size:.83rem;display:none"></div>
                </form>

            </div>{{-- /pane-veeam --}}

        </div>{{-- /tab-content --}}
        </div>{{-- /col contenido --}}

        </div>{{-- /row --}}
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

/* ─────────────────────────────────────────────────────────────────────────
   Estado real de cada conexión.

   El badge "ON" anterior solo decía que había datos guardados, no que la
   conexión funcionara. Ahora cada una se prueba al entrar: el punto de color
   del menú resume el resultado y el detalle queda dentro de su pestaña.

   Las pruebas van en serie a propósito. Comparten la sesión de PHP, que se
   bloquea por request, así que lanzarlas en paralelo no las aceleraría y solo
   haría más difícil ver cuál está corriendo.
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    var CSRF    = '{{ csrf_token() }}';
    var tabs    = Array.prototype.slice.call(document.querySelectorAll('[data-cfg-test]'));
    var resumen = document.getElementById('cfgResumen');

    function pintar(tab, estado, mensaje) {
        // El title va en el punto y no en el botón: puesto en el botón pisa su
        // nombre accesible y el lector de pantalla anuncia el mensaje de error
        // en lugar de la conexión.
        var dot = tab.querySelector('.cfg-dot');
        if (dot) {
            dot.setAttribute('data-estado', estado);
            dot.setAttribute('title', mensaje || '');
        }

        var panel = document.getElementById(tab.getAttribute('data-cfg-panel'));
        if (!panel) return;

        var clase = estado === 'ok' ? 'ok' : (estado === 'error' ? 'error' : 'info');
        var icono = estado === 'ok'    ? 'bi-check-circle-fill'
                  : estado === 'error' ? 'bi-exclamation-triangle-fill'
                  : estado === 'probando' ? 'bi-hourglass-split'
                  : 'bi-dash-circle';

        panel.className = 'cfg-estado-pane ' + clase;
        panel.innerHTML = '<i class="bi ' + icono + '" style="margin-top:.15rem"></i><span></span>';
        panel.querySelector('span').textContent = mensaje;
        panel.style.display = 'flex';
    }

    /** Prueba una conexión. Devuelve true/false, o null si no está configurada. */
    function probar(tab, cuerpo) {
        if (tab.getAttribute('data-cfg-lista') !== '1' && !cuerpo) {
            pintar(tab, 'sin', 'Sin configurar.');
            return Promise.resolve(null);
        }

        pintar(tab, 'probando', 'Probando conexión…');

        return fetch(tab.getAttribute('data-cfg-test'), {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body:    JSON.stringify(cuerpo || {})
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                pintar(tab, d.ok ? 'ok' : 'error', d.message);
                return !!d.ok;
            })
            .catch(function () {
                pintar(tab, 'error', 'No hubo respuesta del servidor al probar.');
                return false;
            });
    }

    function probarTodas() {
        var ok = 0, fallidas = 0, configuradas = 0;
        resumen.textContent = 'Comprobando conexiones…';

        return tabs.reduce(function (cadena, tab) {
            return cadena.then(function () {
                if (tab.getAttribute('data-cfg-lista') === '1') configuradas++;
                return probar(tab).then(function (r) {
                    if (r === true)  ok++;
                    if (r === false) fallidas++;
                });
            });
        }, Promise.resolve()).then(function () {
            if (!configuradas) {
                resumen.textContent = 'Ninguna conexión configurada.';
            } else if (fallidas) {
                resumen.innerHTML = '<span class="text-danger fw-semibold">' + fallidas +
                                    ' con problemas</span> de ' + configuradas;
            } else {
                resumen.innerHTML = '<span class="text-success fw-semibold">' + ok +
                                    ' de ' + configuradas + ' conectadas</span>';
            }
        });
    }

    document.getElementById('btnProbarTodas')?.addEventListener('click', probarTodas);
    probarTodas();

    /* Botones "Probar conexión" de cada pestaña.

       Los que declaran campos mandan lo escrito en el formulario, para poder
       probar antes de guardar; el resto usa lo que ya está guardado. En ambos
       casos se actualiza el semáforo, para que el menú no quede diciendo algo
       distinto de lo que muestra la pestaña. */
    var BOTONES = [
        ['btnTestLdap',    'ldapTestResult',    'tab-ldap',    null],
        ['btnTestLdap2',   'ldap2TestResult',   'tab-ldap2',   null],
        ['btnTestLdap3',   'ldap3TestResult',   'tab-ldap3',   null],
        ['btnTestGlpi',    'glpiTestResult',    'tab-glpi',    { glpi_db_host: 'host', glpi_db_port: 'port', glpi_db_database: 'database', glpi_db_username: 'username', glpi_db_password: 'password' }],
        ['btnTestGlpiuni', 'glpiuniTestResult', 'tab-glpiuni', { glpiuni_db_host: 'host', glpiuni_db_port: 'port', glpiuni_db_database: 'database', glpiuni_db_username: 'username', glpiuni_db_password: 'password' }],
        ['btnTestAzure',   'azureTestResult',   'tab-azure',   null],
        ['btnTestCheckmk', 'checkmkTestResult', 'tab-checkmk', null],
        ['btnTestVeeam',   'veeamTestResult',   'tab-veeam',   { veeam_url: 'url', veeam_user: 'user', veeam_password: 'password' }]
    ];

    BOTONES.forEach(function (cfg) {
        var btn = document.getElementById(cfg[0]);
        var tab = document.getElementById(cfg[2]);
        if (!btn || !tab) return;

        btn.addEventListener('click', function () {
            var result = document.getElementById(cfg[1]);
            var campos = cfg[3];
            var form   = btn.closest('form');
            var cuerpo = null;

            if (campos && form) {
                cuerpo = {};
                Object.keys(campos).forEach(function (name) {
                    var input = form.querySelector('[name=' + name + ']');
                    if (input) cuerpo[campos[name]] = input.value;
                });
            }

            btn.disabled  = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Probando…';
            if (result) result.style.display = 'none';

            probar(tab, cuerpo)
                .then(function (r) {
                    if (!result) return;
                    var panel = document.getElementById(tab.getAttribute('data-cfg-panel'));
                    var msg   = panel ? panel.querySelector('span').textContent : '';
                    result.textContent   = (r ? '✓ ' : '✗ ') + msg;
                    result.className     = 'mt-2 ' + (r ? 'text-success' : 'text-danger');
                    result.style.display = 'block';
                })
                .finally(function () {
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="bi bi-plug me-1"></i>Probar conexión';
                });
        });
    });
})();
</script>
@endpush
