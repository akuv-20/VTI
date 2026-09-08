@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <h4 class="mb-3"><i class="bi bi-phone-fill me-2"></i>Nuevo Equipo</h4>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <form action="{{ route('equipos.store') }}" method="POST">
                        @csrf
                        @include('equipos._form', ['equipo' => null])

                        <div class="d-flex gap-2 mt-2">
                            <button class="btn btn-success" type="submit"><i class="bi bi-check-lg me-1"></i>Guardar</button>
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
