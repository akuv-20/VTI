<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitioHost extends Model
{
    protected $table = 'sitio_hosts';

    protected $guarded = ['id'];

    public const ROLES = [
        'enlace'   => 'Enlace principal',
        'vpn'      => 'Túnel VPN',
        'respaldo' => 'Enlace de respaldo',
        'otro'     => 'Otro',
    ];

    public function sitio(): BelongsTo
    {
        return $this->belongsTo(Sitio::class, 'sitio_id');
    }

    public function getRolLabelAttribute(): string
    {
        return self::ROLES[$this->rol] ?? $this->rol;
    }
}
