<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class Marca extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    use HasFactory;

    protected $table = 'marcas';

    protected $fillable = ['nombre'];

    public function aparatos()
    {
        return $this->hasMany(Aparato::class, 'id_marca');
    }
}
