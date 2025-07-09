<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    protected $fillable = [
        'factura',
        'oc',
        'valor_neto',
        'valor_iva',
        'fecha_emision',
        'id_servicio',
        'descripcion',
    ];

    // Relación inversa con Servicio
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'id_servicio');
    }
}