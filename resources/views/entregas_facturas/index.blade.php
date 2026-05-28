@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-box-arrow-up-right me-2"></i>Entregas de Facturas</h4>
        <a href="{{ route('entregas_facturas.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nueva Entrega
        </a>
    </div>

    <form action="{{ route('entregas_facturas.index') }}" method="GET" class="mb-3">
        <div class="row g-2" style="max-width:480px">
            <div class="col">
                <input type="text" name="buscar" class="form-control form-control-sm"
                       placeholder="Buscar por número de factura…"
                       value="{{ $buscar ?? '' }}">
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="bi bi-search"></i>
                </button>
                @if($buscar)
                    <a href="{{ route('entregas_facturas.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Fecha</th>
                    <th>Entregado por</th>
                    <th>Facturas</th>
                    <th>Observación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entregas as $entrega)
                <tr>
                    <td class="text-muted" style="font-size:.8rem">{{ $entrega->id }}</td>
                    <td>
                        <span class="fw-semibold">{{ $entrega->created_at->format('d/m/Y') }}</span>
                        <span class="text-muted ms-1" style="font-size:.78rem">{{ $entrega->created_at->format('H:i') }}</span>
                    </td>
                    <td>{{ $entrega->usuario->name ?? '—' }}</td>
                    <td>
                        <span class="badge bg-primary rounded-pill">{{ $entrega->items_count }}</span>
                    </td>
                    <td class="text-muted" style="font-size:.85rem">{{ $entrega->observacion ?? '—' }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('entregas_facturas.show', $entrega) }}"
                               class="vti-btn-view" title="Ver detalle">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <button type="button" class="vti-btn-edit" title="Imprimir"
                                    style="background:#6f42c1;border-color:#6f42c1;color:#fff"
                                    onclick="window.open('{{ route('entregas_facturas.imprimir', $entrega) }}','_blank','width=1100,height=700,scrollbars=yes,resizable=yes')">
                                <i class="bi bi-printer-fill"></i>
                            </button>
                            <form action="{{ route('entregas_facturas.destroy', $entrega) }}" method="POST"
                                  data-confirm="entrega #{{ $entrega->id }} ({{ $entrega->items_count }} factura(s))"
                                  data-confirm-sub="Las facturas volverán a estar disponibles para nuevas entregas.">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="6">No hay entregas registradas aún.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $entregas->total() }} entrega(s)</span>
        {{ $entregas->links() }}
    </div>

</div>
@endsection
