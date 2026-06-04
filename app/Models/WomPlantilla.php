<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WomPlantilla extends Model
{
    protected $table = 'wom_plantilla';

    protected $fillable = ['id_linea_telefonica', 'monto'];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }
}
