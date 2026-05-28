@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-building me-2"></i>Editar Compañía</h4>
        <a href="{{ route('companias.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div style="max-width:480px">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body">
                <form action="{{ route('companias.update', $compania->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.85rem">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nombre"
                               class="form-control form-control-sm @error('nombre') is-invalid @enderror"
                               value="{{ old('nombre', $compania->nombre) }}" required>
                        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold" style="font-size:.85rem">RUT</label>
                        <input type="text" name="rut"
                               class="form-control form-control-sm @error('rut') is-invalid @enderror"
                               value="{{ old('rut', $compania->rut) }}"
                               placeholder="Ej: 92580000-7" maxlength="15">
                        <div class="form-text">Opcional. Se usa en el documento de entrega de facturas.</div>
                        @error('rut')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Actualizar
                        </button>
                        <a href="{{ route('companias.index') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
