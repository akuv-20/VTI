<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ranking de técnicos por tickets solucionados y cerrados, leído del GLPI de
 * Unifrutti: es la única ticketera en uso. El GLPI de Verfrut sigue conectado
 * para Inventario, pero su mesa de ayuda quedó fuera a propósito.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Criterios que hay que tener presentes al leer los números:
 *
 *  1. Un ticket cuenta para QUIEN LO SOLUCIONÓ: el autor de su última
 *     solución no rechazada (glpi_itilsolutions, status ≠ 4). Si el usuario
 *     rechaza una solución y otro técnico escribe la siguiente, cuenta para
 *     el segundo. Así cada ticket suma una sola vez, aunque tenga varios
 *     técnicos asignados. Los tickets cerrados sin solución registrada (se
 *     cierran a mano, ~6%) cuentan para su técnico asignado (type=2), para
 *     no perderlos; si tienen dos, suma a ambos.
 *
 *  2. El periodo se mide por `solvedate`, no por `closedate`: todo ticket
 *     cerrado pasó antes por solucionado, y así un ticket no cambia de mes
 *     cuando el usuario tarda en aprobar la solución.
 *
 *  3. Estados de GLPI: 5 = solucionado (espera la aprobación del usuario),
 *     6 = cerrado.
 *
 *  4. Las fechas de GLPI son columnas `timestamp`, que MySQL entrega en la
 *     zona de la sesión, y el servidor de Unifrutti está en UTC. Por eso cada
 *     consulta fija la sesión en hora de Chile y los periodos se arman en esa
 *     misma zona: sin eso, el corte de mes queda corrido tres horas.
 *
 *  5. El país del técnico sale de su ubicación en GLPI (glpi_locations, la
 *     raíz de completename: «Chile», «Perú > Lima»). Quien no tiene ubicación
 *     solo aparece con «Todos los países».
 * ─────────────────────────────────────────────────────────────────────────────
 */
class RankingTickets
{
    /** Dominio de config/inventario.php cuyo GLPI es la ticketera. */
    public const DOMINIO = 'unifrutti';

    /** Zona en la que se leen las fechas de GLPI y se arman los periodos (punto 4). */
    public const ZONA = 'America/Santiago';

    private const CACHE_TTL       = 300; // segundos
    private const CACHE_TTL_ERROR = 60;

    /** Mínimo de tickets para competir por las insignias de rapidez, SLA y satisfacción. */
    private const MINIMO_INSIGNIA = 5;

    /** Países del filtro: clave de la URL → nombres de la raíz de la ubicación en GLPI (punto 5). */
    public const PAISES = [
        'chile' => ['Chile'],
        'peru'  => ['Perú', 'Peru'],
    ];

    private DominioInventario $dominio;

    public function __construct()
    {
        $this->dominio = DominioInventario::oFalla(self::DOMINIO);
    }

    /* ── Caché ────────────────────────────────────────────────────────────── */

    private static function clave(string $que, ?Carbon $desde, ?Carbon $hasta, ?string $grupo, ?string $pais = null): string
    {
        return 'ranking_tickets_' . md5(implode('|', [
            self::version(), $que, $desde?->toDateTimeString(), $hasta?->toDateTimeString(), $grupo, $pais,
        ]));
    }

    /**
     * Ejecuta la consulta o la sirve desde la caché. El fallo también se
     * cachea (más corto): con el GLPI caído, cada carga esperaría el timeout.
     */
    private function recordar(string $clave, \Closure $consulta): array
    {
        $guardado = Cache::get($clave);
        if ($guardado !== null) return $guardado;

        try {
            $resultado = ['ok' => true, 'datos' => $consulta()];
            Cache::put($clave, $resultado, self::CACHE_TTL);
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['ok' => false, 'error' => $e->getMessage()];
            Cache::put($clave, $resultado, self::CACHE_TTL_ERROR);
        }

        return $resultado;
    }

