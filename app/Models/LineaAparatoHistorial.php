<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaAparatoHistorial extends Model
{
    protected $table = 'linea_aparato_historial';

    protected $fillable = [
        'id_linea_telefonica',
        'id_aparato_anterior',
        'id_aparato_nuevo',
    ];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }

    public function aparatoAnterior()
    {
        return $this->belongsTo(Aparato::class, 'id_aparato_anterior');
    }

    public function aparatoNuevo()
    {
        return $this->belongsTo(Aparato::class, 'id_aparato_nuevo');
    }
}
