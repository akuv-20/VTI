<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\CruceAdGlpi;
use App\Services\DominioInventario;
use Illuminate\Http\Request;

/** Cruce del AD de un dominio contra el inventario de su GLPI. */
class CruceController extends BaseController
{
    public function index()
    {
        $dom = $this->dominio();
        $cruce = new CruceAdGlpi($dom);

        try {
            $data = $cruce->analizar();

            return view('inventario.cruce', [
                'dom'        => $dom,
                'equipos'    => $data['equipos'],
                'resumen'    => $data['resumen'],
                'generado'   => $data['generado'],
                'diasBaja'   => $cruce->diasBaja(),
                'diasAgente' => $cruce->diasAgente(),
            ]);

        } catch (\Throwable $e) {
            return view('inventario.cruce', [
                'dom'        => $dom,
                'equipos'    => collect(),
                'resumen'    => null,
                'generado'   => null,
                'diasBaja'   => $cruce->diasBaja(),
                'diasAgente' => $cruce->diasAgente(),
                'error'      => $this->mensajeError($e, $dom),
            ]);
        }
    }

    /** Umbrales configurables (compartidos por todos los dominios). */
    public function ajustes(Request $request)
    {
        $this->dominio();

        $request->validate([
            'cruce_dias_baja'   => 'required|integer|min:1|max:3650',
            'cruce_dias_agente' => 'required|integer|min:1|max:3650',
        ], [
            'cruce_dias_baja.required'   => 'Indica los días para "posible baja".',
            'cruce_dias_agente.required' => 'Indica los días para "agente mudo".',
        ]);

        Configuracion::set('cruce_dias_baja',   (int) $request->input('cruce_dias_baja'));
        Configuracion::set('cruce_dias_agente', (int) $request->input('cruce_dias_agente'));

        return back()->with('success', 'Umbrales actualizados.');
    }

    /** Fuerza la recarga desde AD y GLPI de este dominio. */
    public function refrescar()
    {
        (new CruceAdGlpi($this->dominio()))->olvidarCache();

        return back()->with('success', 'Datos actualizados desde AD y GLPI.');
    }

    private function mensajeError(\Throwable $e, DominioInventario $dom): string
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

        return 'Error al cruzar AD ↔ GLPI: ' . $msg;
    }
}
