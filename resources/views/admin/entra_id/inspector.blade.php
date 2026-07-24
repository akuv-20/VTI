@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header mb-3">
        <h4>
            <i class="bi bi-microsoft me-1" style="color:#0078d4"></i>Entra ID
            <span class="text-muted fw-normal mx-1">/</span>
            <i class="bi bi-clipboard2-data me-1"></i>Value Inspector
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.entra_id.dashboard') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-heart-pulse me-1"></i>Salud de datos
            </a>
            <a href="{{ route('admin.entra_id.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver al listado
            </a>
        </div>
    </div>

    @if(isset($graphError))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $graphError }}
        </div>
    @else

    {{-- Totales generales --}}
    <p class="text-muted mb-3" style="font-size:.85rem">
        <i class="bi bi-people me-1"></i>Analizando <strong>{{ number_format($totalUSer) }}</strong> cuentas.
        Haz clic en cualquier valor para ver las cuentas que lo tienen.
    </p>

    <div class="row g-3">
        @foreach($resumen as $campo => $info)
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-2">
                    <span class="fw-semibold" style="font-size:.9rem">
                        <i class="bi bi-tag me-1 text-primary"></i>{{ $info['etiqueta'] }}
                        <code class="text-muted ms-1" style="font-size:.75rem">{{ $campo }}</code>
                    </span>
                    <div class="d-flex gap-2" style="font-size:.78rem">
                        @if($info['total_vacio'] > 0)
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                <i class="bi bi-slash-circle me-1"></i>{{ $info['total_vacio'] }} vacíos
                            </span>
                        @endif
                        @if($info['total_inc'] > 0)
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bi bi-exclamation-triangle me-1"></i>{{ $info['total_inc'] }} inconsistentes
                            </span>
                        @endif
                        @if($info['total_ok'] > 0)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check-circle me-1"></i>{{ $info['total_ok'] }} correctos
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:280px;overflow-y:auto">
                        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end" style="width:70px">Cuentas</th>
                                    <th class="text-end" style="width:60px">%</th>
                                    <th style="width:30px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($info['valores'] as $v)
                                @php
                                    $pct = $totalUSer > 0 ? round($v['count'] / $totalUSer * 100, 1) : 0;
                                @endphp
                                <tr>
                                    <td>
                                        @if($v['vacio'])
                                            <span class="text-muted fst-italic">(vacío)</span>
                                        @elseif($v['inconsistente'])
                                            <span class="text-warning fw-semibold">
                                                <i class="bi bi-exclamation-triangle-fill me-1" style="font-size:.75rem"></i>{{ $v['valor'] }}
                                            </span>
                                        @else
                                            <span class="text-success">
                                                <i class="bi bi-check-circle-fill me-1" style="font-size:.75rem"></i>{{ $v['valor'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($v['count']) }}</td>
                                    <td class="text-end text-muted">{{ $pct }}%</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.entra_id.inspector.detalle', $campo) }}?valor={{ urlencode($v['valor']) }}"
                                           class="btn btn-outline-secondary btn-sm py-0 px-1" title="Ver cuentas" style="font-size:.72rem">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @endif
</div>
@endsection
