<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class Aparato extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    use HasFactory;

    protected $table = 'aparatos';

    protected $fillable = ['id_marca', 'modelo'];

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca');
    }

    public function lineasTelefonicas()
    {
        return $this->hasMany(LineaTelefonica::class, 'id_aparato');
    }
}
