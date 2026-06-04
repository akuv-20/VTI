@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    @php
        $sam    = $usuario->getFirstAttribute('samaccountname');
        $nombre = $usuario->getFirstAttribute('cn') ?: $sam;
    @endphp

    <div class="vti-page-header">
        <h4><i class="bi bi-person-gear me-2"></i>Editar usuario AD — Grupo Verfrut (Perú)
            <span class="text-muted fw-normal">— {{ $nombre }}</span>
        </h4>
        <a href="{{ $returnUrl }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">

                <div class="d-flex align-items-center gap-3 mb-4 pb-3" style="border-bottom:1px solid #f1f5f9">
                    @php
                        $iniciales = collect(explode(' ', $nombre))->take(2)->map(fn($p)=>strtoupper(substr($p,0,1)))->join('');
                        $enabled   = !(((int)$usuario->getFirstAttribute('useraccountcontrol')) & 2);
                    @endphp
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                         style="width:52px;height:52px;font-size:1.1rem;background:{{ $enabled ? '#2563eb' : '#94a3b8' }}">
                        {{ $iniciales }}
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:1rem">{{ $nombre }}</div>
                        <div class="font-monospace text-muted" style="font-size:.82rem">{{ $sam }}</div>
                        <div class="mt-1">
                            @if($enabled)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i>Habilitado
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-x-circle-fill me-1"></i>Deshabilitado
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="ms-auto">
                        <form action="{{ route('admin.active_directory2.toggle', $sam) }}" method="POST"
                              data-confirm="{{ $nombre }}"
                              data-confirm-verb="{{ $enabled ? 'deshabilitar' : 'habilitar' }}"
                              data-confirm-title="{{ $enabled ? 'Deshabilitar cuenta' : 'Habilitar cuenta' }}"
                              data-confirm-sub="El cambio se aplica inmediatamente en Active Directory."
                              data-confirm-btn="{{ $enabled ? 'Sí, deshabilitar' : 'Sí, habilitar' }}"
                              data-confirm-icon="{{ $enabled ? 'bi-person-x-fill' : 'bi-person-check-fill' }}"
                              data-confirm-color="{{ $enabled ? 'warning' : 'success' }}">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $enabled ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                <i class="bi {{ $enabled ? 'bi-person-x' : 'bi-person-check' }} me-1"></i>
                                {{ $enabled ? 'Deshabilitar' : 'Habilitar' }}
                            </button>
                        </form>
                    </div>
                </div>

                <form action="{{ route('admin.active_directory2.update', $sam) }}" method="POST">
                    @csrf @method('PUT')
                    <input type="hidden" name="_return" value="{{ $returnUrl }}">

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="givenname"
                                   class="form-control @error('givenname') is-invalid @enderror"
                                   value="{{ old('givenname', $usuario->getFirstAttribute('givenname')) }}"
                                   placeholder="Felipe">
                            @error('givenname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Apellido(s) <span class="text-danger">*</span></label>
                            <input type="text" name="sn"
                                   class="form-control @error('sn') is-invalid @enderror"
                                   value="{{ old('sn', $usuario->getFirstAttribute('sn')) }}"
                                   placeholder="Henriquez Olguín">
                            @error('sn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo electrónico</label>
                        <input type="email" name="mail"
                               class="form-control @error('mail') is-invalid @enderror"
                               value="{{ old('mail', $usuario->getFirstAttribute('mail')) }}"
                               placeholder="usuario@dominio.com">
                        @error('mail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono fijo</label>
                            <input type="text" name="telephonenumber" class="form-control"
                                   value="{{ old('telephonenumber', $usuario->getFirstAttribute('telephonenumber')) }}"
                                   placeholder="+51 1 1234 5678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Móvil</label>
                            <input type="text" name="mobile" class="form-control"
                                   value="{{ old('mobile', $usuario->getFirstAttribute('mobile')) }}"
                                   placeholder="+51 9 1234 5678">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Área / Departamento</label>
                            <input type="text" name="department" class="form-control"
                                   value="{{ old('department', $usuario->getFirstAttribute('department')) }}"
                                   placeholder="Tecnología">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cargo</label>
                            <input type="text" name="title" class="form-control"
                                   value="{{ old('title', $usuario->getFirstAttribute('title')) }}"
                                   placeholder="Administrador TI">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Notas sobre el usuario">{{ old('description', $usuario->getFirstAttribute('description')) }}</textarea>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ $returnUrl }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-floppy-fill me-1"></i>Guardar cambios
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <div class="card border-0 shadow-sm border-warning-subtle">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1 text-warning-emphasis">
                    <i class="bi bi-key-fill me-1"></i>Restablecer contraseña
                </h6>
                <p class="text-muted small mb-3">
                    Requiere conexión <strong>LDAPS (puerto 636)</strong>. Con LDAP estándar (389) esta operación será rechazada por AD.
                </p>
                <form action="{{ route('admin.active_directory2.reset-password', $sam) }}" method="POST">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold" style="font-size:.85rem">Nueva contraseña</label>
                            <input type="password" name="nueva_password"
                                   class="form-control form-control-sm @error('nueva_password') is-invalid @enderror"
                                   autocomplete="new-password">
                            @error('nueva_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold" style="font-size:.85rem">Confirmar contraseña</label>
                            <input type="password" name="nueva_password_confirmation"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning btn-sm w-100"
                                    onclick="return confirm('¿Resetear la contraseña de {{ $nombre }}?')">
                                <i class="bi bi-key me-1"></i>Resetear
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
    </div>

</div>
@endsection
