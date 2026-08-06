/**
 * Levantamiento en terreno sin conexión.
 *
 * Guarda en el teléfono lo que se escribe (IndexedDB) y lo envía cuando hay
 * señal. Pensado para recorrer campos sin enlace, donde el formulario se llena
 * completo y recién horas después aparece red.
 *
 * Dos decisiones que conviene tener presentes:
 *
 * 1. NO se confía en `navigator.onLine`. Devuelve true con solo estar pegado a
 *    un WiFi o a una antena, aunque no haya salida a internet — justo lo que
 *    pasa en un campo. En vez de preguntarle, se intenta enviar con un corte de
 *    tiempo: si no llega, se encola.
 *
 * 2. El token CSRF NO se guarda junto a la ficha. La sesión dura una hora y el
 *    levantamiento puede quedar encolado toda la tarde; un token viejo haría
 *    fallar el envío con un 419. Se toma el de la página en el momento de
 *    sincronizar.
 */
window.VtiTerreno = (function () {
    'use strict';

    const DB      = 'vti-terreno';
    const STORE   = 'fichas';
    const ESPERA  = 20000;   // corte al enviar; en el campo la red se cuelga sin avisar
    const LADO_MAX = 1600;   // px del lado mayor de las fotos
    const CALIDAD  = 0.75;

    /* ── IndexedDB ─────────────────────────────────────────────────────── */

    function abrir() {
        return new Promise((ok, error) => {
            const req = indexedDB.open(DB, 1);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'sitioId' });
                }
            };
            req.onsuccess = () => ok(req.result);
            req.onerror   = () => error(req.error);
        });
    }

    async function conStore(modo, fn) {
        const db = await abrir();
        return new Promise((ok, error) => {
            const tx = db.transaction(STORE, modo);
            const rs = fn(tx.objectStore(STORE));
            // Siempre `rs.result`, incluso cuando es undefined: devolver el
            // IDBRequest en ese caso haría que "no hay borrador" pareciera un
            // borrador válido, porque el objeto es truthy.
            tx.oncomplete = () => ok(rs instanceof IDBRequest ? rs.result : rs);
            tx.onerror    = () => error(tx.error);
        });
    }

    const guardar = (registro) => conStore('readwrite', (s) => s.put(registro));
    const leer    = (sitioId)  => conStore('readonly',  (s) => s.get(sitioId));
    const borrar  = (sitioId)  => conStore('readwrite', (s) => s.delete(sitioId));
    const todas   = ()         => conStore('readonly',  (s) => s.getAll());

    async function pendientes() {
        return (await todas()).filter((r) => r.estado === 'pendiente');
    }

    /* ── Fotos ─────────────────────────────────────────────────────────── */

    /**
     * Reduce una foto antes de guardarla o enviarla.
     *
     * Una foto de celular pesa 4-6 MB. Con 52 sitios y varias fotos por sitio
     * no cabría en el teléfono, y subirlas por 4G rural sería inviable. A 1600 px
     * el archivo baja a ~300 KB y sigue sirviendo para documentar un poste, un
     * tablero o la línea de vista.
     */
    async function reducirImagen(archivo) {
        if (!archivo.type.startsWith('image/')) return archivo;

        try {
            // `from-image` respeta la orientación EXIF: sin esto, las fotos
            // tomadas en vertical quedan acostadas.
            const bitmap = await createImageBitmap(archivo, { imageOrientation: 'from-image' });
            const escala = Math.min(1, LADO_MAX / Math.max(bitmap.width, bitmap.height));

            if (escala === 1 && archivo.size < 400 * 1024) { bitmap.close?.(); return archivo; }

            const ancho = Math.round(bitmap.width * escala);
            const alto  = Math.round(bitmap.height * escala);

            const lienzo = document.createElement('canvas');
            lienzo.width = ancho; lienzo.height = alto;
            lienzo.getContext('2d').drawImage(bitmap, 0, 0, ancho, alto);
            bitmap.close?.();

            const blob = await new Promise((r) => lienzo.toBlob(r, 'image/jpeg', CALIDAD));
            if (!blob || blob.size >= archivo.size) return archivo;

            return new File([blob], archivo.name.replace(/\.[^.]+$/, '') + '.jpg',
                            { type: 'image/jpeg', lastModified: Date.now() });
        } catch {
            return archivo;   // si el navegador no puede, se sube tal cual
        }
    }

    /* ── Formulario ────────────────────────────────────────────────────── */

    /** Lee el formulario a un objeto plano (sin archivos ni token). */
    function recolectar(form) {
        const campos = {};
        for (const el of form.elements) {
            if (!el.name || el.type === 'file' || el.name === '_token') continue;
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (el.checked) campos[el.name] = el.value;
            } else {
                campos[el.name] = el.value;
            }
        }
        return campos;
    }

    /** Vuelve a poner en el formulario lo guardado. */
    function aplicar(form, campos) {
        Object.entries(campos || {}).forEach(([nombre, valor]) => {
            const elementos = form.querySelectorAll('[name="' + CSS.escape(nombre) + '"]');
            if (!elementos.length) return;

            if (elementos[0].type === 'radio' || elementos[0].type === 'checkbox') {
                elementos.forEach((el) => { el.checked = (el.value === valor); });
            } else {
                elementos[0].value = valor;
            }
        });
    }

    /* ── Envío ─────────────────────────────────────────────────────────── */

    function token() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function armarCuerpo(registro) {
        const fd = new FormData();
        fd.append('_token', token());
        Object.entries(registro.campos || {}).forEach(([k, v]) => fd.append(k, v));
        (registro.fotos || []).forEach((f) => {
            fd.append('fotos[]', f.blob, f.nombre);
        });
        return fd;
    }

    /**
     * Intenta enviar un registro. Devuelve:
     *   {ok:true}                     enviado
     *   {ok:false, sesion:true}       la sesión caducó, hay que entrar de nuevo
     *   {ok:false}                    sin red (queda encolado)
     */
    async function enviar(registro) {
        const control = new AbortController();
        const corte   = setTimeout(() => control.abort(), ESPERA);

        try {
            const resp = await fetch(registro.url, {
                method: 'POST',
                body: armarCuerpo(registro),
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest' },
                signal: control.signal,
            });

            // Si caducó la sesión, Laravel responde 419 o redirige al login.
            // Marcarlo como enviado sería perder el levantamiento.
            if (resp.status === 419 || resp.status === 401 ||
                (resp.redirected && /\/login/.test(resp.url))) {
                return { ok: false, sesion: true };
            }

            return { ok: resp.ok };
        } catch {
            return { ok: false };
        } finally {
            clearTimeout(corte);
        }
    }

    /** Envía todo lo encolado. Devuelve {enviadas, quedan, sesion}. */
    async function sincronizar(alAvanzar) {
        const cola = await pendientes();
        let enviadas = 0;

        for (const registro of cola) {
            const r = await enviar(registro);

            if (r.sesion) {
                return { enviadas, quedan: cola.length - enviadas, sesion: true };
            }
            if (!r.ok) break;   // sin red: se corta y se reintenta después

            await borrar(registro.sitioId);
            enviadas++;
            if (alAvanzar) alAvanzar(enviadas, cola.length);
        }

        return { enviadas, quedan: (await pendientes()).length, sesion: false };
    }

    return {
        guardar, leer, borrar, todas, pendientes,
        reducirImagen, recolectar, aplicar, enviar, sincronizar, token,

        /** Registra el service worker. Sin HTTPS el navegador lo rechaza. */
        async registrarSw() {
            if (!('serviceWorker' in navigator)) return null;
            try {
                return await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            } catch {
                return null;
            }
        },

        /** Texto tipo "hace 5 min" para los avisos de borrador. */
        haceCuanto(ts) {
            const seg = Math.round((Date.now() - ts) / 1000);
            if (seg < 60)    return 'recién';
            if (seg < 3600)  return 'hace ' + Math.round(seg / 60) + ' min';
            if (seg < 86400) return 'hace ' + Math.round(seg / 3600) + ' h';
            return 'hace ' + Math.round(seg / 86400) + ' días';
        },
    };
})();
