<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActaDevolucionTelefono extends Model
{
    protected $table = 'actas_devolucion_telefono';

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
}
