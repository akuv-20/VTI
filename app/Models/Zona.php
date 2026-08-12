<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona de operación: la agrupación con la que TI mira los sitios, que no
 * coincide con comuna ni región. Se mantiene desde el modal de Zonas.
 */
class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = ['nombre', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function sitios(): HasMany
    {
        return $this->hasMany(Sitio::class, 'zona_id');
    }

    /** Por orden manual (norte a sur), y a igual orden por nombre. */
    public function scopeOrdenadas($q)
    {
        return $q->orderBy('orden')->orderBy('nombre');
    }
}
