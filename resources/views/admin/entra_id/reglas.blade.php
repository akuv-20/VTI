@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-sliders me-2" style="color:#0078d4"></i>Reglas de calidad</h4>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="abrirModal()">
                <i class="bi bi-plus-lg me-1"></i>Nueva regla
            </button>
            <a href="{{ route('admin.entra_id.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-heart-pulse me-1"></i>Salud de datos
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <p class="text-muted mb-3" style="font-size:.84rem">
        Las reglas activas se evalúan en <a href="{{ route('admin.entra_id.dashboard') }}">Salud de datos</a>
        y determinan la puntuación de cada indicador.
    </p>

    @if($reglas->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-sliders" style="font-size:2.5rem;opacity:.35"></i>
            <p class="mt-2">Aún no hay reglas configuradas.</p>
        </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px" class="text-center">Orden</th>
                        <th>Regla</th>
                        <th>Tipo</th>
                        <th>Campo</th>
                        <th>Configuración</th>
                        <th class="text-center">Severidad</th>
                        <th class="text-center">Activa</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reglas as $r)
                    <tr class="{{ $r->activa ? '' : 'opacity-50' }}">
                        <td class="text-center text-muted">{{ $r->orden }}</td>
                        <td>
                            <div class="fw-semibold">{{ $r->etiqueta }}</div>
                            @if($r->descripcion)
                                <div class="text-muted" style="font-size:.76rem">{{ $r->descripcion }}</div>
                            @endif
                            @if($r->solo_habilitados)
                                <div class="text-muted" style="font-size:.7rem">
                                    <i class="bi bi-funnel"></i> solo habilitadas
                                </div>
                            @endif
                        </td>
                        <td style="font-size:.8rem">{{ $r->tipo_etiqueta }}</td>
                        <td>
                            @if($r->campo)
                                <code style="font-size:.75rem">{{ $r->campo }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="font-size:.76rem" class="text-muted">
                            @php $cfg = $r->config ?? []; @endphp
                            @if($r->tipo === 'valores_permitidos')
                                {{ collect($cfg['valores'] ?? [])->join(', ') }}
                            @elseif($r->tipo === 'sin_duplicados')
                                {{ collect($cfg['campos'] ?? [])->join(' + ') }}
                            @elseif($r->tipo === 'actividad_reciente')
                                {{ $cfg['dias'] ?? 90 }} días
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $r->severidad === 'error' ? 'bg-danger' : 'bg-warning text-dark' }}"
                                  style="font-size:.66rem">
                                {{ $r->severidad === 'error' ? 'ERROR' : 'AVISO' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('admin.entra_id.reglas.toggle', $r) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm py-0 px-1 border-0 bg-transparent" title="{{ $r->activa ? 'Desactivar' : 'Activar' }}">
                                    <i class="bi {{ $r->activa ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' }}"
                                       style="font-size:1.3rem"></i>
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-outline-secondary btn-sm py-0 px-1"
                                    onclick='abrirModal(@json($r))' title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.entra_id.reglas.destroy', $r) }}"
                                  class="d-inline" onsubmit="return confirm('¿Eliminar la regla «{{ $r->etiqueta }}»?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- ── Modal crear / editar ─────────────────────────────────────────────── --}}
<div class="modal fade" id="modalRegla" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="formRegla" action="{{ route('admin.entra_id.reglas.store') }}">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="modal-titulo">Nueva regla</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:.8rem">Tipo de regla *</label>
                            <select name="tipo" id="f-tipo" class="form-select form-select-sm" required
                                    onchange="ajustarCampos()">
                                @foreach(\App\Models\EntraRegla::TIPOS as $k => $t)
                                    <option value="{{ $k }}">{{ $t['etiqueta'] }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="ayuda-tipo" style="font-size:.73rem"></div>
                        </div>

                        <div class="col-md-6" id="wrap-campo">
                            <label class="form-label" style="font-size:.8rem">Campo evaluado *</label>
                            <select name="campo" id="f-campo" class="form-select form-select-sm">
                                <option value="">— Selecciona —</option>
                                @foreach(\App\Models\EntraRegla::CAMPOS as $k => $lbl)
                                    <option value="{{ $k }}">{{ $lbl }} ({{ $k }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" style="font-size:.8rem">Nombre de la regla *</label>
                            <input type="text" name="etiqueta" id="f-etiqueta" class="form-control form-control-sm"
                                   maxlength="150" required placeholder="Ej: País con código válido">
                        </div>

                        <div class="col-12">
                            <label class="form-label" style="font-size:.8rem">Descripción</label>
                            <textarea name="descripcion" id="f-descripcion" class="form-control form-control-sm"
                                      rows="2" maxlength="500" placeholder="Qué valida y por qué"></textarea>
                        </div>

                        {{-- Config: valores permitidos --}}
                        <div class="col-12 cfg cfg-valores_permitidos">
                            <label class="form-label" style="font-size:.8rem">Valores permitidos *</label>
                            <textarea name="valores" id="f-valores" class="form-control form-control-sm"
                                      rows="3" placeholder="CL, PE, AR, US, ES"></textarea>
                            <div class="form-text" style="font-size:.73rem">
                                Separa con comas o saltos de línea. La comparación ignora mayúsculas y tildes.
                            </div>
                        </div>

                        {{-- Config: duplicados --}}
                        <div class="col-12 cfg cfg-sin_duplicados">
                            <label class="form-label" style="font-size:.8rem">Campos que forman el duplicado *</label>
                            <div class="row g-1">
                                @foreach(\App\Models\EntraRegla::CAMPOS as $k => $lbl)
                                <div class="col-6 col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input f-campos-dup" type="checkbox"
                                               name="campos_dup[]" value="{{ $k }}" id="dup-{{ $k }}">
                                        <label class="form-check-label" for="dup-{{ $k }}" style="font-size:.76rem">
                                            {{ $lbl }}
                                        </label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="form-text" style="font-size:.73rem">
                                Se marcan como duplicadas las cuentas que coincidan en todos los campos elegidos.
                            </div>
                        </div>

                        {{-- Config: actividad --}}
                        <div class="col-md-4 cfg cfg-actividad_reciente">
                            <label class="form-label" style="font-size:.8rem">Días sin actividad *</label>
                            <input type="number" name="dias" id="f-dias" class="form-control form-control-sm"
                                   min="1" max="3650" value="90">
                            <div class="form-text" style="font-size:.73rem">Requiere AuditLog.Read.All</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.8rem">Severidad *</label>
                            <select name="severidad" id="f-severidad" class="form-select form-select-sm">
                                <option value="error">Error</option>
                                <option value="advertencia">Advertencia</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.8rem">Orden</label>
                            <input type="number" name="orden" id="f-orden" class="form-control form-control-sm"
                                   min="0" max="9999" value="0">
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_habilitados"
                                       id="f-solo-hab" value="1" checked>
                                <label class="form-check-label" for="f-solo-hab" style="font-size:.8rem">
                                    Solo cuentas habilitadas
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const TIPOS  = @json(\App\Models\EntraRegla::TIPOS);
    const URL_ST = @json(route('admin.entra_id.reglas.store'));
    const URL_UP = @json(route('admin.entra_id.reglas.update', ['regla' => '__ID__']));
    let modal;

    function ajustarCampos() {
        const tipo = document.getElementById('f-tipo').value;
        const info = TIPOS[tipo] || {};

        document.getElementById('ayuda-tipo').textContent = info.ayuda || '';

        // Campo evaluado solo si el tipo lo requiere
        const wrapCampo = document.getElementById('wrap-campo');
        wrapCampo.style.display = info.requiere_campo ? '' : 'none';
        document.getElementById('f-campo').required = !!info.requiere_campo;

        // Bloques de configuración específicos
        document.querySelectorAll('.cfg').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.cfg-' + tipo).forEach(el => el.style.display = '');
    }

    function abrirModal(regla = null) {
        const form = document.getElementById('formRegla');
        form.reset();
        document.querySelectorAll('.f-campos-dup').forEach(c => c.checked = false);

        if (regla) {
            document.getElementById('modal-titulo').textContent = 'Editar regla';
            document.getElementById('form-method').value = 'PUT';
            form.action = URL_UP.replace('__ID__', regla.id);

            document.getElementById('f-tipo').value        = regla.tipo;
            document.getElementById('f-campo').value       = regla.campo || '';
            document.getElementById('f-etiqueta').value    = regla.etiqueta;
            document.getElementById('f-descripcion').value = regla.descripcion || '';
            document.getElementById('f-severidad').value   = regla.severidad;
            document.getElementById('f-orden').value       = regla.orden;
            document.getElementById('f-solo-hab').checked  = !!regla.solo_habilitados;

            const cfg = regla.config || {};
            if (cfg.valores) document.getElementById('f-valores').value = cfg.valores.join(', ');
            if (cfg.dias)    document.getElementById('f-dias').value    = cfg.dias;
            if (cfg.campos)  cfg.campos.forEach(c => {
                const chk = document.getElementById('dup-' + c);
                if (chk) chk.checked = true;
            });
        } else {
            document.getElementById('modal-titulo').textContent = 'Nueva regla';
            document.getElementById('form-method').value = 'POST';
            form.action = URL_ST;
            document.getElementById('f-solo-hab').checked = true;
            document.getElementById('f-dias').value = 90;
        }

        ajustarCampos();
        modal = modal || new bootstrap.Modal(document.getElementById('modalRegla'));
        modal.show();
    }

    ajustarCampos();
</script>
@endsection
