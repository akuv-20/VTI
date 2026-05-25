<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionMovistar extends Model
{
    protected $table = 'importaciones_movistar';

    protected $fillable = [
        'folio', 'tipo_servicio', 'codigo_servicio',
        'fecha_emision', 'periodo_cobro', 'periodo_anio', 'periodo_mes',
        'archivo_nombre', 'total_lineas',
    ];

    protected $casts = ['fecha_emision' => 'date'];

    public function detalles()
    {
        return $this->hasMany(ImportacionMovistarDetalle::class, 'id_importacion');
    }

    public function getPeriodoLabelAttribute(): string
    {
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                      'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return $meses[$this->periodo_mes] . ' ' . $this->periodo_anio;
    }

    /** Última importación por tipo de servicio */
    public static function ultimaPorTipo(string $tipo): ?self
    {
        return static::where('tipo_servicio', $tipo)
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->orderByDesc('id')
            ->first();
    }
}