    /** Vacía la caché del ranking (botón «Actualizar»). */
    public static function olvidarCache(): void
    {
        // Las claves dependen del periodo y del grupo, así que no se pueden
        // enumerar: se rota una versión que forma parte de todas ellas.
        Cache::forever('ranking_tickets_version', self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get('ranking_tickets_version', 0);
    }

    /* ── Consultas a GLPI ─────────────────────────────────────────────────── */

    /**
     * Conexión al GLPI con la sesión en hora de Chile (punto 4). Se usa el
     * desplazamiento y no el nombre de la zona porque el servidor no tiene
     * cargadas las tablas de zonas horarias de MySQL.
     */
    private function conexion()
    {
        $db = DB::connection($this->dominio->glpi());
        $db->statement("SET time_zone = '" . now(self::ZONA)->format('P') . "'");

        return $db;
    }

    /** Condición «la ubicación l es de este país» (la raíz o cualquier hija). */
    private static function wherePais($q, string $pais): void
    {
        foreach (self::PAISES[$pais] as $nombre) {
            $q->orWhere('l.completename', $nombre)->orWhere('l.completename', 'like', $nombre . ' > %');
        }
    }

    /** País de una ubicación de GLPI, o null si no es de ninguno conocido. */
    public static function paisDe(?string $ubicacion): ?string
    {
        $raiz = mb_strtolower(trim(Str::before((string) $ubicacion, '>')));
        foreach (self::PAISES as $clave => $nombres) {
            if (in_array($raiz, array_map('mb_strtolower', $nombres), true)) return $clave;
        }

        return null;
    }

    /**
     * Tickets solucionados o cerrados del periodo, con los filtros de grupo y
     * de país (algún técnico asignado de ese país).
     */
    private function baseTickets(?Carbon $desde, ?Carbon $hasta, ?string $grupo, ?string $pais = null)
    {
        return $this->conexion()->table('glpi_tickets as t')
            ->where('t.is_deleted', 0)
            ->whereIn('t.status', [5, 6])
            ->when($desde, fn($q) => $q->where('t.solvedate', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('t.solvedate', '<=', $hasta))
            ->when($grupo, fn($q) => $q->whereExists(fn($s) => $s->selectRaw('1')
                ->from('glpi_groups_tickets as gt')
                ->join('glpi_groups as g', 'g.id', '=', 'gt.groups_id')
                ->whereColumn('gt.tickets_id', 't.id')
                ->where('gt.type', 2)
                ->where('g.completename', $grupo)))
            // País de quien se lleva el ticket (punto 1): el autor de la
            // solución o, si no hay solución, algún técnico asignado.
            ->when($pais, fn($q) => $q->where(fn($w) => $w
                ->whereExists(fn($s) => $s->selectRaw('1')
                    ->from('glpi_itilsolutions as ps')
                    ->join('glpi_users as pu', 'pu.id', '=', 'ps.users_id')
                    ->join('glpi_locations as l', 'l.id', '=', 'pu.locations_id')
                    ->whereColumn('ps.items_id', 't.id')
                    ->where('ps.itemtype', 'Ticket')
                    ->where('ps.status', '<>', 4)
                    ->where(fn($x) => self::wherePais($x, $pais)))
                ->orWhere(fn($o) => $o
                    ->whereNotExists(fn($s) => self::solucionVigente($s))
                    ->whereExists(fn($s) => $s->selectRaw('1')
                        ->from('glpi_tickets_users as px')
                        ->join('glpi_users as pu', 'pu.id', '=', 'px.users_id')
                        ->join('glpi_locations as l', 'l.id', '=', 'pu.locations_id')
                        ->whereColumn('px.tickets_id', 't.id')
                        ->where('px.type', 2)
                        ->where(fn($x) => self::wherePais($x, $pais))))));
    }

    /** Subconsulta «el ticket t tiene una solución no rechazada». */
    private static function solucionVigente($s)
    {
        return $s->selectRaw('1')->from('glpi_itilsolutions as sv')
            ->whereColumn('sv.items_id', 't.id')->where('sv.itemtype', 'Ticket')->where('sv.status', '<>', 4);
    }

    /** Una fila por técnico asignado, con sus contadores crudos. */
    private function porTecnico(?Carbon $desde, ?Carbon $hasta, ?string $grupo, ?string $pais): array
    {
        // Última solución no rechazada de cada ticket (punto 1).
        $ultimaSolucion = DB::connection($this->dominio->glpi())->table('glpi_itilsolutions')
            ->where('itemtype', 'Ticket')->where('status', '<>', 4)
            ->selectRaw('items_id, max(id) as id')->groupBy('items_id');

        return $this->baseTickets($desde, $hasta, $grupo, $pais)
            ->leftJoinSub($ultimaSolucion, 'us', 'us.items_id', '=', 't.id')
            ->leftJoin('glpi_itilsolutions as so', 'so.id', '=', 'us.id')
            // Sin solución registrada: cuenta para los técnicos asignados.
            ->leftJoin('glpi_tickets_users as tu', fn($j) => $j->on('tu.tickets_id', '=', 't.id')->where('tu.type', 2)->whereNull('us.id'))
            ->join('glpi_users as u', 'u.id', '=', DB::raw('coalesce(so.users_id, tu.users_id)'))
            ->leftJoin('glpi_locations as ul', 'ul.id', '=', 'u.locations_id')
            ->leftJoin('glpi_ticketsatisfactions as s', 's.tickets_id', '=', 't.id')
            ->selectRaw('u.id, u.name, u.firstname, u.realname, u.is_active, u.is_deleted, ul.completename as ubicacion')
            ->selectRaw('count(distinct t.id) as total')
            ->selectRaw('count(distinct case when t.status = 5 then t.id end) as solucionados')
            ->selectRaw('count(distinct case when t.status = 6 then t.id end) as cerrados')
            ->selectRaw('coalesce(sum(t.solve_delay_stat), 0) as seg_resolucion')
            ->selectRaw('sum(t.solve_delay_stat > 0) as con_tiempo')
            ->selectRaw('sum(t.time_to_resolve is not null) as con_sla')
            ->selectRaw('sum(t.time_to_resolve is not null and t.solvedate <= t.time_to_resolve) as sla_ok')
            ->selectRaw('coalesce(sum(s.satisfaction), 0) as satisfaccion_suma')
            ->selectRaw('count(s.satisfaction) as satisfaccion_n')
            ->groupBy('u.id', 'u.name', 'u.firstname', 'u.realname', 'u.is_active', 'u.is_deleted', 'ul.completename')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    /** Totales del periodo contando cada ticket una sola vez. */
    private function totales(?Carbon $desde, ?Carbon $hasta, ?string $grupo, ?string $pais): array
    {
        $r = $this->baseTickets($desde, $hasta, $grupo, $pais)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(t.status = 5) as solucionados')
            ->selectRaw('sum(t.status = 6) as cerrados')
            // Sin nadie a quien contarlo: ni solución con autor ni técnico asignado.
            ->selectRaw("sum(not exists (select 1 from glpi_itilsolutions s where s.items_id = t.id and s.itemtype = 'Ticket' and s.status <> 4)
                             and not exists (select 1 from glpi_tickets_users x where x.tickets_id = t.id and x.type = 2)) as sin_tecnico")
            ->selectRaw('coalesce(sum(t.solve_delay_stat), 0) as seg_resolucion')
            ->selectRaw('sum(t.solve_delay_stat > 0) as con_tiempo')
            ->first();

        return array_map('intval', (array) $r);
    }

    /* ── API pública ──────────────────────────────────────────────────────── */

    /** Grupos técnicos que alguna vez tuvieron tickets asignados, para el filtro. */
    public function gruposDisponibles(): array
    {
        return $this->recordar(self::clave('grupos', null, null, null), fn() =>
            DB::connection($this->dominio->glpi())->table('glpi_groups_tickets as gt')
                ->join('glpi_groups as g', 'g.id', '=', 'gt.groups_id')
                ->where('gt.type', 2)
                ->distinct()
                ->orderBy('g.completename')
                ->pluck('g.completename')
                ->all()
        )['datos'] ?? [];
    }

    /**
     * Calcula el ranking del periodo.
     *
     * @return array{
     *   tecnicos: Collection,
     *   resumen: array,
     *   error: ?string
     * }
     */
    public function calcular(?Carbon $desde, ?Carbon $hasta, ?string $grupo = null, ?string $pais = null): array
    {
        if ($pais !== null && !array_key_exists($pais, self::PAISES)) $pais = null;

        $filas = $this->recordar(self::clave('tecnicos', $desde, $hasta, $grupo, $pais), fn() => $this->porTecnico($desde, $hasta, $grupo, $pais));
        $tot   = $this->recordar(self::clave('totales', $desde, $hasta, $grupo, $pais), fn() => $this->totales($desde, $hasta, $grupo, $pais));

        $resumen = ['total' => 0, 'solucionados' => 0, 'cerrados' => 0, 'sin_tecnico' => 0, 'seg_resolucion' => 0, 'con_tiempo' => 0];

        if (!$filas['ok'] || !$tot['ok']) {
            return [
                'tecnicos' => collect(),
                'resumen'  => $resumen + ['tecnicos' => 0, 'prom_resolucion' => null],
                'error'    => $filas['error'] ?? $tot['error'],
            ];
        }

        $resumen = array_merge($resumen, $tot['datos']);

        // Con país, los tickets compartidos traen también a sus técnicos del
        // otro país: solo compiten los de este.
        $tecnicos = collect($filas['datos'])->map(fn($f) => self::tecnico($f))
            ->when($pais, fn($c) => $c->where('pais', $pais))
            ->values();
        $tecnicos = $this->ordenar($tecnicos);
        $this->asignarInsignias($tecnicos);

        $resumen['tecnicos']        = $tecnicos->count();
        $resumen['prom_resolucion'] = $resumen['con_tiempo'] > 0 ? intdiv($resumen['seg_resolucion'], $resumen['con_tiempo']) : null;

        return ['tecnicos' => $tecnicos, 'resumen' => $resumen, 'error' => null];
    }

    /* ── Armado de cada técnico ───────────────────────────────────────────── */

    /** Convierte la fila de GLPI en técnico, con los indicadores derivados. */
    private static function tecnico(array $f): array
    {
        $t = [
            'clave'           => (int) $f['id'],
            'nombre'          => self::nombreCorto($f),
            'nombre_completo' => self::nombreCompleto($f),
            'iniciales'       => self::iniciales($f),
            'login'           => Str::before($f['name'], '@'),
            'activo'          => (int) $f['is_active'] === 1 && (int) $f['is_deleted'] === 0,
            'pais'            => self::paisDe($f['ubicacion'] ?? null),
            'insignias'       => [],
        ];

        foreach (['total', 'solucionados', 'cerrados', 'seg_resolucion', 'con_tiempo', 'con_sla', 'sla_ok', 'satisfaccion_suma', 'satisfaccion_n'] as $campo) {
            $t[$campo] = (int) $f[$campo];
        }

        $t['prom_resolucion'] = $t['con_tiempo'] > 0 ? intdiv($t['seg_resolucion'], $t['con_tiempo']) : null;
        $t['pct_sla']         = $t['con_sla'] > 0 ? round($t['sla_ok'] * 100 / $t['con_sla'], 1) : null;
        $t['satisfaccion']    = $t['satisfaccion_n'] > 0 ? round($t['satisfaccion_suma'] / $t['satisfaccion_n'], 2) : null;

        return $t;
    }

    /**
     * Ordena por total y numera con ranking de competición: dos empatados en
     * el primer lugar son ambos 1º, y el siguiente es 3º.
     */
    private function ordenar(Collection $tecnicos): Collection
    {
        $ordenados = $tecnicos->sort(fn($a, $b) =>
            [$b['total'], $b['cerrados'], $a['nombre']] <=> [$a['total'], $a['cerrados'], $b['nombre']]
        )->values();

        $anterior = null;
        $puesto   = 0;

        return $ordenados->map(function ($t, $i) use (&$anterior, &$puesto) {
            if ($t['total'] !== $anterior) {
                $puesto   = $i + 1;
                $anterior = $t['total'];
            }
            $t['puesto'] = $puesto;

            return $t;
        });
    }

    /**
     * Insignias: el GOAT (más tickets) y quien lidera en rapidez, SLA y
     * satisfacción. Para estas tres se exige un mínimo de tickets, o ganaría
     * quien resolvió uno solo en diez minutos.
     */
    private function asignarInsignias(Collection $tecnicos): void
    {
        if ($tecnicos->isEmpty()) return;

        $marcar = function (string $insignia, Collection $ganadores) use ($tecnicos) {
            $claves = $ganadores->pluck('clave')->all();

            $tecnicos->transform(function ($t) use ($claves, $insignia) {
                if (in_array($t['clave'], $claves, true)) $t['insignias'][] = $insignia;
                return $t;
            });
        };

        $marcar('goat', $tecnicos->where('puesto', 1));

        $elegibles = $tecnicos->filter(fn($t) => $t['total'] >= self::MINIMO_INSIGNIA);

        $marcar('rapido', $elegibles->whereNotNull('prom_resolucion')->sortBy('prom_resolucion')->take(1));
        $marcar('sla', $elegibles->filter(fn($t) => $t['con_sla'] >= self::MINIMO_INSIGNIA)->sortByDesc('pct_sla')->take(1));
        $marcar('satisfaccion', $tecnicos->filter(fn($t) => $t['satisfaccion_n'] >= self::MINIMO_INSIGNIA)->sortByDesc('satisfaccion')->take(1));
    }

    /* ── Nombres ──────────────────────────────────────────────────────────── */
    //
    // GLPI guarda los nombres como vienen del AD, a veces en mayúsculas
    // («MARCO ANTONIO GONZALES»): se pasan a tipo título para mostrarlos.

    private static function titulo(?string $texto): string
    {
        return mb_convert_case(mb_strtolower(trim((string) $texto)), MB_CASE_TITLE, 'UTF-8');
    }

    private static function nombreCompleto(array $f): string
    {
        $nombre = trim(self::titulo($f['firstname']) . ' ' . self::titulo($f['realname']));

        return $nombre !== '' ? $nombre : Str::before($f['name'], '@');
    }

    /** Primer nombre y primer apellido: «Marco Gonzales». */
    private static function nombreCorto(array $f): string
    {
        $nombre   = Str::before(self::titulo($f['firstname']) . ' ', ' ');
        $apellido = Str::before(self::titulo($f['realname']) . ' ', ' ');
        $corto    = trim("{$nombre} {$apellido}");

        return $corto !== '' ? $corto : Str::before($f['name'], '@');
    }

    private static function iniciales(array $f): string
    {
        $partes = array_filter([trim((string) $f['firstname']), trim((string) $f['realname'])]);
        if (!$partes) $partes = [Str::before($f['name'], '@')];

        return Str::upper(implode('', array_map(fn($p) => mb_substr(Str::ascii($p), 0, 1), $partes)));
    }
}
