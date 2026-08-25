<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas al GLPI de un dominio de inventario.
 *
 * Todo lo que antes vivía duplicado en InventarioTiController y en el de
 * Unifrutti pasa por acá: la única diferencia entre un dominio y otro es la
 * conexión y el usuario excluido, y ambos salen del DominioInventario.
 */
class InventarioGlpi
{
    public function __construct(private DominioInventario $dominio) {}

    private function db()
    {
        return DB::connection($this->dominio->glpi());
    }

    /* ── Indicadores del listado ─────────────────────────────────────────── */

    /**
     * Indicadores: clave => [etiqueta, icono, color].
     *
     * Muestran su conteo y al pulsarlos filtran la tabla. La etiqueta del
     * antivirus depende del dominio —Verfrut despliega Bitdefender y Unifrutti
     * ESET—, por eso esto es un método y no una constante.
     */
    public function filtros(): array
    {
        $filtros = [
            'todos'         => ['Todos',                'bi-list-ul',     '#3b82f6'],
            'sin_ubicacion' => ['Sin ubicación',        'bi-geo-alt',     '#eab308'],
            'sin_usuario'   => ['Sin usuario asignado', 'bi-person-dash', '#f97316'],
        ];

        if ($av = $this->dominio->antivirus()) {
            $filtros = array_merge(
                ['todos' => $filtros['todos']],
                ['sin_antivirus' => ["Sin {$av}", 'bi-shield-exclamation', '#ef4444']],
                array_diff_key($filtros, ['todos' => null]),
            );
        }

        return $filtros;
    }

    /** Aplica la condición de un indicador sobre una consulta aliasada como `c`. */
    private function aplicarFiltro($query, string $filtro)
    {
        return match ($filtro) {
            'sin_ubicacion' => $query->where('c.locations_id', 0),
            'sin_usuario'   => $query->where('c.users_id', 0),

            // Cubre dos casos que en la práctica son el mismo problema: que el
            // antivirus corporativo no esté instalado, y que esté pero
            // desactivado. Se busca el del dominio y no "cualquier antivirus",
            // porque Windows Defender queda registrado en casi todos los
            // equipos y el chequeo genérico daría siempre cero.
            'sin_antivirus' => $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('glpi_itemantiviruses as av')
                  ->whereColumn('av.items_id', 'c.id')
                  ->where('av.itemtype', 'Computer')
                  ->where('av.is_deleted', 0)
                  ->where('av.is_active', 1)
                  ->where('av.name', 'like', '%' . $this->dominio->antivirus() . '%');
            }),

            default => $query,   // 'todos'
        };
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

    /** Conteo de cada indicador sobre el universo completo (no la página). */
    public function conteosFiltros(string $search = ''): array
    {
        $conteos = [];

        foreach (array_keys($this->filtros()) as $clave) {
            $q = $this->db()->table('glpi_computers as c')
                ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
                ->where('c.is_deleted', 0)
                ->where('c.is_template', 0);

            $this->dominio->sinUsuarioExcluido($q);
            $this->aplicarFiltro($q, $clave);
            $this->aplicarBusqueda($q, $search);

            $conteos[$clave] = $q->distinct()->count('c.id');
        }

        return $conteos;
    }

    /* ── Listado de equipos ──────────────────────────────────────────────── */

    public function equipos(string $search = '', string $filtro = 'todos', int $porPagina = 25)
    {
        $query = $this->db()
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
                  ->where('av.name', 'like', '%' . $this->dominio->antivirus() . '%');
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
                DB::raw('MAX(av.antivirus_version) as av_version'),
                DB::raw('MAX(av.is_active) as av_activo'),
            ])
            ->where('c.is_deleted', 0)
            ->where('c.is_template', 0)
            ->groupBy('c.id', 'c.name', 'c.serial', 'u.firstname', 'u.realname',
                     'man.name', 'cm.name', 'loc.completename', 'os.name');

        $this->dominio->sinUsuarioExcluido($query);
        $this->aplicarFiltro($query, $filtro);
        $this->aplicarBusqueda($query, $search);

        return $query->orderBy('c.name')->paginate($porPagina)->withQueryString();
    }

    /* ── Ficha de un equipo ──────────────────────────────────────────────── */

    public function equipo(int $id): ?object
    {
        return $this->db()
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
    }

    /** Procesador, RAM total y disco principal de un equipo. */
    public function hardwareDe(int $computerId): array
    {
        $procesador = $this->db()->table('glpi_items_deviceprocessors as ip')
            ->join('glpi_deviceprocessors as dp', 'dp.id', '=', 'ip.deviceprocessors_id')
            ->where('ip.itemtype', 'Computer')
            ->where('ip.items_id', $computerId)
            ->where('ip.is_deleted', 0)
            ->value('dp.designation');

        $ramMb = $this->db()->table('glpi_items_devicememories')
            ->where('itemtype', 'Computer')->where('items_id', $computerId)
            ->where('is_deleted', 0)->sum('size');

        $discoMb = $this->db()->table('glpi_items_deviceharddrives')
            ->where('itemtype', 'Computer')->where('items_id', $computerId)
            ->where('is_deleted', 0)->max('capacity');

        return [
            'procesador' => $procesador,
            'ram'        => $ramMb   ? $this->formatoCapacidad((int) $ramMb)   : null,
            'disco'      => $discoMb ? $this->formatoCapacidad((int) $discoMb) : null,
        ];
    }

    /** Datos del agente de inventario, si el equipo tiene uno. */
    public function agenteDe(int $computerId): ?object
    {
        return $this->db()
            ->table('glpi_agents')
            ->where('itemtype', 'Computer')
            ->where('items_id', $computerId)
            ->orderByDesc('last_contact')
            ->select('name', 'version', 'deviceid', 'last_contact')
            ->first();
    }

    /** Antivirus registrados en el equipo, los activos primero. */
    public function antivirusDe(int $computerId): Collection
    {
        return $this->db()
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
    public function formatoCapacidad(int $mb): string
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
}
