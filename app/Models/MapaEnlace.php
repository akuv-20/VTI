<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HoraLocal;

class MapaEnlace extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    protected $table = 'mapa_enlaces';

    protected $fillable = ['mapa_id', 'nodo_a_id', 'nodo_b_id', 'tipo', 'etiqueta', 'etiqueta_px', 'etiqueta_color', 'puntos'];

    protected $casts = [
        'puntos' => 'array',
    ];

    /** Tipos de enlace y su representación visual. */
    public const TIPOS = [
        'fibra'       => 'Fibra óptica',
        'cable'       => 'Cable / UTP',
        'inalambrico' => 'Inalámbrico / PtP',
        'starlink'    => 'Starlink (S2S)',
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
