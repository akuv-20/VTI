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
            Elige el sitio que estás visitando. El formulario pide solo lo esencial y toma las coordenadas del GPS del teléfono.
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
