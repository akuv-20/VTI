<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Lógica de negocio del KPI 1 — Disponibilidad de servicios críticos.
 *
 * Todo el conocimiento del KPI (metas, umbrales de nivel, cómo se calcula el %
 * excluyendo mantenimientos programados y cómo se acumula el año) vive aquí,
 * separado del acceso a datos (CheckMkClient) y de la persistencia (modelos).
 */
class KpiDisponibilidad
{
    /** Peso del KPI dentro de la evaluación anual. */
    public const PESO = 15;

    /** Meta (nivel 3) de disponibilidad. */
    public const META = 99.5;

    /**
     * Umbrales de nivel: cota inferior de % para alcanzar cada nivel (1–5).
     * Editable si la definición del KPI cambia.
     *
     *   Nivel 1: < 99,0
     *   Nivel 2: ≥ 99,0
     *   Nivel 3: ≥ 99,5  (meta)
     *   Nivel 4: ≥ 99,7
     *   Nivel 5: ≥ 99,9
     */
    public const UMBRALES = [
        5 => 99.9,
        4 => 99.7,
        3 => 99.5,
        2 => 99.0,
        1 => 0.0,
    ];

    /** Descripción textual de cada nivel para el informe. */
    public const NIVELES = [
        1 => 'Significativamente por debajo',
        2 => 'Por debajo de la meta',
        3 => 'Cumple la meta',
        4 => 'Sobre la meta',
        5 => 'Significativamente por encima',
    ];

    /**
     * Umbral mínimo de tiempo evaluable (up + down, ya netos de excepciones)
     * para considerar un mes con dato. Por debajo se trata como "no evaluable".
     */
    public const MIN_EVALUABLE = 600; // 10 minutos

    /**
     * Calcula el % de disponibilidad a partir de los segundos ya NETOS de
     * excepciones. Devuelve null si prácticamente todo el periodo quedó excluido
     * (por mantenimientos, sin datos o excepciones justificadas): el mes no es
     * evaluable para ese objeto.
     */
    public static function porcentaje(int $upSeconds, int $downSeconds): ?float
    {
        $base = $upSeconds + $downSeconds;
        if ($base < self::MIN_EVALUABLE) {
            return null; // sin tiempo evaluable → N/A
        }
        return round($upSeconds / $base * 100, 3);
    }

    /** Devuelve el nivel (1–5) del KPI para un % de disponibilidad dado. */
    public static function nivel(?float $pct): int
    {
        if ($pct === null) return 1;

        foreach (self::UMBRALES as $nivel => $minimo) {
            if ($pct >= $minimo) {
                return $nivel;
            }
        }
        return 1;
    }

    /** Color asociado al nivel, para gráficos e indicadores. */
    public static function colorNivel(int $nivel): string
    {
        return match ($nivel) {
            5 => '#16a34a', // verde intenso
            4 => '#22c55e', // verde
            3 => '#84cc16', // lima (meta)
            2 => '#f59e0b', // ámbar
            default => '#ef4444', // rojo
        };
    }

    /** Color según el % respecto de la meta (para tarjetas por servicio). */
    public static function colorPct(?float $pct): string
    {
        if ($pct === null) return '#94a3b8';
        return self::colorNivel(self::nivel($pct));
    }

    /**
     * Promedio anual acumulado a partir de una colección de snapshots mensuales.
     * Pondera por segundos evaluados (up+down) para que un mes con más datos
     * pese proporcionalmente; si no hay segundos, promedia los pct disponibles.
     *
     * @param  Collection<int,\App\Models\KpiDisponibilidadMensual>  $snapshots
     */
    public static function promedioAnual(Collection $snapshots): ?float
    {
        if ($snapshots->isEmpty()) return null;

        // up_seconds y down_seconds ya vienen netos de excepciones.
        $sumUp   = $snapshots->sum('up_seconds');
        $sumDown = $snapshots->sum('down_seconds');

        if (($sumUp + $sumDown) >= self::MIN_EVALUABLE) {
            return round($sumUp / ($sumUp + $sumDown) * 100, 3);
        }

        $conPct = $snapshots->whereNotNull('pct');
        return $conPct->isEmpty() ? null : round($conPct->avg('pct'), 3);
    }

    /** Nombres cortos de los meses, para ejes de gráficos. */
    public const MESES = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];
}
