<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionWomDetalle extends Model
{
    protected $table = 'importaciones_wom_detalle';

    protected $fillable = ['id_importacion', 'id_linea_telefonica', 'monto'];

    public function importacion()
    {
        return $this->belongsTo(ImportacionWom::class, 'id_importacion');
    }

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }
}
