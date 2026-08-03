@extends('layouts.app')

@section('content')
<style>
    .en-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.1rem; margin-bottom:1rem; }
    .en-host { border:1px solid #fecaca; border-radius:10px; background:#fff; margin-bottom:.9rem; overflow:hidden; }
    .en-host > .cab { display:flex; align-items:center; gap:.6rem; padding:.7rem .9rem; background:#fef2f2; border-bottom:1px solid #fee2e2; flex-wrap:wrap; }
    .en-host > .cab .nom { font-family:ui-monospace,monospace; font-size:.86rem; font-weight:700; color:#991b1b; }
    .en-host > .cuerpo { padding:.85rem .9rem; }
    .en-usos { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.8rem; }
    .en-uso { display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; text-decoration:none;
              border:1px solid #e2e8f0; border-radius:20px; padding:2px 10px; color:#475569; background:#f8fafc; }
    .en-uso:hover { border-color:#94a3b8; color:#1e293b; }
    .en-sug { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.7rem; }
    .en-sug button { border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; border-radius:20px;
                     font-size:.72rem; padding:3px 11px; font-family:ui-monospace,monospace; }
    .en-sug button:hover { background:#dcfce7; }
    .en-sug button .pct { font-family:system-ui,sans-serif; opacity:.6; margin-left:5px; }
    .en-sug button.ocupado { border-color:#fde68a; background:#fffbeb; color:#92400e; }
    .en-vacio { text-align:center; padding:2.4rem 1rem; color:#64748b; }
    .en-vacio i { font-size:2.6rem; color:#86efac; display:block; margin-bottom:.6rem; }
    .en-nota { font-size:.74rem; color:#64748b; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-link-45deg me-2" style="color:#7c3aed"></i>Enlaces con CheckMK</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sitios.descubrimiento') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search me-1"></i>Descubrir hosts
            </a>
            <a href="{{ route('admin.sitios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Sitios</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2" style="font-size:.84rem">{{ session('success') }}</div>
    @endif
    @foreach($errors->all() as $e)
        <div class="alert alert-danger py-2" style="font-size:.84rem">{{ $e }}</div>
    @endforeach

    @if(!$ok)
        <div class="alert alert-warning">
            <b><i class="bi bi-exclamation-triangle me-1"></i>No se pudo revisar</b>
            <div style="font-size:.82rem" class="mt-1">{{ $error }}</div>
            <div style="font-size:.78rem" class="mt-2 text-muted">
                Mientras CheckMK no responda no se marca ningún enlace como roto: un corte de conexión
                no significa que los hosts hayan desaparecido.
            </div>
        </div>
    @elseif(empty($huerfanos))
        <div class="en-card en-vacio">
            <i class="bi bi-check-circle-fill"></i>
            <div style="font-size:.92rem;font-weight:600;color:#166534">Todos los enlaces están sanos</div>
            <div style="font-size:.8rem" class="mt-1">
                Cada host enlazado en fichas, equipos y nodos del mapa existe en CheckMK
                ({{ $total }} hosts monitoreados).
            </div>
        </div>
    @else
        <div class="alert alert-danger py-2">
            <b><i class="bi bi-exclamation-octagon me-1"></i>{{ count($huerfanos) }}
                {{ count($huerfanos) === 1 ? 'host enlazado ya no existe' : 'hosts enlazados ya no existen' }} en CheckMK</b>
            <div style="font-size:.8rem" class="mt-1">
                Pasa cuando allá borran o renombran un host. Mientras tanto, esas fichas y nodos
                se quedan sin estado en vivo. Elige el host nuevo o suelta la referencia.
            </div>
        </div>

        @foreach($huerfanos as $h)
        <div class="en-host">
            <div class="cab">
                <i class="bi bi-plug text-danger"></i>
                <span class="nom">{{ $h['host_name'] }}</span>
                <span class="text-muted" style="font-size:.72rem">
                    · {{ count($h['usos']) }} {{ count($h['usos']) === 1 ? 'referencia' : 'referencias' }}
                </span>
                <form method="POST" action="{{ route('admin.sitios.enlaces.desenlazar') }}" class="ms-auto"
                      onsubmit="return confirm('¿Soltar la referencia a {{ $h['host_name'] }}?\n\nLas fichas, equipos y nodos se conservan, solo quedan sin host asociado.')">
                    @csrf
                    <input type="hidden" name="host_name" value="{{ $h['host_name'] }}">
                    <button class="btn btn-outline-secondary btn-sm py-0" title="Quitar el enlace sin borrar nada más">
                        <i class="bi bi-scissors me-1"></i>Soltar
                    </button>
                </form>
            </div>

            <div class="cuerpo">
                <div class="en-usos">
                    @foreach($h['usos'] as $u)
                        @php
                            $iconos = ['ficha' => 'bi-card-text', 'equipo' => 'bi-hdd-network', 'nodo' => 'bi-diagram-3'];
                            $destino = $u['origen'] === 'nodo'
                                ? route('admin.monitoreo.mapas.show', $u['mapa_id'])
                                : route('admin.sitios.show', $u['sitio_id']);
                        @endphp
                        <a href="{{ $destino }}" class="en-uso" target="_blank">
                            <i class="bi {{ $iconos[$u['origen']] }}"></i>
                            <b>{{ $u['titulo'] }}</b>
                            <span class="text-muted">{{ $u['detalle'] }}</span>
                        </a>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('admin.sitios.enlaces.remapear') }}">
                    @csrf
                    <input type="hidden" name="viejo" value="{{ $h['host_name'] }}">

                    @if(!empty($h['sugerencias']))
                    <div class="en-sug">
                        <span class="en-nota align-self-center me-1">Parecidos:</span>
                        @foreach($h['sugerencias'] as $s)
                        <button type="button" class="btn-sugerencia {{ $s['libre'] ? '' : 'ocupado' }}"
                                data-host="{{ $s['host_name'] }}"
                                title="{{ $s['libre'] ? 'Este host no está enlazado a nada' : 'Ojo: ya está enlazado en otra parte' }}">
                            {{ $s['host_name'] }}<span class="pct">{{ $s['score'] }}%</span>
                        </button>
                        @endforeach
                    </div>
                    @endif

                    <div class="row g-2 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label en-nota mb-1">Host nuevo en CheckMK</label>
                            <input type="text" name="nuevo" class="form-control form-control-sm host-nuevo"
                                   list="dlHostsVivos" required placeholder="Elige o escribe el host que lo reemplaza">
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-left-right me-1"></i>Remapear las {{ count($h['usos']) }} referencias
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endforeach

        <datalist id="dlHostsVivos">
            @foreach($hosts as $host)<option value="{{ $host }}">@endforeach
        </datalist>
    @endif

    <div class="en-card">
        <div class="en-nota">
            <b>Cómo funciona.</b> Se comparan los hosts enlazados en fichas, equipos y nodos del mapa
            contra los que CheckMK devuelve en vivo. Al remapear se actualizan de una vez todas las
            referencias a ese host, así un renombre no obliga a corregir ficha por ficha.
            La revisión se cachea 1 minuto.
        </div>
    </div>
</div>

@push('scripts')
<script>
// Un clic en el host parecido lo escribe en el campo del mismo formulario
document.querySelectorAll('.btn-sugerencia').forEach(b => b.addEventListener('click', () => {
    b.closest('form').querySelector('.host-nuevo').value = b.dataset.host;
}));
</script>
@endpush
@endsection
