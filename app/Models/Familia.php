<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Familia extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    // Relación uno a muchos con Servicio
    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'id_familia');
    }
}
