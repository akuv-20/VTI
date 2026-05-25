@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-gear-fill me-2"></i>Configuración del Sistema</h4>
    </div>

    <div class="row g-4" style="max-width:700px">

        {{-- Nombre de la aplicación --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header fw-bold border-0" style="background:#f8fafc">
                    <i class="bi bi-fonts me-2 text-primary"></i>Nombre de la aplicación
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.configuracion.update') }}" method="POST" data-loader>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre que aparece en la barra de navegación y pantalla de login</label>
                            <input type="text" name="app_nombre" class="form-control @error('app_nombre') is-invalid @enderror"
                                   value="{{ old('app_nombre', $appNombre) }}"
                                   maxlength="60" placeholder="{{ config('app.name') }}">
                            <div class="form-text">Máximo 60 caracteres. Si se deja vacío se usa el nombre por defecto del sistema.</div>
                            @error('app_nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Guardar nombre
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Logo / Ícono de la aplicación --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header fw-bold border-0" style="background:#f8fafc">
                    <i class="bi bi-image-fill me-2 text-primary"></i>Logo / Ícono de la aplicación
                </div>
                <div class="card-body">

                    {{-- Preview actual --}}
                    <div class="mb-3">
                        @if($appLogo)
                            <div class="position-relative d-inline-block">
                                <img src="{{ Storage::url($appLogo) }}"
                                     alt="Logo actual"
                                     class="rounded-2 border"
                                     style="height:80px;object-fit:contain;background:#f8fafc;padding:8px">
                                <span class="badge bg-success position-absolute top-0 start-0 m-1" style="font-size:.7rem">
                                    <i class="bi bi-check-circle-fill me-1"></i>Logo actual
                                </span>
                            </div>
                        @else
                            <div class="rounded-2 d-inline-flex align-items-center justify-content-center"
                                 style="height:80px;width:80px;background:linear-gradient(135deg,#1e3a5f,#2563eb)">
                                <i class="bi bi-building-check" style="font-size:2rem;color:#fff"></i>
                            </div>
                            <div class="text-muted small mt-1">Sin logo — se usa el ícono por defecto</div>
                        @endif
                    </div>

                    {{-- Subir nuevo logo --}}
                    <form action="{{ route('admin.configuracion.update') }}" method="POST"
                          enctype="multipart/form-data" data-loader>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subir nuevo logo</label>
                            <input type="file" name="app_logo" class="form-control"
                                   accept="image/jpeg,image/png,image/webp,image/svg+xml">
                            <div class="form-text">JPG, PNG, WebP o SVG — máx. 2 MB. Se mostrará en la barra de navegación y en la pantalla de login.</div>
                            @error('app_logo')
                                <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Guardar logo
                        </button>
                    </form>

                    {{-- Eliminar logo --}}
                    @if($appLogo)
                    <form action="{{ route('admin.configuracion.update') }}" method="POST"
                          class="mt-2" data-confirm="el logo de la aplicación">
                        @csrf
                        <input type="hidden" name="eliminar_logo" value="1">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash3-fill me-1"></i>Quitar logo (usar ícono por defecto)
                        </button>
                    </form>
                    @endif

                </div>
            </div>
        </div>

        {{-- Fondo del login --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header fw-bold border-0" style="background:#f8fafc">
                    <i class="bi bi-image me-2 text-primary"></i>Fondo de pantalla del Login
                </div>
                <div class="card-body">

                    {{-- Preview actual --}}
                    <div class="mb-3">
                        @if($loginBg)
                            <div class="position-relative d-inline-block">
                                <img src="{{ Storage::url($loginBg) }}"
                                     alt="Fondo actual"
                                     class="rounded-2 border"
                                     style="height:160px;object-fit:cover;width:320px">
                                <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                    <i class="bi bi-check-circle-fill me-1"></i>Imagen actual
                                </span>
                            </div>
                        @else
                            <div class="rounded-2 d-flex align-items-center justify-content-center"
                                 style="height:160px;width:320px;background:linear-gradient(135deg,#1e3a5f,#2563eb)">
                                <div class="text-center text-white">
                                    <i class="bi bi-image" style="font-size:2rem;opacity:.5"></i>
                                    <div class="small mt-1 opacity-75">Sin imagen — fondo azul por defecto</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Subir nueva imagen --}}
                    <form action="{{ route('admin.configuracion.update') }}" method="POST"
                          enctype="multipart/form-data" data-loader>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subir nueva imagen de fondo</label>
                            <input type="file" name="login_background" class="form-control"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG o WebP — máx. 10 MB. Recomendado: 1920×1080 px.</div>
                            @error('login_background')
                                <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Guardar imagen
                        </button>
                    </form>

                    {{-- Eliminar imagen --}}
                    @if($loginBg)
                    <form action="{{ route('admin.configuracion.update') }}" method="POST"
                          class="mt-2" data-confirm="la imagen de fondo del login">
                        @csrf
                        <input type="hidden" name="eliminar_fondo" value="1">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash3-fill me-1"></i>Quitar imagen (usar fondo azul)
                        </button>
                    </form>
                    @endif

                </div>
            </div>
        </div>

    </div>

</div>
@endsection
