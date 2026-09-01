<?php

namespace App\Services;

use App\Models\InventarioExcepcion;
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
        $filtros = ['todos' => ['Todos', 'bi-list-ul', '#3b82f6']];

        if ($av = $this->dominio->antivirus()) {
            $filtros['sin_antivirus'] = ["Sin {$av}", 'bi-shield-exclamation', '#ef4444'];

            // El indicador de exceptuados solo aparece si hay reglas: un tile
            // en cero permanente es ruido, y de paso deja ver de un vistazo
            // cuando alguien agrega una excepción.
            if ($this->excepciones()->isNotEmpty()) {
                $filtros['exceptuados'] = ['Exceptuados', 'bi-shield-slash', '#94a3b8'];
            }
        }

        $filtros['sin_ubicacion'] = ['Sin ubicación',        'bi-geo-alt',     '#eab308'];
        $filtros['sin_usuario']   = ['Sin usuario asignado', 'bi-person-dash', '#f97316'];

        return $filtros;
    }

    /**
     * Consulta base de equipos vivos del dominio, con el join del sistema
     * operativo ya puesto para que las reglas de excepción puedan mirarlo.
     */
    public function baseEquipos()
    {
        $q = $this->db()->table('glpi_computers as c')
            ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                $j->on('ios.items_id', '=', 'c.id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->where('c.is_deleted', 0)
            ->where('c.is_template', 0);

        return $this->dominio->sinUsuarioExcluido($q);
    }

    /** Reglas de excepción vigentes para este dominio (se resuelven una vez). */
    public function excepciones(): Collection
    {
        return $this->excepciones ??= InventarioExcepcion::activas()
            ->delDominio($this->dominio->clave)
            ->orderBy('campo')->orderBy('valor')
            ->get();
    }

    private ?Collection $excepciones = null;

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
            //
            // Los equipos exceptuados quedan fuera: macOS, Linux o servidores
            // que nunca van a llevar el antivirus corporativo ensucian el
            // indicador de forma permanente.
            'sin_antivirus' => InventarioExcepcion::aplicarA(
                $this->sinAntivirusCorporativo($query),
                $this->excepciones(),
                negar: true,
            ),

            // Los exceptuados: se muestran aparte en vez de esconderse, para
            // poder auditar qué se dejó fuera y por qué.
            //
            // Solo cuentan los que SIN la excepción figurarían como "sin
            // antivirus". Si no, el indicador incluiría equipos que sí lo
            // tienen instalado y dejaría de cuadrar: la suma de "sin
            // antivirus" + "exceptuados" tiene que dar el total original.
            'exceptuados' => InventarioExcepcion::aplicarA(
                $this->sinAntivirusCorporativo($query),
                $this->excepciones(),
            ),

            default => $query,   // 'todos'
        };
    }

    /**
     * Condición "no tiene el antivirus corporativo".
     *
     * Un equipo se considera protegido si cumple CUALQUIERA de estas dos:
     *   - tiene el antivirus activo en la sección de antivirus del inventario, o
     *   - tiene el producto entre el software instalado.
     *
     * La segunda vía existe porque el agente de GLPI no siempre reporta ESET en
     * `glpi_itemantiviruses` (depende del SecurityCenter de Windows), y quedaban
     * equipos con ESET instalado marcados como desprotegidos. Entonces "sin
     * antivirus" = ni activo en la tabla de AV, ni presente en el software.
     *
     * Vive aparte porque la comparte el dashboard: tener dos definiciones de
     * "sin antivirus" hacía que las dos pantallas mostraran números distintos.
     */
    public function sinAntivirusCorporativo($query, string $alias = 'c')
    {
        $lista = $this->dominio->antivirusLista();

        if (!$lista) return $query;

        return $query
            ->whereNotExists(function ($q) use ($lista, $alias) {
                $q->select(DB::raw(1))
                  ->from('glpi_itemantiviruses as av')
                  ->whereColumn('av.items_id', "{$alias}.id")
                  ->where('av.itemtype', 'Computer')
                  ->where('av.is_deleted', 0)
                  ->where('av.is_active', 1)
                  ->where(fn($w) => $this->orNombres($w, 'av.name', $lista));
            })
            ->whereNotExists(function ($q) use ($lista, $alias) {
                $q->select(DB::raw(1))
                  ->from('glpi_items_softwareversions as isv')
                  ->join('glpi_softwareversions as sv', 'sv.id', '=', 'isv.softwareversions_id')
                  ->join('glpi_softwares as s', 's.id', '=', 'sv.softwares_id')
                  ->whereColumn('isv.items_id', "{$alias}.id")
                  ->where('isv.itemtype', 'Computer')
                  ->where(fn($w) => $this->orNombres($w, 's.name', $lista));
            });
    }

    /**
     * Agrega el match del antivirus con OR para cada nombre, exigiendo que la
     * marca aparezca al inicio o tras un espacio (límite de palabra).
     *
     * No usar `%n%` a secas: nombres como «ChineseTextConverterService»,
     * «RemoteSetup» o «TTRFServiceSetup» contienen la subcadena "eset" y daban
     * falsos positivos de ESET en equipos que no lo tienen.
     */
    private function orNombres($w, string $col, array $lista)
    {
        foreach ($lista as $n) {
            $w->orWhere($col, 'like', "{$n}%")     // empieza con la marca
              ->orWhere($col, 'like', "% {$n}%");  // …o precedida de espacio
        }
        return $w;
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
            // El join del sistema operativo va siempre: las reglas de excepción
            // pueden mirar `os.name`, y sin él la condición no compila.
            $q = $this->db()->table('glpi_computers as c')
                ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
                ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                    $j->on('ios.items_id', '=', 'c.id')
                      ->where('ios.itemtype', 'Computer')
                      ->where('ios.is_deleted', 0);
                })
                ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
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

    public function equipos(
        string $search = '',
        string $filtro = 'todos',
        int $porPagina = 25,
        ?string $versionAgente = null,
        ?string $so = null,
        ?string $ubicacion = null,
        array $drills = [],
        int $diasAgente = 90,
    ) {
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
                  ->where('av.is_deleted', 0);

                // Un equipo cuenta como protegido si tiene CUALQUIERA de los
                // antivirus corporativos del dominio (Verfrut acepta Bitdefender
                // o ESET durante la migración).
                $lista = $this->dominio->antivirusLista();
                if ($lista) {
                    $j->where(fn($w) => $this->orNombres($w, 'av.name', $lista));
                }
            })
            ->select([
                'c.id',
                'c.name as nombre_equipo',
                'c.serial as numero_serie',
                'c.contact as usuario_alternativo',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,''))) as nombre_usuario"),
                'man.name as marca',
                'cm.name as modelo',
                'loc.completename as ubicacion',
                'os.name as sistema_operativo',
                DB::raw('MAX(a.last_contact) as last_contact'),
                DB::raw('MAX(av.antivirus_version) as av_version'),
                DB::raw('MAX(av.is_active) as av_activo'),
                DB::raw('MAX(av.name) as av_nombre'),
            ])
            ->where('c.is_deleted', 0)
            ->where('c.is_template', 0)
            ->groupBy('c.id', 'c.name', 'c.serial', 'c.contact', 'u.firstname', 'u.realname',
                     'man.name', 'cm.name', 'loc.completename', 'os.name');

        // Respaldo: nombre del antivirus corporativo hallado entre el software
        // instalado (null si no hay), para cuando el agente no lo reporta en la
        // tabla de AV. Sirve además para distinguir Bitdefender de ESET.
        if ($avLista = $this->dominio->antivirusLista()) {
            // Mismo límite de palabra que orNombres(): inicio o tras espacio.
            $parts = [];
            $bindSw = [];
            foreach ($avLista as $n) {
                $parts[]  = 's.name LIKE ?';  $bindSw[] = "{$n}%";
                $parts[]  = 's.name LIKE ?';  $bindSw[] = "% {$n}%";
            }
            $condSw = implode(' OR ', $parts);
            $query->selectRaw(
                "(SELECT s.name FROM glpi_items_softwareversions isv
                    JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id
                    JOIN glpi_softwares s ON s.id = sv.softwares_id
                    WHERE isv.items_id = c.id AND isv.itemtype = 'Computer'
                      AND ({$condSw})
                    ORDER BY s.name LIMIT 1) as av_software",
                $bindSw
            );
        }

        $this->dominio->sinUsuarioExcluido($query);
        $this->aplicarFiltro($query, $filtro);
        $this->aplicarBusqueda($query, $search);

        // Drill-down desde el dashboard: equipos cuyo agente reporta esta versión.
        if ($versionAgente !== null && $versionAgente !== '') {
            $query->where('a.version', $versionAgente);
        }

        // Drill-down por sistema operativo y por ubicación (mismos textos que el
        // dashboard: os.name y loc.completename).
        if ($so !== null && $so !== '') {
            $query->where('os.name', $so);
        }
        if ($ubicacion !== null && $ubicacion !== '') {
            $query->where('loc.completename', $ubicacion);
        }

        // Drill-downs booleanos desde los KPI del dashboard.
        if (!empty($drills['sin_agente'])) {
            $query->whereNull('a.items_id');   // ningún agente vinculado
        }
        if (!empty($drills['agente_inactivo'])) {
            $fecha = now()->subDays($diasAgente)->toDateTimeString();
            $query->havingRaw('MAX(a.last_contact) < ?', [$fecha]);
        }
        if (!empty($drills['duplicados'])) {
            $dupSeriales = $this->db()->table('glpi_computers')
                ->where('is_deleted', 0)->where('is_template', 0)
                ->whereNotNull('serial')->where('serial', '!=', '')
                ->select('serial')
                ->groupBy('serial')
                ->havingRaw('COUNT(*) > 1');
            $query->whereIn('c.serial', $dupSeriales);
        }

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

    /**
     * Software instalado que corresponde al antivirus corporativo del dominio.
     *
     * Sirve de respaldo en la ficha: cuando el agente no reportó el AV en
     * `glpi_itemantiviruses`, esto confirma que el producto igual está instalado.
     */
    public function antivirusSoftwareDe(int $computerId): Collection
    {
        $lista = $this->dominio->antivirusLista();

        if (!$lista) return collect();

        return $this->db()
            ->table('glpi_items_softwareversions as isv')
            ->join('glpi_softwareversions as sv', 'sv.id', '=', 'isv.softwareversions_id')
            ->join('glpi_softwares as s', 's.id', '=', 'sv.softwares_id')
            ->where('isv.items_id', $computerId)
            ->where('isv.itemtype', 'Computer')
            ->where(fn($w) => $this->orNombres($w, 's.name', $lista))
            ->orderBy('s.name')
            ->select('s.name', 'sv.name as version')
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
