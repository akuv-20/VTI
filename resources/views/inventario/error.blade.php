{{--
    Pantalla de un módulo de inventario que no pudo cargar porque el sistema
    externo (GLPI o AD) no respondió.

    Las pantallas que ya traen su propia tabla —equipos, cruce— muestran el
    aviso dentro de sí mismas y conservan lo que sí pudieron leer. Esta es para
    las que no tienen nada que mostrar sin la consulta: se cae la consulta y no
    queda página.

    Deja a mano el conmutador de dominio: si el GLPI que falla es el de un
    dominio, el del otro suele seguir en pie.

    Espera:
      $dom     — DominioInventario actual
      $seccion — sección, para el enlace al otro dominio
      $titulo  — título de la pantalla
      $icono   — clase de Bootstrap Icons del título
      $error   — mensaje ya traducido a algo legible
--}}

@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi {{ $icono }} me-2" style="color:{{ $dom->color() }}"></i>{{ $titulo }}</span>
            @include('inventario._dominio', ['seccion' => $seccion])
        </h4>
    </div>

    <div class="alert alert-danger d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>No se pudo cargar {{ mb_strtolower($titulo) }}:</strong> {{ $error }}<br>
            <a href="{{ route('admin.configuracion.index') }}#pane-glpi" class="alert-link">Ir a Configuración</a>
        </div>
    </div>

</div>
@endsection
