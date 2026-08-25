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

    /**
     * Traduce el fallo de un sistema externo a algo que se pueda leer.
     *
     * El mensaje crudo de PDO trae host, usuario y base de datos; mostrarlo tal
     * cual delata la infraestructura a cualquiera que abra la pantalla.
     */
    protected function mensajeError(\Throwable $e, DominioInventario $dom): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'No connections exist')
            || ($dom->ad() && str_contains($msg, $dom->ad()))) {
            return "No se pudo conectar al Active Directory de {$dom->label()}. Revisa Admin → Configuración.";
        }
        if (str_contains($msg, 'Access denied') || str_contains($msg, 'getaddrinfo')
            || str_contains($msg, 'Connection refused') || str_contains($msg, $dom->glpi())) {
            return "No se pudo conectar al GLPI de {$dom->label()}. Revisa Admin → Configuración.";
        }
        if (str_contains($msg, 'Base table') || str_contains($msg, '1146')) {
            return "La base de datos GLPI de {$dom->label()} no tiene las tablas esperadas.";
        }

        return 'Error: ' . $msg;
    }

    /** Pantalla completa de error, para las vistas que sin datos no existen. */
    protected function vistaError(\Throwable $e, DominioInventario $dom,
                                  string $seccion, string $titulo, string $icono)
    {
        report($e);   // al log: el mensaje que se muestra ya no lleva el detalle

        return response()->view('inventario.error', [
            'dom'     => $dom,
            'seccion' => $seccion,
            'titulo'  => $titulo,
            'icono'   => $icono,
            'error'   => $this->mensajeError($e, $dom),
        ], 503);
    }
}
