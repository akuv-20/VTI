<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ventana de tiempo justificada que se descuenta del KPI de disponibilidad.
 *
 * Ejemplo: un corte eléctrico de la compañía entre el 05/06 10:15 y las 14:30
 * no debe penalizar la disponibilidad gestionada. La caída dentro de esta
 * ventana se resta del denominador del KPI, dejando registrada la justificación.
 */
class KpiExcepcion extends Model
{
    protected $table = 'kpi_excepciones';

    protected $fillable = [
        'host_name',
        'service_description',
        'desde',
        'hasta',
        'categoria',
        'justificacion',
        'activa',
    ];

    protected $casts = [
        'desde'  => 'datetime',
        'hasta'  => 'datetime',
        'activa' => 'boolean',
    ];

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeActivas($q) { return $q->where('activa', true); }

    /** Excepciones que se solapan con un rango [desde, hasta]. */
    public function scopeEnRango($q, Carbon $desde, Carbon $hasta)
    {
        return $q->where('desde', '<', $hasta)->where('hasta', '>', $desde);
    }

    /** Excepciones aplicables a un objeto (host + servicio opcional). */
    public function scopeParaObjeto($q, string $host, ?string $service)
    {
        return $q->where('host_name', $host)
            ->where(function ($sub) use ($service) {
                if ($service === null) {
                    $sub->whereNull('service_description');
                } else {
                    // Aplica la excepción del servicio puntual o la del host completo.
                    $sub->whereNull('service_description')->orWhere('service_description', $service);
                }
            });
    }

    /* ── Accesores ──────────────────────────────────────────────────────── */

    public function getObjetoAttribute(): string
    {
        return $this->service_description
            ? "{$this->host_name} / {$this->service_description}"
            : "{$this->host_name} (host)";
    }

    public function getDuracionHorasAttribute(): float
    {
        return round($this->desde->diffInSeconds($this->hasta) / 3600, 2);
    }
}
