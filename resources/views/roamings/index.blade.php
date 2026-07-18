@extends('layouts.app')

@php
    // Helper de badge de vigencia para pasaportes
    if (!function_exists('vigenciaBadge')) {
        function vigenciaBadge($r) {
            $v = $r->vigencia;
            return match($v) {
                'vigente'    => ['bg-success', 'Vigente'],
                'programado' => ['bg-info text-dark', 'Programado'],
                'vencido'    => ['bg-secondary', 'Vencido'],
                'archivado'  => ['bg-light text-muted border', 'Archivado'],
                default      => ['bg-light text-muted border', ucfirst($v)],
            };
        }
    }
@endphp

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-globe-americas me-2"></i>Roamings</h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="btnAgendarMovistar" style="background:#0099CC;border-color:#0099CC">
                <i class="bi bi-plus-circle-fill me-1"></i>Agendar Movistar
            </button>
            <button type="button" class="btn btn-sm text-white" id="btnActivarEntel" style="background:#002C7F;border-color:#002C7F">
                <i class="bi bi-plus-circle-fill me-1"></i>Activar Entel
            </button>
        </div>
    </div>

    {{-- ── Pestañas ─────────────────────────────────────────────────────────── --}}
    <ul class="nav nav-tabs mb-0" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-pasaportes" type="button">
                <i class="bi bi-airplane me-1" style="color:#0099CC"></i>Movistar — Pasaportes
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-recurrentes" type="button">
                <i class="bi bi-arrow-repeat me-1" style="color:#0099CC"></i>Recurrentes
                @if($recurrentes->count())
                    <span class="badge rounded-pill bg-warning text-dark ms-1">{{ $recurrentes->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-entel" type="button">
                <i class="bi bi-broadcast me-1" style="color:#002C7F"></i>Entel — Por uso
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom bg-white p-0">

        {{-- ① Movistar — Pasaportes --}}
        <div class="tab-pane fade show active" id="tab-pasaportes">
            @if($pasaportes->isEmpty())
                <div class="text-center py-5 text-muted"><i class="bi bi-airplane" style="font-size:2rem"></i><div class="mt-2">Sin pasaportes registrados.</div></div>
            @else
                <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                    <table class="vti-table">
                        <thead>
                            <tr>
                                <th>Usuario</th><th>Número</th><th>Pasaporte</th><th>Inicio</th>
                                <th>Término</th><th>Destino</th><th>ID Solicitud</th><th>Vigencia</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pasaportes as $r)
                            @php [$cls,$txt] = vigenciaBadge($r); @endphp
                            <tr>
                                <td>{{ $r->nombre_usuario ?? ($r->lineaTelefonica->usuario->nombre ?? '—') }}</td>
                                <td class="font-monospace">{{ $r->numero }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $r->pasaporte_dias }} días</span></td>
                                <td>{{ $r->fecha_inicio?->format('d/m/Y H:i') }}</td>
                                <td>{{ $r->fecha_termino?->format('d/m/Y H:i') }}</td>
                                <td>{{ $r->destino ?? '—' }}</td>
                                <td style="font-size:.8rem">{{ $r->id_solicitud ?? '—' }}</td>
                                <td><span class="badge {{ $cls }}">{{ $txt }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if($r->estado !== 'archivado')
                                        <form method="POST" action="{{ route('roamings.archivar', $r) }}" title="Archivar">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i></button>
                                        </form>
                                        @endif
                                        @can('admin')
                                        <form method="POST" action="{{ route('roamings.destroy', $r) }}" onsubmit="return confirm('¿Eliminar este roaming?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $pasaportes->links() }}</div>
            @endif
        </div>

        {{-- ② Recurrentes --}}
        <div class="tab-pane fade" id="tab-recurrentes">
            <div class="p-3 text-muted" style="font-size:.85rem">
                <i class="bi bi-info-circle me-1"></i>Roaming recurrente Movistar (30 días autorenovable). Mientras esté activo, la línea no puede agendar otros roamings.
            </div>
            @if($recurrentes->isEmpty())
                <div class="text-center py-4 text-muted">Sin recurrentes activos.</div>
            @else
                <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                    <table class="vti-table">
                        <thead>
                            <tr><th>Usuario</th><th>Número</th><th>Activo desde</th><th>Destino</th><th>ID Solicitud</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($recurrentes as $r)
                            <tr>
                                <td>{{ $r->nombre_usuario ?? ($r->lineaTelefonica->usuario->nombre ?? '—') }}</td>
                                <td class="font-monospace">{{ $r->numero }}</td>
                                <td>{{ $r->fecha_inicio?->format('d/m/Y H:i') }}</td>
                                <td>{{ $r->destino ?? '—' }}</td>
                                <td style="font-size:.8rem">{{ $r->id_solicitud ?? '—' }}</td>
                                <td><span class="badge bg-warning text-dark">Activo</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('roamings.cerrar', $r) }}"
                                          onsubmit="return confirm('¿Desactivar el recurrente de {{ $r->nombre_usuario }}?')">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Desactivar</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ③ Entel — Por uso --}}
        <div class="tab-pane fade" id="tab-entel">
            <div class="p-3 text-muted" style="font-size:.85rem">
                <i class="bi bi-info-circle me-1"></i>Roaming Entel: se activa y se paga por uso. Cierra la activación cuando ya no se necesite.
            </div>
            @if($entel->isEmpty())
                <div class="text-center py-4 text-muted">Sin activaciones Entel.</div>
            @else
                <div class="vti-table-wrapper m-0 shadow-none rounded-0">
                    <table class="vti-table">
                        <thead>
                            <tr><th>Usuario</th><th>Número</th><th>Activado</th><th>Destino</th><th>ID Solicitud</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($entel as $r)
                            <tr>
                                <td>{{ $r->nombre_usuario ?? ($r->lineaTelefonica->usuario->nombre ?? '—') }}</td>
                                <td class="font-monospace">{{ $r->numero }}</td>
                                <td>{{ $r->fecha_inicio?->format('d/m/Y H:i') }}</td>
                                <td>{{ $r->destino ?? '—' }}</td>
                                <td style="font-size:.8rem">{{ $r->id_solicitud ?? '—' }}</td>
                                <td>
                                    @if($r->estado === 'activo')
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Cerrado</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if($r->estado === 'activo')
                                        <form method="POST" action="{{ route('roamings.cerrar', $r) }}" onsubmit="return confirm('¿Cerrar la activación Entel?')">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cerrar</button>
                                        </form>
                                        @endif
                                        @can('admin')
                                        <form method="POST" action="{{ route('roamings.destroy', $r) }}" onsubmit="return confirm('¿Eliminar este registro?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $entel->links() }}</div>
            @endif
        </div>
    </div>
