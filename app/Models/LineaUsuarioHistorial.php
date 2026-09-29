<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class LineaUsuarioHistorial extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
