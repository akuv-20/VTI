<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionWom extends Model
{
    protected $table = 'importaciones_wom';

    protected $fillable = [
        'factura', 'periodo_mes', 'periodo_anio',
        'fecha_emision', 'observacion', 'total_lineas',
    ];

    protected $casts = ['fecha_emision' => 'date'];

    public function detalles()
    {
        return $this->hasMany(ImportacionWomDetalle::class, 'id_importacion');
    }

    public function getPeriodoLabelAttribute(): string
    {
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                      'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return $meses[$this->periodo_mes] . ' ' . $this->periodo_anio;
    }

    /** Última importación WOM cargada */
    public static function ultima(): ?self
    {
        return static::orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->orderByDesc('id')
            ->first();
    }
}
