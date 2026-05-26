<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaContable extends Model
{
    use HasFactory;

    // Nombre de la tabla asociada al modelo
    protected $table = 'cuentas_contables';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'numero_cuenta',
        'nombre_cuenta',
    ];

    /**
     * Define la relación uno a muchos con el modelo Servicio.
     * Una cuenta contable puede tener muchos servicios asociados.
     */
    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'id_cuenta_contable');
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class, 'id_cuenta_contable');
    }
}