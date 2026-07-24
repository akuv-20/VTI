@extends('layouts.app')

@section('content')
<style>
    .mapx-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
    .mapx-card h6 { font-size:.82rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }
    .mapx-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1rem; }
    .mapx-item { border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.1rem; background:#f8fafc; transition:border-color .15s; }
    .mapx-item:hover { border-color:#94a3b8; }
    .mapx-item h5 { font-size:.95rem; font-weight:700; color:#1e293b; margin:0 0 .15rem; }
    .mapx-item .desc { font-size:.78rem; color:#64748b; margin-bottom:.5rem; min-height:1.1rem; }
    .mapx-item .meta { font-size:.72rem; color:#94a3b8; display:flex; gap:.9rem; flex-wrap:wrap; }
    .mapx-tvurl { font-family:ui-monospace,monospace; font-size:.74rem; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; padding:.35rem .6rem; word-break:break-all; }
    .mapx-tec { display:inline-block; background:#e0f2fe; color:#0369a1; border-radius:5px; padding:1px 7px; font-size:.68rem; font-weight:600; margin:2px 3px 0 0; }
    .mapx-pub { display:inline-block; background:#f3e8ff; color:#7e22ce; border-radius:5px; padding:1px 7px; font-size:.68rem; font-weight:600; }
    .mapx-adm { border-top:1px dashed #e2e8f0; margin-top:.7rem; padding-top:.6rem; }
    .mapx-adm select[multiple] { font-size:.75rem; }
    .mapx-tvmini { font-family:ui-monospace,monospace; font-size:.66rem; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:5px; padding:.25rem .45rem; word-break:break-all; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-diagram-2 me-2" style="color:#0ea5e9"></i>Mapa de red · Monitoreo</h4>
    </div>

    {{-- ── Mapas ──────────────────────────────────────────────────────────── --}}
    <div class="mapx-card">
        <h6><i class="bi bi-map me-1"></i>Mis mapas ({{ $mapas->count() }})</h6>

        @if($mapas->isEmpty())
            <div class="text-muted text-center py-4" style="font-size:.85rem">
                Aún no hay mapas. Crea el primero abajo — por ejemplo <strong>«General»</strong> con el datacenter,
                las plantas y los campos, y luego un mapa por planta con su detalle interno.
            </div>
        @else
        <div class="mapx-grid">
            @foreach($mapas as $m)
            <div class="mapx-item">
                <a href="{{ route('admin.monitoreo.mapas.show', $m) }}" style="text-decoration:none;color:inherit;display:block">
                    <h5><i class="bi bi-diagram-3 me-1" style="color:#0ea5e9"></i>{{ $m->nombre }}</h5>
                    <div class="desc">{{ $m->descripcion }}</div>
                    <div class="meta">
                        <span><i class="bi bi-hdd-network me-1"></i>{{ $m->nodos_count }} nodos</span>
                        @if($m->en_tv)<span><i class="bi bi-tv me-1"></i>En TV</span>@endif
                        @unless($m->activo)<span class="text-danger">Inactivo</span>@endunless
                    </div>
                </a>
                <div class="mt-1">
                    @if($m->publico_lectura)<span class="mapx-pub"><i class="bi bi-eye me-1"></i>Todos (lectura)</span>@endif
                    @foreach($m->tecnicos as $t)
                        <span class="mapx-tec"><i class="bi bi-person-fill me-1"></i>{{ $t->name }}</span>
                    @endforeach
                    @if(!$m->publico_lectura && $m->tecnicos->isEmpty())
                        <span class="text-muted" style="font-size:.68rem">Sin técnicos asignados (solo admins)</span>
                    @endif
                </div>

                @can('admin')
                <div class="mapx-adm">
                    <button type="button" class="btn btn-link btn-sm p-0 btn-toggle-adm" data-id="{{ $m->id }}" style="font-size:.72rem;text-decoration:none">
                        <i class="bi bi-sliders me-1"></i>Asignación y TV
                    </button>
                    <div id="adm-{{ $m->id }}" style="display:none" class="mt-2">
                        <form method="POST" action="{{ route('admin.monitoreo.mapas.tecnicos', $m) }}">
                            @csrf
                            <label class="form-label" style="font-size:.7rem">Técnicos que mantienen este mapa <span class="text-muted">(Ctrl+clic para varios)</span></label>
                            <select name="user_ids[]" class="form-select form-select-sm" multiple size="5">
                                @foreach($usuarios as $u)
                                    <option value="{{ $u->id }}" @selected($m->tecnicos->contains('id', $u->id))>{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="publico_lectura" value="1" id="pub-{{ $m->id }}" @checked($m->publico_lectura)>
                                <label class="form-check-label" for="pub-{{ $m->id }}" style="font-size:.72rem">
                                    Visible para todos en solo lectura (ideal para el mapa General)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-2 w-100" style="font-size:.72rem">
                                <i class="bi bi-check-lg me-1"></i>Guardar asignación
                            </button>
                        </form>

                        @if($m->tv_token)
                        <label class="form-label mt-2 mb-1" style="font-size:.7rem">URL TV de este mapa</label>
                        <div class="mapx-tvmini" id="tvm-{{ $m->id }}">{{ route('monitoreo.tv', $m->tv_token) }}</div>
                        <div class="d-flex gap-1 mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" style="font-size:.7rem"
                                    onclick="navigator.clipboard.writeText(document.getElementById('tvm-{{ $m->id }}').textContent).then(()=>this.innerHTML='<i class=&quot;bi bi-check-lg&quot;></i> Copiado')">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                            <a href="{{ route('monitoreo.tv', $m->tv_token) }}" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size:.7rem">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.monitoreo.mapas.tv_token_mapa', $m) }}" class="d-inline"
                                  onsubmit="return confirm('¿Regenerar la URL TV de este mapa? El monitor que la use quedará sin señal.')">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm" style="font-size:.7rem" title="Regenerar URL">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                        </div>
                        @else
                        <form method="POST" action="{{ route('admin.monitoreo.mapas.tv_token_mapa', $m) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100" style="font-size:.72rem">
                                <i class="bi bi-key me-1"></i>Generar URL TV de este mapa
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @endcan
            </div>
            @endforeach
        </div>
        @endif
    </div>

    @can('admin')
    {{-- ── Crear mapa ─────────────────────────────────────────────────────── --}}
    <div class="mapx-card">
        <h6><i class="bi bi-plus-square me-1"></i>Crear mapa</h6>
        <form method="POST" action="{{ route('admin.monitoreo.mapas.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label" style="font-size:.75rem">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control form-control-sm" value="{{ old('nombre') }}" required placeholder="General / Planta Rapel / Campo Porcenin">
            </div>
            <div class="col-md-5">
                <label class="form-label" style="font-size:.75rem">Descripción</label>
                <input type="text" name="descripcion" class="form-control form-control-sm" value="{{ old('descripcion') }}" placeholder="Vista general: datacenter, plantas y campos">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success btn-sm w-100">
                    <i class="bi bi-plus-lg me-1"></i>Crear y abrir editor
                </button>
            </div>
        </form>
    </div>

    {{-- ── Modo TV global ─────────────────────────────────────────────────── --}}
    <div class="mapx-card">
        <h6><i class="bi bi-tv me-1"></i>Modo TV global (rotación de todos los mapas)</h6>
        <div style="font-size:.82rem;color:#475569" class="mb-2">
            URL pública (sin login) que <strong>rota</strong> entre los mapas marcados «En TV» — ideal para el monitor
            central de TI. Cada mapa tiene además su URL TV propia (en «Asignación y TV») para el monitor de cada planta.
        </div>
        @if($tvToken)
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div class="mapx-tvurl flex-grow-1" id="tvUrl">{{ route('monitoreo.tv', $tvToken) }}</div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('tvUrl').textContent).then(()=>this.innerHTML='<i class=&quot;bi bi-check-lg&quot;></i> Copiado')">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
                <a href="{{ route('monitoreo.tv', $tvToken) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                </a>
                <form method="POST" action="{{ route('admin.monitoreo.mapas.tv_token') }}" class="d-inline"
                      onsubmit="return confirm('¿Regenerar el token? La URL actual dejará de funcionar en el monitor.')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Regenerar token</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('admin.monitoreo.mapas.tv_token') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-key me-1"></i>Generar URL del modo TV</button>
            </form>
        @endif
    </div>
    @endcan
</div>

@push('scripts')
<script>
// Entrar desde el índice reinicia la ruta de navegación (migas de pan) de los mapas.
sessionStorage.removeItem('mapv_trail');

document.querySelectorAll('.btn-toggle-adm').forEach(btn => {
    btn.addEventListener('click', () => {
        const box = document.getElementById('adm-' + btn.dataset.id);
        box.style.display = box.style.display === 'none' ? '' : 'none';
    });
});
</script>
@endpush
@endsection
