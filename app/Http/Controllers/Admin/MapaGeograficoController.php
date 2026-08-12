<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sitio;

/**
 * Mapa geográfico de los sitios.
 *
 * Una sola pantalla, sin tarjetas alrededor: el mapa ocupa todo el alto útil.
 * Los datos van resueltos a un arreglo plano —nada de modelos— porque la vista
 * los serializa con `json_encode` para el navegador.
 */
class MapaGeograficoController extends Controller
{
    public function index()
    {
        $sitios = Sitio::activos()
            ->with(['zona:id,nombre'])
            ->ordenados()
            ->get();

        $conGeo = $sitios->filter(fn(Sitio $s) => $s->latitud && $s->longitud);

        $puntos = [];
        foreach ($conGeo as $s) {
            $puntos[] = [
                'lat'    => (float) $s->latitud,
                'lon'    => (float) $s->longitud,
                'nombre' => $s->titulo,
                'estado' => $s->estado_enlace,
                'label'  => $s->estado_enlace_label,
                'color'  => $s->estado_enlace_color,
                'tipo'   => $s->tipo_label,
                'comuna' => $s->comuna,
                'zona'   => $s->zona?->nombre,
                'compl'  => $s->completitud,
                'url'    => route('admin.sitios.show', $s),
            ];
        }

        return view('admin.mapa_geografico.index', [
            'puntos'    => $puntos,
            'sinCoords' => $sitios->reject(fn(Sitio $s) => $s->latitud && $s->longitud)->values(),
        ]);
    }
}
