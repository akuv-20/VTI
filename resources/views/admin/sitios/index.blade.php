@extends('layouts.app')

@php use App\Models\Sitio; @endphp

@section('content')
<style>
    .sit-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
    .sit-card h6 { font-size:.82rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }
    .sit-tabs { display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.9rem; }
    .sit-tab { font-size:.78rem; padding:.3rem .8rem; border-radius:20px; border:1px solid #e2e8f0; background:#fff; color:#475569; text-decoration:none; }
    .sit-tab.on { background:#0f172a; border-color:#0f172a; color:#fff; font-weight:600; }
    .sit-tab .n { opacity:.65; margin-left:.3rem; font-size:.72rem; }
    .sit-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:1rem; }
    .sit-item { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; transition:border-color .15s, box-shadow .15s; }
    .sit-item:hover { border-color:#94a3b8; box-shadow:0 2px 8px rgba(15,23,42,.06); }
    .sit-item a.lnk { text-decoration:none; color:inherit; display:block; }
    .sit-foto { height:118px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .sit-foto img { width:100%; height:100%; object-fit:cover; }
    .sit-foto i { font-size:2rem; color:#cbd5e1; }
    .sit-body { padding:.75rem .9rem .9rem; }
    .sit-body h5 { font-size:.92rem; font-weight:700; color:#1e293b; margin:0 0 .2rem; }
    .sit-meta { font-size:.72rem; color:#94a3b8; display:flex; gap:.7rem; flex-wrap:wrap; margin-top:.4rem; }
    .sit-badge { display:inline-block; font-size:.66rem; font-weight:700; padding:1px 8px; border-radius:20px; color:#fff; }
    .sit-tipo { display:inline-block; font-size:.66rem; font-weight:600; padding:1px 8px; border-radius:5px; background:#eef2ff; color:#4338ca; }
    .sit-comp { height:4px; background:#f1f5f9; border-radius:3px; overflow:hidden; margin-top:.5rem; }
    .sit-comp span { display:block; height:100%; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-pin-map-fill me-2" style="color:#7c3aed"></i>Sitios</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sitios.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-graph-up me-1"></i>Avance</a>
            <a href="{{ route('admin.sitios.descubrimiento') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Descubrir hosts</a>
            <a href="{{ route('admin.sitios.importar') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Importar</a>
        </div>
    </div>

    {{-- ── Filtros ────────────────────────────────────────────────────────── --}}
    <div class="sit-card">
        <div class="sit-tabs">
            <a href="{{ route('admin.sitios.index', ['estado' => $estado, 'q' => $q]) }}" class="sit-tab {{ $tipo ? '' : 'on' }}">
                Todos <span class="n">{{ $conteos['total'] }}</span>
            </a>
            @foreach(Sitio::TIPOS as $k => $label)
                <a href="{{ route('admin.sitios.index', ['tipo' => $k, 'estado' => $estado, 'q' => $q]) }}" class="sit-tab {{ $tipo === $k ? 'on' : '' }}">
                    <i class="bi {{ Sitio::ICONOS_TIPO[$k] }} me-1"></i>{{ $label }} <span class="n">{{ $conteos['tipos'][$k] }}</span>
                </a>
            @endforeach
        </div>

        <div class="sit-tabs">
            <a href="{{ route('admin.sitios.index', ['tipo' => $tipo, 'q' => $q]) }}" class="sit-tab {{ $estado ? '' : 'on' }}" style="font-size:.72rem">Cualquier estado</a>
            @foreach(Sitio::ESTADOS_ENLACE as $k => $label)
                <a href="{{ route('admin.sitios.index', ['tipo' => $tipo, 'estado' => $k, 'q' => $q]) }}"
                   class="sit-tab {{ $estado === $k ? 'on' : '' }}" style="font-size:.72rem">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:{{ Sitio::COLORES_ENLACE[$k] }};margin-right:4px"></span>
                    {{ $label }} <span class="n">{{ $conteos['estados'][$k] }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.sitios.index') }}" class="row g-2 align-items-end mt-1">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" value="{{ $q }}" placeholder="Buscar por nombre, código, comuna o encargado…">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-search me-1"></i>Buscar</button>
            </div>
            @if($q)
            <div class="col-md-2">
                <a href="{{ route('admin.sitios.index', ['tipo' => $tipo, 'estado' => $estado]) }}" class="btn btn-link btn-sm w-100" style="font-size:.78rem">Limpiar</a>
            </div>
            @endif
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

    {{-- ── Alta rápida ────────────────────────────────────────────────────── --}}
    <div class="sit-card">
        <h6><i class="bi bi-plus-square me-1"></i>Alta rápida <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400">— solo nombre y tipo; el resto se completa después</span></h6>
        <form method="POST" action="{{ route('admin.sitios.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label" style="font-size:.75rem">Código</label>
                <input type="text" name="codigo" class="form-control form-control-sm" value="{{ old('codigo') }}" placeholder="51">
            </div>
            <div class="col-md-5">
                <label class="form-label" style="font-size:.75rem">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control form-control-sm" value="{{ old('nombre') }}" required placeholder="Campo Las Palmas">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.75rem">Tipo <span class="text-danger">*</span></label>
                <select name="tipo" class="form-select form-select-sm" required>
                    @foreach(Sitio::TIPOS as $k => $label)
                        <option value="{{ $k }}" @selected(old('tipo', $tipo ?: 'campo') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Crear</button>
            </div>
        </form>
    </div>

    {{-- ── Listado ────────────────────────────────────────────────────────── --}}
    @if($sitios->isEmpty())
        <div class="sit-card text-center text-muted py-5" style="font-size:.85rem">
            No hay sitios con estos filtros. Crea uno arriba, o
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
                    <h5>{{ $s->titulo }}</h5>
                    <span class="sit-tipo"><i class="bi {{ $s->icono }} me-1"></i>{{ $s->tipo_label }}</span>
                    <span class="sit-badge" style="background:{{ $s->estado_enlace_color }}">{{ $s->estado_enlace_label }}</span>
                    <div class="sit-meta">
                        @if($s->comuna)<span><i class="bi bi-geo me-1"></i>{{ $s->comuna }}</span>@endif
                        @if($s->equipos_count)<span><i class="bi bi-hdd-network me-1"></i>{{ $s->equipos_count }} equipos</span>@endif
                        @if($s->tecnico)<span><i class="bi bi-person me-1"></i>{{ Str::before($s->tecnico->name, ' ') }}</span>@endif
                    </div>
                    <div class="sit-comp" title="Completitud de la ficha: {{ $s->completitud }}%">
                        <span style="width:{{ $s->completitud }}%;background:{{ $s->completitud >= 80 ? '#16a34a' : ($s->completitud >= 40 ? '#d97706' : '#dc2626') }}"></span>
                    </div>
                    <div style="font-size:.66rem;color:#94a3b8;margin-top:2px">Ficha {{ $s->completitud }}% completa</div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
