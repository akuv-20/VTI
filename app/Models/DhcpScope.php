<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DhcpScope extends Model
{
    protected $table = 'dhcp_scopes';

    protected $fillable = [
        'scope_id', 'nombre', 'descripcion', 'subnet_mask',
        'rango_inicio', 'rango_fin', 'estado',
        'total_direcciones', 'en_uso', 'libres', 'porcentaje_uso',
        'reservas_count', 'actualizado_at',
    ];

    protected $casts = [
        'actualizado_at' => 'datetime',
        'porcentaje_uso' => 'decimal:2',
    ];

    public function reservas()
    {
        return $this->hasMany(DhcpReserva::class, 'scope_id', 'scope_id');
    }
}
