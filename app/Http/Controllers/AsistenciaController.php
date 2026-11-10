<?php

namespace App\Http\Controllers;

use App\Services\RankingTickets;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Asistencia GLPI: indicadores de la mesa de ayuda, leídos del GLPI de
 * Unifrutti (la única ticketera en uso; ver RankingTickets).
 *
 * Por ahora una sola pantalla, el ranking de técnicos. El periodo y el grupo
 * viajan en la URL para que un ranking se pueda compartir con un enlace.
 */
class AsistenciaController extends Controller
{
    /** Periodos del selector, en el orden en que se muestran. */
    private const PERIODOS = [
        'mes'           => 'Este mes',
        'mes_anterior'  => 'Mes anterior',
        'anio'          => 'Este año',
        'anio_anterior' => 'Año anterior',
        'historico'     => 'Histórico',
        'rango'         => 'Rango de fechas',
    ];

    public function ranking(Request $request)
    {
        $periodo = array_key_exists($request->query('periodo'), self::PERIODOS) ? $request->query('periodo') : 'mes';
        $grupo   = $request->query('grupo') ?: null;
        $pais    = array_key_exists((string) $request->query('pais'), RankingTickets::PAISES) ? $request->query('pais') : null;

        [$desde, $hasta]         = $this->rango($periodo, $request);
        [$prevDesde, $prevHasta] = $this->rangoAnterior($periodo, $desde, $hasta);

        $servicio = new RankingTickets();
        $ranking  = $servicio->calcular($desde, $hasta, $grupo, $pais);
        $grupos  = $servicio->gruposDisponibles();

        // Movimiento respecto del periodo anterior: cuántos puestos subió o
        // bajó cada técnico. Sin periodo anterior (histórico) no se calcula.
        $puestosPrevios = [];
        if ($prevDesde) {
            $anterior = $servicio->calcular($prevDesde, $prevHasta, $grupo, $pais);
            $puestosPrevios = $anterior['tecnicos']->pluck('puesto', 'clave')->all();
        }

        $tecnicos = $ranking['tecnicos']->map(function ($t) use ($puestosPrevios, $prevDesde) {
            $t['puesto_anterior'] = $puestosPrevios[$t['clave']] ?? null;
            $t['movimiento'] = !$prevDesde ? null
                : ($t['puesto_anterior'] === null ? 'nuevo' : $t['puesto_anterior'] - $t['puesto']);

            return $t;
        });

        return view('asistencia.ranking', [
            'tecnicos'     => $tecnicos,
            'resumen'      => $ranking['resumen'],
            'error'        => $ranking['error'],
            'periodos'     => self::PERIODOS,
            'periodo'      => $periodo,
            'desde'        => $desde,
            'hasta'        => $hasta,
            'prevDesde'    => $prevDesde,
            'prevHasta'    => $prevHasta,
            'grupos'       => $grupos,
            'grupo'        => $grupo,
            'pais'         => $pais,
            'etiquetaPeriodo' => $this->etiqueta($periodo, $desde, $hasta),
        ]);
    }

    public function actualizar(Request $request)
    {
        RankingTickets::olvidarCache();

        return redirect()->route('asistencia.ranking', $request->only('periodo', 'grupo', 'pais', 'desde', 'hasta'))
            ->with('success', 'Datos actualizados desde GLPI.');
    }

    /* ── Periodos ─────────────────────────────────────────────────────────── */

    /** @return array{0:?Carbon,1:?Carbon} */
    private function rango(string $periodo, Request $request): array
    {
        // En hora de Chile, la misma con la que el servicio lee las fechas de GLPI.
        $hoy = now(RankingTickets::ZONA);

        return match ($periodo) {
            'mes'           => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()],
            'mes_anterior'  => [$hoy->copy()->subMonthNoOverflow()->startOfMonth(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()],
            'anio'          => [$hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()],
            'anio_anterior' => [$hoy->copy()->subYear()->startOfYear(), $hoy->copy()->subYear()->endOfYear()],
            'historico'     => [null, null],
            'rango'         => $this->rangoPersonalizado($request),
        };
    }

    /** Rango de fechas elegido a mano; si viene incompleto o al revés, este mes. */
    private function rangoPersonalizado(Request $request): array
    {
        $zona    = RankingTickets::ZONA;
        $esteMes = [now($zona)->startOfMonth(), now($zona)->endOfMonth()];

        if (!$request->filled(['desde', 'hasta'])) return $esteMes;

        try {
            $desde = Carbon::parse($request->query('desde'), $zona)->startOfDay();
            $hasta = Carbon::parse($request->query('hasta'), $zona)->endOfDay();
        } catch (\Throwable) {
            return $esteMes;
        }

        if ($desde->gt($hasta)) return $esteMes;

        return [$desde, $hasta];
    }

    /**
     * El periodo inmediatamente anterior, del mismo largo: el mes anterior al
     * mes, el año anterior al año, y para un rango los mismos días hacia atrás.
     *
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function rangoAnterior(string $periodo, ?Carbon $desde, ?Carbon $hasta): array
    {
        if (!$desde || !$hasta) return [null, null];

        return match ($periodo) {
            'mes', 'mes_anterior'   => [$desde->copy()->subMonthNoOverflow()->startOfMonth(), $desde->copy()->subMonthNoOverflow()->endOfMonth()],
            'anio', 'anio_anterior' => [$desde->copy()->subYear()->startOfYear(), $desde->copy()->subYear()->endOfYear()],
            default                 => (function () use ($desde, $hasta) {
                $dias = (int) $desde->diffInDays($hasta) + 1;
                return [$desde->copy()->subDays($dias), $hasta->copy()->subDays($dias)];
            })(),
        };
    }

    private function etiqueta(string $periodo, ?Carbon $desde, ?Carbon $hasta): string
    {
        return match ($periodo) {
            'mes', 'mes_anterior'   => ucfirst($desde->copy()->locale('es')->isoFormat('MMMM [de] YYYY')),
            'anio', 'anio_anterior' => (string) $desde->year,
            'historico'             => 'Histórico completo',
            default                 => $desde->format('d/m/Y') . ' – ' . $hasta->format('d/m/Y'),
        };
    }
}
