@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-pc-display-horizontal me-2"></i>Inventario TI — Equipos
        </h4>
        <form method="GET" action="{{ route('inventario_ti.index') }}" class="vti-search">
            <input type="text" name="q" value="{{ $search }}"
                   class="form-control" placeholder="Buscar equipo, serial, usuario…" style="width:260px">
            <button class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            @if($search)
                <a href="{{ route('inventario_ti.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
            @endif
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario Asignado</th>
                    <th>Marca / Modelo</th>
                    <th>N° Serie</th>
                    <th>Sistema Operativo</th>
                    <th>Ubicación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($computadores as $eq)
                <tr>
                    <td class="fw-semibold">{{ $eq->nombre_equipo }}</td>
                    <td>{{ trim($eq->nombre_usuario) ?: '—' }}</td>
                    <td>
                        @if($eq->marca || $eq->modelo)
                            {{ $eq->marca }} {{ $eq->modelo }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="font-monospace" style="font-size:.78rem">{{ $eq->numero_serie ?: '—' }}</td>
                    <td style="font-size:.8rem">{{ $eq->sistema_operativo ?: '—' }}</td>
                    <td style="font-size:.8rem">{{ $eq->ubicacion ?: '—' }}</td>
                    <td class="text-end">
                        <div class="vti-actions justify-content-end">
                            <a href="{{ route('inventario_ti.show', $eq->id) }}"
                               class="vti-btn-view" title="Ver ficha">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="7">
                        <i class="bi bi-inbox" style="font-size:1.4rem"></i>
                        <div class="mt-1">No se encontraron equipos.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center px-1">
        <small class="text-muted">{{ $computadores->total() }} equipos encontrados</small>
        {{ $computadores->links() }}
    </div>

</div>
@endsection
