<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HoraLocal;

/**
 * Zona de operación: la agrupación con la que TI mira los sitios, que no
 * coincide con comuna ni región. Se mantiene desde el modal de Zonas.
 */
class Zona extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
