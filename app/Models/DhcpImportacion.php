<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DhcpImportacion extends Model
{
    protected $table = 'dhcp_importaciones';

    protected $fillable = [
        'recibido_at', 'generado_at', 'scopes_count',
        'reservas_count', 'reservas_activas', 'origen_ip',
    ];

    protected $casts = [
        'recibido_at' => 'datetime',
        'generado_at' => 'datetime',
    ];
}
