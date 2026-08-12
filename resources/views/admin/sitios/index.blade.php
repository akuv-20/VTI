@extends('layouts.app')

@php use App\Models\Sitio; @endphp

@section('content')
<style>
    .sit-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }

    /* ── Filtros: una sola barra, no una tarjeta con tres bloques ─────────── */
    .sit-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:10px;
                   padding:.5rem .6rem; margin-bottom:.85rem;
                   display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .sit-buscar { position:relative; flex:0 1 280px; min-width:200px; }
    .sit-buscar .bi-search { position:absolute; left:.6rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.8rem; }
    .sit-buscar input { padding-left:1.9rem; padding-right:1.8rem; font-size:.78rem; }
    .sit-buscar .limpiar { position:absolute; right:.2rem; top:50%; transform:translateY(-50%);
                           color:#94a3b8; text-decoration:none; padding:.2rem .35rem; line-height:1; font-size:.75rem; }
    .sit-buscar .limpiar:hover { color:#475569; }
    .sit-sep { width:1px; align-self:stretch; background:#e2e8f0; margin:.1rem .15rem; }
    .sit-zona-sel { font-size:.72rem; padding:.2rem 1.4rem .2rem .5rem; height:auto; width:auto; max-width:190px; }
    .sit-zona { display:inline-block; font-size:.62rem; font-weight:600; padding:1px 7px;
                border-radius:5px; background:#ede9fe; color:#5b21b6; max-width:100%;
                overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    /* Sin asignar se ve, pero apagado: con 52 campos por clasificar, el hueco
       hay que notarlo sin que grite en cada tarjeta. */
    .sit-zona.vacia { background:#f8fafc; color:#cbd5e1; border:1px dashed #e2e8f0; font-weight:500; }
    .sit-chips { display:flex; gap:.25rem; flex-wrap:wrap; }
    .sit-tab { font-size:.72rem; padding:.2rem .6rem; border-radius:20px; border:1px solid #e2e8f0;
               background:#fff; color:#475569; text-decoration:none; white-space:nowrap; line-height:1.5; }
    .sit-tab:hover { border-color:#94a3b8; color:#1e293b; }
    .sit-tab.on { background:#0f172a; border-color:#0f172a; color:#fff; font-weight:600; }
    .sit-tab .n { opacity:.6; margin-left:.25rem; font-size:.68rem; }
    .sit-tab .pt { display:inline-block; width:6px; height:6px; border-radius:50%; margin-right:4px; }

    /* ── Tarjetas: más chicas, para ver muchos sitios de una ──────────────── */
    .sit-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(198px,1fr)); gap:.7rem; }
    .sit-item { border:1px solid #e2e8f0; border-radius:9px; overflow:hidden; background:#fff; transition:border-color .15s, box-shadow .15s; }
    .sit-item:hover { border-color:#94a3b8; box-shadow:0 2px 8px rgba(15,23,42,.06); }
    .sit-item a.lnk { text-decoration:none; color:inherit; display:block; }
    .sit-foto { height:74px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .sit-foto img { width:100%; height:100%; object-fit:cover; }
    .sit-foto i { font-size:1.5rem; color:#cbd5e1; }
    .sit-body { padding:.5rem .6rem .55rem; }
    .sit-body h5 { font-size:.82rem; font-weight:700; color:#1e293b; margin:0 0 .3rem;
                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sit-tags { display:flex; align-items:center; gap:.25rem; flex-wrap:wrap; }
    .sit-badge { display:inline-block; font-size:.62rem; font-weight:700; padding:1px 6px; border-radius:20px; color:#fff; }
    .sit-tipo { display:inline-block; font-size:.62rem; font-weight:600; padding:1px 6px; border-radius:5px; background:#eef2ff; color:#4338ca; }
    .sit-meta { font-size:.66rem; color:#94a3b8; display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.35rem; }
    .sit-comp { height:3px; background:#f1f5f9; border-radius:3px; overflow:hidden; margin-top:.4rem; }
    .sit-comp span { display:block; height:100%; }
    .sit-pct { margin-left:auto; font-size:.62rem; font-weight:700; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-pin-map-fill me-2" style="color:#7c3aed"></i>Sitios
            <span class="text-muted fw-normal" style="font-size:.82rem">{{ $sitios->count() }} de {{ $conteos['total'] }}</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sitios.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-graph-up me-1"></i>Avance</a>
            <a href="{{ route('admin.sitios.descubrimiento') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Descubrir hosts</a>
            <a href="{{ route('admin.sitios.importar') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Importar</a>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoSitio">
                <i class="bi bi-plus-lg me-1"></i>Nuevo sitio
            </button>
        </div>
    </div>

    {{-- ── Filtros ────────────────────────────────────────────────────────── --}}
    <div class="sit-filtros">
        <form method="GET" action="{{ route('admin.sitios.index') }}" class="sit-buscar">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <input type="hidden" name="zona" value="{{ $zona }}">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control form-control-sm" value="{{ $q }}"
                   placeholder="Nombre, código, comuna o encargado…" autocomplete="off">
            @if($q)
                <a href="{{ route('admin.sitios.index', ['tipo' => $tipo, 'estado' => $estado, 'zona' => $zona]) }}"
                   class="limpiar" title="Limpiar búsqueda"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>

        <div class="sit-sep"></div>

        <div class="sit-chips">
            <a href="{{ route('admin.sitios.index', ['tipo' => null, 'estado' => $estado, 'zona' => $zona, 'q' => $q]) }}" class="sit-tab {{ $tipo ? '' : 'on' }}">
                Todos <span class="n">{{ $conteos['total'] }}</span>
            </a>
            @foreach(Sitio::TIPOS as $k => $label)
                <a href="{{ route('admin.sitios.index', ['tipo' => $k, 'estado' => $estado, 'zona' => $zona, 'q' => $q]) }}"
                   class="sit-tab {{ $tipo === $k ? 'on' : '' }}">
                    <i class="bi {{ Sitio::ICONOS_TIPO[$k] }} me-1"></i>{{ $label }} <span class="n">{{ $conteos['tipos'][$k] }}</span>
                </a>
            @endforeach
        </div>

        <div class="sit-sep"></div>

        <div class="sit-chips">
            <a href="{{ route('admin.sitios.index', ['tipo' => $tipo, 'estado' => null, 'zona' => $zona, 'q' => $q]) }}" class="sit-tab {{ $estado ? '' : 'on' }}">Cualquier estado</a>
            @foreach(Sitio::ESTADOS_ENLACE as $k => $label)
                <a href="{{ route('admin.sitios.index', ['tipo' => $tipo, 'estado' => $k, 'zona' => $zona, 'q' => $q]) }}"
                   class="sit-tab {{ $estado === $k ? 'on' : '' }}">
                    <span class="pt" style="background:{{ Sitio::COLORES_ENLACE[$k] }}"></span>{{ $label }}
                    <span class="n">{{ $conteos['estados'][$k] }}</span>
                </a>
            @endforeach
        </div>

        <div class="sit-sep"></div>

        {{-- Zona: un select y no chips, porque el mantenedor no tiene tope y
             una decena de zonas partiría la barra en tres líneas. --}}
        <form method="GET" action="{{ route('admin.sitios.index') }}" class="d-flex align-items-center gap-1">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <input type="hidden" name="q" value="{{ $q }}">
            <select name="zona" class="form-select form-select-sm sit-zona-sel" onchange="this.form.submit()"
                    title="Filtrar por zona">
                <option value="">Cualquier zona</option>
                @foreach($zonas as $z)
                    <option value="{{ $z->id }}" @selected((string) $zona === (string) $z->id)>
                        {{ $z->nombre }} ({{ $z->sitios_count }})
                    </option>
                @endforeach
                @if($conteos['sin_zona'])
                    <option value="sin" @selected($zona === 'sin')>— Sin zona ({{ $conteos['sin_zona'] }})</option>
                @endif
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                    data-bs-toggle="modal" data-bs-target="#modalZonas" title="Administrar zonas">
                <i class="bi bi-gear"></i>
            </button>
        </form>
    </div>

    @if(session('import_errores') && count(session('import_errores')))
    <div class="alert alert-warning py-2" style="font-size:.8rem">
        <b>Filas con problemas en la importación:</b>
        <ul class="mb-0 mt-1">
            @foreach(array_slice(session('import_errores'), 0, 10) as $e)<li>{{ $e }}</li>@endforeach
            @if(count(session('import_errores')) > 10)<li>… y {{ count(session('import_errores')) - 10 }} más</li>@endif
        </ul>
    </div>
    @endif

    {{-- ── Listado ────────────────────────────────────────────────────────── --}}
    @if($sitios->isEmpty())
        <div class="sit-card text-center text-muted py-5" style="font-size:.85rem">
            No hay sitios con estos filtros. Crea uno con <b>Nuevo sitio</b>, o
            <a href="{{ route('admin.sitios.importar') }}">importa varios desde Excel</a>.
        </div>
    @else
    <div class="sit-grid">
        @foreach($sitios as $s)
        <div class="sit-item">
            <a class="lnk" href="{{ route('admin.sitios.show', $s) }}">
                <div class="sit-foto">
                    @if($s->portada && $s->portada->thumb_url)
                        <img src="{{ $s->portada->thumb_url }}" alt="">
                    @else
                        <i class="bi {{ $s->icono }}"></i>
                    @endif
                </div>
                <div class="sit-body">
                    <h5 title="{{ $s->titulo }}">{{ $s->titulo }}</h5>
                    <div class="sit-tags">
                        <span class="sit-tipo"><i class="bi {{ $s->icono }} me-1"></i>{{ $s->tipo_label }}</span>
                        <span class="sit-badge" style="background:{{ $s->estado_enlace_color }}">{{ $s->estado_enlace_label }}</span>
                        @php $c = $s->completitud; $cc = $c >= 80 ? '#16a34a' : ($c >= 40 ? '#d97706' : '#dc2626'); @endphp
                        <span class="sit-pct" style="color:{{ $cc }}">{{ $c }}%</span>
                    </div>
                    {{-- La zona va en su propia fila y no junto al tipo y el estado:
                         en una tarjeta de 198 px los tres chips juntos se parten en
                         dos líneas apenas el nombre pasa de una palabra. --}}
                    <div class="sit-tags mt-1">
                        @if($s->zona)
                            <span class="sit-zona"><i class="bi bi-signpost-split me-1"></i>{{ $s->zona->nombre }}</span>
                        @else
                            <span class="sit-zona vacia"><i class="bi bi-signpost me-1"></i>Sin zona</span>
                        @endif
                    </div>
                    <div class="sit-meta">
                        @if($s->comuna)<span><i class="bi bi-geo me-1"></i>{{ $s->comuna }}</span>@endif
                        @if($s->equipos_count)<span><i class="bi bi-hdd-network me-1"></i>{{ $s->equipos_count }}</span>@endif
                        @if($s->tecnico)<span><i class="bi bi-person me-1"></i>{{ Str::before($s->tecnico->name, ' ') }}</span>@endif
                    </div>
                    <div class="sit-comp" title="Ficha {{ $c }}% completa">
                        <span style="width:{{ $c }}%;background:{{ $cc }}"></span>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ── Alta rápida ────────────────────────────────────────────────────────
     Sale del flujo de la página: se usa unas pocas veces y ocupaba una tarjeta
     entera encima del listado, que es lo que de verdad se viene a mirar. --}}
<div class="modal fade" id="modalNuevoSitio" tabindex="-1" aria-labelledby="tituloNuevoSitio" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.sitios.store') }}">
                @csrf
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="tituloNuevoSitio">
                        <i class="bi bi-plus-square me-1"></i>Nuevo sitio
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size:.78rem">
                        Solo nombre y tipo; el resto de la ficha se completa después, o en terreno.
                    </p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label" style="font-size:.75rem">Código</label>
                            <input type="text" name="codigo" class="form-control form-control-sm @error('codigo') is-invalid @enderror"
                                   value="{{ old('codigo') }}" placeholder="51">
                            @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-8">
                            <label class="form-label" style="font-size:.75rem">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="nuevoSitioNombre"
                                   class="form-control form-control-sm @error('nombre') is-invalid @enderror"
                                   value="{{ old('nombre') }}" required placeholder="Campo Las Palmas">
                            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select form-select-sm @error('tipo') is-invalid @enderror" required>
                                @foreach(Sitio::TIPOS as $k => $label)
                                    <option value="{{ $k }}" @selected(old('tipo', $tipo ?: 'campo') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem">Zona</label>
                            <select name="zona_id" class="form-select form-select-sm @error('zona_id') is-invalid @enderror">
                                <option value="">— Sin zona —</option>
                                @foreach($zonas as $z)
                                    {{-- Si estás filtrando por una zona, lo más probable es
                                         que el sitio nuevo sea de esa misma. --}}
                                    <option value="{{ $z->id }}" @selected((string) old('zona_id', $zona) === (string) $z->id)>{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                            @error('zona_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm px-3"><i class="bi bi-plus-lg me-1"></i>Crear ficha</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Al cerrarlo se recarga la página si cambió algo: el filtro por zona de arriba
     se habría quedado viejo. --}}
@include('admin.sitios._modal_zonas', ['recargarAlCerrar' => true])

@push('scripts')
<script>
// En DOMContentLoaded, no antes: `bootstrap` lo publica el bundle de Vite, que
// es un módulo y por lo tanto se ejecuta después de este script en línea.
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalNuevoSitio');
    if (!modal) return;

    modal.addEventListener('shown.bs.modal', () => document.getElementById('nuevoSitioNombre').focus());

    // Si la validación del servidor rechazó el alta, el modal ya está cerrado y
    // los mensajes quedarían invisibles: se reabre para que se vean.
    @if($errors->has('nombre') || $errors->has('tipo') || $errors->has('codigo'))
        try { new bootstrap.Modal(modal).show(); } catch (e) {}
    @endif
});
</script>
@endpush
@endsection
