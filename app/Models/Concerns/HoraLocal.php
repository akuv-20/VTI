<?php

namespace App\Models\Concerns;

use App\Support\ZonaHoraria;

/**
 * Muestra las marcas de tiempo en la zona horaria de quien mira, guardándolas
 * siempre en UTC.
 *
 * Por qué acá y no en cada vista: hay 124 columnas con hora repartidas en 55
 * tablas, y unas 175 llamadas a `->format()` entre vistas y controladores.
 * Convertir una por una es garantía de olvidar algunas —y peor, de convertir
 * por error una de las 11 columnas que son solo fecha—. Acá el modelo ya sabe
 * cuál es cuál, porque Laravel distingue el cast `date` del `datetime`.
 *
 * Esa distinción es la razón de que `asDate()` esté reescrito: una fecha sin
 * hora NO se convierte nunca. `2026-09-29` guardado como medianoche UTC, visto
 * desde Santiago, serían las 21:00 del 28 — la factura cambiaría de día sola.
 */
trait HoraLocal
{
    /**
     * Al LEER una fecha con hora: se pasa a la zona de quien mira.
     *
     * La conversión no altera el instante, solo cómo se escribe. Comparar dos
     * Carbon en husos distintos sigue funcionando: Carbon compara instantes.
     */
    protected function asDateTime($value)
    {
        $fecha = parent::asDateTime($value);

        return $fecha?->setTimezone(ZonaHoraria::actual());
    }

    /**
     * Al LEER una fecha sin hora: tal cual, sin convertir.
     *
     * Se llama a `parent::asDateTime()` y no a `$this->asDateTime()` para
     * saltarse la conversión de arriba, que acá correría el día.
     */
    protected function asDate($value)
    {
        return parent::asDateTime($value)->startOfDay();
    }

    /**
     * Al GUARDAR: siempre en UTC, venga en la zona que venga.
     *
     * Sin esto el arreglo se muerde la cola: `asDateTime()` devuelve la fecha
     * en hora de Santiago, y al guardar ese mismo objeto se escribiría hora de
     * Santiago en una base que está toda en UTC. En un par de idas y vueltas
     * el dato queda corrido y ya no hay forma de saber cuál es cuál.
     */
    public function fromDateTime($value)
    {
        $fecha = parent::asDateTime($value);

        return $fecha?->copy()->utc()->format($this->getDateFormat());
    }
}
