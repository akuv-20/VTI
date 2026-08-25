<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Services\DominioInventario;

/**
 * Pantalla de elección de dominio.
 *
 * Cada sección del módulo (dashboard, equipos, cruce, actas) entra por acá:
 * se eligen los botones grandes y recién ahí se ve el contenido del dominio.
 *
 * Si el usuario solo tiene permiso sobre un dominio no tiene nada que elegir,
 * así que se le manda directo y nunca ve esta pantalla.
 */
class SelectorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard() { return $this->elegir('dashboard'); }
    public function equipos()   { return $this->elegir('equipos'); }
    public function cruce()     { return $this->elegir('cruce'); }
    public function actas()     { return $this->elegir('actas'); }

    private function elegir(string $seccion)
    {
        $dominios = DominioInventario::permitidos();

        abort_if($dominios->isEmpty(), 403);

        if ($dominios->count() === 1) {
            return redirect()->route("inventario.{$dominios->first()->clave}.{$seccion}");
        }

        return view('inventario.selector', [
            'seccion'  => $seccion,
            'dominios' => $dominios,
            'titulo'   => self::TITULOS[$seccion] ?? ucfirst($seccion),
            'icono'    => self::ICONOS[$seccion]  ?? 'bi-pc-display',
        ]);
    }

    private const TITULOS = [
        'dashboard' => 'Dashboard',
        'equipos'   => 'Equipos',
        'cruce'     => 'Cruce AD ↔ GLPI',
        'actas'     => 'Actas de Entrega',
    ];

    private const ICONOS = [
        'dashboard' => 'bi-speedometer2',
        'equipos'   => 'bi-display-fill',
        'cruce'     => 'bi-diagram-3',
        'actas'     => 'bi-file-earmark-text-fill',
    ];
}
