<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaImeiHistorial extends Model
{
    protected $table = 'linea_imei_historial';

    protected $fillable = [
        'id_linea_telefonica',
        'campo',
        'valor_anterior',
        'valor_nuevo',
    ];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }

    /** Etiqueta legible del campo */
    public function getLabelAttribute(): string
    {
        return match($this->campo) {
            'imei_equipo' => 'IMEI Equipo',
            'imei_sim'    => 'IMEI SIM',
            default       => $this->campo,
        };
    }
}
