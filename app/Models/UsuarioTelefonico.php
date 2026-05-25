<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioTelefonico extends Model
{
    use HasFactory;

    protected $table = 'usuarios_telefonicos';

    protected $fillable = ['nombre'];

    public function lineasTelefonicas()
    {
        return $this->hasMany(LineaTelefonica::class, 'id_usuario');
    }
}
