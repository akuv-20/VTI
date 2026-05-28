@extends('layouts.app')

@push('styles')
<style>
    .btn-outline-secondary.emisor-wom { color: #6f42c1; border-color: #6f42c1; }
    .btn-outline-secondary.emisor-wom:hover,
    .btn-check:checked + .btn-outline-secondary.emisor-wom { background-color: #6f42c1; border-color: #6f42c1; color: #fff; }

    .btn-outline-movistar { color: #0099CC; border-color: #0099CC; }
    .btn-outline-movistar:hover,
    .btn-check:checked + .btn-outline-movistar { background-color: #0099CC; border-color: #0099CC; color: #fff; }
</style>
@endpush

@section('content')
<div class="container-fluid vti-page">

    {{-- ── Cabecera ────────────────────────────────────────────────────────── --}}
    <div class="vti-page-header">
        <h4><i class="bi bi-phone me-2"></i>Líneas Telefónicas</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('lineas_telefonicas.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nueva Línea
            </a>
            <form action="{{ route('lineas_telefonicas.reprocesar_ccosto') }}" method="POST"
                  data-confirm="todas las líneas sin centro de costo"
                  data-confirm-verb="reprocesar"
                  data-confirm-title="Reprocesar centros de costo"
                  data-confirm-sub="Se asignará automáticamente el CC a líneas con empresa + ubicación."
                  data-confirm-btn="Sí, reprocesar"
                  data-confirm-icon="bi-arrow-repeat"
                  data-confirm-color="warning">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm"
                        title="Asigna el CC correspondiente a líneas con empresa + ubicación pero sin CC asignado">
                    <i class="bi bi-arrow-repeat"></i> Reprocesar CC
                </button>
            </form>
        </div>
    </div>

    {{-- ── Filtros ─────────────────────────────────────────────────────────── --}}
    <form action="{{ route('lineas_telefonicas.index') }}" method="GET" class="mb-3">
        {{-- Buscador --}}
        <div class="row g-2 mb-2">
            <div class="col-12 col-md-6 col-lg-5">
                <input type="text" name="buscar" class="form-control form-control-sm"
                    placeholder="Línea, usuario, empresa, IMEI…"
                    value="{{ request('buscar') }}">
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Buscar</span>
                </button>
                @if(request('buscar') || $estado !== 'Activo' || $emisorFiltro !== 'Todos' || $vigenciaFiltro !== 'Todos' || $soloIncompletas)
                    <a href="{{ route('lineas_telefonicas.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </div>

        {{-- Grupos de filtro (scrollables en móvil) --}}
        <div class="d-flex gap-2 flex-wrap align-items-center">
            {{-- Estado --}}
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="estado" id="estado_activo" value="Activo" autocomplete="off"
                    {{ $estado === 'Activo' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-success fw-semibold" for="estado_activo">
                    Activos <span class="badge bg-success ms-1">{{ $countActivo }}</span>
                </label>
                <input type="radio" class="btn-check" name="estado" id="estado_inactivo" value="Inactivo" autocomplete="off"
                    {{ $estado === 'Inactivo' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-danger fw-semibold" for="estado_inactivo">
                    Inactivos <span class="badge bg-danger ms-1">{{ $countInactivo }}</span>
                </label>
                <input type="radio" class="btn-check" name="estado" id="estado_todos" value="Todos" autocomplete="off"
                    {{ $estado === 'Todos' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-secondary fw-semibold" for="estado_todos">
                    Todos <span class="badge bg-secondary ms-1">{{ $totalLineas }}</span>
                </label>
            </div>

            {{-- Vigencia --}}
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="vigencia" id="vigencia_todos" value="Todos" autocomplete="off"
                    {{ $vigenciaFiltro === 'Todos' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-secondary fw-semibold" for="vigencia_todos">Todos</label>
                <input type="radio" class="btn-check" name="vigencia" id="vigencia_vigente" value="Vigente" autocomplete="off"
                    {{ $vigenciaFiltro === 'Vigente' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-success fw-semibold" for="vigencia_vigente">
                    Vigente <span class="badge bg-success ms-1">{{ $countVigente }}</span>
                </label>
                <input type="radio" class="btn-check" name="vigencia" id="vigencia_no_vigente" value="No Vigente" autocomplete="off"
                    {{ $vigenciaFiltro === 'No Vigente' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-danger fw-semibold" for="vigencia_no_vigente">
                    No Vigente <span class="badge bg-danger ms-1">{{ $countNoVigente }}</span>
                </label>
            </div>

            {{-- Emisor --}}
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="emisor" id="emisor_todos" value="Todos" autocomplete="off"
                    {{ $emisorFiltro === 'Todos' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-secondary fw-semibold" for="emisor_todos">Todos</label>
                <input type="radio" class="btn-check" name="emisor" id="emisor_entel" value="Entel" autocomplete="off"
                    {{ $emisorFiltro === 'Entel' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-primary fw-semibold" for="emisor_entel">
                    Entel <span class="badge bg-primary ms-1">{{ $countEntel }}</span>
                </label>
                <input type="radio" class="btn-check" name="emisor" id="emisor_movistar" value="Movistar" autocomplete="off"
                    {{ $emisorFiltro === 'Movistar' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-movistar fw-semibold" for="emisor_movistar">
                    Movistar <span class="badge ms-1" style="background:#0099CC">{{ $countMovistar }}</span>
                </label>
                <input type="radio" class="btn-check" name="emisor" id="emisor_wom" value="WOM" autocomplete="off"
                    {{ $emisorFiltro === 'WOM' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-secondary emisor-wom fw-semibold" for="emisor_wom">
                    WOM <span class="badge ms-1" style="background:#6f42c1">{{ $countWOM }}</span>
                </label>
            </div>

            {{-- Incompletas --}}
            <input type="checkbox" class="btn-check" name="incompletas" id="chk_incompletas"
                value="1" autocomplete="off"
                {{ $soloIncompletas ? 'checked' : '' }} onchange="this.form.submit()">
            <label class="btn btn-outline-warning btn-sm fw-semibold" for="chk_incompletas">
                ⚠ Incompletas
                @if($totalIncompletas > 0)
                    <span class="badge bg-warning text-dark ms-1">{{ $totalIncompletas }}</span>
                @endif
            </label>
        </div>

        <input type="hidden" name="_keep" value="1">
    </form>

    <div class="vti-table-wrapper">
        <table class="vti-table">
            <thead>
                <tr>
                    <th>Línea</th>
                    <th>Emisor</th>
                    <th>Usuario</th>
                    <th title="Último usuario que tenía esta línea antes del actual">Últ. Usuario</th>
                    <th>Empresa</th>
                    <th>Ubicación</th>
                    <th>Centro Costo</th>
                    <th>Aparato</th>
                    <th>Estado</th>
                    <th>Vigencia</th>
                    <th title="Azul claro=Movistar · Azul oscuro=Entel&#10;Movistar: {{ $ultimoMovil ? 'Móvil '.$ultimoMovil->periodo_label : '-' }} / {{ $ultimoBAM ? 'BAM '.$ultimoBAM->periodo_label : '-' }}&#10;Entel: {{ $ultimoEntelMovil ? 'Móvil '.$ultimoEntelMovil->periodo_label : '-' }} / {{ $ultimoEntelBAM ? 'BAM '.$ultimoEntelBAM->periodo_label : '-' }}">
                        Emisor_IMP
                    </th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lineas as $linea)
                <tr>
                    <td><strong>{{ $linea->linea }}</strong></td>
                    <td>{{ $linea->emisor->nombre ?? '—' }}</td>
                    <td>{{ $linea->usuario->nombre ?? '—' }}</td>
                    <td>
                        @php $ultHist = $linea->lastHistorialUsuario; @endphp
                        @if($ultHist && $ultHist->usuarioAnterior)
                            <span class="badge rounded-pill"
                                  style="background:#fef3c7;color:#92400e;font-weight:600;font-size:.74rem"
                                  title="Cambio el {{ $ultHist->created_at->format('d/m/Y') }}">
                                {{ $ultHist->usuarioAnterior->nombre }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $linea->empresa->nombre ?? '—' }}</td>
                    <td>{{ $linea->ubicacion->nombre ?? '—' }}</td>
                    <td>{{ $linea->centroCosto?->ccosto ?? '—' }}</td>
                    <td>
                        @if($linea->aparato)
                            {{ $linea->aparato->marca->nombre ?? '' }} {{ $linea->aparato->modelo }}
                        @else —
                        @endif
                    </td>
                    <td>
                        @if($linea->estado === 'Activo')
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-danger">Inactivo</span>
                        @endif
                    </td>
                    <td>
                        @if($lineasMovistarMovil->has($linea->id) || $lineasMovistarBAM->has($linea->id) || $lineasEntelMovil->has($linea->id) || $lineasEntelBAM->has($linea->id))
                            <span class="badge bg-success">Vigente</span>
                        @else
                            <span class="badge bg-danger">No Vigente</span>
                        @endif
                    </td>
                    <td>
                        @if($lineasMovistarMovil->has($linea->id))
                            <span class="badge" style="background-color:#0099CC" title="Movistar Móvil — {{ $ultimoMovil->periodo_label }}">Movil</span>
                        @endif
                        @if($lineasMovistarBAM->has($linea->id))
                            <span class="badge" style="background-color:#0099CC" title="Movistar BAM — {{ $ultimoBAM->periodo_label }}">BAM</span>
                        @endif
                        @if($lineasEntelMovil->has($linea->id))
                            <span class="badge" style="background-color:#002C7F" title="Entel Móvil — {{ $ultimoEntelMovil->periodo_label }}">Movil</span>
                        @endif
                        @if($lineasEntelBAM->has($linea->id))
                            <span class="badge" style="background-color:#002C7F" title="Entel BAM — {{ $ultimoEntelBAM->periodo_label }}">BAM</span>
                        @endif
                        @if(!$lineasMovistarMovil->has($linea->id) && !$lineasMovistarBAM->has($linea->id) && !$lineasEntelMovil->has($linea->id) && !$lineasEntelBAM->has($linea->id))
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="vti-actions">
                            <a href="{{ route('lineas_telefonicas.show', $linea->id) }}" class="vti-btn-view" title="Ver detalle">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <a href="{{ route('lineas_telefonicas.edit', $linea->id) }}" class="vti-btn-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form action="{{ route('lineas_telefonicas.destroy', $linea->id) }}" method="POST"
                                  data-confirm="línea {{ $linea->linea }}">
                                @csrf @method('DELETE')
                                <button class="vti-btn-delete" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="vti-empty"><td colspan="12">No hay líneas que coincidan con los filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="vti-footer">
        <span>{{ $lineas->total() }} línea(s)</span>
        {{ $lineas->links() }}
    </div>
</div>
@endsection
