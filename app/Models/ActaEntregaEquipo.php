<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActaEntregaEquipo extends Model
{
    protected $table = 'actas_entrega_equipo';

    protected $fillable = [
        'glpi_computer_id',
        'fecha_emision',
        'nombre_equipo',
        'nombre_receptor',
        'ubicacion',
        'marca',
        'modelo',
        'numero_serie',
        'sistema_operativo',
        'procesador',
        'ram',
        'disco',
        'condicion',
        'accesorios',
        'observacion',
        'entregado_por',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'accesorios'    => 'array',
    ];
}
