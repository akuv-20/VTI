@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
    </div>

    {{-- ── Telefonía ──────────────────────────────────────────────────── --}}
    <div class="mb-2 mt-1">
        <span class="text-uppercase fw-bold" style="font-size:.7rem;letter-spacing:.08em;color:#94a3b8">
            <i class="bi bi-phone me-1"></i>Líneas Telefónicas — Activas
        </span>
    </div>
    <div class="row g-3 mb-4">

        {{-- Entel --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('lineas_telefonicas.index', ['emisor' => 'Entel', 'estado' => 'Activo']) }}"
               class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:4px solid #2563eb !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Entel</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:#eff6ff">
                                <i class="bi bi-telephone-fill" style="color:#2563eb;font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:#1e293b;line-height:1">{{ $lineasEntel }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">líneas activas</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Movistar --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('lineas_telefonicas.index', ['emisor' => 'Movistar', 'estado' => 'Activo']) }}"
               class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:4px solid #0ea5e9 !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Movistar</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:#f0f9ff">
                                <i class="bi bi-telephone-fill" style="color:#0ea5e9;font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:#1e293b;line-height:1">{{ $lineasMovistar }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">líneas activas</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- WOM --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('lineas_telefonicas.index', ['emisor' => 'WOM', 'estado' => 'Activo']) }}"
               class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:4px solid #a855f7 !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">WOM</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:#faf5ff">
                                <i class="bi bi-telephone-fill" style="color:#a855f7;font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:#1e293b;line-height:1">{{ $lineasWOM }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">líneas activas</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Huérfanas --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('lineas_telefonicas.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100"
                     style="border-left:4px solid {{ $lineasHuerfanas > 0 ? '#f59e0b' : '#22c55e' }} !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Sin usuario</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:{{ $lineasHuerfanas > 0 ? '#fffbeb' : '#f0fdf4' }}">
                                <i class="bi bi-person-x-fill"
                                   style="color:{{ $lineasHuerfanas > 0 ? '#f59e0b' : '#22c55e' }};font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:{{ $lineasHuerfanas > 0 ? '#d97706' : '#1e293b' }};line-height:1">{{ $lineasHuerfanas }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">líneas sin asignar</div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    {{-- ── Facturación ─────────────────────────────────────────────────── --}}
    <div class="mb-2 mt-1 d-flex align-items-center gap-2">
        <span class="text-uppercase fw-bold" style="font-size:.7rem;letter-spacing:.08em;color:#94a3b8">
            <i class="bi bi-receipt me-1"></i>Facturación — Servicios Periódicos
        </span>
        <span class="badge rounded-pill"
              style="background:#e0f2fe;color:#0369a1;font-size:.68rem;font-weight:700;letter-spacing:.04em">
            <i class="bi bi-calendar3 me-1"></i>{{ $periodoLabel }}
        </span>
    </div>
    <div class="row g-3 mb-4">

        {{-- Total periódicos --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('servicios.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:4px solid #64748b !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Total</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:#f1f5f9">
                                <i class="bi bi-list-ul" style="color:#64748b;font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:#1e293b;line-height:1">{{ $serviciosPeriodicos }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">servicios periódicos</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Facturados este mes --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('servicios.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:4px solid #22c55e !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Facturados</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:#f0fdf4">
                                <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:#1e293b;line-height:1">{{ $serviciosFacturadosMes }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">con factura este mes</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Sin facturar este mes --}}
        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <a href="{{ route('servicios.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100"
                     style="border-left:4px solid {{ $serviciosSinFacturarMes > 0 ? '#ef4444' : '#22c55e' }} !important">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Sin facturar</span>
                            <span class="d-flex align-items-center justify-content-center rounded-2"
                                  style="width:30px;height:30px;background:{{ $serviciosSinFacturarMes > 0 ? '#fef2f2' : '#f0fdf4' }}">
                                <i class="bi bi-{{ $serviciosSinFacturarMes > 0 ? 'exclamation-triangle-fill' : 'check-circle-fill' }}"
                                   style="color:{{ $serviciosSinFacturarMes > 0 ? '#ef4444' : '#22c55e' }};font-size:.85rem"></i>
                            </span>
                        </div>
                        <div class="fw-bold" style="font-size:1.9rem;color:{{ $serviciosSinFacturarMes > 0 ? '#ef4444' : '#1e293b' }};line-height:1">{{ $serviciosSinFacturarMes }}</div>
                        <div class="mt-1" style="font-size:.72rem;color:#94a3b8">pendientes este mes</div>
                    </div>
                </div>
            </a>
        </div>

    </div>

</div>
@endsection
