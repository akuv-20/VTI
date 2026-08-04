<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plano de fondo de un mapa, guardado en la base de datos.
 *
 * Vive aparte de `mapas_red` a propósito: así el listado y el render del mapa
 * nunca arrastran el blob, que solo se lee al servir la imagen.
 */
class MapaFondo extends Model
{
    protected $table = 'mapa_fondos';

    protected $primaryKey = 'mapa_id';
    public $incrementing  = false;

    protected $fillable = ['mapa_id', 'mime', 'bytes', 'imagen'];

    public function mapa(): BelongsTo
    {
        return $this->belongsTo(MapaRed::class, 'mapa_id');
    }
}
