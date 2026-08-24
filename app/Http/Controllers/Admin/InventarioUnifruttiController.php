<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\CruceAdGlpi;
use Illuminate\Http\Request;

class InventarioUnifruttiController extends Controller
{
    /** Cruce AD Unifrutti ↔ GLPI Unifrutti. */
    public function index(Request $request)
    {
        $cruce = new CruceAdGlpi();

        try {
            $data = $cruce->analizar();

            return view('admin.inventario_unifrutti.index', [
                'equipos'    => $data['equipos'],
                'resumen'    => $data['resumen'],
                'generado'   => $data['generado'],
                'diasBaja'   => $cruce->diasBaja(),
                'diasAgente' => $cruce->diasAgente(),
            ]);

        } catch (\Throwable $e) {
            return view('admin.inventario_unifrutti.index', [
                'equipos'    => collect(),
                'resumen'    => null,
                'generado'   => null,
                'diasBaja'   => $cruce->diasBaja(),
                'diasAgente' => $cruce->diasAgente(),
                'error'      => $this->mensajeError($e),
            ]);
        }
    }

    /** Guardar umbrales configurables. */
    public function ajustes(Request $request)
    {
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

    /** Forzar recarga desde AD y GLPI (vacía la caché). */
    public function refrescar(Request $request)
    {
        CruceAdGlpi::olvidarCache();

        return back()->with('success', 'Datos actualizados desde AD y GLPI.');
    }

    private function mensajeError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'No connections exist') || str_contains($msg, 'tertiary')) {
            return 'No se pudo conectar al AD de Unifrutti. Revisa Admin → Configuración → AD Unifrutti.';
        }
        if (str_contains($msg, 'Access denied') || str_contains($msg, 'getaddrinfo')
            || str_contains($msg, 'Connection refused') || str_contains($msg, 'glpi_unifrutti')) {
            return 'No se pudo conectar al GLPI de Unifrutti. Revisa Admin → Configuración → GLPI Unifrutti.';
        }
        if (str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table')) {
            return 'La base de datos GLPI de Unifrutti no tiene las tablas esperadas. Verifica que apunte a la BD correcta.';
        }

        return 'Error al cruzar AD ↔ GLPI: ' . $msg;
    }
}
