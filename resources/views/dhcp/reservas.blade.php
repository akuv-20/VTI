@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-hdd-network me-2"></i>Reservas DHCP</h4>
        <a href="{{ route('dhcp.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Panel
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('dhcp.reservas') }}" class="mb-3">
        <div class="row g-2 mb-2">
            <div class="col-12 col-md-5">
                <input type="text" name="buscar" class="form-control form-control-sm"
                       placeholder="IP, MAC, nombre o descripción…" value="{{ request('buscar') }}">
            </div>
            <div class="col-auto">
                <select name="scope" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos los scopes</option>
                    @foreach($scopes as $s)
                        <option value="{{ $s->scope_id }}" {{ request('scope') == $s->scope_id ? 'selected' : '' }}>
                            {{ $s->nombre ?: $s->scope_id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                @if(request()->hasAny(['buscar','scope']) || $estado !== 'todas')
                    <a href="{{ route('dhcp.reservas') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>

        <div class="btn-group btn-group-sm" role="group">
            @php
                $tabs = [
                    'todas'      => ['Todas', 'secondary', $countTodas],
                    'inactivas'  => ["Inactivas > {$umbral}d", 'danger', $countInactivas],
                    'activas'    => ['Activas ahora', 'success', $countActivas],
                    'eliminadas' => ['Eliminadas del DHCP', 'dark', $countEliminadas],
                ];
            @endphp
            @foreach($tabs as $val => [$lbl, $col, $cnt])
                <input type="radio" class="btn-check" name="estado" id="est_{{ $val }}" value="{{ $val }}"
                       autocomplete="off" {{ $estado === $val ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-{{ $col }} fw-semibold" for="est_{{ $val }}">
                    {{ $lbl }} <span class="badge bg-{{ $col }} ms-1">{{ $cnt }}</span>
                </label>
            @endforeach
        </div>
    </form>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Nombre / Reserva</th>
                    <th>MAC</th>
                    <th>Scope</th>
                    <th>Última actividad</th>
                    <th class="text-end">Inactiva</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reservas as $r)
                @php
                    $dias = $r->dias_inactiva;
                    $esInactiva = ($dias === null) || ($dias > $umbral);
                @endphp
                <tr>
                    <td class="font-monospace fw-semibold">{{ $r->ip }}</td>
                    <td>
                        {{ $r->nombre ?: '—' }}
                        @if($r->descripcion)
                            <span class="text-muted small d-block">{{ $r->descripcion }}</span>
                        @endif
                    </td>
                    <td class="font-monospace text-muted" style="font-size:.78rem">{{ $r->mac ?: '—' }}</td>
                    <td style="font-size:.82rem">{{ $r->scope->nombre ?? $r->scope_id }}</td>
                    <td style="font-size:.82rem">
                        @if($r->ultima_actividad)
                            {{ $r->ultima_actividad->format('d/m/Y') }}
                            <span class="text-muted d-block" style="font-size:.72rem">{{ $r->ultima_actividad->diffForHumans() }}</span>
                        @else
                            <span class="text-muted fst-italic">nunca vista activa</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($dias === null)
                            <span class="badge bg-dark">—</span>
                        @elseif($dias > $umbral)
                            <span class="badge bg-danger">{{ $dias }} días</span>
                        @elseif($dias > ($umbral / 2))
                            <span class="badge bg-warning text-dark">{{ $dias }} días</span>
                        @else
                            <span class="text-muted">{{ $dias }} días</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if(!$r->activa)
                            <span class="badge bg-dark" title="Ya no existe en el DHCP">Eliminada</span>
                        @elseif($r->visto_activa)
                            <span class="badge bg-success">Conectada</span>
                        @elseif($esInactiva)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">A depurar</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Offline</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="7">No hay reservas que coincidan con los filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $reservas->total() }} reserva(s)</span>
        {{ $reservas->links() }}
    </div>

</div>
@endsection
