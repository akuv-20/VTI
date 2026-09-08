<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $table = 'equipos';

    protected $fillable = [
        'id_aparato',
        'imei',
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
}
