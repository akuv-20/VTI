<?php

namespace App\Console\Commands;

use App\Services\CapturaDisponibilidad;
use App\Services\KpiDisponibilidad;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Congela la disponibilidad mensual del KPI 1 consultando CheckMK.
 *
 * Sin opciones captura el MES ANTERIOR (pensado para ejecutarse el día 1 de
 * cada mes vía scheduler). Con --anio/--mes permite cargas retroactivas.
 *
 *   php artisan kpi:capturar-disponibilidad
 *   php artisan kpi:capturar-disponibilidad --anio=2027 --mes=3
 */
class CapturarDisponibilidadKpi extends Command
{
    protected $signature = 'kpi:capturar-disponibilidad
                            {--anio= : Año a capturar (por defecto, el del mes anterior)}
                            {--mes=  : Mes 1-12 (por defecto, el mes anterior)}';

    protected $description = 'Congela la disponibilidad mensual de servicios críticos desde CheckMK (KPI 1)';

    public function handle(CapturaDisponibilidad $captura): int
    {
        // Por defecto, el mes anterior al actual.
        $ref  = now()->subMonthNoOverflow();
        $anio = (int) ($this->option('anio') ?: $ref->year);
        $mes  = (int) ($this->option('mes')  ?: $ref->month);

        if ($mes < 1 || $mes > 12) {
            $this->error("Mes inválido: {$mes}. Debe estar entre 1 y 12.");
            return self::FAILURE;
        }

        $etiqueta = (KpiDisponibilidad::MESES[$mes] ?? $mes) . " {$anio}";
        $this->info("Capturando disponibilidad de {$etiqueta}…");

        $r = $captura->capturarMes($anio, $mes);

        if ($r['total'] === 0) {
            $this->warn('No hay servicios críticos activos que capturar.');
            return self::SUCCESS;
        }

        $this->info("Servicios actualizados: {$r['ok']} / {$r['total']}");

        if ($r['errores']) {
            $this->warn('Con errores en:');
            foreach ($r['errores'] as $e) {
                $this->line("  · {$e}");
            }
        }

        return self::SUCCESS;
    }
}
