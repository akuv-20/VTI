<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\CruceAdGlpi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioUnifruttiController extends Controller
{
    /** Conexión al GLPI de Unifrutti (helpdesk). */
    private const GLPI = 'glpi_unifrutti';

    // ── Cruce AD Unifrutti ↔ GLPI Unifrutti ──────────────────────────────────

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

    // ── Equipos inventariados en el GLPI de Unifrutti ─────────────────────────
    //
    // Espejo del listado de Inventario TI, pero sobre la conexión del helpdesk.
    // A diferencia de aquel, aquí NO se excluye ningún usuario: el EXCLUIR_USER
    // de Inventario TI es un id de SU base de GLPI, y en esta otra base ese
    // mismo id corresponde a una persona distinta.

    /**
     * Indicadores del listado: clave => [etiqueta, icono, color].
     *
     * Cada uno muestra su conteo y al pulsarlo filtra la tabla. Para agregar
     * uno nuevo basta sumarlo aquí y darle su condición en aplicarFiltro().
     */
    public const FILTROS = [
        'todos'         => ['Todos',                'bi-list-ul',            '#3b82f6'],
        'sin_eset'      => ['Sin ESET',             'bi-shield-exclamation', '#ef4444'],
        'sin_ubicacion' => ['Sin ubicación',        'bi-geo-alt',            '#eab308'],
        'sin_usuario'   => ['Sin usuario asignado', 'bi-person-dash',        '#f97316'],
    ];

    /**
     * Aplica la condición de un indicador sobre una consulta de glpi_computers
     * (que debe venir aliasada como `c`).
     */
    private function aplicarFiltro($query, string $filtro)
    {
        return match ($filtro) {
            'sin_ubicacion' => $query->where('c.locations_id', 0),
            'sin_usuario'   => $query->where('c.users_id', 0),

            // "Sin ESET" cubre dos casos que en la practica son el mismo
            // problema: que no este instalado, y que este pero desactivado.
            // Windows Defender queda registrado en casi todos los equipos, asi
            // que un "sin antivirus" generico daria siempre cero.
            'sin_eset' => $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('glpi_itemantiviruses as av')
                  ->whereColumn('av.items_id', 'c.id')
                  ->where('av.itemtype', 'Computer')
                  ->where('av.is_deleted', 0)
                  ->where('av.is_active', 1)
                  ->where('av.name', 'like', '%ESET%');
            }),

            default => $query,   // 'todos'
        };
    }

    /** Conteo de cada indicador sobre el universo completo (no la pagina). */
    private function conteosFiltros(string $search): array
    {
        $conteos = [];

        foreach (array_keys(self::FILTROS) as $clave) {
            $q = DB::connection(self::GLPI)
                ->table('glpi_computers as c')
                ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
                ->where('c.is_deleted', 0)
                ->where('c.is_template', 0);

            $this->aplicarFiltro($q, $clave);
            $this->aplicarBusqueda($q, $search);

            $conteos[$clave] = $q->distinct()->count('c.id');
        }

        return $conteos;
    }

    /** Filtro de texto libre, compartido por listado y conteos. */
    private function aplicarBusqueda($query, string $search)
    {
        if ($search === '') return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('c.name', 'like', "%{$search}%")
              ->orWhere('c.serial', 'like', "%{$search}%")
              ->orWhere('u.name', 'like', "%{$search}%")
              ->orWhereRaw("CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,'')) like ?", ["%{$search}%"]);
        });
    }

    public function equipos(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $filtro = (string) $request->input('filtro', 'todos');

        if (!array_key_exists($filtro, self::FILTROS)) {
            $filtro = 'todos';
        }

        try {
            $query = DB::connection(self::GLPI)
                ->table('glpi_computers as c')
                ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
                ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
                ->leftJoin('glpi_computermodels as cm', 'cm.id', '=', 'c.computermodels_id')
                ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
                ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                    $j->on('ios.items_id', '=', 'c.id')
                      ->where('ios.itemtype', 'Computer')
                      ->where('ios.is_deleted', 0);
                })
                ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
                ->leftJoin('glpi_agents as a', function ($j) {
                    $j->on('a.items_id', '=', 'c.id')->where('a.itemtype', 'Computer');
                })
                ->leftJoin('glpi_itemantiviruses as av', function ($j) {
                    $j->on('av.items_id', '=', 'c.id')
                      ->where('av.itemtype', 'Computer')
                      ->where('av.is_deleted', 0)
                      ->where('av.name', 'like', '%ESET%');
                })
                ->select([
                    'c.id',
                    'c.name as nombre_equipo',
                    'c.serial as numero_serie',
                    DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,''))) as nombre_usuario"),
                    'man.name as marca',
                    'cm.name as modelo',
                    'loc.completename as ubicacion',
                    'os.name as sistema_operativo',
                    DB::raw('MAX(a.last_contact) as last_contact'),
                    DB::raw('MAX(av.antivirus_version) as eset_version'),
                    DB::raw('MAX(av.is_active) as eset_activo'),
                ])
                ->where('c.is_deleted', 0)
                ->where('c.is_template', 0)
                ->groupBy('c.id', 'c.name', 'c.serial', 'u.firstname', 'u.realname',
                         'man.name', 'cm.name', 'loc.completename', 'os.name');

            $this->aplicarFiltro($query, $filtro);
            $this->aplicarBusqueda($query, $search);

            $computadores = $query->orderBy('c.name')->paginate(25)->withQueryString();

            return view('admin.inventario_unifrutti.equipos', [
                'computadores' => $computadores,
                'search'       => $search,
                'filtro'       => $filtro,
                'conteos'      => $this->conteosFiltros($search),
                'diasAgente'   => (int) (Configuracion::get('cruce_dias_agente', 90) ?: 90),
            ]);

        } catch (\Throwable $e) {
            return view('admin.inventario_unifrutti.equipos', [
                'computadores' => null,
                'search'       => $search,
                'filtro'       => $filtro,
                'conteos'      => [],
                'diasAgente'   => 90,
                'error'        => $this->mensajeError($e),
            ]);
        }
    }

    /** Ficha de un equipo del GLPI de Unifrutti. */
    public function equipoShow($id)
    {
        $equipo = DB::connection(self::GLPI)
            ->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
            ->leftJoin('glpi_computermodels as cm', 'cm.id', '=', 'c.computermodels_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->leftJoin('glpi_computertypes as ct', 'ct.id', '=', 'c.computertypes_id')
            ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                $j->on('ios.items_id', '=', 'c.id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->select([
                'c.id',
                'c.name as nombre_equipo',
                'c.serial as numero_serie',
                'c.otherserial as numero_inventario',
                'c.comment',
                'c.date_creation',
                'c.date_mod',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,''))) as nombre_usuario"),
                'u.phone as telefono_usuario',
                'man.name as marca',
                'cm.name as modelo',
                'loc.completename as ubicacion',
                'os.name as sistema_operativo',
                'ct.name as tipo',
            ])
            ->where('c.id', $id)
            ->where('c.is_deleted', 0)
            ->groupBy('c.id')
            ->first();

        abort_if(!$equipo, 404);

        return view('admin.inventario_unifrutti.equipo', [
            'equipo'     => $equipo,
            'hardware'   => $this->hardwareDe((int) $equipo->id),
            'agente'     => $this->agenteDe((int) $equipo->id),
            'antivirus'  => $this->antivirusDe((int) $equipo->id),
            'diasAgente' => (int) (Configuracion::get('cruce_dias_agente', 90) ?: 90),
        ]);
    }

    // ── Helpers de hardware (espejo de InventarioTiController) ────────────────

    /** Procesador, RAM total y disco principal de un equipo. */
    private function hardwareDe(int $computerId): array
    {
        $glpi = DB::connection(self::GLPI);

        $procesador = $glpi->table('glpi_items_deviceprocessors as ip')
            ->join('glpi_deviceprocessors as dp', 'dp.id', '=', 'ip.deviceprocessors_id')
            ->where('ip.itemtype', 'Computer')
            ->where('ip.items_id', $computerId)
            ->where('ip.is_deleted', 0)
            ->value('dp.designation');

        $ramMb = $glpi->table('glpi_items_devicememories')
            ->where('itemtype', 'Computer')->where('items_id', $computerId)
            ->where('is_deleted', 0)->sum('size');

        $discoMb = $glpi->table('glpi_items_deviceharddrives')
            ->where('itemtype', 'Computer')->where('items_id', $computerId)
            ->where('is_deleted', 0)->max('capacity');

        return [
            'procesador' => $procesador,
            'ram'        => $ramMb   ? $this->formatoCapacidad((int) $ramMb)   : null,
            'disco'      => $discoMb ? $this->formatoCapacidad((int) $discoMb) : null,
        ];
    }

    /** Datos del agente de inventario, si el equipo tiene uno. */
    private function agenteDe(int $computerId): ?object
    {
        return DB::connection(self::GLPI)
            ->table('glpi_agents')
            ->where('itemtype', 'Computer')
            ->where('items_id', $computerId)
            ->orderByDesc('last_contact')
            ->select('name', 'version', 'deviceid', 'last_contact')
            ->first();
    }

    /** Antivirus registrados en el equipo, los activos primero. */
    private function antivirusDe(int $computerId)
    {
        return DB::connection(self::GLPI)
            ->table('glpi_itemantiviruses')
            ->where('itemtype', 'Computer')
            ->where('items_id', $computerId)
            ->where('is_deleted', 0)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->select('name', 'antivirus_version', 'signature_version',
                     'is_active', 'is_uptodate', 'date_expiration')
            ->get();
    }

    /** Convierte MB a GB o TB redondeado (ej: 32768 → "32 GB"). */
    private function formatoCapacidad(int $mb): string
    {
        // Discos comerciales usan base decimal: 1 TB ≈ 1.000.000 MB
        if ($mb >= 950000) {
            return round($mb / 1000000, $mb < 1500000 ? 0 : 1) . ' TB';
        }
        $gb = $mb / 1024;
        if ($gb >= 1) {
            return round($gb) . ' GB';
        }
        return $mb . ' MB';
    }

    // ── Errores ──────────────────────────────────────────────────────────────

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
        if (str_contains($msg, 'Base table') || str_contains($msg, '1146')) {
            return 'La base de datos GLPI de Unifrutti no tiene las tablas esperadas. Verifica que apunte a la BD correcta.';
        }

        return 'Error: ' . $msg;
    }
}