</div>

@include('roamings._modal_movistar')
@include('roamings._modal_entel')
@endsection

{{-- @prepend: estas funciones base deben definirse ANTES que los scripts de los modales incluidos --}}
@prepend('scripts')
<script>
// ── Buscador de líneas reutilizable ──
function initBuscadorLinea(cfg) {
    const input      = document.getElementById(cfg.inputId);
    const resultados = document.getElementById(cfg.resultadosId);
    const buscarUrl  = @json(route('roamings.buscar_lineas'));
    let debounce;

    input?.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { resultados.innerHTML = ''; return; }
        debounce = setTimeout(() => buscar(q), 320);
    });
    input?.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });

    async function buscar(q) {
        resultados.innerHTML = '<div class="list-group-item text-muted small">Buscando…</div>';
        try {
            const resp = await fetch(`${buscarUrl}?carrier=${cfg.carrier}&q=${encodeURIComponent(q)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            if (!data.length) { resultados.innerHTML = '<div class="list-group-item text-muted small">Sin resultados.</div>'; return; }
            resultados.innerHTML = data.map(l => `
                <button type="button" class="list-group-item list-group-item-action"
                        data-id="${l.id}" data-linea="${esc(l.linea)}" data-usuario="${esc(l.usuario)}"
                        data-empresa="${esc(l.empresa)}" data-emisor="${esc(l.emisor)}"
                        data-recurrente="${l.recurrente_activo ? 1 : 0}">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold font-monospace">${esc(l.linea)}</span>
                        <span class="text-muted small">${esc(l.emisor)}</span>
                    </div>
                    <div class="small text-muted">${esc(l.usuario)} · ${esc(l.empresa)}</div>
                </button>`).join('');
        } catch (e) { resultados.innerHTML = '<div class="list-group-item text-danger small">Error al buscar.</div>'; }
    }

    resultados?.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-id]');
        if (!btn) return;
        e.preventDefault();
        try { cfg.onSelect(btn.dataset); }
        catch (err) { console.error('[roaming] error en onSelect:', err); }
    });

    function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
}

// Abrir modal con fallback (por si Bootstrap JS no carga)
function abrirModal(modalEl) {
    try { bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
    catch (e) {
        modalEl.classList.add('show'); modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden'); document.body.classList.add('modal-open');
        const bd = document.createElement('div'); bd.className = 'modal-backdrop fade show';
        bd.dataset.for = modalEl.id; document.body.appendChild(bd);
    }
}
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => {
        const bd = document.querySelector(`.modal-backdrop[data-for="${m.id}"]`);
        if (!bd) return;
        if (e.target.closest('[data-bs-dismiss="modal"]') || e.target === m) {
            m.classList.remove('show'); m.style.display = 'none';
            document.body.classList.remove('modal-open'); bd.remove();
        }
    });
});

// Fallback de pestañas si Bootstrap JS no está disponible
if (typeof bootstrap === 'undefined' || !bootstrap.Tab) {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.dataset.bsTarget);
            this.closest('.nav')?.querySelectorAll('.nav-link').forEach(n => n.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
            this.classList.add('active');
            target?.classList.add('show', 'active');
        });
    });
}
</script>
@endprepend
