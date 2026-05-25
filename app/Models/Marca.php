<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    use HasFactory;

    protected $table = 'marcas';

    protected $fillable = ['nombre'];

    public function aparatos()
    {
        return $this->hasMany(Aparato::class, 'id_marca');
    }
}
