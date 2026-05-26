@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Facturas</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="{{ route('facturas.resumen') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-table me-1"></i> Resumen anual
            </a>
            <a href="{{ route('facturas.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nueva Factura
            </a>
        </div>
    </div>

    {{-- ── Filtros ──────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('facturas.index') }}" class="mb-3">
        <div class="d-flex gap-2 align-items-end flex-wrap">

            {{-- Tipo --}}
            <div>
                <label class="form-label small mb-1 text-muted">Tipo</label>
                <select name="tipo" class="form-select form-select-sm" style="width:130px">
                    <option value="Todas"      {{ request('tipo','Todas') === 'Todas'      ? 'selected' : '' }}>Todas</option>
                    <option value="Mensual"    {{ request('tipo') === 'Mensual'    ? 'selected' : '' }}>Mensual</option>
                    <option value="Esporádica" {{ request('tipo') === 'Esporádica' ? 'selected' : '' }}>Esporádica</option>
                </select>
            </div>

            {{-- Año --}}
            <div>
                <label class="form-label small mb-1 text-muted">Año</label>
                <select name="anio" class="form-select form-select-sm" style="width:90px">
                    <option value="">Todos</option>
                    @foreach($aniosDisponibles as $a)
                        <option value="{{ $a }}" {{ request('anio') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Mes --}}
            <div>
                <label class="form-label small mb-1 text-muted">Mes</label>
                <select name="mes" class="form-select form-select-sm" style="width:120px">
                    <option value="">Todos</option>
                    @foreach($meses as $num => $nombre)
                        @if($num > 0)
                            <option value="{{ $num }}" {{ request('mes') == $num ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            {{-- Cuenta Contable --}}
            <div>
                <label class="form-label small mb-1 text-muted">Cuenta Contable</label>
                <select name="cuenta_contable" class="form-select form-select-sm" style="width:200px">
                    <option value="">Todas</option>
                    @foreach($cuentasContables as $cc)
                        <option value="{{ $cc->id }}" {{ request('cuenta_contable') == $cc->id ? 'selected' : '' }}>
                            {{ $cc->numero_cuenta }} — {{ $cc->nombre_cuenta }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Búsqueda --}}
            <div>
                <label class="form-label small mb-1 text-muted">Buscar</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="buscar" class="form-control" style="width:220px"
                           placeholder="Factura, proveedor, descripción…" value="{{ request('buscar') }}">
                </div>
            </div>

            <div class="d-flex gap-1 align-self-end">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="bi bi-funnel-fill"></i> Filtrar
                </button>
                <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>

    {{-- ── Totales del filtro ───────────────────────────────────────────────── --}}
    @if(request()->hasAny(['tipo','anio','mes','cuenta_contable','buscar']))
    <div class="d-flex gap-3 mb-3 flex-wrap">
        <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
            <i class="bi bi-cash-coin text-secondary"></i>
            <div>
                <div class="small text-muted">Neto filtrado</div>
                <div class="fw-bold">$ {{ number_format($totalNeto, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
            <i class="bi bi-receipt text-secondary"></i>
            <div>
                <div class="small text-muted">IVA filtrado</div>
                <div class="fw-bold">$ {{ number_format($totalIva, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
            <i class="bi bi-calculator text-primary"></i>
            <div>
                <div class="small text-muted">Total c/IVA</div>
                <div class="fw-bold text-primary">$ {{ number_format($totalNeto + $totalIva, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Tabla ────────────────────────────────────────────────────────────── --}}
    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>N° Factura</th>
                    <th>Proveedor / Servicio</th>
                    <th>Cuenta Contable</th>
                    <th>Fecha</th>
                    <th class="text-end">Neto</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($facturas as $factura)
                <tr>
                    <td>
                        @if($factura->tipo === 'Mensual')
                            <span class="badge bg-primary bg-opacity-75">Mensual</span>
                        @else
                            <span class="badge bg-warning text-dark">Esporádica</span>
                        @endif
                    </td>
                    <td><strong>{{ $factura->factura }}</strong>
                        @if($factura->oc)
                            <br><span class="text-muted small">OC: {{ $factura->oc }}</span>
                        @endif
                    </td>
                    <td>
                        @if($factura->tipo === 'Mensual' && $factura->servicio)
                            <div>{{ $factura->servicio->servicio }}</div>
                            <div class="small text-muted">{{ $factura->servicio->empresa->nombre ?? '' }} / {{ $factura->servicio->compania->nombre ?? '' }}</div>
                        @elseif($factura->proveedor)
                            {{ $factura->proveedor }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @php $cc = $factura->cuenta_contable_efectiva; @endphp
                        @if($cc)
                            <span class="small" title="{{ $cc->nombre_cuenta }}">{{ $cc->numero_cuenta }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $factura->fecha_emision->format('d/m/Y') }}</td>
                    <td class="text-end">$ {{ number_format($factura->valor_neto, 0, ',', '.') }}</td>
                    <td class="text-end text-muted small">$ {{ number_format($factura->valor_iva, 0, ',', '.') }}</td>
                    <td class="text-end fw-semibold">$ {{ number_format($factura->total, 0, ',', '.') }}</td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('facturas.edit', $factura->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('facturas.destroy', $factura->id) }}" method="POST"
                                  data-confirm="Factura N° {{ $factura->factura }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="9">No hay facturas que coincidan con los filtros.</td></tr>
                @endforelse
            </tbody>
            @if($facturas->count() > 0)
            <tfoot>
                <tr class="fw-bold" style="border-top:2px solid #dee2e6;background:var(--vti-table-head-bg,#f8fafc)">
                    <td colspan="5" class="text-end text-muted small pe-3">Totales página:</td>
                    <td class="text-end">$ {{ number_format($facturas->sum('valor_neto'), 0, ',', '.') }}</td>
                    <td class="text-end text-muted small">$ {{ number_format($facturas->sum('valor_iva'), 0, ',', '.') }}</td>
                    <td class="text-end text-primary">$ {{ number_format($facturas->sum('total'), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div class="vti-footer mt-2">
        <span class="text-muted small">{{ $facturas->total() }} resultado(s)</span>
        {{ $facturas->links() }}
    </div>

</div>
@endsection
