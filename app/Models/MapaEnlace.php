<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapaEnlace extends Model
{
    protected $table = 'mapa_enlaces';

    protected $fillable = ['mapa_id', 'nodo_a_id', 'nodo_b_id', 'tipo', 'etiqueta'];

    /** Tipos de enlace y su representación visual. */
    public const TIPOS = [
        'fibra'       => 'Fibra óptica',
        'cable'       => 'Cable / UTP',
        'inalambrico' => 'Inalámbrico / PtP',
    ];

    public function mapa(): BelongsTo
    {
        return $this->belongsTo(MapaRed::class, 'mapa_id');
    }

    public function nodoA(): BelongsTo
    {
        return $this->belongsTo(MapaNodo::class, 'nodo_a_id');
    }

    public function nodoB(): BelongsTo
    {
        return $this->belongsTo(MapaNodo::class, 'nodo_b_id');
    }
}
