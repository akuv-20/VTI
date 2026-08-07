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

    /* La barra queda fija bajo el topbar: con muchos hosts se filtra sin volver arriba. */
    .dc-buscar { position:sticky; top:var(--topbar-h); z-index:6; background:#f1f5f9; padding:.55rem 0 .7rem; }
    .dc-buscar .campo { position:relative; }
    .dc-buscar .campo .bi-search { position:absolute; left:.7rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.85rem; }
    .dc-buscar input { padding-left:2.1rem; padding-right:2.1rem; }
    .dc-buscar .limpiar { position:absolute; right:.35rem; top:50%; transform:translateY(-50%);
                          border:0; background:none; color:#94a3b8; line-height:1; padding:.25rem .4rem; }
    .dc-buscar .limpiar:hover { color:#475569; }
    .dc-buscar .cuenta { font-size:.74rem; color:#64748b; white-space:nowrap; }
    [hidden] { display:none !important; }
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

    @php $totalHosts = count($datos['sitios']) + count($datos['equipos']) + count($datos['sin_clasificar']); @endphp

    {{-- Filtro en el navegador, no en el servidor: los hosts ya están todos en la
         página y recargar significaría volver a consultar CheckMK por cada letra. --}}
    <div class="dc-buscar">
        <div class="d-flex align-items-center gap-2">
            <div class="campo flex-grow-1">
                <i class="bi bi-search"></i>
                <input type="search" id="dcQ" class="form-control form-control-sm" autofocus
                       autocomplete="off" spellcheck="false"
                       placeholder="Buscar host, código o nombre propuesto — «19», «campo palmas», «vpn», «switch»…">
                <button type="button" class="limpiar" id="dcLimpiar" hidden title="Limpiar (Esc)">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <span class="cuenta" id="dcCuenta">{{ $totalHosts }} {{ $totalHosts === 1 ? 'host' : 'hosts' }} sin ficha</span>
        </div>
    </div>

    {{-- ── Candidatos a sitio ─────────────────────────────────────────────── --}}
    <div class="dc-card">
        <h6><i class="bi bi-pin-map-fill"></i>Candidatos a sitio
            <span class="n" data-total="{{ count($datos['sitios']) }}">{{ count($datos['sitios']) }}</span></h6>
        @forelse($datos['sitios'] as $f)
        <div class="dc-row" data-buscar="{{ $f['host_name'] }} {{ $f['nombre_sugerido'] }} {{ $f['codigo'] }} {{ Sitio::TIPOS[$f['sugerido']] ?? '' }}">
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
        <h6><i class="bi bi-hdd-network"></i>Candidatos a equipo de red
            <span class="n" data-total="{{ count($datos['equipos']) }}">{{ count($datos['equipos']) }}</span></h6>
        @forelse($datos['equipos'] as $f)
        <div class="dc-row" data-buscar="{{ $f['host_name'] }} {{ $f['nombre_sugerido'] }} {{ SitioEquipo::TIPOS[$f['sugerido']] ?? '' }}">
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
        <h6><i class="bi bi-question-circle"></i>Sin clasificar
            <span class="n" data-total="{{ count($datos['sin_clasificar']) }}"
                  data-cola=" — el nombre no calza con ningún patrón conocido">{{ count($datos['sin_clasificar']) }} — el nombre no calza con ningún patrón conocido</span></h6>
        @foreach($datos['sin_clasificar'] as $f)
        <div class="dc-row" data-buscar="{{ $f['host_name'] }} {{ $f['nombre_sugerido'] }}">
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

    <div class="dc-card dc-sin-nada" hidden>
        <div class="text-center py-3" style="font-size:.85rem;color:#64748b">
            <i class="bi bi-search d-block mb-2" style="font-size:1.6rem;color:#cbd5e1"></i>
            Ningún host coincide con <b id="dcEco"></b>.
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const q      = document.getElementById('dcQ');
    const limpia = document.getElementById('dcLimpiar');
    const cuenta = document.getElementById('dcCuenta');
    const eco    = document.getElementById('dcEco');
    const vacio  = document.querySelector('.dc-sin-nada');
    if (!q) return;

    const filas    = [...document.querySelectorAll('.dc-row')];
    const tarjetas = [...document.querySelectorAll('.dc-card:not(.dc-sin-nada)')];
    const TOTAL    = filas.length;

    // Sin tildes y en minúsculas de los dos lados: quien escribe "camion" en el
    // teléfono no debería perderse un host llamado "Camión".
    const norm = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    filas.forEach(f => f.dataset.norm = norm(f.dataset.buscar || ''));

    const plural = n => n + (n === 1 ? ' host' : ' hosts');

    function filtrar() {
        // Cada palabra por separado: "campo palmas" encuentra "19.CAMPO_LAS_PALMAS".
        const terminos = norm(q.value).split(/\s+/).filter(Boolean);
        let visibles = 0;

        filas.forEach(f => {
            const ok = terminos.every(t => f.dataset.norm.includes(t));
            f.hidden = !ok;
            if (ok) visibles++;
        });

        tarjetas.forEach(c => {
            const propias = [...c.querySelectorAll('.dc-row')];
            const vistas  = propias.filter(f => !f.hidden).length;

            // La tarjeta sin coincidencias se oculta entera en vez de repetir tres
            // veces el mismo "no hay resultados". Con la búsqueda vacía vuelve a su
            // estado original, incluido el "no quedan… por registrar" cuando de
            // verdad está vacía.
            c.hidden = terminos.length > 0 && vistas === 0;

            const n = c.querySelector('.n');
            if (n) {
                const total = n.dataset.total;
                n.textContent = (terminos.length ? vistas + ' de ' + total : total) + (n.dataset.cola || '');
            }
        });

        cuenta.textContent = terminos.length
            ? plural(visibles) + ' de ' + TOTAL
            : plural(TOTAL) + ' sin ficha';

        if (vacio) {
            vacio.hidden = !(terminos.length && visibles === 0);
            if (eco) eco.textContent = '«' + q.value.trim() + '»';
        }
        limpia.hidden = q.value === '';
    }

    q.addEventListener('input', filtrar);
    q.addEventListener('keydown', e => { if (e.key === 'Escape') { q.value = ''; filtrar(); } });
    limpia.addEventListener('click', () => { q.value = ''; q.focus(); filtrar(); });

    filtrar(); // el navegador puede restaurar el texto al volver atrás
})();
</script>
@endpush
@endsection
