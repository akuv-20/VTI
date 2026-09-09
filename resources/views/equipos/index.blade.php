@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    {{-- ── Cabecera ────────────────────────────────────────────────────────── --}}
    <div class="vti-page-header">
        <h4><i class="bi bi-phone-fill me-2"></i>Equipos</h4>
        <a href="{{ route('equipos.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Equipo
        </a>
    </div>

    {{-- ── Filtros ─────────────────────────────────────────────────────────── --}}
    <form action="{{ route('equipos.index') }}" method="GET" class="mb-3">
        <div class="row g-2 mb-2">
            <div class="col-12 col-md-6 col-lg-5">
                <input type="text" name="buscar" class="form-control form-control-sm"
                    placeholder="IMEI, modelo, usuario, número de línea…" value="{{ request('buscar') }}">
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                @if(request('buscar') || $propiedad !== 'Todos' || $estado !== 'Todos' || $vinculo !== 'Todos')
                    <a href="{{ route('equipos.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 align-items-center">
            {{-- Propiedad --}}
            <div class="btn-group btn-group-sm" role="group">
                @foreach(['Todos' => $total, 'Empresa' => $countEmpresa, 'Personal' => $countPersonal] as $op => $n)
                    <input type="radio" class="btn-check" name="propiedad" id="prop_{{ $op }}" value="{{ $op }}"
                        {{ $propiedad === $op ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-secondary fw-semibold" for="prop_{{ $op }}">
                        {{ $op }} <span class="badge bg-secondary ms-1">{{ $n }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Estado --}}
            <div class="btn-group btn-group-sm" role="group">
                @foreach(['Todos', 'En uso', 'En bodega', 'De baja'] as $op)
                    <input type="radio" class="btn-check" name="estado" id="est_{{ $loop->index }}" value="{{ $op }}"
                        {{ $estado === $op ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-secondary fw-semibold" for="est_{{ $loop->index }}">{{ $op }}</label>
                @endforeach
            </div>

            {{-- Vínculo --}}
            <div class="btn-group btn-group-sm" role="group">
                @foreach(['Todos', 'Con línea', 'Sin línea'] as $op)
                    <input type="radio" class="btn-check" name="vinculo" id="vin_{{ $loop->index }}" value="{{ $op }}"
                        {{ $vinculo === $op ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-primary fw-semibold" for="vin_{{ $loop->index }}">
                        {{ $op }}{{ $op === 'Sin línea' ? ' ('.$countSinLinea.')' : '' }}
                    </label>
                @endforeach
            </div>
        </div>
    </form>

    {{-- ── Tabla ───────────────────────────────────────────────────────────── --}}
    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>IMEI</th>
                    <th>MAC WiFi</th>
                    <th>Modelo</th>
                    <th>Propiedad</th>
                    <th>Usuario</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Reserva DHCP</th>
                    <th>Línea</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($equipos as $e)
                <tr>
                    <td class="font-monospace" style="font-size:.85rem">{{ $e->imei ?? '—' }}</td>
                    <td class="font-monospace" style="font-size:.85rem">{{ $e->mac_wifi ?? '—' }}</td>
                    <td>{{ $e->modelo_completo }}</td>
                    <td>
                        @if($e->propiedad === 'Empresa')
                            <span class="badge bg-primary">Empresa</span>
                        @else
                            <span class="badge bg-secondary">Personal</span>
                        @endif
                    </td>
                    <td>{{ $e->usuario->nombre ?? '—' }}</td>
                    <td>{{ $e->ubicacion->nombre ?? '—' }}</td>
                    <td>
                        @php $ecls = ['En uso' => 'bg-success', 'En bodega' => 'bg-info text-dark', 'De baja' => 'bg-secondary'][$e->estado] ?? 'bg-light text-dark'; @endphp
                        <span class="badge {{ $ecls }}">{{ $e->estado }}</span>
                    </td>
                    <td>
                        @if(!$e->mac_wifi)
                            <span class="text-muted">—</span>
                        @elseif($reservasDhcp->has($e->mac_wifi))
                            <span class="badge bg-success" title="MAC {{ $e->mac_wifi }}">
                                <i class="bi bi-check-lg"></i> {{ $reservasDhcp[$e->mac_wifi] }}
                            </span>
                        @else
                            <span class="badge bg-warning text-dark" title="MAC {{ $e->mac_wifi }}">Sin Reserva</span>
                        @endif
                    </td>
                    <td>
                        @if($e->lineaTelefonica)
                            <a href="{{ route('lineas_telefonicas.show', $e->lineaTelefonica->id) }}"
                               class="font-monospace text-decoration-none">{{ $e->lineaTelefonica->linea }}</a>
                        @else
                            <span class="badge bg-light text-muted border">Sin línea</span>
                        @endif
                    </td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('equipos.show', $e) }}" class="vti-btn-view" title="Ver"><i class="bi bi-eye-fill"></i></a>
                            <a href="{{ route('equipos.edit', $e) }}" class="vti-btn-edit" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            <form method="POST" action="{{ route('equipos.destroy', $e) }}"
                                  onsubmit="return confirm('¿Eliminar este equipo?')" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="10">No hay equipos que coincidan con el filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $equipos->total() }} equipo(s)</span>
        {{ $equipos->links() }}
    </div>
</div>
@endsection
