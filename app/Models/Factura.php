<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo',
        'proveedor',
        'id_cuenta_contable',
        'id_servicio',
        'factura',
        'oc',
        'valor_neto',
        'valor_iva',
        'fecha_emision',
        'descripcion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'valor_neto'    => 'float',
        'valor_iva'     => 'float',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'id_servicio');
    }

    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class, 'id_cuenta_contable');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /** Cuenta contable efectiva: propia si esporádica, del servicio si mensual. */
    public function getCuentaContableEfectivaAttribute(): ?CuentaContable
    {
        return $this->cuentaContable ?? $this->servicio?->cuentaContable;
    }

    /** Valor total (neto + IVA guardado). */
    public function getTotalAttribute(): float
    {
        return $this->valor_neto + $this->valor_iva;
    }

    /** Ítem de entrega al que pertenece esta factura (si ya fue entregada). */
    public function entregaItem()
    {
        return $this->hasOne(EntregaFacturaItem::class, 'id_factura');
    }
}
