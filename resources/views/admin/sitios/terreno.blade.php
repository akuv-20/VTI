@extends('layouts.app')

@section('content')
<style>
    .tr-wrap { max-width:620px; margin:0 auto; }
    .tr-buscar { position:sticky; top:0; z-index:5; background:#e8ecf0; padding:.5rem 0 .7rem; }
    .tr-item { display:flex; align-items:center; gap:.75rem; background:#fff; border:1px solid #e2e8f0; border-radius:11px;
               padding:.7rem .85rem; margin-bottom:.6rem; text-decoration:none; color:inherit; }
    .tr-item:active { background:#f8fafc; }
    .tr-foto { width:52px; height:52px; border-radius:9px; background:#f1f5f9; flex:0 0 auto; overflow:hidden;
               display:flex; align-items:center; justify-content:center; }
    .tr-foto img { width:100%; height:100%; object-fit:cover; }
    .tr-foto i { font-size:1.4rem; color:#cbd5e1; }
    .tr-nom { font-size:.95rem; font-weight:700; color:#1e293b; line-height:1.25; }
    .tr-sub { font-size:.75rem; color:#94a3b8; margin-top:1px; }
    .tr-badge { font-size:.66rem; font-weight:700; padding:2px 9px; border-radius:20px; color:#fff; }
    .tr-comp { font-size:.7rem; font-weight:700; color:#64748b; flex:0 0 auto; }
    /* En pantallas chicas el formulario ocupa todo el ancho disponible */
    @media (max-width:576px) {
        .tr-item { padding:.85rem; }
        .tr-nom { font-size:1rem; }
    }
</style>

<div class="container-fluid vti-page">
    <div class="tr-wrap">
        <div class="vti-page-header">
            <h4><i class="bi bi-phone me-2" style="color:#7c3aed"></i>Levantamiento en terreno</h4>
        </div>

        <div class="alert alert-info py-2" style="font-size:.8rem">
            <i class="bi bi-info-circle-fill me-1"></i>
            Elige el sitio que estás visitando. El formulario toma las coordenadas del GPS del teléfono.
        </div>

        {{-- ── Modo sin conexión ───────────────────────────────────────────
             Los campos por evaluar no tienen enlace y varios están sin señal.
             Acá se descarga todo antes de salir y se envía lo pendiente al
             volver a tener red. --}}
        <div id="trOffline" class="mb-3" style="display:none">

            {{-- Sin la clase `d-flex`: sus reglas llevan !important y pisan el
                 display:none, con lo que la barra quedaría siempre a la vista. --}}
            <div id="trPendientes" class="align-items-center gap-2 mb-2"
                 style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.6rem .75rem">
                <i class="bi bi-cloud-arrow-up-fill" style="color:#b45309"></i>
                <div style="flex:1 1 auto;font-size:.8rem;color:#b45309" id="trPendTexto"></div>
                <button type="button" class="btn btn-warning btn-sm" id="btnSincronizar">Sincronizar</button>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btnPreparar"
                    style="font-size:.8rem">
                <i class="bi bi-download me-1"></i>Preparar para terreno (descargar fichas)
            </button>
            <div id="trPrepMsg" class="mt-1" style="font-size:.74rem;color:#64748b"></div>
        </div>

        <div class="tr-buscar">
            <form method="GET" action="{{ route('admin.sitios.terreno') }}" class="d-flex gap-2">
                <input type="search" name="q" class="form-control" value="{{ $q }}" placeholder="Buscar sitio…" autocomplete="off">
                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
        </div>

        @forelse($sitios as $s)
        <a href="{{ route('admin.sitios.terreno.ficha', $s) }}" class="tr-item">
            <div class="tr-foto">
                @if($s->portada && $s->portada->thumb_url)
                    <img src="{{ $s->portada->thumb_url }}" alt="">
                @else
                    <i class="bi {{ $s->icono }}"></i>
                @endif
            </div>
            <div style="flex:1 1 auto;min-width:0">
                <div class="tr-nom">{{ $s->titulo }}</div>
                <div class="tr-sub">
                    {{ $s->tipo_label }}@if($s->comuna) · {{ $s->comuna }}@endif
                </div>
                <div class="mt-1">
                    <span class="tr-badge" style="background:{{ $s->estado_enlace_color }}">{{ $s->estado_enlace_label }}</span>
                </div>
            </div>
            <div class="tr-comp">{{ $s->completitud }}%</div>
            <i class="bi bi-chevron-right text-muted"></i>
        </a>
        @empty
        <div class="text-center text-muted py-5" style="font-size:.85rem">No hay sitios que coincidan.</div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
<link rel="manifest" href="/manifest.webmanifest">
@endpush

@push('scripts')
<script src="/js/terreno-offline.js?v={{ filemtime(public_path('js/terreno-offline.js')) }}"></script>
<script>
(function () {
    const T     = window.VtiTerreno;
    const caja  = document.getElementById('trOffline');
    const barra = document.getElementById('trPendientes');
    const texto = document.getElementById('trPendTexto');
    const msg   = document.getElementById('trPrepMsg');

    if (!('indexedDB' in window)) return;   // navegador muy viejo: se sigue online
    caja.style.display = 'block';

    /** Marca en el listado las fichas que están guardadas sin enviar. */
    async function pintarPendientes() {
        const cola = await T.pendientes();

        barra.style.display = cola.length ? 'flex' : 'none';
        texto.textContent = cola.length
            ? cola.length + ' ficha' + (cola.length > 1 ? 's' : '') +
              ' guardada' + (cola.length > 1 ? 's' : '') + ' en el teléfono sin enviar'
            : '';

        document.querySelectorAll('.tr-item').forEach((a) => a.style.borderColor = '#e2e8f0');
        cola.forEach((r) => {
            const enlace = document.querySelector('.tr-item[href$="/' + r.sitioId + '"]');
            if (enlace) {
                enlace.style.borderColor = '#f59e0b';
                enlace.style.background  = '#fffbeb';
            }
        });
    }

    document.getElementById('btnSincronizar').addEventListener('click', async function () {
        const b = this;
        b.disabled = true; b.textContent = 'Enviando…';

        const r = await T.sincronizar((hechas, total) => { b.textContent = hechas + '/' + total; });

        b.disabled = false; b.textContent = 'Sincronizar';
        await pintarPendientes();

        if (r.sesion) {
            msg.innerHTML = '<span style="color:#b91c1c">Tu sesión caducó. Vuelve a entrar y sincroniza de nuevo; nada se perdió.</span>';
        } else if (r.quedan) {
            msg.innerHTML = '<span style="color:#b45309">' + r.enviadas + ' enviada(s), quedan ' + r.quedan + '. Reintenta con mejor señal.</span>';
        } else {
            msg.innerHTML = '<span style="color:#15803d">Listo: ' + r.enviadas + ' ficha(s) enviadas.</span>';
        }
    });

    // ── Descarga previa ──────────────────────────────────────────────────────
    // Hay que tocarlo ANTES de salir, con conexión: es lo que deja las fichas
    // guardadas en el teléfono para poder abrirlas sin señal.
    document.getElementById('btnPreparar').addEventListener('click', async function () {
        const b = this;
        const reg = await T.registrarSw();

        if (!reg) {
            msg.innerHTML = '<span style="color:#b91c1c">Este navegador no permite el modo sin conexión (requiere HTTPS).</span>';
            return;
        }

        await navigator.serviceWorker.ready;

        // Junto con las páginas hay que bajar lo que esas páginas necesitan.
        // Guardar solo el HTML da una pantalla que abre pero no funciona: sin el
        // JavaScript no se guarda nada en el teléfono, y eso en un campo se
        // descubre demasiado tarde.
        const recursos = [
            ...[...document.querySelectorAll('script[src]')].map(s => s.src),
            ...[...document.querySelectorAll('link[rel="stylesheet"]')].map(l => l.href),
            // Tipografías e iconos: los pide el CSS, así que no están en el DOM.
            ...performance.getEntriesByType('resource')
                .filter(e => /\.(woff2?|ttf|png|svg)(\?|$)/i.test(e.name))
                .map(e => e.name),
        ];

        const urls = [...new Set([
            location.origin + '/admin/sitios/terreno',
            ...[...document.querySelectorAll('.tr-item')].map(a => a.href),
            ...recursos,
        ])];

        b.disabled = true;
        msg.textContent = 'Descargando ' + urls.length + ' archivos…';

        navigator.serviceWorker.addEventListener('message', function alAvanzar(ev) {
            const d = ev.data || {};
            if (d.tipo === 'precarga-avance') {
                msg.textContent = 'Descargando… ' + (d.ok + d.fallos) + '/' + d.total;
            }
            if (d.tipo === 'precarga-fin') {
                b.disabled = false;
                msg.innerHTML = d.fallos
                    ? '<span style="color:#b45309">' + d.ok + ' listas, ' + d.fallos + ' fallaron. Reintenta con mejor señal.</span>'
                    : '<span style="color:#15803d">' + d.ok + ' fichas guardadas en el teléfono. Ya puedes salir sin señal.</span>';
                navigator.serviceWorker.removeEventListener('message', alAvanzar);
            }
        });

        (navigator.serviceWorker.controller || reg.active).postMessage({ tipo: 'precargar', urls });
    });

    T.registrarSw().then(pintarPendientes);
})();
</script>
@endpush
