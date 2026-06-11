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

{{-- Módulos agrupados por categoría --}}
@php
    $iconosGrupo = [
        'Facturación'      => 'bi-receipt-cutoff',
        'Telefonía'        => 'bi-phone',
        'Inventario TI'    => 'bi-pc-display',
        'Active Directory' => 'bi-diagram-3-fill',
    ];
    $modulosPorGrupo = $modulos->groupBy('grupo');
@endphp

<div class="card" id="card-modulos">
    <div class="card-header fw-bold"><i class="bi bi-grid-fill me-1"></i> Permisos por módulo</div>
    <div class="card-body" id="modulos-body">
        <p class="text-muted small mb-3">
            Selecciona los módulos a los que tendrá acceso este usuario.
            Los administradores tienen acceso a todo independientemente de esta selección.
        </p>

        <div class="row g-3">
            @foreach($modulosPorGrupo as $grupo => $mods)
            <div class="col-lg-6">
                <div class="border rounded-3 h-100" data-permiso-grupo>
                    {{-- Cabecera del grupo con "seleccionar todo" --}}
                    <div class="d-flex align-items-center gap-2 px-3 py-2"
                         style="background:#f8fafc;border-bottom:1px solid #e2e8f0;border-radius:.5rem .5rem 0 0">
                        <div class="form-check mb-0">
                            <input class="form-check-input grupo-toggle" type="checkbox"
                                   id="grupo_{{ Str::slug($grupo) }}">
                            <label class="form-check-label fw-bold" for="grupo_{{ Str::slug($grupo) }}"
                                   style="font-size:.88rem">
                                <i class="bi {{ $iconosGrupo[$grupo] ?? 'bi-folder' }} me-1 text-primary"></i>{{ $grupo ?: 'Otros' }}
                            </label>
                        </div>
                        <span class="badge bg-light text-secondary border ms-auto" style="font-size:.68rem"
                              data-grupo-count>0/{{ $mods->count() }}</span>
                    </div>
                    {{-- Items del grupo --}}
                    <div class="px-3 py-2">
                        @foreach($mods as $mod)
                        <div class="form-check py-1" style="border-bottom:1px dashed #f1f5f9">
                            <input class="form-check-input modulo-chk" type="checkbox"
                                   name="modulos[]" value="{{ $mod->id }}"
                                   id="mod_{{ $mod->id }}"
                                   {{ in_array($mod->id, old('modulos', $asignados ?? [])) ? 'checked' : '' }}>
                            <label class="form-check-label d-block" for="mod_{{ $mod->id }}">
                                <span class="fw-semibold" style="font-size:.85rem">{{ $mod->label }}</span>
                                <small class="text-muted d-block" style="font-size:.74rem">{{ $mod->descripcion }}</small>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- JS: grupos + admin --}}
<script>
(function() {
    const chkAdmin = document.getElementById('es_admin');
    const cardMods = document.getElementById('card-modulos');

    // ── Checkbox de grupo: marca/desmarca todos sus hijos ──────────────
    document.querySelectorAll('[data-permiso-grupo]').forEach(grupo => {
        const master = grupo.querySelector('.grupo-toggle');
        const hijos  = grupo.querySelectorAll('.modulo-chk');
        const badge  = grupo.querySelector('[data-grupo-count]');

        function sincronizar() {
            const marcados = [...hijos].filter(c => c.checked).length;
            master.checked       = marcados === hijos.length && hijos.length > 0;
            master.indeterminate = marcados > 0 && marcados < hijos.length;
            badge.textContent    = marcados + '/' + hijos.length;
            badge.className      = marcados > 0
                ? 'badge bg-primary-subtle text-primary border border-primary-subtle ms-auto'
                : 'badge bg-light text-secondary border ms-auto';
            badge.style.fontSize = '.68rem';
        }

        master.addEventListener('change', () => {
            hijos.forEach(c => { if (!c.disabled) c.checked = master.checked; });
            sincronizar();
        });
        hijos.forEach(c => c.addEventListener('change', sincronizar));
        sincronizar();
    });

    // ── Admin: deshabilita la selección manual ──────────────────────────
    function toggleAdmin() {
        cardMods.style.opacity = chkAdmin.checked ? '0.4' : '1';
        cardMods.querySelectorAll('input[type=checkbox]').forEach(cb => cb.disabled = chkAdmin.checked);
    }
    chkAdmin.addEventListener('change', toggleAdmin);
    toggleAdmin();
})();
</script>
