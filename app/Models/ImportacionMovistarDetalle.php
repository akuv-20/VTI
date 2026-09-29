<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class ImportacionMovistarDetalle extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    protected $table = 'importaciones_movistar_detalle';

    protected $fillable = [
        'id_importacion', 'numero_servicio', 'plan_tarifario',
        'producto', 'monto', 'id_linea_telefonica',
    ];

    public function importacion()
    {
        return $this->belongsTo(ImportacionMovistar::class, 'id_importacion');
    }

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }
}
