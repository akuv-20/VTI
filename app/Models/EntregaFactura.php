<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HoraLocal;

class EntregaFactura extends Model
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

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
