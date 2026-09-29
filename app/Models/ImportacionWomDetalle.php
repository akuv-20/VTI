<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class ImportacionWomDetalle extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
