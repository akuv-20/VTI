<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sitio extends Model
{
    protected $table = 'sitios';

    protected $guarded = ['id'];

    protected $casts = [
        'vpn'               => 'boolean',
        'generador'         => 'boolean',
        'puesta_tierra'     => 'boolean',
        'climatizacion'     => 'boolean',
        'estacional'        => 'boolean',
        'activo'            => 'boolean',
        'fecha_instalacion' => 'date',
        'levantado_at'      => 'datetime',
        'latitud'           => 'float',
        'longitud'          => 'float',
        'superficie_ha'     => 'float',
    ];

    public const TIPOS = [
        'planta'     => 'Planta',
        'campo'      => 'Campo',
        'datacenter' => 'Datacenter',
        'oficina'    => 'Oficina',
    ];

    public const ICONOS_TIPO = [
        'planta'     => 'bi-buildings',
        'campo'      => 'bi-pin-map-fill',
        'datacenter' => 'bi-hdd-rack-fill',
        'oficina'    => 'bi-building-fill',
    ];

    /** Etapas del enlazamiento (estado de proyecto, distinto del estado en vivo). */
    public const ESTADOS_ENLACE = [
        'sin_enlace'      => 'Sin enlace',
        'en_gestion'      => 'En gestión',
        'en_instalacion'  => 'En instalación',
        'operativo'       => 'Operativo',
        'intermitente'    => 'Intermitente',
    ];

    public const COLORES_ENLACE = [
        'sin_enlace'     => '#94a3b8',
        'en_gestion'     => '#d97706',
        'en_instalacion' => '#0284c7',
        'operativo'      => '#16a34a',
        'intermitente'   => '#dc2626',
    ];

    public const ENLACE_TIPOS = [
        'fibra'     => 'Fibra óptica',
        'ptp'       => 'Punto a punto',
        'starlink'  => 'Starlink',
        '4g'        => '4G / LTE',
        'satelital' => 'Satelital',
        'ninguno'   => 'Sin enlace',
    ];

    /**
     * Campos exigidos por tipo para considerar la ficha "completa".
     * Alimenta el % de completitud y el estándar de levantamiento.
     */
    public const REQUISITOS = [
        'comun' => ['codigo', 'nombre', 'region', 'comuna', 'encargado_nombre', 'encargado_telefono'],
        'campo' => ['acceso', 'latitud', 'enlace_tipo', 'superficie_ha', 'usuarios_cant'],
        'planta' => ['acceso', 'latitud', 'enlace_tipo', 'isp', 'subred', 'usuarios_cant', 'pcs_cant'],
        'datacenter' => ['enlace_tipo', 'isp', 'subred', 'racks_cant', 'ups_modelo', 'climatizacion'],
        'oficina' => ['direccion', 'enlace_tipo', 'usuarios_cant'],
    ];

    /* ── Relaciones ──────────────────────────────────────────────────────── */

    public function equipos(): HasMany
    {
        return $this->hasMany(SitioEquipo::class, 'sitio_id');
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(SitioHost::class, 'sitio_id');
    }

    public function fotos(): MorphMany
    {
        return $this->morphMany(SitioFoto::class, 'fotable')->orderBy('orden')->orderBy('id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function levantadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'levantado_por');
    }

    public function mapa(): BelongsTo
    {
        return $this->belongsTo(MapaRed::class, 'mapa_id');
    }

    /* ── Scopes ──────────────────────────────────────────────────────────── */

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }

    public function scopeTipo($q, ?string $tipo)
    {
        return $tipo ? $q->where('tipo', $tipo) : $q;
    }

    public function scopeEstadoEnlace($q, ?string $estado)
    {
        return $estado ? $q->where('estado_enlace', $estado) : $q;
    }

    public function scopeBuscar($q, ?string $texto)
    {
        if (!$texto) return $q;

        return $q->where(function ($sub) use ($texto) {
            $like = '%' . $texto . '%';
            $sub->where('nombre', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhere('comuna', 'like', $like)
                ->orWhere('encargado_nombre', 'like', $like);
        });
    }

    public function scopeOrdenados($q)
    {
        // Código numérico primero (2, 3, 12…), luego el resto por nombre.
        return $q->orderByRaw('CAST(codigo AS UNSIGNED) = 0, CAST(codigo AS UNSIGNED)')->orderBy('nombre');
    }

    /* ── Atributos derivados ─────────────────────────────────────────────── */

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getIconoAttribute(): string
    {
        return self::ICONOS_TIPO[$this->tipo] ?? 'bi-geo-alt';
    }

    public function getEstadoEnlaceLabelAttribute(): string
    {
        return self::ESTADOS_ENLACE[$this->estado_enlace] ?? $this->estado_enlace;
    }

    public function getEstadoEnlaceColorAttribute(): string
    {
        return self::COLORES_ENLACE[$this->estado_enlace] ?? '#94a3b8';
    }

    public function getTituloAttribute(): string
    {
        return $this->codigo ? "{$this->codigo}. {$this->nombre}" : $this->nombre;
    }

    public function getPortadaAttribute(): ?SitioFoto
    {
        return $this->fotos->firstWhere('portada', true) ?? $this->fotos->first();
    }

    /** Campos requeridos para este tipo de sitio. */
    public function requisitos(): array
    {
        return array_merge(self::REQUISITOS['comun'], self::REQUISITOS[$this->tipo] ?? []);
    }

    /** Lista de campos requeridos que están vacíos. */
    public function faltantes(): array
    {
        return array_values(array_filter($this->requisitos(), function ($campo) {
            $v = $this->{$campo};
            return $v === null || $v === '' || $v === 0;
        }));
    }

    /** % de completitud de la ficha (0-100). */
    public function getCompletitudAttribute(): int
    {
        $req = $this->requisitos();
        if (empty($req)) return 100;

        return (int) round((count($req) - count($this->faltantes())) / count($req) * 100);
    }

    /** Nombres de host de CheckMK asociados al sitio (incluye los de sus equipos). */
    public function todosLosHosts(): array
    {
        return collect($this->hosts->pluck('host_name'))
            ->merge($this->equipos->pluck('host_name'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
