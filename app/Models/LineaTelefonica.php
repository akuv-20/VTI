<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineaTelefonica extends Model
{
    use HasFactory;

    protected $table = 'lineas_telefonicas';

    protected $fillable = [
        'estado',
        'linea',
        'id_emisor',
        'id_usuario',
        'id_empresa',
        'id_ubicacion',
        'id_centro_costo',
        'id_aparato',
        'imei_equipo',
        'imei_sim',
        'fecha_entrega_sim',
        'fecha_renovacion_equipo',
        'observacion',
    ];

    public function emisor()
    {
        return $this->belongsTo(Emisor::class, 'id_emisor');
    }

    public function usuario()
    {
        return $this->belongsTo(UsuarioTelefonico::class, 'id_usuario');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'id_ubicacion');
    }

    public function aparato()
    {
        return $this->belongsTo(Aparato::class, 'id_aparato');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class, 'id_centro_costo');
    }

    /** Todos los registros de cambio de usuario, del más reciente al más antiguo. */
    public function historialUsuarios()
    {
        return $this->hasMany(LineaUsuarioHistorial::class, 'id_linea_telefonica')->latest();
    }

    /** Último cambio de usuario (para el listado). */
    public function lastHistorialUsuario()
    {
        return $this->hasOne(LineaUsuarioHistorial::class, 'id_linea_telefonica')->latestOfMany();
    }

    /** Todos los registros de cambio de IMEI, del más reciente al más antiguo. */
    public function historialImei()
    {
        return $this->hasMany(LineaImeiHistorial::class, 'id_linea_telefonica')->latest();
    }

    /** Historial de cambios de aparato. */
    public function historialAparato()
    {
        return $this->hasMany(LineaAparatoHistorial::class, 'id_linea_telefonica')->latest();
    }

    /** Historial de cambios de ubicación. */
    public function historialUbicacion()
    {
        return $this->hasMany(LineaUbicacionHistorial::class, 'id_linea_telefonica')->latest();
    }
}
