@extends('layouts.app')

@php use App\Models\Sitio; @endphp

@section('content')
<style>
    /* El mapa ocupa el alto util de la ventana, no una altura fija: el topbar
       mide --topbar-h y el header de la pantalla ~46 px. */
    .mg-caja { position:relative; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;
               background:#f8fafc; height:calc(100vh - var(--topbar-h) - 104px); min-height:380px; z-index:0; }
    .mg-caja .aviso { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                      text-align:center; padding:1.5rem; font-size:.85rem; color:#94a3b8; }

    /* En pantalla completa manda el elemento, no el layout. */
    .mg-caja:fullscreen { border-radius:0; border:0; height:100vh; }
    .mg-caja:-webkit-full-screen { border-radius:0; border:0; height:100vh; }

    .mg-pin { width:14px; height:14px; border-radius:50%; border:2.5px solid #fff;
              box-shadow:0 1px 4px rgba(15,23,42,.4); }
    .mg-grupo { border-radius:50%; border:3px solid #fff; box-shadow:0 2px 8px rgba(15,23,42,.35);
                color:#fff; font-weight:700; display:flex; align-items:center; justify-content:center;
                font-size:.8rem; font-family:system-ui,sans-serif; }

    .leaflet-popup-content { margin:.6rem .8rem; font-size:.8rem; }
    .mg-pop b { display:block; font-size:.88rem; color:#1e293b; margin-bottom:.25rem; }
    .mg-pop .est { display:inline-block; font-size:.66rem; font-weight:700; padding:1px 7px;
                   border-radius:20px; color:#fff; margin-bottom:.35rem; }
    .mg-pop .dato { font-size:.73rem; color:#64748b; }
    .mg-pop a { display:inline-block; margin-top:.45rem; font-size:.75rem; text-decoration:none; color:#7c3aed; }

    /* Leyenda flotante sobre el mapa, para no gastarle alto. */
    .mg-leg { position:absolute; left:12px; bottom:20px; z-index:500; background:rgba(255,255,255,.94);
              border:1px solid #e2e8f0; border-radius:8px; padding:.5rem .65rem;
              box-shadow:0 2px 10px rgba(15,23,42,.1); font-size:.72rem; }
    .mg-leg .fila { display:flex; align-items:center; gap:.4rem; padding:1px 0; color:#475569; }
    .mg-leg i { width:10px; height:10px; border-radius:50%; display:inline-block; flex:0 0 auto; }
    .mg-sin { font-size:.74rem; color:#94a3b8; margin-top:.5rem; }
</style>

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
@endpush

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-geo-alt-fill me-2" style="color:#7c3aed"></i>Mapa geográfico
            <span class="text-muted fw-normal" style="font-size:.82rem">{{ count($puntos) }} sitios ubicados</span>
        </h4>
        <div class="d-flex gap-2">
            <button type="button" id="mgPantalla" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrows-fullscreen me-1"></i>Pantalla completa
            </button>
            <a href="{{ route('admin.sitios.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pin-map-fill me-1"></i>Sitios
            </a>
        </div>
    </div>

    <div class="mg-caja" id="mgCaja"
         data-puntos="{{ json_encode($puntos) }}"
         data-colores="{{ json_encode(Sitio::COLORES_ENLACE) }}"
         data-gravedad="{{ json_encode(['intermitente', 'sin_enlace', 'solo_internet', 'en_gestion', 'en_instalacion', 'operativo']) }}">

        {{-- Si Leaflet no carga queda este aviso, en vez de un recuadro gris sin
             explicación. Pasa si el equipo no tiene salida a internet. --}}
        <div class="aviso" id="mgAviso">Cargando el mapa…</div>

        <div class="mg-leg" id="mgLeyenda" hidden>
            @foreach(Sitio::ESTADOS_ENLACE as $k => $l)
                <div class="fila"><i style="background:{{ Sitio::COLORES_ENLACE[$k] }}"></i>{{ $l }}</div>
            @endforeach
        </div>
    </div>

    @if($sinCoords->isNotEmpty())
    <div class="mg-sin">
        <i class="bi bi-exclamation-circle me-1"></i>
        {{ $sinCoords->count() }} {{ $sinCoords->count() === 1 ? 'sitio sin coordenadas' : 'sitios sin coordenadas' }},
        así que no aparecen en el mapa:
        @foreach($sinCoords as $s)<a href="{{ route('admin.sitios.show', $s) }}">{{ $s->titulo }}</a>@if(!$loop->last), @endif @endforeach
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const caja = document.getElementById('mgCaja');
    if (!caja) return;

    const aviso = document.getElementById('mgAviso');

    if (typeof L === 'undefined') {
        aviso.textContent = 'No se pudo cargar el mapa. Revisa la conexión a internet del equipo.';
        return;
    }
    aviso.remove();

    const puntos   = JSON.parse(caja.dataset.puntos);
    const colores  = JSON.parse(caja.dataset.colores);
    const gravedad = JSON.parse(caja.dataset.gravedad);

    // Rueda = zoom, sin Ctrl. Acá el mapa es la pantalla entera, así que no hay
    // contenido debajo que uno quisiera alcanzar haciendo scroll.
    const mapa = L.map(caja, { scrollWheelZoom: true, zoomControl: true });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(mapa);

    const esc = s => String(s ?? '').replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** El estado menos avanzado del conjunto: es el que define el color del grupo.
     *  Un grupo verde escondiendo un campo sin enlace diría lo contrario de lo
     *  que este mapa tiene que mostrar. */
    const peor = estados => gravedad.find(g => estados.includes(g)) ?? 'operativo';

    const grupos = L.markerClusterGroup({
        maxClusterRadius: 45,
        showCoverageOnHover: false,
        spiderfyOnMaxZoom: true,      // dos sitios en la misma coordenada se abren en abanico
        iconCreateFunction(cluster) {
            const hijos = cluster.getAllChildMarkers();
            const color = colores[peor(hijos.map(m => m.options.estadoSitio))] ?? '#94a3b8';
            const n     = hijos.length;
            const lado  = n < 10 ? 36 : (n < 100 ? 42 : 48);

            return L.divIcon({
                html: `<div class="mg-grupo" style="width:${lado}px;height:${lado}px;background:${color}">${n}</div>`,
                className: '',
                iconSize: L.point(lado, lado),
            });
        },
    });

    puntos.forEach(p => {
        const marca = L.marker([p.lat, p.lon], {
            estadoSitio: p.estado,
            title: p.nombre,
            icon: L.divIcon({
                html: `<div class="mg-pin" style="background:${p.color}"></div>`,
                className: '',
                iconSize: L.point(14, 14),
                iconAnchor: L.point(7, 7),
            }),
        });

        marca.bindPopup(
            `<div class="mg-pop">
                <b>${esc(p.nombre)}</b>
                <span class="est" style="background:${p.color}">${esc(p.label)}</span>
                <div class="dato">${esc(p.tipo)}${[p.zona, p.comuna].filter(Boolean).map(x => ' · ' + esc(x)).join('')}</div>
                <div class="dato">Ficha ${p.compl}% completa</div>
                <a href="${p.url}">Abrir la ficha →</a>
            </div>`
        );

        grupos.addLayer(marca);
    });

    mapa.addLayer(grupos);

    if (puntos.length) {
        mapa.fitBounds(grupos.getBounds(), { padding: [40, 40], maxZoom: 14 });
    } else {
        mapa.setView([-33.45, -70.67], 8);      // Santiago, por si aún no hay coordenadas
    }

    document.getElementById('mgLeyenda').hidden = false;

    /* ── Pantalla completa ───────────────────────────────────────────────── */

    const btn = document.getElementById('mgPantalla');

    btn.addEventListener('click', () => {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            // El prefijo webkit sigue haciendo falta en Safari.
            (caja.requestFullscreen || caja.webkitRequestFullscreen).call(caja);
        }
    });

    document.addEventListener('fullscreenchange', () => {
        const lleno = !!document.fullscreenElement;
        btn.innerHTML = lleno
            ? '<i class="bi bi-fullscreen-exit me-1"></i>Salir de pantalla completa'
            : '<i class="bi bi-arrows-fullscreen me-1"></i>Pantalla completa';

        // Leaflet mide el contenedor al crearse: si cambia de tamaño hay que
        // avisarle o los tiles quedan cortados a la mitad de la pantalla.
        setTimeout(() => mapa.invalidateSize(), 120);
    });

    // Lo mismo al abrir o cerrar la barra lateral, que cambia el ancho útil.
    window.addEventListener('resize', () => mapa.invalidateSize());
});
</script>
@endpush
@endsection
