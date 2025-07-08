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
        'id_empresa',
        'id_compania',
        'id_cuenta_contable',
        'servicio',
        'fecha_facturacion',
        'concepto',
        'es_periodico',
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

    // Relación inversa con Empresa
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
    // Relación inversa con Compania
    public function compania()
    {
        return $this->belongsTo(Compania::class, 'id_compania');
    }
     // Relación inversa con CuentaContable
     public function cuentaContable()
     {
         return $this->belongsTo(CuentaContable::class, 'id_cuenta_contable');
     }
}