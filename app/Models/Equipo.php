<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $table = 'equipos';

    protected $fillable = [
        'id_aparato',
        'imei',
        'mac_wifi',
        'propiedad',
        'id_usuario',
        'id_ubicacion',
        'estado',
        'observacion',
    ];

    public function aparato()
    {
        return $this->belongsTo(Aparato::class, 'id_aparato');
    }

    public function usuario()
    {
        return $this->belongsTo(UsuarioTelefonico::class, 'id_usuario');
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'id_ubicacion');
    }

    /** Línea asociada (si el equipo está ligado a una). */
    public function lineaTelefonica()
    {
        return $this->hasOne(LineaTelefonica::class, 'id_equipo');
    }

    /** Nombre legible del modelo (marca + modelo). */
    public function getModeloCompletoAttribute(): string
    {
        if (!$this->aparato) return '—';
        return trim(($this->aparato->marca->nombre ?? '') . ' ' . $this->aparato->modelo);
    }

    /**
     * Normaliza una MAC al formato del módulo DHCP: minúsculas con guiones,
     * p. ej. "AA:BB:CC:DD:EE:FF" o "aabbccddeeff" → "aa-bb-cc-dd-ee-ff".
     * Devuelve null si no hay 12 dígitos hex válidos.
     */
    public static function normalizarMac(?string $mac): ?string
    {
        if (!$mac) return null;
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac));
        if (strlen($hex) !== 12) return null;
        return implode('-', str_split($hex, 2));
    }

    /** Reserva DHCP que coincide con la MAC WiFi (si existe). */
    public function reservaDhcp()
    {
        return $this->belongsTo(DhcpReserva::class, 'mac_wifi', 'mac');
    }
}
