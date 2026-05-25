@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Facturas</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('facturas.index') }}">
                <div class="vti-search">
                    <input type="text" name="buscar" class="form-control form-control-sm" style="width:260px"
                           placeholder="Factura, descripción o compañía…" value="{{ request('buscar') }}">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    @if(request('buscar'))
                        <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <a href="{{ route('facturas.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nueva Factura
            </a>
        </div>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>N° Factura</th>
                    <th>OC</th>
                    <th>Empresa</th>
                    <th>Compañía</th>
                    <th>Servicio</th>
                    <th>Concepto</th>
                    <th>Fecha Emisión</th>
                    <th>Neto</th>
                    <th>Total c/IVA</th>
                    <th>N° CC</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($facturas as $factura)
                <tr>
                    <td><strong>{{ $factura->factura }}</strong></td>
                    <td>{{ $factura->oc }}</td>
                    <td>{{ $factura->servicio->empresa->nombre }}</td>
                    <td>{{ $factura->servicio->compania->nombre }}</td>
                    <td>{{ $factura->servicio->servicio }}</td>
                    <td>{{ $factura->servicio->concepto }}</td>
                    <td>{{ \Carbon\Carbon::parse($factura->fecha_emision)->format('d/m/Y') }}</td>
                    <td>$ {{ number_format($factura->valor_neto, 0, ',', '.') }}</td>
                    <td>$ {{ number_format($factura->valor_neto * 1.19, 0, ',', '.') }}</td>
                    <td>{{ $factura->servicio->cuentaContable->numero_cuenta }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('facturas.edit', $factura->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('facturas.destroy', $factura->id) }}" method="POST"
                                  data-confirm="Factura {{ $factura->factura }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="11">No hay facturas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span></span>
        {{ $facturas->links() }}
    </div>

</div>
@endsection
