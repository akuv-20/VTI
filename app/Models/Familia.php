<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class Familia extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    use HasFactory;

    protected $fillable = ['nombre'];

    // Relación uno a muchos con Servicio
    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'id_familia');
    }
}
