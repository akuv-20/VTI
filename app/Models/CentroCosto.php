<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentroCosto extends Model
{
    use HasFactory;

    protected $table = 'centros_costo';

    protected $fillable = ['id_empresa', 'id_ubicacion', 'codigo_b', 'codigo_c'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'id_ubicacion');
    }

    public function getCcostoAttribute(): string
    {
        return $this->codigo_b . '-' . $this->codigo_c;
    }
}
