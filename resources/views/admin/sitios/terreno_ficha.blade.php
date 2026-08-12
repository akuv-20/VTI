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

    /* Opciones como botones: un toque en vez de abrir un desplegable y buscar.
       Con 52 sitios que llenar, esa diferencia se nota.
       48 px de alto es el mínimo que recomienda Android para tocar con el dedo;
       esto se usa de pie en un campo, a veces con guantes. */
    .tf-opts { display:flex; gap:.35rem; flex-wrap:wrap; }
    /* Texto libre con un atajo al valor más repetido. El botón no achica el
       campo por debajo de lo escribible en un teléfono. */
    .tf-nohay { display:flex; gap:.35rem; }
    .tf-nohay input { flex:1 1 auto; min-width:0; }
    .tf-nohay button { flex:0 0 auto; font-size:.8rem; white-space:nowrap; }
    .tf-nohay button.puesto { background:#e2e8f0; border-color:#cbd5e1; color:#334155; }
    .tf-opts .btn { flex:1 1 auto; min-height:48px; min-width:52px; font-size:.85rem;
                    padding:.45rem .3rem; display:flex; align-items:center; justify-content:center; }
    .tf-opts .btn-check:checked + .btn { background:#7c3aed; border-color:#7c3aed; color:#fff; }

    /* Cobertura: una fila por operador, con el nombre a la izquierda.
       El nombre va angosto y las etiquetas abreviadas para que los cuatro
       botones entren en una sola línea a 375 px. */
    .tf-op { display:flex; align-items:center; gap:.4rem; margin-bottom:.4rem; }
    .tf-op-nombre { flex:0 0 62px; font-size:.78rem; font-weight:600; color:#334155; }
    .tf-op .tf-opts { flex:1 1 auto; gap:.25rem; flex-wrap:nowrap; }
    .tf-op .tf-opts .btn { font-size:.76rem; padding:.4rem .1rem; min-width:0; }
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

        {{-- Avisos del modo sin conexión: borrador recuperado o ficha en cola. --}}
        <div id="tfAviso" class="mb-2" style="display:none;font-size:.8rem;border-radius:9px;padding:.55rem .7rem"></div>

        <form method="POST" action="{{ route('admin.sitios.terreno.guardar', $sitio) }}"
              enctype="multipart/form-data" id="formTerreno" data-sitio="{{ $sitio->id }}">
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
                    <select name="enlace_tipo" id="selTipoEnlace" class="form-select">
                        <option value="">— No definido —</option>
                        @foreach(Sitio::ENLACE_TIPOS as $k => $l)
                            <option value="{{ $k }}" @selected($sitio->enlace_tipo === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Ancho de banda y proveedor solo tienen sentido si llega algo:
                     preguntarlos en un campo sin enlace es ruido, y contarlos como
                     faltantes castigaría una visita bien hecha. Se muestran y se
                     exigen únicamente cuando el tipo no es «Sin enlace». --}}
                <div id="bloqueEnlace" @if(!$sitio->tieneEnlace()) hidden @endif>
                    <div class="tf-f">
                        <label>Ancho de banda (Internet)</label>
                        <input type="text" name="ancho_banda" class="form-control"
                               value="{{ $sitio->ancho_banda }}" placeholder="100/100 Mbps">
                    </div>
                    <div class="tf-f mb-0">
                        <label>Proveedor</label>
                        <select name="isp_id" class="form-select">
                            <option value="">— Sin definir —</option>
                            @foreach($companias as $c)
                                <option value="{{ $c->id }}" @selected($sitio->isp_id === $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-shield-lock me-1"></i>VPN al datacenter</h6>
                @include('admin.sitios._tri', ['name' => 'vpn', 'label' => '¿Tiene VPN al datacenter?', 'valor' => $sitio->vpn])
                <div class="tf-f mb-0" style="font-size:.74rem;color:#64748b">
                    Responde «No» explícitamente cuando no la tenga: sin eso, en el informe
                    no se distingue de un sitio que nadie revisó.
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

                {{-- Se sugieren desde las coordenadas, pero mandan estos campos:
                     el servicio de geocodificación se equivoca en zonas rurales y
                     quien está parado ahí sabe mejor. --}}
                <div class="row g-2">
                    <div class="col-6 tf-f">
                        <label>Región</label>
                        <input type="text" name="region" id="inpRegion" class="form-control"
                               value="{{ $sitio->region }}">
                    </div>
                    <div class="col-6 tf-f">
                        <label>Comuna</label>
                        <input type="text" name="comuna" id="inpComuna" class="form-control"
                               value="{{ $sitio->comuna }}">
                    </div>
                </div>
                <div id="geoMsg" style="font-size:.72rem;color:#64748b;margin:-.4rem 0 .8rem"></div>
            </div>

            {{-- «Cómo llegar», el encargado y su teléfono salieron de acá: en los
                 12 levantamientos reales el acceso se escribió 0 veces y el
                 teléfono 0 veces. La ruta la cuenta mejor una foto de categoría
                 «Entorno / acceso», y el contacto se llena en el escritorio. --}}

            <div class="tf-card">
                <h6><i class="bi bi-people me-1"></i>Tamaño</h6>
                <div class="row g-2">
                    <div class="col-6 tf-f mb-0">
                        <label>Usuarios</label>
                        <input type="number" name="usuarios_cant" class="form-control" value="{{ $sitio->usuarios_cant }}" inputmode="numeric">
                    </div>
                    <div class="col-6 tf-f mb-0">
                        <label>PCs / equipos</label>
                        <input type="number" name="pcs_cant" class="form-control" value="{{ $sitio->pcs_cant }}" inputmode="numeric">
                    </div>
                </div>
            </div>

            {{-- ══════════ Evaluación de un sitio sin enlace ══════════════════
                 Estos bloques son el objetivo de la visita a los campos nuevos:
                 no describen lo que hay instalado (no hay nada), sino qué haría
                 falta para llevarles conectividad. --}}

            <div class="tf-card">
                <h6><i class="bi bi-reception-4 me-1"></i>Cobertura móvil</h6>
                <div style="font-size:.74rem;color:#64748b;margin-bottom:.6rem">
                    Mide con el teléfono de cada compañía, parado donde iría la antena.
                </div>

                @foreach(Sitio::OPERADORES as $campo => $operador)
                    <div class="tf-op">
                        <div class="tf-op-nombre">{{ $operador }}</div>
                        <div class="tf-opts">
                            @foreach(Sitio::COBERTURA_CORTA as $k => $l)
                                <input type="radio" class="btn-check" name="{{ $campo }}"
                                       id="{{ $campo }}_{{ $k }}" value="{{ $k }}"
                                       @checked($sitio->$campo === $k)>
                                <label class="btn btn-outline-secondary" for="{{ $campo }}_{{ $k }}"
                                       title="{{ Sitio::COBERTURA[$k] }}">{{ $l }}</label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="tf-f mb-0 mt-2">
                    <label>Notas de señal (dBm, dónde medí)</label>
                    <textarea name="cob_notas" class="form-control" rows="2">{{ $sitio->cob_notas }}</textarea>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-lightning-charge me-1"></i>Energía</h6>

                <div class="tf-f">
                    <label>¿Hay energía eléctrica?</label>
                    <div class="tf-opts">
                        {{-- El «—» ya no viene marcado: antes era el estado inicial y
                             pasarlo por alto no se notaba. De 12 visitas, 9 salieron
                             sin responder esta pregunta teniéndola al frente. --}}
                        @foreach(Sitio::EVAL_ENERGIA as $k => $l)
                            <input type="radio" class="btn-check" name="eval_energia" id="ene_{{ $k }}" value="{{ $k }}"
                                   @checked($sitio->eval_energia === $k)>
                            <label class="btn btn-outline-secondary" for="ene_{{ $k }}">{{ $l }}</label>
                        @endforeach
                    </div>
                </div>

                @include('admin.sitios._tri', ['name' => 'eval_energia_estable', 'label' => '¿La energía es estable? (según el encargado)', 'valor' => $sitio->eval_energia_estable])
            </div>

            {{-- «¿Tienen algún internet hoy?» se fue: lo dice el estado del enlace
                 de arriba —«Solo Internet» más el tipo— sin preguntarlo dos veces.
                 La línea de vista hacia otro sitio también salió: rara vez aplica
                 y en 12 visitas nadie escribió hacia dónde ni a qué distancia. --}}

            <div class="tf-card">
                <h6><i class="bi bi-broadcast-pin me-1"></i>Viabilidad del enlace</h6>

                @include('admin.sitios._tri', ['name' => 'eval_cielo_despejado', 'label' => '¿Cielo despejado? (para Starlink)', 'valor' => $sitio->eval_cielo_despejado])

                <div class="tf-f mb-0">
                    <label>¿Hay fibra en la zona?</label>
                    <div class="tf-opts">
                        <input type="radio" class="btn-check" name="eval_fibra_zona" id="fz_na" value=""
                               @checked(!$sitio->eval_fibra_zona)>
                        <label class="btn btn-outline-secondary" for="fz_na">—</label>
                        @foreach(Sitio::EVAL_FIBRA_ZONA as $k => $l)
                            <input type="radio" class="btn-check" name="eval_fibra_zona" id="fz_{{ $k }}" value="{{ $k }}"
                                   @checked($sitio->eval_fibra_zona === $k)>
                            <label class="btn btn-outline-secondary" for="fz_{{ $k }}">{{ $l }}</label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="tf-card">
                <h6><i class="bi bi-building-up me-1"></i>Dónde montar</h6>

                <div class="tf-f">
                    <label>Punto de montaje disponible</label>
                    <select name="eval_punto_montaje" class="form-select">
                        <option value="">— Sin evaluar —</option>
                        @foreach(Sitio::EVAL_PUNTO_MONTAJE as $k => $l)
                            <option value="{{ $k }}" @selected($sitio->eval_punto_montaje === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col-5 tf-f">
                        <label>Altura (m)</label>
                        <input type="text" name="eval_altura_m" class="form-control"
                               value="{{ $sitio->eval_altura_m }}" inputmode="decimal">
                    </div>
                    <div class="col-7 tf-f">
                        <label>Sala de equipos</label>
                        <select name="eval_sala_equipos" class="form-select">
                            <option value="">— Sin evaluar —</option>
                            @foreach(Sitio::EVAL_SALA_EQUIPOS as $k => $l)
                                <option value="{{ $k }}" @selected($sitio->eval_sala_equipos === $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Siguen siendo texto libre —"Gabinete mural 9U", "Grupo
                     Electrogeno"— pero el caso más común en estos campos es que no
                     haya nada: en 10 de 15 fichas está escrito «No hay» a mano. El
                     botón lo pone de un toque y el campo se puede editar igual. --}}
                <div class="tf-f">
                    <label>Gabinete / rack</label>
                    <div class="tf-nohay">
                        <input type="text" name="gabinete" class="form-control"
                               value="{{ $sitio->gabinete }}" placeholder="Gabinete mural 9U…">
                        <button type="button" class="btn btn-outline-secondary" data-nohay="gabinete">No hay</button>
                    </div>
                </div>
                <div class="tf-f mb-0">
                    <label>UPS</label>
                    <div class="tf-nohay">
                        <input type="text" name="ups_modelo" class="form-control"
                               value="{{ $sitio->ups_modelo }}" placeholder="APC 1500VA…">
                        <button type="button" class="btn btn-outline-secondary" data-nohay="ups_modelo">No hay</button>
                    </div>
                </div>
            </div>

            {{-- Fuera del móvil, a la ficha de escritorio: «qué se necesita» y la
                 conclusión de la visita —solución, orden, costo, acciones— son
                 decisiones que se toman después, con los presupuestos a la vista.
                 Parado en el campo se llenaron entre 0 y 2 veces de 12. --}}

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

                @if($sitio->notas)
                    {{-- Lo anterior se muestra pero no se edita: desde el celular
                         se agrega, no se pisa lo que ya estaba en la ficha. --}}
                    <div style="font-size:.75rem;color:#475569;white-space:pre-wrap;background:#f8fafc;
                                border:1px solid #e2e8f0;border-radius:8px;padding:.55rem .65rem;
                                margin-bottom:.7rem;max-height:170px;overflow:auto">{{ $sitio->notas }}</div>
                @endif

                <div class="tf-f mb-0">
                    <label>Agregar nota</label>
                    <textarea name="nota_nueva" class="form-control" rows="4"
                              placeholder="Qué encontraste, qué falta, qué hay que gestionar…"></textarea>
                    <div style="font-size:.72rem;color:#64748b;margin-top:.25rem">
                        Se agrega fechada y con tu nombre. No reemplaza lo anterior.
                    </div>
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

@push('styles')
<link rel="manifest" href="/manifest.webmanifest">
@endpush

@push('scripts')
{{-- Archivo estático (no pasa por Vite) para que el service worker lo guarde
     con una URL estable y siga estando ahí sin conexión. --}}
<script src="/js/terreno-offline.js?v={{ filemtime(public_path('js/terreno-offline.js')) }}"></script>
<script>
// ── Ancho de banda y proveedor solo si hay enlace ────────────────────────────
// Se ocultan en vez de deshabilitarse: un input deshabilitado no se envía y en
// terreno el formulario se guarda en IndexedDB tal cual, así que un campo que
// desaparece del envío se leería como "lo borró" al sincronizar.
(() => {
    const tipo   = document.getElementById('selTipoEnlace');
    const bloque = document.getElementById('bloqueEnlace');
    if (!tipo || !bloque) return;

    const refrescar = () => {
        bloque.hidden = (tipo.value === '' || tipo.value === 'ninguno');
    };
    tipo.addEventListener('change', refrescar);
    refrescar();
})();

// ── Atajo «No hay» para los campos de texto libre ────────────────────────────
document.querySelectorAll('[data-nohay]').forEach(btn => {
    const campo = document.querySelector('[name="' + btn.dataset.nohay + '"]');
    if (!campo) return;

    const marcar = () => btn.classList.toggle('puesto', campo.value.trim().toLowerCase() === 'no hay');

    btn.addEventListener('click', () => {
        // Segundo toque: se arrepintió y quiere escribir el modelo de verdad.
        campo.value = campo.value.trim().toLowerCase() === 'no hay' ? '' : 'No hay';
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        marcar();
        if (!campo.value) campo.focus();
    });
    campo.addEventListener('input', marcar);
    marcar();
});

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

            sugerirComuna(lat, lon);
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

// ── Sugerencia de comuna y región a partir del GPS ───────────────────────────
// Es solo una ayuda: nunca pisa lo que ya esté escrito, y sin señal simplemente
// no sugiere nada. Cuando la ficha se sincroniza, el servidor lo intenta de
// nuevo si esos campos siguen vacíos.
async function sugerirComuna(lat, lon) {
    const region = document.getElementById('inpRegion');
    const comuna = document.getElementById('inpComuna');
    const aviso  = document.getElementById('geoMsg');

    if (region.value.trim() && comuna.value.trim()) return;

    aviso.textContent = 'Buscando comuna…';

    try {
        const control = new AbortController();
        setTimeout(() => control.abort(), 8000);

        const resp = await fetch('{{ route('admin.sitios.geocodificar') }}?lat=' + lat + '&lon=' + lon,
                                 { credentials: 'same-origin', signal: control.signal });
        if (!resp.ok) throw new Error();

        const d = await resp.json();
        let puestos = [];

        if (d.region && !region.value.trim()) { region.value = d.region; puestos.push('región'); }
        if (d.comuna && !comuna.value.trim()) { comuna.value = d.comuna; puestos.push('comuna'); }

        aviso.innerHTML = puestos.length
            ? '<span style="color:#15803d">Sugerido desde la ubicación (' + puestos.join(' y ') + '). Corrígelo si no calza.</span>'
            : 'No se pudo determinar la comuna desde la ubicación.';
    } catch {
        aviso.textContent = 'Sin señal para buscar la comuna; escríbela a mano o se completa al sincronizar.';
    }
}

// ── Captura sin conexión ─────────────────────────────────────────────────────
// Los campos que se evalúan están sin enlace y algunos sin señal de celular.
// Todo lo que se escribe se guarda en el teléfono; si el envío no sale, la ficha
// queda en cola y se manda apenas haya red.
(function () {
    const T    = window.VtiTerreno;
    const form = document.getElementById('formTerreno');

    // Si la librería no cargó (caché incompleto, red a medias), NO se toca el
    // formulario: mejor que envíe como siempre a que se quede sin hacer nada
    // porque el manejador de envío reventó a mitad de camino.
    if (!T || !form) {
        if (form) {
            const av = document.getElementById('tfAviso');
            av.style.cssText = 'display:block;background:#fffbeb;border:1px solid #fde68a;color:#b45309;' +
                               'font-size:.8rem;border-radius:9px;padding:.55rem .7rem';
            av.textContent = 'Modo sin conexión no disponible en esta carga. El formulario se envía directo: necesitas señal.';
        }
        return;
    }

    const sitioId = parseInt(form.dataset.sitio, 10);
    const aviso   = document.getElementById('tfAviso');
    const inpFotos = document.getElementById('inpFotos');
    const prev    = document.getElementById('prevFotos');
    const btn     = form.querySelector('.tf-guardar button');

    // Fotos ya reducidas, en memoria, listas para guardar o enviar.
    let fotos = [];

    function mostrarAviso(texto, tono, accion) {
        const tonos = {
            info: ['#eff6ff', '#bfdbfe', '#1d4ed8'],
            ok:   ['#f0fdf4', '#bbf7d0', '#15803d'],
            warn: ['#fffbeb', '#fde68a', '#b45309'],
        }[tono] || ['#f8fafc', '#e2e8f0', '#475569'];

        aviso.style.background   = tonos[0];
        aviso.style.border       = '1px solid ' + tonos[1];
        aviso.style.color        = tonos[2];
        aviso.style.display      = 'block';
        aviso.textContent        = texto;

        if (accion) {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'btn btn-link btn-sm p-0 ms-2';
            a.style.fontSize = '.78rem';
            a.textContent = accion.texto;
            a.addEventListener('click', accion.alTocar);
            aviso.appendChild(a);
        }
    }

    function pintarPrevias() {
        prev.innerHTML = '';
        fotos.slice(0, 12).forEach(f => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f.blob);
            prev.appendChild(img);
        });
    }

    async function persistir(estado) {
        await T.guardar({
            sitioId,
            url: form.getAttribute('action'),
            campos: T.recolectar(form),
            fotos,
            estado: estado || 'borrador',
            actualizado: Date.now(),
        });
    }

    // Guardado del borrador con un respiro, para no escribir en cada tecla.
    let temporizador = null;
    const persistirPronto = () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => persistir('borrador'), 700);
    };
    form.addEventListener('input',  persistirPronto);
    form.addEventListener('change', persistirPronto);

    // ── Fotos: se reducen al elegirlas, no al enviar ─────────────────────────
    inpFotos.addEventListener('change', async (ev) => {
        const elegidas = [...ev.target.files].slice(0, 12);
        if (!elegidas.length) return;

        mostrarAviso('Procesando ' + elegidas.length + ' foto(s)…', 'info');
        let pesoOriginal = 0, pesoFinal = 0;

        for (const archivo of elegidas) {
            pesoOriginal += archivo.size;
            const reducida = await T.reducirImagen(archivo);
            pesoFinal += reducida.size;
            fotos.push({ blob: reducida, nombre: reducida.name });
        }

        // El input se limpia: las fotos ya viven en `fotos` y se envían desde ahí.
        // Si se dejaran en el input, se subirían dos veces y sin reducir.
        ev.target.value = '';

        pintarPrevias();
        await persistir('borrador');

        const mb = (b) => (b / 1048576).toFixed(1);
        mostrarAviso(fotos.length + ' foto(s) listas · ' + mb(pesoOriginal) + ' MB → ' + mb(pesoFinal) + ' MB', 'ok');
    });

    // ── Envío ────────────────────────────────────────────────────────────────
    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();

        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando…';

        const registro = {
            sitioId,
            url: form.getAttribute('action'),
            campos: T.recolectar(form),
            fotos,
            estado: 'pendiente',
            actualizado: Date.now(),
        };

        // Se guarda ANTES de intentar enviar: si el envío falla o el navegador
        // se cierra en medio, el levantamiento ya está a salvo en el teléfono.
        await T.guardar(registro);

        const r = await T.enviar(registro);

        if (r.ok) {
            await T.borrar(sitioId);
            window.location = '{{ route('admin.sitios.terreno') }}';
            return;
        }

        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar levantamiento';

        if (r.sesion) {
            mostrarAviso('Tu sesión caducó. La ficha quedó guardada en el teléfono: entra de nuevo y sincroniza desde el listado.', 'warn');
        } else {
            mostrarAviso('Sin señal. La ficha quedó guardada en el teléfono y se enviará cuando sincronices.', 'warn');
        }
    });

    // ── Al abrir: recuperar lo que hubiera guardado ──────────────────────────
    (async function () {
        await T.registrarSw();

        const guardado = await T.leer(sitioId);
        if (!guardado) return;

        T.aplicar(form, guardado.campos);
        fotos = guardado.fotos || [];
        pintarPrevias();

        const cuando = T.haceCuanto(guardado.actualizado);

        if (guardado.estado === 'pendiente') {
            mostrarAviso('Esta ficha está guardada en el teléfono sin enviar (' + cuando + '). Toca Guardar para reintentar.', 'warn');
        } else {
            mostrarAviso('Borrador recuperado del teléfono (' + cuando + ').', 'info', {
                texto: 'Descartar',
                alTocar: async () => { await T.borrar(sitioId); window.location.reload(); },
            });
        }
    })();
})();
</script>
@endpush
@endsection
