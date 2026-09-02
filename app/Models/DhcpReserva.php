<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DhcpReserva extends Model
{
    protected $table = 'dhcp_reservas';

    protected $fillable = [
        'scope_id', 'ip', 'mac', 'nombre', 'descripcion',
        'ultima_actividad', 'visto_activa', 'lease_expira',
        'primera_vez_visto', 'activa',
    ];

    protected $casts = [
        'ultima_actividad'  => 'datetime',
        'lease_expira'      => 'datetime',
        'primera_vez_visto' => 'datetime',
        'visto_activa'      => 'boolean',
        'activa'            => 'boolean',
    ];

    public function scope()
    {
        return $this->belongsTo(DhcpScope::class, 'scope_id', 'scope_id');
    }

    /** Días desde la última actividad conocida (null si nunca se vio activa) */
    public function getDiasInactivaAttribute(): ?int
    {
        if (!$this->ultima_actividad) return null;
        return (int) $this->ultima_actividad->diffInDays(now());
    }
}
