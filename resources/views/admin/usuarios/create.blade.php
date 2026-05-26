@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario</h4>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

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
