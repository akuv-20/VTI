{{-- Datos básicos --}}
<div class="card mb-3">
    <div class="card-header fw-bold"><i class="bi bi-person-fill me-1"></i> Datos del usuario</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $usuario->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $usuario->email ?? '') }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Contraseña {{ isset($usuario) ? '(dejar vacío para no cambiar)' : '' }}
                    @if(!isset($usuario))<span class="text-danger">*</span>@endif
                </label>
                <input type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       {{ !isset($usuario) ? 'required' : '' }}
                       placeholder="{{ isset($usuario) ? 'Nueva contraseña…' : 'Contraseña…' }}">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="form-control"
                       placeholder="Repetir contraseña…">
            </div>
        </div>
    </div>
</div>

{{-- Permisos --}}
<div class="card mb-3">
    <div class="card-header fw-bold"><i class="bi bi-shield-lock-fill me-1"></i> Permisos y estado</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="es_admin" name="es_admin"
                           value="1" {{ old('es_admin', $usuario->es_admin ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="es_admin">
                        Administrador <span class="badge bg-danger ms-1">Admin</span>
                    </label>
                    <div class="form-text">Acceso total a todos los módulos.</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo"
                           value="1" {{ old('activo', $usuario->activo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="activo">Usuario Activo</label>
                    <div class="form-text">Los usuarios inactivos no pueden iniciar sesión.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Módulos --}}
<div class="card" id="card-modulos">
    <div class="card-header fw-bold"><i class="bi bi-grid-fill me-1"></i> Módulos asignados</div>
    <div class="card-body" id="modulos-body">
        <p class="text-muted small mb-2">
            Selecciona los módulos a los que tendrá acceso este usuario.
            Los administradores tienen acceso a todo independientemente de esta selección.
        </p>
        <div class="row g-2">
            @foreach($modulos as $mod)
            <div class="col-md-4 col-sm-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="modulos[]" value="{{ $mod->id }}"
                           id="mod_{{ $mod->id }}"
                           {{ in_array($mod->id, old('modulos', $asignados ?? [])) ? 'checked' : '' }}>
                    <label class="form-check-label" for="mod_{{ $mod->id }}">
                        <strong>{{ $mod->label }}</strong>
                        <br><small class="text-muted">{{ $mod->descripcion }}</small>
                    </label>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- JS: ocultar módulos si es admin --}}
<script>
(function() {
    const chkAdmin  = document.getElementById('es_admin');
    const cardMods  = document.getElementById('card-modulos');

    function toggle() {
        cardMods.style.opacity = chkAdmin.checked ? '0.4' : '1';
        cardMods.querySelectorAll('input[type=checkbox]').forEach(cb => cb.disabled = chkAdmin.checked);
    }

    chkAdmin.addEventListener('change', toggle);
    toggle();
})();
</script>
