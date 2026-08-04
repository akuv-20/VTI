<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapaRed extends Model
{
    protected $table = 'mapas_red';

    protected $fillable = ['nombre', 'descripcion', 'fondo_opacidad', 'fondo_actualizado_at', 'orden', 'activo', 'en_tv', 'publico_lectura', 'tv_token'];

    protected $casts = [
        'activo'               => 'boolean',
        'en_tv'                => 'boolean',
        'publico_lectura'      => 'boolean',
        'orden'                => 'integer',
        'fondo_opacidad'       => 'integer',
        'fondo_actualizado_at' => 'datetime',
    ];

    /** Técnicos asignados: pueden mantener el contenido del mapa. */
    public function tecnicos()
    {
        return $this->belongsToMany(User::class, 'mapa_red_user', 'mapa_id', 'user_id')->withTimestamps();
    }

    /** ¿El usuario puede editar el contenido (nodos, enlaces, fondo)? */
    public function puedeEditar(User $user): bool
    {
        if ($user->can('admin')) return true;
        return $this->tecnicos()->whereKey($user->id)->exists();
    }

    /** ¿El usuario puede ver el mapa (en vivo)? */
    public function puedeVer(User $user): bool
    {
        return $this->publico_lectura || $this->puedeEditar($user);
    }

    /** Mapas visibles para un usuario del módulo (admin ve todos). */
    public function scopeVisiblesPara($q, User $user)
    {
        if ($user->can('admin')) return $q;
        return $q->where(function ($q2) use ($user) {
            $q2->where('publico_lectura', true)
               ->orWhereHas('tecnicos', fn($t) => $t->whereKey($user->id));
        });
    }

    /** Plano de fondo guardado en la base (relación aparte para no cargar el blob). */
    public function fondo(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MapaFondo::class, 'mapa_id');
    }

    /**
     * URL de la imagen de fondo (plano), o null si no tiene.
     * Basta la marca de tiempo para saber que existe: el blob no se toca, y de
     * paso sirve de versión para que el navegador refresque al cambiar el plano.
     */
    public function getFondoUrlAttribute(): ?string
    {
        if (!$this->fondo_actualizado_at) return null;

        return route('monitoreo.mapas.fondo', [
            'mapa' => $this->id,
            'v'    => $this->fondo_actualizado_at->timestamp,
        ]);
    }

    public function nodos(): HasMany
    {
        return $this->hasMany(MapaNodo::class, 'mapa_id');
    }

    public function enlaces(): HasMany
    {
        return $this->hasMany(MapaEnlace::class, 'mapa_id');
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }

    public function scopeOrdenados($q)
    {
        return $q->orderBy('orden')->orderBy('nombre');
    }
}
