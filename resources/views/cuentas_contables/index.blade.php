@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Cuentas Contables</h4>
        <a href="{{ route('cuentas_contables.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nueva Cuenta
        </a>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Número de Cuenta</th>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cuentasContables as $cc)
                <tr>
                    <td class="text-muted">{{ $cc->id }}</td>
                    <td><strong>{{ $cc->numero_cuenta }}</strong></td>
                    <td>{{ $cc->nombre_cuenta }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('cuentas_contables.edit', $cc->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('cuentas_contables.destroy', $cc->id) }}" method="POST"
                                  data-confirm="{{ $cc->numero_cuenta }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="4">No hay cuentas contables registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
