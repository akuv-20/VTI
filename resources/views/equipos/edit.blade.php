@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="bi bi-pencil-fill me-2"></i>Editar Equipo</h4>
                @if($equipo->lineaTelefonica)
                    <span class="badge bg-light text-dark border">
                        Ligado a línea <strong class="font-monospace">{{ $equipo->lineaTelefonica->linea }}</strong>
                    </span>
                @endif
            </div>

            @if($equipo->lineaTelefonica)
                <div class="alert alert-info py-2" style="font-size:.85rem">
                    <i class="bi bi-info-circle me-1"></i>Este equipo está ligado a una línea. Al cambiar usuario o ubicación, se sincronizarán con la línea.
                </div>
            @endif

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <form action="{{ route('equipos.update', $equipo) }}" method="POST">
                        @csrf @method('PUT')
                        @include('equipos._form', ['equipo' => $equipo])

                        <div class="d-flex gap-2 mt-2">
                            <button class="btn btn-success" type="submit"><i class="bi bi-check-lg me-1"></i>Actualizar</button>
                            <a href="{{ route('equipos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials._creacion_rapida', ['marcas' => $marcas])
@endsection
