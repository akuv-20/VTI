<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActaEntregaTelefono extends Model
{
    protected $table = 'actas_entrega_telefono';

    protected $fillable = [
        'id_linea_telefonica',
        'fecha_emision',
        'numero_telefono',
        'nombre_receptor',
        'zona',
        'marca',
        'modelo',
        'compania',
        'imei_equipo',
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

    /** El acta queda bloqueada para edición si fue emitida hace más de 2 días. */
    public function bloqueadaParaEdicion(): bool
    {
        return $this->created_at && $this->created_at->lt(now()->subDays(2));
    }
}
