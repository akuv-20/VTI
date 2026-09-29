<?php

namespace App\Support;

/**
 * Zona horaria con la que se le muestran las horas a cada persona.
 *
 * Todo se guarda en UTC —eso no cambia— y la conversión ocurre recién al leer
 * el dato, en HoraLocal. Guardar en UTC es lo que permite que dos personas en
 * husos distintos vean la misma marca de tiempo cada una en su hora sin que el
 * dato se toque.
 *
 * Se usa el identificador `America/Santiago`, no un desfase fijo: Chile cambia
 * de hora dos veces al año (UTC−4 en invierno, UTC−3 en verano) y fijar −4
 * dejaría todo corrido una hora durante medio año.
 */
class ZonaHoraria
{
    /** Zonas ofrecidas en el formulario. Donde el grupo tiene operaciones. */
    public const OPCIONES = [
        'America/Santiago'     => 'Chile continental (Santiago)',
        'Pacific/Easter'       => 'Isla de Pascua',
        'America/Lima'         => 'Perú (Lima)',
        'America/Argentina/Buenos_Aires' => 'Argentina (Buenos Aires)',
        'America/Sao_Paulo'    => 'Brasil (São Paulo)',
        'America/Bogota'       => 'Colombia (Bogotá)',
        'America/Mexico_City'  => 'México (Ciudad de México)',
        'Europe/Madrid'        => 'España (Madrid)',
        'UTC'                  => 'UTC (sin desfase)',
    ];

    /** La zona por defecto, para quien no eligió ninguna. */
    public static function porDefecto(): string
    {
        $zona = (string) config('app.zona_horaria_por_defecto', 'America/Santiago');

        return self::valida($zona) ? $zona : 'America/Santiago';
    }

    /**
     * La zona de quien está mirando, o la por defecto.
     *
     * Se pregunta con `hasUser()` y no con `user()` a propósito: `user()`
     * resolvería la sesión y cargaría el modelo User, cuya propia hidratación
     * vuelve a pasar por acá. Con `hasUser()` solo se responde cuando el
     * usuario YA está resuelto, y mientras tanto se usa la por defecto.
     */
    public static function actual(): string
    {
        try {
            $guard = auth()->guard();

            if (method_exists($guard, 'hasUser') && $guard->hasUser()) {
                $zona = $guard->user()?->zona_horaria;

                if (self::valida($zona)) return $zona;
            }
        } catch (\Throwable) {
            // Sin contenedor de autenticación (consola, tareas programadas):
            // se sigue con la por defecto en vez de reventar.
        }

        return self::porDefecto();
    }

    /** Si el identificador existe de verdad en la base de husos de PHP. */
    public static function valida(?string $zona): bool
    {
        if ($zona === null || $zona === '') return false;

        return in_array($zona, timezone_identifiers_list(), true);
    }
}
