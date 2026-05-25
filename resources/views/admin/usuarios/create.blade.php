@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario</h4>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger no-autodismiss">
            <strong>Por favor corrige los siguientes errores:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.usuarios.store') }}" method="POST" data-loader>
        @csrf
        @include('admin.usuarios._form', ['usuario' => null])

        <div class="mt-3">
            <button type="submit" class="btn btn-success" id="btn-submit">
                <i class="bi bi-check-lg"></i> Crear Usuario
            </button>
        </div>
    </form>
</div>
@endsection
