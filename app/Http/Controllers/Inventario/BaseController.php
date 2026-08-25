<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Services\DominioInventario;

/**
 * Base de las pantallas de Inventario: resuelve el dominio de la ruta.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Por qué el dominio NO se recibe como parámetro del método:
 *
 *  Laravel pasa los parámetros de ruta a la acción POSICIONALMENTE, no por
 *  nombre. El dominio llega por ->defaults(), y los defaults se agregan DESPUÉS
 *  de los parámetros de la URI, así que en una ruta como
 *  `inventario/equipos/verfrut/{id}` el arreglo queda ['id' => …, 'dominio' => …]
 *  y una firma `show(string $dominio, $id)` recibe el id en $dominio y "verfrut"
 *  en $id. El síntoma era un 404 al abrir la ficha de cualquier equipo.
 *
 *  Leerlo de la ruta evita el problema y no depende del orden ni de cuántos
 *  parámetros tenga cada método.
 * ─────────────────────────────────────────────────────────────────────────────
 */
abstract class BaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Dominio del grupo de rutas al que pertenece la petición actual. */
    protected function dominio(): DominioInventario
    {
        return DominioInventario::oFalla(
            request()->route()?->defaults['dominio'] ?? null
        );
    }
}
