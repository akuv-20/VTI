<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class ActaEntregaTelefono extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    protected $table = 'actas_entrega_telefono';

    protected $fillable = [
        'id_linea_telefonica',
        'id_equipo',
        'tipo_acta',
        'fecha_emision',
        'numero_telefono',
        'nombre_receptor',
        'zona',
        'marca',
        'modelo',
        'propiedad',
        'compania',
        'imei_equipo',
        'mac_wifi',
        'imei_sim',
        'condicion',
        'accesorios',
        'documentacion',
        'observacion',
        'impreso_por',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'accesorios'    => 'array',
        'documentacion' => 'array',
    ];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }

    /** El acta queda bloqueada para edición si fue emitida hace más de 2 días. */
    public function bloqueadaParaEdicion(): bool
    {
        return $this->created_at && $this->created_at->lt(now()->subDays(2));
    }
}
