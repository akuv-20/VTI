<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un host/servicio de CheckMK marcado manualmente como "crítico" y que, por
 * tanto, entra en el cálculo del KPI 1 de disponibilidad.
 *
 * Si service_description es null, el crítico representa la disponibilidad del
 * host completo (estado del propio host, no de un servicio puntual).
 */
class KpiServicioCritico extends Model
{
    protected $table = 'kpi_servicios_criticos';

    protected $fillable = [
        'host_name',
        'service_description',
        'etiqueta',
        'grupo',
        'sector',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** Sectores disponibles: clave → etiqueta legible. */
    public const SECTORES = [
        'planta' => 'Planta',
        'campo'  => 'Campo',
    ];

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(KpiDisponibilidadMensual::class, 'servicio_id');
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeActivos($q) { return $q->where('activo', true); }
    public function scopeOrdenados($q) { return $q->orderBy('grupo')->orderBy('orden')->orderBy('etiqueta'); }

    /** Filtra por sector: 'planta', 'campo' o 'sin_asignar' (null). */
    public function scopeSector($q, ?string $sector)
    {
        if ($sector === 'sin_asignar') return $q->whereNull('sector');
        if ($sector) return $q->where('sector', $sector);
        return $q;
    }

    /* ── Accesores ──────────────────────────────────────────────────────── */

    /** Etiqueta del objeto monitoreado tal como lo identifica CheckMK. */
    public function getObjetoAttribute(): string
    {
        return $this->service_description
            ? "{$this->host_name} / {$this->service_description}"
            : "{$this->host_name} (host)";
    }

    public function getEsHostAttribute(): bool
    {
        return empty($this->service_description);
    }

    public function getSectorLabelAttribute(): string
    {
        return self::SECTORES[$this->sector] ?? 'Sin asignar';
    }
}
