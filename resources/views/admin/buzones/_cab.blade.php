{{--
    Cabecera de un bloque del informe, con la casilla que decide si sale en el PDF.

    Si viene marcada o no lo dice ActividadBuzonesController::BLOQUES, que es la
    misma fuente que usa el PDF cuando nadie eligió nada. Así la pantalla y el
    archivo no pueden discrepar.

    Espera:
      $clave  — identificador del bloque, el mismo que declara el controlador
      $titulo — cómo se llama en pantalla
--}}
@php
    $marcado = \App\Http\Controllers\Admin\ActividadBuzonesController::BLOQUES[$clave][1] ?? true;
@endphp

<div class="buz-cab">
    <span class="buz-etq">{{ $titulo }}</span>

    <input type="checkbox" class="form-check-input buz-chk buz-noprint"
           id="chk-{{ $clave }}" value="{{ $clave }}" @checked($marcado)>
    <label for="chk-{{ $clave }}" class="buz-noprint">en el PDF</label>
</div>
