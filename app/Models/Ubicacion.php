<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class Ubicacion extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    use HasFactory;

    protected $table = 'ubicaciones';

    protected $fillable = ['nombre'];

    public function lineasTelefonicas()
    {
        return $this->hasMany(LineaTelefonica::class, 'id_ubicacion');
    }
}
