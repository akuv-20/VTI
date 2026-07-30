<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SitioEquipo extends Model
{
    protected $table = 'sitio_equipos';

    protected $guarded = ['id'];

    protected $casts = [
        'poe'              => 'boolean',
        'fecha_compra'     => 'date',
        'garantia_hasta'   => 'date',
        'ptp_distancia_km' => 'float',
        'ptp_altura_m'     => 'float',
    ];

    public const TIPOS = [
        'switch'   => 'Switch',
        'ap'       => 'Access Point',
        'ptp'      => 'Enlace PtP',
        'router'   => 'Router',
        'firewall' => 'Firewall',
        'nvr'      => 'NVR / Cámaras',
        'ups'      => 'UPS',
        'servidor' => 'Servidor',
        'otro'     => 'Otro',
    ];

    public const ICONOS_TIPO = [
        'switch'   => 'bi-hdd-network',
        'ap'       => 'bi-wifi',
        'ptp'      => 'bi-broadcast',
        'router'   => 'bi-router',
        'firewall' => 'bi-bricks',
        'nvr'      => 'bi-camera-video',
        'ups'      => 'bi-battery-charging',
        'servidor' => 'bi-hdd-stack',
        'otro'     => 'bi-box',
    ];

    public const ESTADOS = [
        'operativo' => 'Operativo',
        'falla'     => 'Con falla',
        'respaldo'  => 'De respaldo',
        'baja'      => 'Dado de baja',
    ];

    public const COLORES_ESTADO = [
        'operativo' => '#16a34a',
        'falla'     => '#dc2626',
        'respaldo'  => '#0284c7',
        'baja'      => '#94a3b8',
    ];

    public function sitio(): BelongsTo
    {
        return $this->belongsTo(Sitio::class, 'sitio_id');
    }

    public function fotos(): MorphMany
    {
        return $this->morphMany(SitioFoto::class, 'fotable')->orderBy('orden')->orderBy('id');
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getIconoAttribute(): string
    {
        return self::ICONOS_TIPO[$this->tipo] ?? 'bi-box';
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getEstadoColorAttribute(): string
    {
        return self::COLORES_ESTADO[$this->estado] ?? '#94a3b8';
    }

    /** "Ubiquiti UniFi US-24-250W" a partir de marca y modelo. */
    public function getMarcaModeloAttribute(): ?string
    {
        return trim(($this->marca ?? '') . ' ' . ($this->modelo ?? '')) ?: null;
    }

    /** Uso de puertos en % (null si no está informado). */
    public function getUsoPuertosAttribute(): ?int
    {
        if (!$this->puertos_totales) return null;

        return (int) round(min(100, ($this->puertos_usados ?? 0) / $this->puertos_totales * 100));
    }
}
