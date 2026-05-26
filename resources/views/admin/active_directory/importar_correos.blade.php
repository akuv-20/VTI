@extends('layouts.app')
@section('content')
<div class="container vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-envelope-arrow-up me-2" style="color:#6366f1"></i>Importar correos desde Excel</h4>
        <a href="{{ route('admin.active_directory.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver al listado
        </a>
    </div>

    @if(!isset($resultados))
    {{-- ── FORMULARIO DE CARGA ──────────────────────────────────────────────── --}}
    <div class="row justify-content-center">
        <div class="col-lg-7">

            {{-- Instrucciones --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0 py-2">
                    <span class="fw-semibold small">
                        <i class="bi bi-info-circle-fill text-primary me-1"></i>Formato esperado
                    </span>
                </div>
                <div class="card-body py-3">
                    <p class="text-muted small mb-3">
                        El archivo debe tener <strong>columna A = correo actual</strong> y
                        <strong>columna B = correo nuevo</strong>.
                        La primera fila se considera encabezado y se omite.
                    </p>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.84rem">
                            <thead class="table-dark">
                                <tr>
                                    <th>A — Correo actual (en AD)</th>
                                    <th>B — Correo nuevo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-monospace text-muted">fhenriquez@verfrut.cl</td>
                                    <td class="font-monospace text-muted">felipe.henriquez@unifrutti.com</td>
                                </tr>
                                <tr>
                                    <td class="font-monospace text-muted">jperez@verfrut.cl</td>
                                    <td class="font-monospace text-muted">juan.perez@unifrutti.com</td>
                                </tr>
                                <tr>
                                    <td class="font-monospace text-muted fst-italic opacity-50" colspan="2">… más filas …</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info py-2 mb-0 small">
                        <i class="bi bi-lightbulb me-1"></i>
                        El sistema busca cada usuario por el atributo <code>mail</code> del AD.
                        Si no coincide, la fila aparece como <em>no encontrado</em>.
                        Formatos soportados: <strong>xlsx, xls, csv</strong>.
                    </div>
                </div>
            </div>

            {{-- Dropzone --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST"
                          action="{{ route('admin.active_directory.procesar_importacion') }}"
                          enctype="multipart/form-data"
                          id="form-import">
                        @csrf
                        <div id="dropzone"
                             class="rounded-3 text-center p-5"
                             style="border: 2px dashed #cbd5e1; background:#f8fafc; cursor:pointer; transition: all .2s;">
                            <i class="bi bi-file-earmark-spreadsheet" style="font-size:2.8rem;color:#6366f1"></i>
                            <p class="mt-2 mb-1 fw-semibold" style="color:#1e293b">Arrastra tu archivo aquí</p>
                            <p class="text-muted small mb-3">o haz clic para seleccionarlo</p>
                            <input type="file" name="archivo" id="archivo"
                                   accept=".xlsx,.xls,.csv" class="d-none" required>
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="document.getElementById('archivo').click()">
                                <i class="bi bi-folder2-open me-1"></i>Seleccionar archivo
                            </button>
                            <div id="file-info" class="mt-3 d-none">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                    <i class="bi bi-file-check me-1"></i>
                                    <span id="file-name"></span>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">Tamaño máximo: 10 MB</small>
                            <button type="submit" class="btn btn-primary" id="btn-procesar" disabled>
                                <i class="bi bi-play-fill me-1"></i>Procesar importación
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    @else
    {{-- ── RESULTADOS ────────────────────────────────────────────────────────── --}}
    @php
        $actualizados  = $resumen['actualizados'];
        $noEncontrados = $resumen['noEncontrados'];
        $sinCambio     = $resumen['sinCambio'];
        $errores       = $resumen['errores'];
        $total         = count($resultados);
    @endphp

    {{-- Cards de resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold" style="font-size:2rem;color:#16a34a">{{ $actualizados }}</div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>Actualizados
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold" style="font-size:2rem;color:#64748b">{{ $noEncontrados }}</div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-question-circle-fill text-secondary me-1"></i>No encontrados
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold" style="font-size:2rem;color:#d97706">{{ $sinCambio }}</div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-dash-circle-fill text-warning me-1"></i>Sin cambio
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold" style="font-size:2rem;color:#dc2626">{{ $errores }}</div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-x-circle-fill text-danger me-1"></i>Errores
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alerta de éxito si hubo actualizaciones --}}
    @if($actualizados > 0)
    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        <span>
            Se actualizó el correo de <strong>{{ $actualizados }}</strong>
            {{ $actualizados === 1 ? 'usuario' : 'usuarios' }} en Active Directory.
        </span>
    </div>
    @endif

    {{-- Tabla de detalle --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold small">
                Detalle de resultados
                <span class="text-muted fw-normal">({{ $total }} {{ $total === 1 ? 'fila procesada' : 'filas procesadas' }})</span>
            </span>
            <a href="{{ route('admin.active_directory.importar_correos') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-repeat me-1"></i>Procesar otro archivo
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:.83rem">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px" class="text-center">Fila</th>
                        <th>Correo actual</th>
                        <th>Correo nuevo</th>
                        <th>Usuario AD</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultados as $r)
                    <tr>
                        <td class="text-center text-muted">{{ $r['fila'] }}</td>
                        <td class="font-monospace" style="font-size:.8rem">{{ $r['correo_actual'] }}</td>
                        <td class="font-monospace" style="font-size:.8rem">{{ $r['correo_nuevo'] }}</td>
                        <td class="font-monospace text-muted" style="font-size:.8rem">{{ $r['usuario'] ?: '—' }}</td>
                        <td>
                            @if($r['estado'] === 'actualizado')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i>Actualizado
                                </span>
                            @elseif($r['estado'] === 'no_encontrado')
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <i class="bi bi-search me-1"></i>No encontrado
                                </span>
                            @elseif($r['estado'] === 'sin_cambio')
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                    <i class="bi bi-dash-circle me-1"></i>Sin cambio
                                </span>
                            @elseif($r['estado'] === 'error')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-x-circle-fill me-1"></i>Error
                                </span>
                                @if($r['mensaje'])
                                    <small class="text-danger ms-1 d-inline-block" style="font-size:.78rem">
                                        {{ $r['mensaje'] }}
                                    </small>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No se procesaron filas (el archivo estaba vacío o solo tenía encabezado).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
(function () {
    const dropzone    = document.getElementById('dropzone');
    const fileInput   = document.getElementById('archivo');
    const fileInfo    = document.getElementById('file-info');
    const fileName    = document.getElementById('file-name');
    const btnProcesar = document.getElementById('btn-procesar');
    const formImport  = document.getElementById('form-import');
    if (!dropzone) return;

    function setFile(file) {
        if (!file) return;
        fileName.textContent = file.name;
        fileInfo.classList.remove('d-none');
        btnProcesar.removeAttribute('disabled');
        dropzone.style.borderColor = '#6366f1';
        dropzone.style.background  = '#eef2ff';
    }

    // Click para abrir selector
    dropzone.addEventListener('click', function (e) {
        if (e.target.closest('button') || e.target.closest('input')) return;
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) setFile(fileInput.files[0]);
    });

    // Drag & drop
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = '#6366f1';
        dropzone.style.background  = '#eef2ff';
    });
    dropzone.addEventListener('dragleave', () => {
        if (fileInput.files.length === 0) {
            dropzone.style.borderColor = '#cbd5e1';
            dropzone.style.background  = '#f8fafc';
        }
    });
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            try {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fileInput.files = dt.files;
            } catch (ex) { /* Safari fallback — no DataTransfer */ }
            setFile(files[0]);
        }
    });

    // Loading state al enviar
    formImport.addEventListener('submit', () => {
        btnProcesar.disabled = true;
        btnProcesar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Procesando…';
    });
})();
</script>
@endpush
@endsection
