@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-pencil-square me-2"></i>Editar Acta de Entrega — Equipo</h4>
        <a href="{{ route('inventario_ti.actas') }}" class="btn btn-outline-secondary btn-sm">
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

    <form method="POST" action="{{ route('inventario_ti.actas.update', $acta) }}" style="max-width:900px">
        @csrf @method('PUT')

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white fw-semibold border-bottom py-2">
                <i class="bi bi-pc-display me-1 text-primary"></i> Datos del Equipo
            </div>
            <div class="card-body">
                <div class="row g-3" style="font-size:.86rem">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre del equipo</label>
                        <input type="text" name="nombre_equipo" class="form-control form-control-sm"
                               value="{{ old('nombre_equipo', $acta->nombre_equipo) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Receptor (usuario)</label>
                        <input type="text" name="nombre_receptor" class="form-control form-control-sm"
                               value="{{ old('nombre_receptor', $acta->nombre_receptor) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Fecha de entrega</label>
                        <input type="date" name="fecha_emision" class="form-control form-control-sm"
                               value="{{ old('fecha_emision', $acta->fecha_emision->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Ubicación</label>
                        <input type="text" name="ubicacion" class="form-control form-control-sm"
                               value="{{ old('ubicacion', $acta->ubicacion) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Marca</label>
                        <input type="text" name="marca" class="form-control form-control-sm"
                               value="{{ old('marca', $acta->marca) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Modelo</label>
                        <input type="text" name="modelo" class="form-control form-control-sm"
                               value="{{ old('modelo', $acta->modelo) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">N° de serie</label>
                        <input type="text" name="numero_serie" class="form-control form-control-sm font-monospace"
                               value="{{ old('numero_serie', $acta->numero_serie) }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Sistema operativo</label>
                        <input type="text" name="sistema_operativo" class="form-control form-control-sm"
                               value="{{ old('sistema_operativo', $acta->sistema_operativo) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Procesador</label>
                        <input type="text" name="procesador" class="form-control form-control-sm"
                               value="{{ old('procesador', $acta->procesador) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Memoria RAM</label>
                        <input type="text" name="ram" class="form-control form-control-sm"
                               value="{{ old('ram', $acta->ram) }}" placeholder="16 GB">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Disco principal</label>
                        <input type="text" name="disco" class="form-control form-control-sm"
                               value="{{ old('disco', $acta->disco) }}" placeholder="512 GB">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white fw-semibold border-bottom py-2">
                <i class="bi bi-box-seam me-1 text-primary"></i> Condición y Accesorios
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

                <label class="form-label fw-semibold" style="font-size:.83rem">Accesorios incluidos</label>
                @php $acc = old('accesorios', $acta->accesorios ?? []); @endphp
                <div class="row g-2">
                    @foreach(['monitor' => 'Monitor', 'mouse' => 'Mouse', 'teclado' => 'Teclado', 'mochila' => 'Mochila/Maletín'] as $key => $label)
                    <div class="col-6 col-md-3">
                        <div class="d-flex gap-2 align-items-center" style="font-size:.84rem">
                            <span class="text-muted" style="min-width:60px">{{ $label }}</span>
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
            <a href="{{ route('inventario_ti.actas.imprimir', $acta) }}" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer-fill me-1"></i>Reimprimir
            </a>
            <a href="{{ route('inventario_ti.actas') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

</div>
@endsection
