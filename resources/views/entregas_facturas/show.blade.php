@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-box-arrow-up-right me-2"></i>
            Entrega <strong>#{{ $entrega->id }}</strong>
            <span class="text-muted fw-normal ms-2" style="font-size:.9rem">
                {{ $entrega->created_at->format('d/m/Y H:i') }}
            </span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="window.open('{{ route('entregas_facturas.imprimir', $entrega) }}','_blank','width=1100,height=700,scrollbars=yes,resizable=yes')">
                <i class="bi bi-printer-fill me-1"></i>Imprimir
            </button>
            <a href="{{ route('entregas_facturas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    {{-- Info de la entrega --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4" style="max-width:600px">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Entregado por</div>
                    <div class="fw-semibold">{{ $entrega->usuario->name ?? '—' }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Fecha de entrega</div>
                    <div class="fw-semibold">{{ $entrega->created_at->format('d/m/Y') }}</div>
                </div>
                @if($entrega->observacion)
                <div class="col-12">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Observación</div>
                    <div>{{ $entrega->observacion }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabla de facturas --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header fw-bold border-0 d-flex align-items-center justify-content-between"
             style="background:#f8fafc">
            <span><i class="bi bi-list-check me-2 text-success"></i>Facturas incluidas</span>
            <span class="badge rounded-pill bg-success" style="font-size:.72rem">
                {{ $entrega->items->count() }} {{ $entrega->items->count() === 1 ? 'factura' : 'facturas' }}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                <table class="vti-table">
                    <thead>
                        <tr>
                            <th>Nro. Factura</th>
                            <th>Rut Prov</th>
                            <th>Nombre Prov</th>
                            <th>Producto</th>
                            <th>Cuenta Contable</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:right">Sin IVA</th>
                            <th>OC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entrega->items as $item)
                        @php
                            $f             = $item->factura;
                            $tieneServicio = $f->id_servicio && $f->servicio;
                            if ($tieneServicio) {
                                $nombreProv = $f->servicio->compania->nombre ?? '—';
                                $rutProv    = $f->servicio->compania->rut ?? null;
                            } else {
                                $nombreProv = $f->proveedor ?? '—';
                                $rutProv    = $f->proveedor
                                    ? ($companiasPorNombre->get($f->proveedor)?->rut ?? null)
                                    : null;
                            }
                            $producto  = $tieneServicio
                                ? ($f->descripcion ?? $f->servicio->concepto ?? $f->servicio->servicio ?? '—')
                                : ($f->descripcion ?? '—');
                            $cc        = $f->cuentaContableEfectiva;
                            $cuentaStr = $cc
                                ? $cc->numero_cuenta . ' ' . $cc->nombre_cuenta
                                : '—';
                        @endphp
                        <tr>
                            <td><strong>{{ $f->factura }}</strong></td>
                            <td class="text-muted" style="font-size:.82rem">{{ $rutProv ?? '—' }}</td>
                            <td>{{ $nombreProv }}</td>
                            <td class="text-muted" style="font-size:.82rem">{{ $producto }}</td>
                            <td class="text-muted" style="font-size:.78rem">{{ $cuentaStr }}</td>
                            <td style="text-align:right;font-weight:600">
                                $ {{ number_format($f->total, 0, ',', '.') }}
                            </td>
                            <td style="text-align:right;color:#475569">
                                $ {{ number_format($f->valor_neto, 0, ',', '.') }}
                            </td>
                            <td class="text-muted">{{ $f->oc ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
