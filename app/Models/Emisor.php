<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Emisor extends Model
{
    use HasFactory;

    protected $table = 'emisores';

    protected $fillable = ['nombre'];

    public function lineasTelefonicas()
    {
        return $this->hasMany(LineaTelefonica::class, 'id_emisor');
    }
}
