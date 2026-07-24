<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot congelado de la disponibilidad de un servicio crítico en un mes.
 *
 * El % se guarda ya calculado (pct) para no depender de la retención de datos
 * de CheckMK: una vez cerrado el mes, el número queda como evidencia del KPI.
 */
class KpiDisponibilidadMensual extends Model
{
    protected $table = 'kpi_disponibilidad_mensual';

    protected $fillable = [
        'servicio_id',
        'host_name',
        'service_description',
        'anio',
        'mes',
        'up_seconds',
        'down_seconds',
        'unmonitored_seconds',
        'downtime_seconds',
        'excepcion_seconds',
        'pct',
        'fuente',
        'nota',
        'capturado_en',
    ];

    protected $casts = [
        'anio'                => 'integer',
        'mes'                 => 'integer',
        'up_seconds'          => 'integer',
        'down_seconds'        => 'integer',
        'unmonitored_seconds' => 'integer',
        'downtime_seconds'    => 'integer',
        'excepcion_seconds'   => 'integer',
        'pct'                 => 'float',
        'capturado_en'        => 'datetime',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(KpiServicioCritico::class, 'servicio_id');
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeAnio($q, int $anio) { return $q->where('anio', $anio); }
    public function scopeMes($q, int $mes)    { return $q->where('mes', $mes); }
}
