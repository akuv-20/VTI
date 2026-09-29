<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class LineaUbicacionHistorial extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
