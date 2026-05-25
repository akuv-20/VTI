@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="mb-3">Subir Archivo Movistar</h4>

            <div class="alert alert-info no-autodismiss">
                <strong>Instrucciones:</strong><br>
                Sube el archivo ZIP tal como viene descargado desde Movistar.<br>
                El sistema detectará automáticamente si es <strong>Móvil</strong> (35626534)
                o <strong>BAM</strong> (11502573) según el nombre del archivo interno.
            </div>

            <form action="{{ route('importaciones_movistar.store') }}" method="POST"
                  enctype="multipart/form-data" id="formImport" data-no-loader>
                @csrf
                <div class="mb-3">
                    <label class="form-label">Archivo ZIP <span class="text-danger">*</span></label>
                    <input type="file" name="archivo" id="archivoInput"
                        class="form-control @error('archivo') is-invalid @enderror"
                        accept=".zip" required>
                    @error('archivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Barra de progreso (oculta hasta enviar) --}}
                <div id="loadingBar" class="d-none mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                        <small class="text-muted">Procesando archivo, por favor espere...</small>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                            style="width: 100%"></div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" id="btnProcesar" class="btn btn-success">Procesar</button>
                    <a href="{{ route('importaciones_movistar.index') }}" id="btnCancelar" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('formImport').addEventListener('submit', function () {
    const btn = document.getElementById('btnProcesar');
    const cancelar = document.getElementById('btnCancelar');
    const loading = document.getElementById('loadingBar');

    btn.disabled = true;
    btn.innerHTML = 'Procesando...';
    cancelar.classList.add('disabled');
    loading.classList.remove('d-none');
});
</script>
@endsection
