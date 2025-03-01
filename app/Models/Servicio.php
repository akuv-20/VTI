<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_familia',
        'codigo_servicio',
        'empresa',
        'compania',
        'servicio',
        'fecha_facturacion',
        'concepto',
    ];

    // Relación uno a muchos con Factura
    public function facturas()
    {
        return $this->hasMany(Factura::class, 'id_servicio');
    }

    // Relación inversa con Familia
    public function familia()
    {
        return $this->belongsTo(Familia::class, 'id_familia');
    }
}