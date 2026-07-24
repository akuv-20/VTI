<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapaNodo extends Model
{
    protected $table = 'mapa_nodos';

    protected $fillable = ['mapa_id', 'host_name', 'etiqueta', 'icono', 'icono_px', 'letra_px', 'x', 'y', 'mapa_destino_id'];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
    ];

    /** Iconos disponibles para los nodos (Bootstrap Icons 1.11). */
    public const ICONOS = [
        'bi-hdd-rack'      => 'Rack / Datacenter',
        'bi-hdd-rack-fill' => 'Datacenter (relleno)',
        'bi-server'        => 'Servidores',
        'bi-buildings'     => 'Planta',
        'bi-building-fill' => 'Edificio / Oficina',
        'bi-house'         => 'Casa / Sede',
        'bi-tree'          => 'Campo',
        'bi-hdd-network'   => 'Switch',
        'bi-wifi'          => 'Access Point',
        'bi-router'        => 'Router',
        'bi-broadcast'     => 'Antena PtP',
        'bi-hdd-stack'     => 'Servidor',
        'bi-database'      => 'Base de datos',
        'bi-bricks'        => 'Firewall',
        'bi-cloud'         => 'Nube / ISP',
        'bi-globe'         => 'Internet',
        'bi-camera-video'  => 'Cámara',
        'bi-printer'       => 'Impresora',
        'bi-telephone'     => 'Telefonía',
        'bi-pc-display'    => 'PC / Equipo',
        'bi-ethernet'      => 'Enlace / Puerto',
    ];

    public function mapa(): BelongsTo
    {
        return $this->belongsTo(MapaRed::class, 'mapa_id');
    }

    public function mapaDestino(): BelongsTo
    {
        return $this->belongsTo(MapaRed::class, 'mapa_destino_id');
    }
}
