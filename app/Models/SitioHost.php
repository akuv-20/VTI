<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HoraLocal;

class SitioHost extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
