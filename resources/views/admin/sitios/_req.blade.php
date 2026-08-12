{{--
    Punto que marca los campos que cuentan para la completitud.

    Va junto a la etiqueta, no como un asterisco de "obligatorio": estos campos
    no impiden guardar la ficha, solo mueven el porcentaje. Por eso es un punto
    y no un `*`, que en el resto del formulario ya significa "requerido".

    Lleno = respondido, hueco = lo que falta. Qué campos cuentan depende del
    tipo de sitio y de su estado, así que la lista se calcula por ficha y se
    hereda del scope de la vista: `$req` y `$faltan`.

    Espera: $campo.
--}}
@if(in_array($campo, $req ?? [], true))
    @php $sfFalta = in_array($campo, $faltan ?? [], true); @endphp
    <span class="sf-req {{ $sfFalta ? 'falta' : 'ok' }}"
          title="{{ $sfFalta ? 'Cuenta para la completitud y está sin responder' : 'Cuenta para la completitud, ya respondido' }}"></span>
@endif
