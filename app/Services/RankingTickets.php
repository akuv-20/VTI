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
 *  1. Un ticket cuenta para el TÉCNICO ASIGNADO (glpi_tickets_users, type=2),
 *     no para el autor de la solución; en el 98% de los casos son la misma
 *     persona. Un ticket con dos técnicos suma uno a cada uno, así que la
 *     suma de la columna puede superar el total de tickets del periodo.
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

    private DominioInventario $dominio;

    public function __construct()
    {
        $this->dominio = DominioInventario::oFalla(self::DOMINIO);
    }

    /* ── Caché ────────────────────────────────────────────────────────────── */

    private static function clave(string $que, ?Carbon $desde, ?Carbon $hasta, ?string $grupo): string
    {
        return 'ranking_tickets_' . md5(implode('|', [
            self::version(), $que, $desde?->toDateTimeString(), $hasta?->toDateTimeString(), $grupo,
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

    /** Tickets solucionados o cerrados del periodo, con el filtro de grupo. */
    private function baseTickets(?Carbon $desde, ?Carbon $hasta, ?string $grupo)
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
                ->where('g.completename', $grupo)));
    }

    /** Una fila por técnico asignado, con sus contadores crudos. */
    private function porTecnico(?Carbon $desde, ?Carbon $hasta, ?string $grupo): array
    {
        return $this->baseTickets($desde, $hasta, $grupo)
            ->join('glpi_tickets_users as tu', fn($j) => $j->on('tu.tickets_id', '=', 't.id')->where('tu.type', 2))
            ->join('glpi_users as u', 'u.id', '=', 'tu.users_id')
            ->leftJoin('glpi_ticketsatisfactions as s', 's.tickets_id', '=', 't.id')
            ->selectRaw('u.id, u.name, u.firstname, u.realname, u.is_active, u.is_deleted')
            ->selectRaw('count(distinct t.id) as total')
            ->selectRaw('count(distinct case when t.status = 5 then t.id end) as solucionados')
            ->selectRaw('count(distinct case when t.status = 6 then t.id end) as cerrados')
            ->selectRaw('coalesce(sum(t.solve_delay_stat), 0) as seg_resolucion')
            ->selectRaw('sum(t.solve_delay_stat > 0) as con_tiempo')
            ->selectRaw('sum(t.time_to_resolve is not null) as con_sla')
            ->selectRaw('sum(t.time_to_resolve is not null and t.solvedate <= t.time_to_resolve) as sla_ok')
            ->selectRaw('coalesce(sum(s.satisfaction), 0) as satisfaccion_suma')
            ->selectRaw('count(s.satisfaction) as satisfaccion_n')
            ->groupBy('u.id', 'u.name', 'u.firstname', 'u.realname', 'u.is_active', 'u.is_deleted')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    /** Totales del periodo contando cada ticket una sola vez. */
    private function totales(?Carbon $desde, ?Carbon $hasta, ?string $grupo): array
    {
        $r = $this->baseTickets($desde, $hasta, $grupo)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(t.status = 5) as solucionados')
            ->selectRaw('sum(t.status = 6) as cerrados')
            ->selectRaw('sum(not exists (select 1 from glpi_tickets_users x where x.tickets_id = t.id and x.type = 2)) as sin_tecnico')
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
    public function calcular(?Carbon $desde, ?Carbon $hasta, ?string $grupo = null): array
    {
        $filas = $this->recordar(self::clave('tecnicos', $desde, $hasta, $grupo), fn() => $this->porTecnico($desde, $hasta, $grupo));
        $tot   = $this->recordar(self::clave('totales', $desde, $hasta, $grupo), fn() => $this->totales($desde, $hasta, $grupo));

        $resumen = ['total' => 0, 'solucionados' => 0, 'cerrados' => 0, 'sin_tecnico' => 0, 'seg_resolucion' => 0, 'con_tiempo' => 0];

        if (!$filas['ok'] || !$tot['ok']) {
            return [
                'tecnicos' => collect(),
                'resumen'  => $resumen + ['tecnicos' => 0, 'prom_resolucion' => null],
                'error'    => $filas['error'] ?? $tot['error'],
            ];
        }

        $resumen = array_merge($resumen, $tot['datos']);

        $tecnicos = $this->ordenar(collect($filas['datos'])->map(fn($f) => self::tecnico($f))->values());
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
