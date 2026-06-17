<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaUbicacionHistorial extends Model
{
    protected $table = 'linea_ubicacion_historial';

    protected $fillable = [
        'id_linea_telefonica',
        'id_ubicacion_anterior',
        'id_ubicacion_nueva',
    ];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }

    public function ubicacionAnterior()
    {
        return $this->belongsTo(Ubicacion::class, 'id_ubicacion_anterior');
    }

    public function ubicacionNueva()
    {
        return $this->belongsTo(Ubicacion::class, 'id_ubicacion_nueva');
    }
}
