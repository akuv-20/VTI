<?php

namespace App\Services;

use App\Models\KpiDisponibilidadMensual;
use App\Models\KpiExcepcion;
use App\Models\KpiServicioCritico;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Captura ("congela") la disponibilidad de un mes para todos los servicios
 * críticos activos, consultando CheckMK y guardando el snapshot mensual.
 *
 * Se usa tanto desde el botón manual del dashboard como desde el comando
 * programado (kpi:capturar-disponibilidad) que corre el día 1 de cada mes.
 *
 * Si el objeto tiene excepciones justificadas (cortes de luz, fallas de ISP,
 * etc.) que se solapan con el mes, la caída dentro de esas ventanas se descuenta
 * del KPI: se consulta el timeline de caídas y se resta el tiempo solapado.
 */
class CapturaDisponibilidad
{
    public function __construct(private CheckMkClient $client)
    {
    }

    /**
     * Captura el mes indicado. Si un servicio falla, registra el error y sigue
     * con el resto; el snapshot faltante se puede completar manualmente.
     *
     * @return array{total:int, ok:int, errores:array<int,string>}
     */
    public function capturarMes(int $anio, int $mes, ?string $soloHost = null): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth()->endOfDay();
        if ($hasta->isFuture()) {
            $hasta = now();
        }

        // Al recapturar por una excepción solo cambia el host afectado; el resto
        // de los servicios no se vuelven a consultar (evita timeouts).
        $servicios = KpiServicioCritico::activos()->ordenados()
            ->when($soloHost, fn($q) => $q->where('host_name', $soloHost))
            ->get();

        $resultado = ['total' => $servicios->count(), 'ok' => 0, 'errores' => []];

        foreach ($servicios as $s) {
            try {
                $seg = $this->client->disponibilidadServicio($s->host_name, $s->service_description, $desde, $hasta);

                // Excepciones justificadas: se excluye del cálculo el tiempo (up y
                // down) que cae dentro de las ventanas justificadas.
                $exc = $this->descuentoExcepciones($s, $desde, $hasta);

                $upNet   = max(0, $seg['up_seconds']   - $exc['up']);
                $downNet = max(0, $seg['down_seconds'] - $exc['down']);

                $pct = KpiDisponibilidad::porcentaje($upNet, $downNet);

                KpiDisponibilidadMensual::updateOrCreate(
                    [
                        'host_name'           => $s->host_name,
                        'service_description' => $s->service_description,
                        'anio'                => $anio,
                        'mes'                 => $mes,
                    ],
                    [
                        'servicio_id'         => $s->id,
                        'up_seconds'          => $upNet,
                        'down_seconds'        => $downNet,
                        'unmonitored_seconds' => $seg['unmonitored_seconds'],
                        'downtime_seconds'    => $seg['downtime_seconds'],
                        'excepcion_seconds'   => $exc['down'], // caída justificada (para mostrar)
                        'pct'                 => $pct,
                        'fuente'              => 'checkmk_api',
                        'capturado_en'        => now(),
                    ]
                );
                $resultado['ok']++;
            } catch (\Throwable $e) {
                $resultado['errores'][] = "{$s->objeto}: {$e->getMessage()}";
            }
        }

        return $resultado;
    }

    /**
     * Tiempo (up y down) del objeto que cae dentro de excepciones justificadas y
     * se excluye del KPI. Para cada ventana de excepción (fusionadas y recortadas
     * al mes) se consulta la disponibilidad agregada de CheckMK —el mismo
     * mecanismo validado contra el GUI— y se suman sus segundos up/down.
     *
     * Precisión: el rango de CheckMK es a nivel de día, por lo que ventanas de
     * pocas horas se evalúan sobre el/los día(s) que tocan.
     *
     * @return array{up:int, down:int}
     */
    private function descuentoExcepciones(KpiServicioCritico $s, Carbon $desde, Carbon $hasta): array
    {
        $excepciones = KpiExcepcion::activas()
            ->paraObjeto($s->host_name, $s->service_description)
            ->enRango($desde, $hasta)
            ->get();

        if ($excepciones->isEmpty()) {
            return ['up' => 0, 'down' => 0];
        }

        // Ventanas recortadas al mes y fusionadas (evita contar dos veces solapes).
        $ventanas = $this->fusionarVentanas(
            $excepciones->map(fn($e) => [
                'desde' => $e->desde->greaterThan($desde) ? $e->desde->copy() : $desde->copy(),
                'hasta' => $e->hasta->lessThan($hasta) ? $e->hasta->copy() : $hasta->copy(),
            ])->filter(fn($v) => $v['hasta']->greaterThan($v['desde']))->values()->all()
        );

        $up = 0; $down = 0;
        foreach ($ventanas as $v) {
            $seg = $this->client->disponibilidadServicio($s->host_name, $s->service_description, $v['desde'], $v['hasta']);
            $up   += $seg['up_seconds'];
            $down += $seg['down_seconds'];
        }

        return ['up' => $up, 'down' => $down];
    }

    /**
     * Fusiona intervalos que se solapan o tocan, para no consultar (ni descontar)
     * dos veces el mismo tiempo.
     *
     * @param  array<int,array{desde:Carbon,hasta:Carbon}>  $ventanas
     * @return array<int,array{desde:Carbon,hasta:Carbon}>
     */
    private function fusionarVentanas(array $ventanas): array
    {
        usort($ventanas, fn($a, $b) => $a['desde'] <=> $b['desde']);

        $fusion = [];
        foreach ($ventanas as $v) {
            $ult = end($fusion) ?: null;
            if ($ult && $v['desde']->lessThanOrEqualTo($ult['hasta'])) {
                // Se solapan → extender el último.
                if ($v['hasta']->greaterThan($ult['hasta'])) {
                    $fusion[array_key_last($fusion)]['hasta'] = $v['hasta'];
                }
            } else {
                $fusion[] = $v;
            }
        }
        return $fusion;
    }

    /**
     * Recaptura los meses (año/mes) afectados por un rango de fechas. Se usa al
     * crear o editar una excepción para reflejar el descuento sin esperar al
     * cierre de mes.
     *
     * @return Collection<int,string> etiquetas "MM/AAAA" recapturadas
     */
    public function recapturarRango(Carbon $desde, Carbon $hasta, ?string $soloHost = null): Collection
    {
        $cursor = $desde->copy()->startOfMonth();
        $fin    = $hasta->copy()->startOfMonth();
        $hechos = collect();

        while ($cursor->lessThanOrEqualTo($fin)) {
            // No tiene sentido capturar meses aún sin datos (futuros).
            if ($cursor->copy()->startOfMonth()->lessThanOrEqualTo(now())) {
                $this->capturarMes($cursor->year, $cursor->month, $soloHost);
                $hechos->push($cursor->format('m/Y'));
            }
            $cursor->addMonth();
        }

        return $hechos;
    }
}
