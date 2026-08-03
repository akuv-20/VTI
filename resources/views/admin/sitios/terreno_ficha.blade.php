@extends('layouts.app')

@php use App\Models\Sitio; use App\Models\SitioFoto; @endphp

@section('content')
<style>
    .tf-wrap { max-width:620px; margin:0 auto; }
    .tf-card { background:#fff; border:1px solid #e2e8f0; border-radius:11px; padding:1rem 1.1rem; margin-bottom:.9rem; }
    .tf-card h6 { font-size:.74rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.75rem; }
    .tf-f { margin-bottom:.8rem; }
    .tf-f label { font-size:.76rem; color:#475569; font-weight:600; display:block; margin-bottom:.25rem; }
    /* Controles grandes: se usan con el dedo, no con mouse */
    .tf-f .form-control, .tf-f .form-select { font-size:1rem; padding:.55rem .7rem; }
    .tf-gps { display:flex; gap:.5rem; align-items:center; }
    .tf-gps input { flex:1 1 auto; }
    .tf-fotos { display:grid; grid-template-columns:repeat(auto-fill,minmax(88px,1fr)); gap:.5rem; margin-bottom:.6rem; }
    .tf-fotos img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; }
    .tf-guardar { position:sticky; bottom:0; padding:.8rem 0; background:linear-gradient(to top, #e8ecf0 60%, transparent); }
    .tf-guardar button { font-size:1rem; padding:.7rem; font-weight:600; }
    .tf-prev { display:grid; grid-template-columns:repeat(auto-fill,minmax(88px,1fr)); gap:.5rem; margin-top:.5rem; }
    .tf-prev img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; border:2px solid #7c3aed; }
</style>

<div class="container-fluid vti-page">
    <div class="tf-wrap">

        <div class="vti-page-header">
            <h4><i class="bi {{ $sitio->icono }} me-2" style="color:#7c3aed"></i>{{ $sitio->titulo }}</h4>
            <a href="{{ route('admin.sitios.terreno') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:.78rem;color:#64748b">
            <span>Ficha {{ $sitio->completitud }}% completa</span>
            <a href="{{ route('admin.sitios.show', $sitio) }}" style="font-size:.75rem">Ficha completa (escritorio) →</a>
        </div>

        <form method="POST" action="{{ route('admin.sitios.terreno.guardar', $sitio) }}" enctype="multipart/form-data">
            @csrf

            <div class="tf-card">
                <h6><i class="bi bi-ethernet me-1"></i>Estado del enlace</h6>
                <div class="tf-f">
                    <label>¿Cómo está el enlace hoy?</label>
                    <select name="estado_enlace" class="form-select">
                        @foreach(Sitio::ESTADOS_ENLACE as $k => $l)
                            <option value="{{ $k }}" @selected($sitio->estado_enlace === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tf-f">
                    <label>Tipo de enlace</label>
                    <select name="enlace_tipo" class="form-select">
                        <option value="">— No definido —</option>
                        @foreach(Sitio::ENLACE_TIPOS as $k => $l)
                            <option value="{{ $k }}" @selected($sitio->enlace_tipo === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-geo-alt me-1"></i>Ubicación</h6>

                <button type="button" class="btn btn-primary w-100" id="btnGps" style="font-size:1rem;padding:.7rem">
                    <i class="bi bi-crosshair me-1"></i>Usar mi ubicación actual
                </button>
                <div id="gpsMsg" class="mt-1 mb-2" style="font-size:.74rem;color:#64748b">
                    Párate en el punto que quieras dejar marcado (portón, sala de equipos) y toca el botón.
                </div>

                <div class="tf-f">
                    <label>Link de Google Maps</label>
                    <input type="text" name="maps_url" id="mapsUrl" class="form-control" value="{{ $sitio->maps_url }}"
                           placeholder="Se llena solo con el botón, o pega el link desde Maps">
                    <a href="#" id="verMaps" target="_blank" rel="noopener"
                       class="btn btn-outline-secondary btn-sm w-100 mt-2" style="display:none">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir en Maps para verificar
                    </a>
                </div>

                <details class="tf-f" @if(!$sitio->latitud) style="opacity:.85" @endif>
                    <summary style="font-size:.76rem;color:#64748b;cursor:pointer">Coordenadas exactas</summary>
                    <div class="tf-gps mt-2">
                        <input type="text" name="latitud" id="lat" class="form-control" value="{{ $sitio->latitud }}" placeholder="Latitud" inputmode="decimal">
                        <input type="text" name="longitud" id="lon" class="form-control" value="{{ $sitio->longitud }}" placeholder="Longitud" inputmode="decimal">
                    </div>
                </details>

                <div class="tf-f mb-0">
                    <label>Cómo llegar (portón, referencias)</label>
                    <textarea name="acceso" class="form-control" rows="3">{{ $sitio->acceso }}</textarea>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-person me-1"></i>Contacto y tamaño</h6>
                <div class="tf-f">
                    <label>Encargado del sitio</label>
                    <input type="text" name="encargado_nombre" class="form-control" value="{{ $sitio->encargado_nombre }}">
                </div>
                <div class="tf-f">
                    <label>Teléfono</label>
                    <input type="tel" name="encargado_telefono" class="form-control" value="{{ $sitio->encargado_telefono }}" inputmode="tel">
                </div>
                <div class="row g-2">
                    <div class="col-6 tf-f">
                        <label>Usuarios</label>
                        <input type="number" name="usuarios_cant" class="form-control" value="{{ $sitio->usuarios_cant }}" inputmode="numeric">
                    </div>
                    <div class="col-6 tf-f">
                        <label>PCs / equipos</label>
                        <input type="number" name="pcs_cant" class="form-control" value="{{ $sitio->pcs_cant }}" inputmode="numeric">
                    </div>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-camera me-1"></i>Fotos</h6>

                @if($sitio->fotos->isNotEmpty())
                <div class="tf-fotos">
                    @foreach($sitio->fotos->take(8) as $f)
                        <img src="{{ $f->thumb_url }}" alt="">
                    @endforeach
                </div>
                @endif

                <div class="tf-f">
                    <label>Categoría de las fotos nuevas</label>
                    <select name="categoria" class="form-select">
                        @foreach(SitioFoto::CATEGORIAS as $k => $l)
                            <option value="{{ $k }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tf-f mb-0">
                    <label>Tomar o elegir fotos</label>
                    {{-- capture=environment abre la cámara trasera directamente en el celular --}}
                    <input type="file" name="fotos[]" id="inpFotos" class="form-control" accept="image/*" capture="environment" multiple>
                    <div class="tf-prev" id="prevFotos"></div>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-journal-text me-1"></i>Notas del levantamiento</h6>
                <div class="tf-f mb-0">
                    <textarea name="notas" class="form-control" rows="4" placeholder="Qué encontraste, qué falta, qué hay que gestionar…">{{ $sitio->notas }}</textarea>
                </div>
            </div>

            <div class="tf-guardar">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-check-lg me-1"></i>Guardar levantamiento
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ── GPS del navegador (requiere HTTPS) ───────────────────────────────────────
const inpLat = document.getElementById('lat');
const inpLon = document.getElementById('lon');
const inpUrl = document.getElementById('mapsUrl');
const lnkVer = document.getElementById('verMaps');

// El botón "Abrir en Maps" usa el link escrito, o uno armado con las coordenadas.
function refrescarVerMaps() {
    const destino = inpUrl.value.trim() ||
        (inpLat.value && inpLon.value ? 'https://www.google.com/maps?q=' + inpLat.value + ',' + inpLon.value : '');
    lnkVer.href = destino || '#';
    lnkVer.style.display = destino ? '' : 'none';
}
[inpUrl, inpLat, inpLon].forEach(el => el.addEventListener('input', refrescarVerMaps));
refrescarVerMaps();

document.getElementById('btnGps').addEventListener('click', function () {
    const msg = document.getElementById('gpsMsg');
    if (!navigator.geolocation) {
        msg.textContent = 'Este navegador no permite obtener la ubicación.';
        return;
    }
    msg.textContent = 'Obteniendo ubicación…';
    this.disabled = true;

    navigator.geolocation.getCurrentPosition(
        pos => {
            const lat = pos.coords.latitude.toFixed(6);
            const lon = pos.coords.longitude.toFixed(6);
            inpLat.value = lat;
            inpLon.value = lon;
            inpUrl.value = 'https://www.google.com/maps?q=' + lat + ',' + lon;
            refrescarVerMaps();
            msg.innerHTML = '<span style="color:#16a34a">Ubicación tomada · precisión ' +
                Math.round(pos.coords.accuracy) + ' m. Link de Maps listo.</span>';
            document.getElementById('btnGps').disabled = false;
        },
        err => {
            const causas = {
                1: 'Permiso denegado. Autoriza la ubicación en el navegador.',
                2: 'No se pudo determinar la posición. Prueba al aire libre.',
                3: 'Se agotó el tiempo de espera. Intenta de nuevo.',
            };
            msg.innerHTML = '<span style="color:#dc2626">' + (causas[err.code] || err.message) +
                (location.protocol !== 'https:' ? ' El GPS solo funciona con HTTPS.' : '') + '</span>';
            document.getElementById('btnGps').disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
});

// ── Vista previa de las fotos elegidas ───────────────────────────────────────
document.getElementById('inpFotos').addEventListener('change', ev => {
    const cont = document.getElementById('prevFotos');
    cont.innerHTML = '';
    [...ev.target.files].slice(0, 12).forEach(f => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        cont.appendChild(img);
    });
});
</script>
@endpush
@endsection
