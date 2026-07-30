@extends('layouts.app')

@php use App\Models\Sitio; use App\Models\SitioEquipo; use App\Models\SitioHost; @endphp

@section('content')
<style>
    .dc-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.1rem; }
    .dc-card h6 { font-size:.78rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.8rem; display:flex; align-items:center; gap:.4rem; }
    .dc-card h6 .n { margin-left:auto; font-weight:400; text-transform:none; letter-spacing:0; color:#94a3b8; font-size:.72rem; }
    .dc-row { display:flex; align-items:center; gap:.6rem; padding:.55rem .7rem; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:.5rem; flex-wrap:wrap; }
    .dc-host { font-family:ui-monospace,monospace; font-size:.78rem; font-weight:600; }
    .dc-dot { width:9px; height:9px; border-radius:50%; flex:0 0 auto; }
    .dc-sug { font-size:.66rem; background:#eef2ff; color:#4338ca; border-radius:5px; padding:1px 7px; font-weight:600; }
    .dc-row form { display:flex; gap:.35rem; align-items:center; margin-left:auto; flex-wrap:wrap; }
    .dc-row input, .dc-row select { font-size:.75rem; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-search me-2" style="color:#7c3aed"></i>Descubrir hosts de CheckMK</h4>
        <a href="{{ route('admin.sitios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pin-map-fill me-1"></i>Sitios</a>
    </div>

    @if($error)
        <div class="alert alert-warning py-2" style="font-size:.82rem">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>No se pudo consultar CheckMK: {{ $error }}
        </div>
    @endif

    <div class="alert alert-info py-2" style="font-size:.82rem">
        <i class="bi bi-info-circle-fill me-1"></i>
        Hosts monitoreados que <strong>aún no están en ninguna ficha</strong>. El tipo se propone según la convención
        de nombres de tu CheckMK; revísalo antes de crear. Los hosts con sufijo <code>_VPN</code> normalmente son el
        túnel de un sitio que ya existe: conviene <strong>enlazarlos al sitio</strong> en vez de crear una ficha nueva.
    </div>

    {{-- ── Candidatos a sitio ─────────────────────────────────────────────── --}}
    <div class="dc-card">
        <h6><i class="bi bi-pin-map-fill"></i>Candidatos a sitio <span class="n">{{ count($datos['sitios']) }}</span></h6>
        @forelse($datos['sitios'] as $f)
        <div class="dc-row">
            <span class="dc-dot" style="background:{{ ['up'=>'#16a34a','down'=>'#dc2626','downtime'=>'#d97706'][$f['estado']] ?? '#94a3b8' }}"></span>
            <span class="dc-host">{{ $f['host_name'] }}</span>
            @if($f['sugerido'])<span class="dc-sug">{{ Sitio::TIPOS[$f['sugerido']] ?? $f['sugerido'] }}</span>@endif

            @if(Str::endsWith(Str::upper($f['host_name']), '_VPN'))
                {{-- Túnel: enlazar a un sitio existente --}}
                <form method="POST" action="{{ route('admin.sitios.descubrimiento.host') }}">
                    @csrf
                    <input type="hidden" name="host_name" value="{{ $f['host_name'] }}">
                    <input type="hidden" name="rol" value="vpn">
                    <select name="sitio_id" class="form-select form-select-sm" style="width:auto" required>
                        <option value="">Enlazar como VPN de…</option>
                        @foreach($sitios as $s)<option value="{{ $s->id }}">{{ $s->codigo ? $s->codigo . '. ' : '' }}{{ $s->nombre }}</option>@endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm py-0"><i class="bi bi-link-45deg"></i></button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.sitios.descubrimiento.sitio') }}">
                @csrf
                <input type="hidden" name="host_name" value="{{ $f['host_name'] }}">
                <input type="text" name="codigo" class="form-control form-control-sm" style="width:60px" value="{{ $f['codigo'] }}" placeholder="Cód">
                <input type="text" name="nombre" class="form-control form-control-sm" style="width:190px" value="{{ $f['nombre_sugerido'] }}" required>
                <select name="tipo" class="form-select form-select-sm" style="width:auto">
                    @foreach(Sitio::TIPOS as $k => $l)<option value="{{ $k }}" @selected($f['sugerido'] === $k)>{{ $l }}</option>@endforeach
                </select>
                <button type="submit" class="btn btn-success btn-sm py-0"><i class="bi bi-plus-lg me-1"></i>Crear ficha</button>
            </form>
        </div>
        @empty
            <div class="text-muted text-center py-3" style="font-size:.82rem">No quedan sitios por registrar.</div>
        @endforelse
    </div>

    {{-- ── Candidatos a equipo ────────────────────────────────────────────── --}}
    <div class="dc-card">
        <h6><i class="bi bi-hdd-network"></i>Candidatos a equipo de red <span class="n">{{ count($datos['equipos']) }}</span></h6>
        @forelse($datos['equipos'] as $f)
        <div class="dc-row">
            <span class="dc-dot" style="background:{{ ['up'=>'#16a34a','down'=>'#dc2626','downtime'=>'#d97706'][$f['estado']] ?? '#94a3b8' }}"></span>
            <span class="dc-host">{{ $f['host_name'] }}</span>
            @if($f['sugerido'])<span class="dc-sug">{{ SitioEquipo::TIPOS[$f['sugerido']] ?? $f['sugerido'] }}</span>@endif
            <form method="POST" action="{{ route('admin.sitios.descubrimiento.equipo') }}">
                @csrf
                <input type="hidden" name="host_name" value="{{ $f['host_name'] }}">
                <input type="text" name="nombre" class="form-control form-control-sm" style="width:180px" value="{{ $f['nombre_sugerido'] }}" required>
                <select name="tipo" class="form-select form-select-sm" style="width:auto">
                    @foreach(SitioEquipo::TIPOS as $k => $l)<option value="{{ $k }}" @selected($f['sugerido'] === $k)>{{ $l }}</option>@endforeach
                </select>
                <select name="sitio_id" class="form-select form-select-sm" style="width:auto" required>
                    <option value="">¿En qué sitio?</option>
                    @foreach($sitios as $s)<option value="{{ $s->id }}">{{ $s->codigo ? $s->codigo . '. ' : '' }}{{ $s->nombre }}</option>@endforeach
                </select>
                <button type="submit" class="btn btn-success btn-sm py-0"><i class="bi bi-plus-lg"></i></button>
            </form>
        </div>
        @empty
            <div class="text-muted text-center py-3" style="font-size:.82rem">No quedan equipos por registrar.</div>
        @endforelse
    </div>

    {{-- ── Sin clasificar ─────────────────────────────────────────────────── --}}
    @if(count($datos['sin_clasificar']))
    <div class="dc-card">
        <h6><i class="bi bi-question-circle"></i>Sin clasificar <span class="n">{{ count($datos['sin_clasificar']) }} — el nombre no calza con ningún patrón conocido</span></h6>
        @foreach($datos['sin_clasificar'] as $f)
        <div class="dc-row">
            <span class="dc-dot" style="background:{{ ['up'=>'#16a34a','down'=>'#dc2626','downtime'=>'#d97706'][$f['estado']] ?? '#94a3b8' }}"></span>
            <span class="dc-host">{{ $f['host_name'] }}</span>
            <form method="POST" action="{{ route('admin.sitios.descubrimiento.equipo') }}">
                @csrf
                <input type="hidden" name="host_name" value="{{ $f['host_name'] }}">
                <input type="text" name="nombre" class="form-control form-control-sm" style="width:180px" value="{{ $f['nombre_sugerido'] }}" required>
                <select name="tipo" class="form-select form-select-sm" style="width:auto">
                    @foreach(SitioEquipo::TIPOS as $k => $l)<option value="{{ $k }}" @selected($k === 'otro')>{{ $l }}</option>@endforeach
                </select>
                <select name="sitio_id" class="form-select form-select-sm" style="width:auto" required>
                    <option value="">¿En qué sitio?</option>
                    @foreach($sitios as $s)<option value="{{ $s->id }}">{{ $s->codigo ? $s->codigo . '. ' : '' }}{{ $s->nombre }}</option>@endforeach
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-plus-lg"></i></button>
            </form>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
