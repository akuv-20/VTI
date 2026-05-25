@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="mb-3">Subir Archivo Entel</h4>

            <div class="alert alert-info no-autodismiss">
                <strong>Instrucciones:</strong><br>
                Sube el archivo <strong>.xls</strong> tal como viene descargado desde Entel.<br>
                El nombre debe mantener el formato <strong>resumen_XXXXXXXX.xls</strong> donde XXXXXXXX es el número de factura.<br>
                El sistema detectará automáticamente si es <strong>Móvil</strong> (1.17882753)
                o <strong>BAM</strong> (1.10290392) según el contenido del archivo.
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('importaciones_entel.store') }}" method="POST"
                  enctype="multipart/form-data" id="formImport" data-no-loader>
                @csrf
                <div class="mb-3">
                    <label class="form-label">Período de facturación <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2">
                        <select name="periodo_mes" class="form-select @error('periodo_mes') is-invalid @enderror" required>
                            <option value="">Mes</option>
                            @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $mes)
                                <option value="{{ $i + 1 }}" {{ old('periodo_mes') == $i + 1 ? 'selected' : '' }}>{{ $mes }}</option>
                            @endforeach
                        </select>
                        <select name="periodo_anio" class="form-select @error('periodo_anio') is-invalid @enderror" required>
                            <option value="">Año</option>
                            @for($y = now()->year; $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ old('periodo_anio', now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Archivo XLS <span class="text-danger">*</span></label>
                    <input type="file" name="archivo" id="archivoInput"
                        class="form-control @error('archivo') is-invalid @enderror"
                        accept=".xls,.xlsx" required>
                    @error('archivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div id="loadingBar" class="d-none mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <small class="text-muted">Procesando archivo, por favor espere...</small>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                            style="width: 100%"></div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" id="btnProcesar" class="btn btn-primary">Procesar</button>
                    <a href="{{ route('importaciones_entel.index') }}" id="btnCancelar" class="btn btn-secondary">Cancelar</a>
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
