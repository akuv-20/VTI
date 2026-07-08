@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-pencil-square me-2"></i>Editar Acta de Devolución — Teléfono</h4>
        <a href="{{ route('actas_devolucion_telefono.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('actas_devolucion_telefono.update', $acta) }}" style="max-width:900px">
        @csrf @method('PUT')

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white fw-semibold border-bottom py-2">
                <i class="bi bi-phone me-1 text-primary"></i> Datos Generales
            </div>
            <div class="card-body">
                <div class="row g-3" style="font-size:.86rem">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre del empleado</label>
                        <input type="text" name="nombre_receptor" class="form-control form-control-sm"
                               value="{{ old('nombre_receptor', $acta->nombre_receptor) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha de devolución</label>
                        <input type="date" name="fecha_emision" class="form-control form-control-sm"
                               value="{{ old('fecha_emision', $acta->fecha_emision->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">N° teléfono</label>
                        <input type="text" name="numero_telefono" class="form-control form-control-sm font-monospace"
                               value="{{ old('numero_telefono', $acta->numero_telefono) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Zona</label>
                        <input type="text" name="zona" class="form-control form-control-sm"
                               value="{{ old('zona', $acta->zona) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Compañía</label>
                        <input type="text" name="compania" class="form-control form-control-sm"
                               value="{{ old('compania', $acta->compania) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Marca</label>
                        <input type="text" name="marca" class="form-control form-control-sm"
                               value="{{ old('marca', $acta->marca) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Modelo</label>
                        <input type="text" name="modelo" class="form-control form-control-sm"
                               value="{{ old('modelo', $acta->modelo) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">IMEI equipo</label>
                        <input type="text" name="imei_equipo" class="form-control form-control-sm font-monospace"
                               value="{{ old('imei_equipo', $acta->imei_equipo) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">IMEI SIM</label>
                        <input type="text" name="imei_sim" class="form-control form-control-sm font-monospace"
                               value="{{ old('imei_sim', $acta->imei_sim) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white fw-semibold border-bottom py-2">
                <i class="bi bi-box-seam me-1 text-primary"></i> Condición, Accesorios y Documentación
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.83rem">Condición del Equipo</label>
                    <div class="d-flex gap-3">
                        @foreach(['Nuevo','Usado'] as $cond)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="condicion"
                                   id="cond_{{ $cond }}" value="{{ $cond }}"
                                   {{ old('condicion', $acta->condicion) === $cond ? 'checked' : '' }} required>
                            <label class="form-check-label" for="cond_{{ $cond }}">{{ $cond }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>

                @php $acc = old('accesorios', $acta->accesorios ?? []); $doc = old('documentacion', $acta->documentacion ?? []); @endphp

                <label class="form-label fw-semibold" style="font-size:.83rem">Accesorios</label>
                <div class="row g-2 mb-3">
                    @foreach([
                        'cargador_usb'   => 'Cargador (Cable USB C)',
                        'cargador_auto'  => 'Cargador de automóvil',
                        'manos_libres'   => 'Manos libres (auricular)',
                        'cd_informacion' => 'Cd de información',
                    ] as $key => $label)
                    <div class="col-md-6">
                        <div class="d-flex gap-2 align-items-center justify-content-between" style="font-size:.84rem">
                            <span class="text-muted">{{ $label }}</span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="accesorios[{{ $key }}]"
                                       id="acc_{{ $key }}_si" value="SI" {{ ($acc[$key] ?? '') === 'SI' ? 'checked' : '' }}>
                                <label class="btn btn-outline-success" for="acc_{{ $key }}_si">Sí</label>
                                <input type="radio" class="btn-check" name="accesorios[{{ $key }}]"
                                       id="acc_{{ $key }}_no" value="NO" {{ ($acc[$key] ?? '') === 'NO' ? 'checked' : '' }}>
                                <label class="btn btn-outline-danger" for="acc_{{ $key }}_no">No</label>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <label class="form-label fw-semibold" style="font-size:.83rem">Documentación</label>
                <div class="row g-2">
                    @foreach([
                        'manual_propietario' => 'Manual del Propietario',
                        'procedimiento_uso'  => 'Procedimiento uso de teléfono móvil',
                    ] as $key => $label)
                    <div class="col-md-6">
                        <div class="d-flex gap-2 align-items-center justify-content-between" style="font-size:.84rem">
                            <span class="text-muted">{{ $label }}</span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="documentacion[{{ $key }}]"
                                       id="doc_{{ $key }}_si" value="SI" {{ ($doc[$key] ?? '') === 'SI' ? 'checked' : '' }}>
                                <label class="btn btn-outline-success" for="doc_{{ $key }}_si">Sí</label>
                                <input type="radio" class="btn-check" name="documentacion[{{ $key }}]"
                                       id="doc_{{ $key }}_no" value="NO" {{ ($doc[$key] ?? '') === 'NO' ? 'checked' : '' }}>
                                <label class="btn btn-outline-danger" for="doc_{{ $key }}_no">No</label>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold" style="font-size:.83rem">Observación</label>
                    <textarea name="observacion" class="form-control" rows="2"
                              style="font-size:.83rem">{{ old('observacion', $acta->observacion) }}</textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Guardar cambios
            </button>
            <a href="{{ route('actas_devolucion_telefono.imprimir', $acta) }}" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer-fill me-1"></i>Reimprimir
            </a>
            <a href="{{ route('actas_devolucion_telefono.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

</div>
@endsection
