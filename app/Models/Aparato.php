<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aparato extends Model
{
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
