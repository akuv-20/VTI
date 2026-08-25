@extends('layouts.app')

@section('content')
<style>
    .exc-regla   { font-family:monospace; font-size:.82rem; }
    .exc-motivo  { font-size:.78rem; color:#64748b; }
    .exc-alcance { font-weight:700; font-variant-numeric:tabular-nums; }
    .exc-ayuda   { background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px;
                   padding:.85rem 1rem; font-size:.82rem; margin-bottom:1rem; }
    #previewBox  { border:1px solid #e2e8f0; border-radius:8px; padding:.7rem .85rem;
                   background:#f8fafc; font-size:.8rem; max-height:210px; overflow-y:auto; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-shield-slash me-2" style="color:{{ $dom->color() }}"></i>Excepciones de antivirus</span>
            @include('inventario._dominio', ['seccion' => 'excepciones'])
        </h4>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="abrirModal()">
                <i class="bi bi-plus-lg me-1"></i>Nueva excepción
            </button>
            <a href="{{ route("inventario.{$dom->clave}.equipos") }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-display-fill me-1"></i>Equipos
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

    <div class="exc-ayuda">
        <i class="bi bi-info-circle-fill me-1" style="color:#0284c7"></i>
        Los equipos que cumplan alguna de estas reglas <strong>dejan de contar</strong> en el indicador
        «Sin {{ $dom->antivirus() }}», pero no desaparecen: quedan agrupados en el indicador
        <strong>Exceptuados</strong> del listado, para poder revisarlos.
        @if($totalActivas > 0)
            Hoy hay <strong>{{ $totalActivas }}</strong> equipo(s) exceptuado(s) en {{ $dom->label() }}.
        @endif
    </div>

    @if($reglas->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-shield-slash" style="font-size:2.5rem;opacity:.35"></i>
            <p class="mt-2 mb-0">No hay excepciones definidas.</p>
            <p style="font-size:.85rem">Por ejemplo: <em>Sistema operativo contiene «macOS»</em>.</p>
        </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.86rem">
                <thead class="table-light">
                    <tr>
                        <th>Regla</th>
                        <th>Motivo</th>
                        <th class="text-center">Alcance</th>
                        <th class="text-center">Ámbito</th>
                        <th class="text-center">Activa</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reglas as $r)
                    <tr class="{{ $r->activa ? '' : 'opacity-50' }}">
                        <td class="exc-regla">{{ $r->resumen }}</td>
                        <td class="exc-motivo">{{ $r->motivo }}</td>
                        <td class="text-center">
                            <span class="exc-alcance" style="color:{{ ($alcance[$r->id] ?? 0) > 0 ? '#64748b' : '#cbd5e1' }}">
                                {{ number_format($alcance[$r->id] ?? 0) }}
                            </span>
                            <div class="text-muted" style="font-size:.68rem">equipos</div>
                        </td>
                        <td class="text-center">
                            @if($r->dominio === null)
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:.68rem">
                                    todos los dominios
                                </span>
                            @else
                                <span class="badge" style="font-size:.68rem;background:{{ $dom->color() }}1a;color:{{ $dom->color() }}">
                                    {{ $dom->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route("inventario.{$dom->clave}.excepciones.toggle", $r) }}" class="d-inline">
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
                            <form method="POST" action="{{ route("inventario.{$dom->clave}.excepciones.destroy", $r) }}"
                                  class="d-inline" onsubmit="return confirm('¿Eliminar esta excepción?')">
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
<div class="modal fade" id="modalExc" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="formExc" action="{{ route("inventario.{$dom->clave}.excepciones.store") }}">
            @csrf
            <input type="hidden" name="_method" id="exc-method" value="POST">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="exc-titulo">Nueva excepción</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:.8rem">Campo *</label>
                            <select name="campo" id="exc-campo" class="form-select form-select-sm" onchange="previsualizar()">
                                @foreach(\App\Models\InventarioExcepcion::CAMPOS as $k => $lbl)
                                    <option value="{{ $k }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.8rem">Operador *</label>
                            <select name="operador" id="exc-operador" class="form-select form-select-sm" onchange="previsualizar()">
                                @foreach(\App\Models\InventarioExcepcion::OPERADORES as $k => $lbl)
                                    <option value="{{ $k }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold" style="font-size:.8rem">Texto *</label>
                            <input type="text" name="valor" id="exc-valor" class="form-control form-control-sm"
                                   maxlength="200" placeholder="macOS" oninput="previsualizar()">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.8rem">
                                Motivo * <span class="text-muted fw-normal">(por qué estos equipos no llevan {{ $dom->antivirus() }})</span>
                            </label>
                            <input type="text" name="motivo" id="exc-motivo" class="form-control form-control-sm"
                                   maxlength="300" placeholder="macOS no usa el antivirus corporativo">
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="todos_los_dominios"
                                       id="exc-todos" value="1">
                                <label class="form-check-label" for="exc-todos" style="font-size:.8rem">
                                    Aplicar a todos los dominios
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activa"
                                       id="exc-activa" value="1" checked>
                                <label class="form-check-label" for="exc-activa" style="font-size:.8rem">Activa</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.8rem">
                                <i class="bi bi-eye me-1"></i>Vista previa
                            </label>
                            <div id="previewBox">
                                <span class="text-muted">Escribe un texto para ver a qué equipos afectaría.</span>
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
    const URL_STORE   = @json(route("inventario.{$dom->clave}.excepciones.store"));
    const URL_UPDATE  = @json(route("inventario.{$dom->clave}.excepciones.update", ['excepcion' => '__ID__']));
    const URL_PREVIEW = @json(route("inventario.{$dom->clave}.excepciones.preview"));
    const CSRF        = '{{ csrf_token() }}';
    let modalExc, previewTimer;

    function abrirModal(regla = null) {
        const form = document.getElementById('formExc');
        form.reset();

        if (regla) {
            document.getElementById('exc-titulo').textContent = 'Editar excepción';
            document.getElementById('exc-method').value = 'PUT';
            form.action = URL_UPDATE.replace('__ID__', regla.id);

            document.getElementById('exc-campo').value    = regla.campo;
            document.getElementById('exc-operador').value = regla.operador;
            document.getElementById('exc-valor').value    = regla.valor;
            document.getElementById('exc-motivo').value   = regla.motivo;
            document.getElementById('exc-todos').checked  = regla.dominio === null;
            document.getElementById('exc-activa').checked = !!regla.activa;
        } else {
            document.getElementById('exc-titulo').textContent = 'Nueva excepción';
            document.getElementById('exc-method').value = 'POST';
            form.action = URL_STORE;
            document.getElementById('exc-activa').checked = true;
        }

        previsualizar();
        modalExc = modalExc || new bootstrap.Modal(document.getElementById('modalExc'));
        modalExc.show();
    }

    /* La vista previa es lo que evita el clásico «contiene mac» que también
       atrapa MACARENA-PC. Se consulta con un respiro para no pegarle al
       servidor en cada tecla. */
    function previsualizar() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            const valor = document.getElementById('exc-valor').value.trim();
            const box   = document.getElementById('previewBox');

            if (!valor) {
                box.innerHTML = '<span class="text-muted">Escribe un texto para ver a qué equipos afectaría.</span>';
                return;
            }

            box.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Calculando…</span>';

            fetch(URL_PREVIEW, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    campo:    document.getElementById('exc-campo').value,
                    operador: document.getElementById('exc-operador').value,
                    valor:    valor
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.total === 0) {
                    box.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>' +
                                    'Ningún equipo sin {{ $dom->antivirus() }} coincide con esta regla.</span>';
                    return;
                }
                let html = '<div class="fw-semibold mb-2">Exceptuaría <span class="text-primary">' + d.total +
                           '</span> equipo(s) que hoy figuran sin {{ $dom->antivirus() }}:</div>';
                html += '<ul class="mb-0 ps-3">';
                d.muestra.forEach(function (e) {
                    html += '<li><span class="font-monospace">' + e.equipo + '</span>' +
                            (e.so ? ' <span class="text-muted">— ' + e.so + '</span>' : '') + '</li>';
                });
                html += '</ul>';
                if (d.total > d.muestra.length) {
                    html += '<div class="text-muted mt-1">…y ' + (d.total - d.muestra.length) + ' más.</div>';
                }
                box.innerHTML = html;
            })
            .catch(() => { box.innerHTML = '<span class="text-danger">No se pudo calcular la vista previa.</span>'; });
        }, 350);
    }
</script>
@endsection
