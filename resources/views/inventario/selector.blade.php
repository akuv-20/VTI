@extends('layouts.app')

@section('content')
<style>
    .inv-sel-wrap { max-width: 860px; margin: 0 auto; padding: 2.5rem 0 3rem; }
    .inv-sel-head { text-align:center; margin-bottom: 2rem; }
    .inv-sel-head i     { font-size: 2.4rem; color:#94a3b8; }
    .inv-sel-head h4    { font-weight: 700; margin: .7rem 0 .3rem; }
    .inv-sel-head p     { color:#64748b; font-size:.88rem; margin:0; }

    .inv-sel-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1.1rem; }

    .inv-sel-card {
        background:#fff; border:1px solid #e2e8f0; border-radius:14px;
        padding:1.8rem 1.5rem; text-decoration:none; color:inherit;
        display:flex; flex-direction:column; align-items:center; gap:.7rem;
        text-align:center; transition:transform .12s, box-shadow .15s, border-color .15s;
    }
    .inv-sel-card:hover {
        color:inherit; transform:translateY(-2px);
        box-shadow:0 8px 24px rgba(0,0,0,.10); border-color:#cbd5e1;
    }
    .inv-sel-icono {
        width:62px; height:62px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        font-size:1.6rem; color:#fff;
    }
    .inv-sel-label   { font-size:1.05rem; font-weight:700; color:#1e293b; }
    .inv-sel-dominio { font-size:.8rem; color:#94a3b8; font-family:monospace; }
    .inv-sel-ir      { font-size:.78rem; color:#64748b; margin-top:.2rem; }
</style>

<div class="container-fluid vti-page">
    <div class="inv-sel-wrap">

        <div class="inv-sel-head">
            <i class="bi {{ $icono }}"></i>
            <h4>{{ $titulo }}</h4>
            <p>Elige el dominio que quieres revisar</p>
        </div>

        <div class="inv-sel-grid">
            @foreach($dominios as $d)
            <a href="{{ route("inventario.{$d->clave}.{$seccion}") }}" class="inv-sel-card">
                <div class="inv-sel-icono" style="background:{{ $d->color() }}">
                    <i class="bi {{ $d->icono() }}"></i>
                </div>
                <div>
                    <div class="inv-sel-label">{{ $d->label() }}</div>
                    <div class="inv-sel-dominio">{{ $d->dominio() }}</div>
                </div>
                <div class="inv-sel-ir">Entrar <i class="bi bi-arrow-right"></i></div>
            </a>
            @endforeach
        </div>

    </div>
</div>
@endsection
