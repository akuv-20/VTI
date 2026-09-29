<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class LineaImeiHistorial extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
