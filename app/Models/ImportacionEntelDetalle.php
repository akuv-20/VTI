<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionEntelDetalle extends Model
{
    protected $table = 'importaciones_entel_detalle';

    protected $fillable = [
        'id_importacion', 'numero_servicio', 'plan_tarifario',
        'monto', 'id_linea_telefonica',
    ];

    public function importacion()
    {
        return $this->belongsTo(ImportacionEntel::class, 'id_importacion');
    }

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }
}
