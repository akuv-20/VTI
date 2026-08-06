/**
 * Service worker del levantamiento en terreno.
 *
 * Existe por una razón concreta: los 52 campos que hay que evaluar no tienen
 * enlace, y varios están tan aislados que probablemente no haya ni señal de
 * celular. Sin esto la pantalla ni siquiera carga estando allá.
 *
 * Lo que hace: guardar en el teléfono las páginas y los recursos que ya se
 * visitaron, y servirlos cuando no hay red. Los datos que se escriben NO pasan
 * por acá — de eso se encarga IndexedDB en terreno-offline.js.
 *
 * Alcance: se sirve desde la raíz del sitio, así que controla toda la app.
 */

const CACHE = 'vti-terreno-v1';

/* Tiempo que se espera a la red antes de recurrir a lo guardado. En un campo
   con señal intermitente, una petición puede quedar colgada un minuto; sin este
   corte la pantalla se ve congelada aunque haya una copia local lista. */
const ESPERA_RED_MS = 4000;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (ev) => {
    ev.waitUntil((async () => {
        const nombres = await caches.keys();
        await Promise.all(nombres.filter(n => n !== CACHE).map(n => caches.delete(n)));
        await self.clients.claim();
    })());
});

/** Descarga y guarda una lista de URLs (lo dispara el botón "Preparar para terreno"). */
self.addEventListener('message', (ev) => {
    const datos = ev.data || {};
    if (datos.tipo !== 'precargar') return;

    ev.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        let ok = 0, fallos = 0;

        for (const url of datos.urls || []) {
            try {
                const resp = await fetch(url, { credentials: 'same-origin' });
                if (resp.ok) { await cache.put(url, resp.clone()); ok++; } else { fallos++; }
            } catch { fallos++; }

            // Informar avance a la pestaña que lo pidió.
            if (ev.source) ev.source.postMessage({ tipo: 'precarga-avance', ok, fallos, total: (datos.urls || []).length });
        }

        if (ev.source) ev.source.postMessage({ tipo: 'precarga-fin', ok, fallos });
    })());
});

self.addEventListener('fetch', (ev) => {
    const req = ev.request;

    // Solo GET. Los envíos del formulario los maneja la página: si fallan hay
    // que encolarlos, no reintentarlos a ciegas desde acá.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Nunca cachear el propio service worker ni el login.
    if (url.pathname === '/sw.js' || url.pathname === '/login') return;

    if (req.mode === 'navigate') {
        ev.respondWith(redPrimeroLuegoCache(req));
        return;
    }

    // Recursos (CSS, JS, tipografías, iconos e imágenes), propios o de CDN.
    if (['style', 'script', 'font', 'image'].includes(req.destination)) {
        ev.respondWith(cachePrimeroLuegoRed(req));
    }
});

/** Páginas: intenta la red con un corte de tiempo; si no llega, sirve la copia. */
async function redPrimeroLuegoCache(req) {
    const cache = await caches.open(CACHE);

    try {
        const resp = await conTiempoLimite(fetch(req), ESPERA_RED_MS);
        // Solo se guarda lo que salió bien; una página de error o un redirect
        // al login guardados serían peores que no tener nada.
        if (resp && resp.ok && resp.type === 'basic') cache.put(req, resp.clone());
        return resp;
    } catch {
        const guardada = await cache.match(req, { ignoreSearch: true });
        if (guardada) return guardada;

        return new Response(
            paginaSinConexion(),
            { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

/** Recursos: si ya está guardado se usa al tiro, y si no se busca y se guarda. */
async function cachePrimeroLuegoRed(req) {
    const cache    = await caches.open(CACHE);
    const guardado = await cache.match(req);
    if (guardado) return guardado;

    try {
        const resp = await fetch(req);
        // Las respuestas opacas (CDN sin CORS) también se guardan: no se pueden
        // inspeccionar, pero el navegador sabe usarlas igual.
        if (resp && (resp.ok || resp.type === 'opaque')) cache.put(req, resp.clone());
        return resp;
    } catch {
        return Response.error();
    }
}

function conTiempoLimite(promesa, ms) {
    return Promise.race([
        promesa,
        new Promise((_, rechazar) => setTimeout(() => rechazar(new Error('tiempo agotado')), ms)),
    ]);
}

function paginaSinConexion() {
    return `<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sin conexión</title>
<style>
 body{font-family:system-ui,sans-serif;background:#e8ecf0;margin:0;padding:2rem 1.5rem;color:#334155}
 .c{max-width:420px;margin:3rem auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.5rem}
 h1{font-size:1.1rem;margin:0 0 .6rem}
 p{font-size:.88rem;line-height:1.5;margin:.4rem 0}
 a{display:block;text-align:center;margin-top:1.2rem;padding:.7rem;background:#7c3aed;color:#fff;
   border-radius:8px;text-decoration:none;font-weight:600}
</style></head><body><div class="c">
<h1>Esta pantalla no está guardada</h1>
<p>No hay señal y esta página no se descargó antes de salir.</p>
<p>Las fichas que sí alcanzaste a abrir siguen disponibles, y lo que hayas
escrito está guardado en el teléfono esperando señal para enviarse.</p>
<a href="/admin/sitios/terreno">Volver al listado</a>
</div></body></html>`;
}
