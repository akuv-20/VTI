{{--
    Campo de tres estados para el levantamiento: sin evaluar / sí / no.

    En una evaluación en terreno "todavía no lo miré" y "no hay" son cosas
    distintas, y un simple checkbox las confunde: ambas llegarían como false.
    Por eso el valor vacío es una opción explícita y es la que viene marcada
    mientras nadie responda.

    Ojo con la lista de opciones: va como lista de tuplas y NO como arreglo
    asociativo. Con claves '1' y '0', PHP las convierte a enteros y la
    comparación estricta contra el string guardado falla, con lo que una ficha
    ya respondida se abriría en blanco.

    Espera: $name, $label y $valor (bool|null).
--}}
@php
    $tfOpciones = [['', '—', 'na'], ['1', 'Sí', 'si'], ['0', 'No', 'no']];
    $tfActual   = is_null($valor ?? null) ? '' : ($valor ? '1' : '0');
@endphp

<div class="tf-f">
    <label>{{ $label }}</label>
    <div class="tf-opts">
        @foreach($tfOpciones as [$tfValor, $tfTexto, $tfClave])
            <input type="radio" class="btn-check" name="{{ $name }}"
                   id="{{ $name }}_{{ $tfClave }}" value="{{ $tfValor }}"
                   @checked($tfActual === $tfValor)>
            <label class="btn btn-outline-secondary" for="{{ $name }}_{{ $tfClave }}">{{ $tfTexto }}</label>
        @endforeach
    </div>
</div>
