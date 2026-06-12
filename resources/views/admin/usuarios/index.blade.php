@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('admin.usuarios.index') }}">
                <div class="vti-search">
                    <input type="text" name="buscar" class="form-control form-control-sm" style="width:260px"
                           placeholder="Buscar por nombre o email…" value="{{ $buscar }}">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    @if($buscar)
                        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportarEntra">
                <i class="bi bi-microsoft me-1"></i>Importar desde Entra ID
            </button>
            <a href="{{ route('admin.usuarios.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
            </a>
        </div>
    </div>

    {{-- Modal: importar usuario desde Entra ID --}}
    <div class="modal fade" id="modalImportarEntra" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header border-0 pb-2">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-microsoft me-2 text-primary"></i>Importar usuario desde Entra ID
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <div class="d-flex gap-2 align-items-center mb-3">
                        <input type="text" id="buscarEntraInput" class="form-control form-control-sm"
                               placeholder="Buscar por nombre o correo (mín. 3 caracteres)…" autocomplete="off">
                        <span id="spinnerEntra" class="text-muted" hidden>
                            <i class="bi bi-arrow-repeat" style="display:inline-block;animation:spin .6s linear infinite"></i>
                        </span>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="importarActivo" checked>
                        <label class="form-check-label small" for="importarActivo">
                            Importar como usuario <strong>activo</strong> (puede iniciar sesión de inmediato)
                        </label>
                    </div>
                    <div id="resultadosEntra"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th class="text-center">Admin</th>
                    <th class="text-center">Estado</th>
                    <th>Módulos asignados</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->name }}</td>
                    <td><span class="text-muted">{{ $usuario->email }}</span></td>
                    <td class="text-center">
                        @if($usuario->es_admin)
                            <span class="badge bg-danger">Admin</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($usuario->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td>
                        @if($usuario->es_admin)
                            <em class="text-muted small">Acceso total</em>
                        @else
                            @foreach($usuario->modulos->where('activo', true)->sortBy('orden') as $mod)
                                <span class="badge bg-primary me-1">{{ $mod->label }}</span>
                            @endforeach
                            @if($usuario->modulos->isEmpty())
                                <em class="text-muted small">Sin módulos</em>
                            @endif
                        @endif
                    </td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            @if($usuario->id !== auth()->id())
                            <form action="{{ route('admin.usuarios.destroy', $usuario) }}" method="POST"
                                  data-confirm="{{ $usuario->name }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="6">No hay usuarios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $usuarios->total() }} usuario(s)</span>
        {{ $usuarios->links() }}
    </div>

</div>
@endsection

@push('styles')
<style>
    @keyframes spin { to { transform: rotate(360deg); } }
    .entra-card {
        display: flex; align-items: center; gap: .75rem;
        padding: .6rem .85rem; border: 1px solid #e2e8f0; border-radius: 10px;
        margin-bottom: .45rem;
    }
    .entra-card.deshabilitado { opacity: .55; }
    .entra-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, #1e3a5f, #2563eb); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 700;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const buscarUrl   = @json(route('admin.usuarios.buscar_entra'));
    const importarUrl = @json(route('admin.usuarios.importar_entra'));
    const csrf        = document.querySelector('meta[name="csrf-token"]').content;

    const input      = document.getElementById('buscarEntraInput');
    const spinner    = document.getElementById('spinnerEntra');
    const resultados = document.getElementById('resultadosEntra');
    const chkActivo  = document.getElementById('importarActivo');
    let debounce;

    input?.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 3) { resultados.innerHTML = ''; return; }
        debounce = setTimeout(() => buscar(q), 380);
    });

    // Enfocar al abrir el modal
    document.getElementById('modalImportarEntra')?.addEventListener('shown.bs.modal', () => input.focus());

    async function buscar(q) {
        spinner.hidden = false;
        try {
            const res  = await fetch(buscarUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!data.ok) {
                resultados.innerHTML = `<div class="alert alert-danger py-2 small mb-0">${data.message}</div>`;
                return;
            }
            if (!data.usuarios.length) {
                resultados.innerHTML = '<p class="text-muted small mb-0">Sin resultados en Entra ID.</p>';
                return;
            }

            resultados.innerHTML = data.usuarios.map(u => {
                const iniciales = (u.nombre || '?').split(' ').slice(0, 2).map(p => p[0]?.toUpperCase() || '').join('');
                const sub = [u.cargo, u.departamento].filter(Boolean).join(' · ');
                let accion;
                if (u.existe) {
                    accion = '<span class="badge bg-secondary ms-auto">Ya existe</span>';
                } else if (!u.habilitado) {
                    accion = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-auto">Deshabilitado en Entra</span>';
                } else {
                    accion = `<button type="button" class="btn btn-sm btn-primary ms-auto" onclick="importarEntra('${u.id}', this)">
                                  <i class="bi bi-download me-1"></i>Importar
                              </button>`;
                }
                return `<div class="entra-card ${!u.habilitado ? 'deshabilitado' : ''}">
                    <span class="entra-avatar">${iniciales}</span>
                    <div style="min-width:0">
                        <div class="fw-semibold" style="font-size:.87rem">${u.nombre}</div>
                        <div class="text-muted text-truncate" style="font-size:.78rem">${u.email ?? '—'}${sub ? ' · ' + sub : ''}</div>
                    </div>
                    ${accion}
                </div>`;
            }).join('');
        } catch (e) {
            resultados.innerHTML = '<div class="alert alert-danger py-2 small mb-0">Error al consultar Entra ID.</div>';
        } finally {
            spinner.hidden = true;
        }
    }

    // Enviar formulario POST clásico (redirige a la ficha del usuario importado)
    window.importarEntra = function (id, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = importarUrl;
        form.innerHTML = `
            <input type="hidden" name="_token" value="${csrf}">
            <input type="hidden" name="entra_id" value="${id}">
            <input type="hidden" name="activo" value="${chkActivo.checked ? 1 : 0}">`;
        document.body.appendChild(form);
        form.submit();
    };
})();
</script>
@endpush
