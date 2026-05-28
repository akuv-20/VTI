<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntregaFactura extends Model
{
    use HasFactory;

    protected $table = 'entregas_facturas';

    protected $fillable = ['id_usuario', 'observacion'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function items()
    {
        return $this->hasMany(EntregaFacturaItem::class, 'id_entrega');
    }
}
