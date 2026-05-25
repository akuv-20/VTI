@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Centros de Costo</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('centros_costo.index') }}">
                <div class="vti-search">
                    <input type="text" name="buscar" class="form-control form-control-sm" style="width:280px"
                           placeholder="Buscar por empresa, ubicación o código…" value="{{ request('buscar') }}">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    @if(request('buscar'))
                        <a href="{{ route('centros_costo.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <a href="{{ route('centros_costo.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nuevo
            </a>
        </div>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Ubicación</th>
                    <th>Código B</th>
                    <th>Código C</th>
                    <th>CCosto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($centros as $cc)
                <tr>
                    <td>{{ $cc->empresa->nombre ?? 'N/A' }}</td>
                    <td>{{ $cc->ubicacion->nombre ?? 'N/A' }}</td>
                    <td>{{ $cc->codigo_b }}</td>
                    <td>{{ $cc->codigo_c }}</td>
                    <td><strong>{{ $cc->ccosto }}</strong></td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('centros_costo.edit', $cc->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('centros_costo.destroy', $cc->id) }}" method="POST"
                                  data-confirm="{{ $cc->ccosto }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="6">No hay centros de costo registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $centros->total() }} resultado(s)</span>
        {{ $centros->links() }}
    </div>

</div>
@endsection
