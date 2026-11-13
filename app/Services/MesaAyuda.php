<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Estado en vivo de la mesa de ayuda (GLPI de Unifrutti) para el panel
 * «mesa» del muro: la cola por estado, el movimiento del día y los últimos
 * tickets. La pantalla compara cada lectura con la anterior para animar lo
 * que cambió (un ticket nuevo, uno que se soluciona).
 *
 * Estados de GLPI: 1 nuevo · 2 en curso (asignado) · 3 en curso
 * (planificado) · 4 en espera · 5 solucionado · 6 cerrado.
 *
 * Mismo criterio de zona horaria que RankingTickets: la sesión de MySQL se
 * fija en hora de Chile para que «hoy» empiece a medianoche de acá.
 */
class MesaAyuda
{
    private const CACHE     = 20;   // segundos; la pantalla pide cada 30
    private const ULTIMOS   = 8;
    private const ABIERTOS  = [1, 2, 3, 4];

    public function estado(): array
    {
        $guardado = Cache::get('muro_mesa');
        if ($guardado !== null) return $guardado;

        try {
            $r = ['ok' => true] + $this->leer() + ['generado' => now()->timestamp];
            Cache::put('muro_mesa', $r, self::CACHE);
        } catch (\Throwable $e) {
            report($e);
            $r = ['ok' => false, 'error' => $e->getMessage()];
            Cache::put('muro_mesa', $r, self::CACHE);
        }

        return $r;
    }

    private function leer(): array
    {
        $db = DB::connection(DominioInventario::oFalla(RankingTickets::DOMINIO)->glpi());
        $db->statement("SET time_zone = '" . now(RankingTickets::ZONA)->format('P') . "'");
        $hoy   = now(RankingTickets::ZONA)->startOfDay()->toDateTimeString();
        $ahora = now(RankingTickets::ZONA)->toDateTimeString();
        $base  = fn() => $db->table('glpi_tickets as t')->where('t.is_deleted', 0);

        $porEstado = $base()->whereIn('t.status', self::ABIERTOS)->selectRaw('t.status, count(*) as n')->groupBy('t.status')->pluck('n', 'status');

        // Cola por país del técnico asignado (su ubicación en GLPI, como el ranking).
        $porPais = ['chile' => 0, 'peru' => 0];
        $base()->whereIn('t.status', self::ABIERTOS)
            ->join('glpi_tickets_users as tu', fn($j) => $j->on('tu.tickets_id', '=', 't.id')->where('tu.type', 2))
            ->join('glpi_users as u', 'u.id', '=', 'tu.users_id')
            ->leftJoin('glpi_locations as l', 'l.id', '=', 'u.locations_id')
            ->selectRaw('l.completename as ubicacion, count(distinct t.id) as n')->groupBy('l.completename')->get()
            ->each(function ($r) use (&$porPais) {
                $p = RankingTickets::paisDe($r->ubicacion);
                if ($p) $porPais[$p] += (int) $r->n;
            });

        $ultimos = $base()->orderByDesc('t.id')->limit(self::ULTIMOS)
            ->leftJoin('glpi_itilcategories as c', 'c.id', '=', 't.itilcategories_id')
            ->get(['t.id', 't.name', 't.status', 't.date', 't.solvedate', 't.priority', 'c.name as categoria']);
        $tecnicos = $db->table('glpi_tickets_users as tu')->where('tu.type', 2)->whereIn('tu.tickets_id', $ultimos->pluck('id'))
            ->join('glpi_users as u', 'u.id', '=', 'tu.users_id')
            ->get(['tu.tickets_id', 'u.firstname', 'u.realname', 'u.name'])->groupBy('tickets_id');

        return [
            'cola' => [
                'nuevos'       => (int) ($porEstado[1] ?? 0),
                'asignados'    => (int) ($porEstado[2] ?? 0),
                'planificados' => (int) ($porEstado[3] ?? 0),
                'espera'       => (int) ($porEstado[4] ?? 0),
                'total'        => (int) $porEstado->sum(),
                'sin_tecnico'  => $base()->whereIn('t.status', self::ABIERTOS)
                    ->whereNotExists(fn($q) => $q->selectRaw('1')->from('glpi_tickets_users as x')->whereColumn('x.tickets_id', 't.id')->where('x.type', 2))->count(),
                'vencidos'     => $base()->whereIn('t.status', self::ABIERTOS)->whereNotNull('t.time_to_resolve')->where('t.time_to_resolve', '<', $ahora)->count(),
                'por_pais'     => $porPais,
            ],
            'hoy' => [
                'creados'      => $base()->where('t.date', '>=', $hoy)->count(),
                'solucionados' => $base()->where('t.solvedate', '>=', $hoy)->count(),
                'cerrados'     => $base()->where('t.closedate', '>=', $hoy)->count(),
            ],
            'ultimos' => $ultimos->map(fn($t) => [
                'id'        => (int) $t->id,
                'titulo'    => Str::limit(trim($t->name), 90),
                'estado'    => (int) $t->status,
                // La sesión está en hora de Chile y la app en UTC: hay que decirle la zona.
                'fecha'     => $t->date ? \Illuminate\Support\Carbon::parse($t->date, RankingTickets::ZONA)->timestamp : null,
                'categoria' => $t->categoria,
                'prioridad' => (int) $t->priority,
                'tecnico'   => optional(($tecnicos[$t->id] ?? collect())->first(), fn($u) => trim(
                    Str::before(Str::title(mb_strtolower((string) $u->firstname)) . ' ', ' ') . ' ' . Str::before(Str::title(mb_strtolower((string) $u->realname)) . ' ', ' ')
                ) ?: Str::before($u->name, '@')),
            ])->values()->all(),
        ];
    }
}
