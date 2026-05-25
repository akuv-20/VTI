<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaUsuarioHistorial extends Model
{
    protected $table = 'linea_usuario_historial';

    protected $fillable = [
        'id_linea_telefonica',
        'id_usuario_anterior',
        'id_usuario_nuevo',
    ];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }

    public function usuarioAnterior()
    {
        return $this->belongsTo(UsuarioTelefonico::class, 'id_usuario_anterior');
    }

    public function usuarioNuevo()
    {
        return $this->belongsTo(UsuarioTelefonico::class, 'id_usuario_nuevo');
    }
}
