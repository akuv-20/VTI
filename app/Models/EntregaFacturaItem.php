<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntregaFacturaItem extends Model
{
    use HasFactory;

    protected $table = 'entrega_factura_items';

    protected $fillable = ['id_entrega', 'id_factura'];

    public function entrega()
    {
        return $this->belongsTo(EntregaFactura::class, 'id_entrega');
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'id_factura');
    }
}
