@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-hdd-network me-2"></i>DHCP · Panel</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('dhcp.reservas') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Ver reservas
            </a>
            <a href="{{ route('dhcp.configuracion') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear me-1"></i>Configuración
            </a>
        </div>
    </div>

    {{-- Estado de sincronización --}}
    @if(!$ultimaImp)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>Sin datos aún.</strong> Configura el script del servidor DHCP para que envíe el primer snapshot.
                <a href="{{ route('dhcp.configuracion') }}" class="alert-link">Ir a Configuración</a>.
            </div>
        </div>
    @else
        <div class="alert {{ $datosFrescos ? 'alert-success' : 'alert-warning' }} d-flex align-items-center gap-2 py-2">
            <i class="bi {{ $datosFrescos ? 'bi-check-circle-fill' : 'bi-clock-history' }} flex-shrink-0"></i>
            <span>
                Última sincronización:
                <strong>{{ $ultimaImp->recibido_at->format('d/m/Y H:i') }}</strong>
                ({{ $ultimaImp->recibido_at->diffForHumans() }})
                @unless($datosFrescos)
                    — <span class="fw-semibold">los datos podrían estar desactualizados; revisa el script del DHCP.</span>
                @endunless
            </span>
        </div>
    @endif

    {{-- Tarjetas resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">Reservas activas</div>
                    <div class="fs-4 fw-bold text-primary">{{ number_format($totalReservas, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="{{ route('dhcp.reservas', ['estado' => 'inactivas']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm text-center py-3 {{ $inactivas > 0 ? 'border-start border-danger border-4' : '' }}">
                    <div class="card-body py-1">
                        <div class="text-muted small mb-1">Sin actividad &gt; {{ $umbral }} días</div>
                        <div class="fs-4 fw-bold {{ $inactivas > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($inactivas, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">Scopes (rangos)</div>
                    <div class="fs-4 fw-bold text-secondary">{{ $scopes->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1">
                    <div class="text-muted small mb-1">IPs en uso / total</div>
                    <div class="fs-5 fw-bold text-dark">
                        {{ number_format($scopes->sum('en_uso'), 0, ',', '.') }}
                        <span class="text-muted fw-normal">/ {{ number_format($scopes->sum('total_direcciones'), 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Uso del pool por scope --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold py-2" style="font-size:.9rem">
            <i class="bi bi-diagram-3 me-1 text-primary"></i>Uso del pool de IPs por scope
        </div>
        <div class="card-body">
            @forelse($scopes as $s)
                @php
                    $pct = (float) $s->porcentaje_uso;
                    $color = $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.85rem">
                        <div>
                            <span class="fw-semibold">{{ $s->nombre ?: $s->scope_id }}</span>
                            <span class="text-muted ms-1 font-monospace" style="font-size:.78rem">{{ $s->scope_id }}</span>
                            @if($s->rango_inicio)
                                <span class="text-muted ms-1" style="font-size:.75rem">
                                    ({{ $s->rango_inicio }} – {{ $s->rango_fin }})
                                </span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.8rem">
                            {{ number_format($s->en_uso, 0, ',', '.') }} / {{ number_format($s->total_direcciones, 0, ',', '.') }}
                            · <span class="fw-semibold text-{{ $color }}">{{ number_format($pct, 1) }}%</span>
                            · {{ $s->reservas_count }} reservas
                        </div>
                    </div>
                    <div class="progress" style="height:9px">
                        <div class="progress-bar bg-{{ $color }}" role="progressbar"
                             style="width: {{ min(100, $pct) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No hay scopes registrados todavía.</p>
            @endforelse
        </div>
    </div>

    <p class="text-muted small mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Las reservas se consideran inactivas cuando no se ven conectadas hace más de {{ $umbral }} días
        (ajustable en Configuración). El dato se construye acumulando los snapshots que envía el servidor DHCP.
    </p>

</div>
@endsection
