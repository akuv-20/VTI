@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Aparatos</h4>
        <a href="{{ route('aparatos.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Aparato
        </a>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($aparatos as $aparato)
                <tr>
                    <td class="text-muted">{{ $aparato->id }}</td>
                    <td>{{ $aparato->marca->nombre ?? '—' }}</td>
                    <td>{{ $aparato->modelo }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('aparatos.edit', $aparato->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('aparatos.destroy', $aparato->id) }}" method="POST"
                                  data-confirm="{{ ($aparato->marca->nombre ?? '') . ' ' . $aparato->modelo }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="4">No hay aparatos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
