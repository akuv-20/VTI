@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>Resumen Anual de Facturación</h4>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="{{ route('facturas.resumen') }}" class="d-flex gap-2 align-items-center">
                <select name="anio" class="form-select form-select-sm" style="width:90px" onchange="this.form.submit()">
                    @foreach($aniosDisponibles as $a)
                        <option value="{{ $a }}" {{ $a == $anio ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i> Ver listado
            </a>
        </div>
    </div>

    @php
        $totalAnual = collect($totalesCol)->sum();
    @endphp

    {{-- Tarjetas resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">Total Neto {{ $anio }}</div>
                    <div class="fs-5 fw-bold text-primary">$ {{ number_format($totalAnual, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">IVA estimado (19%)</div>
                    <div class="fs-5 fw-bold text-secondary">$ {{ number_format($totalAnual * 0.19, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">Cuentas contables activas</div>
                    <div class="fs-5 fw-bold text-success">{{ count($ccs) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Matriz CC × Meses --}}
    <div class="vti-table-wrapper" style="overflow-x:auto">
        <table class="vti-table" style="font-size:.82rem;min-width:900px">
            <thead>
                <tr>
                    <th style="min-width:220px">Cuenta Contable</th>
                    @foreach(range(1,12) as $m)
                        <th class="text-end" style="min-width:90px">{{ $meses[$m] }}</th>
                    @endforeach
                    <th class="text-end" style="min-width:100px;background:rgba(59,130,246,.07)">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ccs as $id => $cc)
                @php
                    $totalFila = collect($matriz[$id] ?? [])->sum();
                    $maxMes    = !empty($matriz[$id]) ? max($matriz[$id]) : 0;
                @endphp
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $cc->numero_cuenta }}</span>
                        <span class="text-muted small d-block">{{ $cc->nombre_cuenta }}</span>
                    </td>
                    @foreach(range(1,12) as $m)
                    @php $val = $matriz[$id][$m] ?? 0; @endphp
                    <td class="text-end {{ $val > 0 ? 'fw-semibold' : 'text-muted' }}"
                        style="{{ $val > 0 && $maxMes > 0 ? 'background:rgba(59,130,246,'.min(0.18, ($val/$maxMes)*0.18).')' : '' }}">
                        @if($val > 0)
                            $ {{ number_format($val, 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    @endforeach
                    <td class="text-end fw-bold" style="background:rgba(59,130,246,.07)">
                        $ {{ number_format($totalFila, 0, ',', '.') }}
                    </td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="14">No hay facturas registradas para {{ $anio }}.</td>
                </tr>
                @endforelse
            </tbody>
            @if(count($ccs) > 0)
            <tfoot>
                <tr class="fw-bold" style="border-top:2px solid #dee2e6;background:rgba(59,130,246,.06)">
                    <td class="text-end text-muted small pe-3">Total mes:</td>
                    @foreach(range(1,12) as $m)
                    @php $col = $totalesCol[$m] ?? 0; @endphp
                    <td class="text-end {{ $col > 0 ? 'text-primary' : 'text-muted' }}">
                        @if($col > 0)
                            $ {{ number_format($col, 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    @endforeach
                    <td class="text-end text-primary" style="background:rgba(59,130,246,.12)">
                        $ {{ number_format($totalAnual, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <p class="text-muted small mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Los montos corresponden a valor neto. Para facturas mensuales se usa la cuenta contable del servicio; para esporádicas, la cuenta contable directa.
    </p>

</div>
@endsection
