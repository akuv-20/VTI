{{--
    Cabecera de dominio: dice en cuál estás parado y deja saltar al otro sin
    volver al selector. Si el usuario solo tiene uno, no hay nada que ofrecer
    y se muestra apenas la etiqueta.

    Espera:
      $dom     — DominioInventario actual
      $seccion — sección para armar el enlace del otro dominio
--}}

@once
@push('styles')
<style>
/* invd- : conmutador de dominio */
.invd-chip {
    display:inline-flex; align-items:center; gap:.4rem;
    font-size:.78rem; font-weight:700; color:#fff;
    padding:3px 11px; border-radius:20px; white-space:nowrap;
}
.invd-otros { display:inline-flex; gap:.3rem; }
.invd-otro {
    display:inline-flex; align-items:center; gap:.3rem;
    font-size:.76rem; font-weight:600; text-decoration:none;
    padding:3px 10px; border-radius:20px;
    border:1px solid #e2e8f0; background:#fff; color:#64748b;
    transition:border-color .12s, color .12s;
}
.invd-otro:hover { border-color:#94a3b8; color:#1e293b; }
</style>
@endpush
@endonce

@php $otros = \App\Services\DominioInventario::permitidos()->reject(fn($d) => $d->clave === $dom->clave); @endphp

<span class="invd-chip" style="background:{{ $dom->color() }}">
    <i class="bi {{ $dom->icono() }}"></i>{{ $dom->label() }}
</span>

@if($otros->isNotEmpty())
    <span class="invd-otros">
        @foreach($otros as $o)
            <a href="{{ route("inventario.{$o->clave}.{$seccion}") }}" class="invd-otro"
               title="Ver lo mismo en {{ $o->dominio() }}">
                <i class="bi bi-arrow-left-right"></i>{{ $o->label() }}
            </a>
        @endforeach
    </span>
@endif
