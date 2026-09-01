<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\ActaEntregaEquipo;
use App\Models\Configuracion;
use App\Services\InventarioGlpi;
use Illuminate\Http\Request;

/**
 * Listado y ficha de equipos, para cualquier dominio.
 *
 * El dominio llega como parámetro de ruta (lo inyectan los grupos que arma
 * routes/web.php con ->defaults()), y de él salen las conexiones.
 */
class EquipoController extends BaseController
{
    public function index(Request $request)
    {
        $dom = $this->dominio();
        $glpi   = new InventarioGlpi($dom);
        $search = trim((string) $request->input('q', ''));
        $filtro = (string) $request->input('filtro', 'todos');
        $versionAgente = trim((string) $request->input('version_agente', '')) ?: null;
        $so            = trim((string) $request->input('so', '')) ?: null;
        $ubicacion     = trim((string) $request->input('ubicacion', '')) ?: null;

        // Drill-downs booleanos que llegan de los KPI del dashboard.
        $boolDrills = [];
        foreach (['sin_agente', 'agente_inactivo', 'duplicados'] as $d) {
            if ($request->boolean($d)) {
                $boolDrills[$d] = 1;
            }
        }

        $diasAgente = (int) (Configuracion::get('cruce_dias_agente', 90) ?: 90);

        $filtros = $glpi->filtros();

        if (!array_key_exists($filtro, $filtros)) {
            $filtro = 'todos';
        }

        try {
            return view('inventario.equipos', [
                'dom'           => $dom,
                'computadores'  => $glpi->equipos($search, $filtro, 25, $versionAgente, $so, $ubicacion, $boolDrills, $diasAgente),
                'filtros'       => $filtros,
                'conteos'       => $glpi->conteosFiltros($search),
                'excepciones'   => $glpi->excepciones(),
                'search'        => $search,
                'filtro'        => $filtro,
                'versionAgente' => $versionAgente,
                'so'            => $so,
                'ubicacion'     => $ubicacion,
                'boolDrills'    => $boolDrills,
                'diasAgente'    => $diasAgente,
            ]);

        } catch (\Throwable $e) {
            return view('inventario.equipos', [
                'dom'           => $dom,
                'computadores'  => null,
                'filtros'       => $filtros,
                'conteos'       => [],
                'excepciones'   => collect(),
                'search'        => $search,
                'filtro'        => $filtro,
                'versionAgente' => $versionAgente,
                'so'            => $so,
                'ubicacion'     => $ubicacion,
                'boolDrills'    => $boolDrills,
                'diasAgente'    => $diasAgente,
                'error'         => $this->mensajeError($e, $dom),
            ]);
        }
    }

    public function show($id)
    {
        $dom = $this->dominio();
        $glpi = new InventarioGlpi($dom);

        $equipo = $glpi->equipo((int) $id);
        abort_if(!$equipo, 404);

        // Las actas se filtran por dominio: sin eso, el id 45 de un GLPI
        // traería las actas del 45 del otro.
        $actas = ActaEntregaEquipo::where('dominio', $dom->clave)
            ->where('glpi_computer_id', $id)
            ->latest()
            ->get();

        return view('inventario.equipo', [
            'dom'        => $dom,
            'equipo'     => $equipo,
            'hardware'    => $glpi->hardwareDe((int) $equipo->id),
            'agente'      => $glpi->agenteDe((int) $equipo->id),
            'antivirus'   => $glpi->antivirusDe((int) $equipo->id),
            'avSoftware'  => $glpi->antivirusSoftwareDe((int) $equipo->id),
            'actas'      => $actas,
            'diasAgente' => (int) (Configuracion::get('cruce_dias_agente', 90) ?: 90),
        ]);
    }
}
