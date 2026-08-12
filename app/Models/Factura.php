<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    /**
     * Clase de documento. Es independiente de `tipo` (Mensual / Esporádica):
     * eso dice cada cuánto se gasta, esto qué papel llegó.
     *
     * Una nota de crédito guarda sus importes en NEGATIVO, así todo lo que suma
     * la resta sin necesidad de saber que existe.
     */
    public const TIPOS_DOCUMENTO = ['Factura', 'Nota de crédito'];

    public const NOTA_CREDITO = 'Nota de crédito';

    protected $fillable = [
        'tipo',
        'tipo_documento',
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

    /** Valor total (neto + IVA guardado). Negativo si es nota de crédito. */
    public function getTotalAttribute(): float
    {
        return $this->valor_neto + $this->valor_iva;
    }

    public function esNotaCredito(): bool
    {
        return $this->tipo_documento === self::NOTA_CREDITO;
    }

    /**
     * Importes en positivo, para mostrarlos en el formulario de edición.
     *
     * En la base viven con su signo; al editar se escriben siempre en positivo y
     * el controlador vuelve a aplicarlo. Si no, editar una nota de crédito
     * mostraría un menos en el campo y guardarla lo invertiría de nuevo.
     */
    public function getNetoEditableAttribute(): float
    {
        return abs($this->valor_neto);
    }

    public function getIvaEditableAttribute(): float
    {
        return abs($this->valor_iva);
    }

    /** Solo facturas de verdad: las notas de crédito no acreditan un mes. */
    public function scopeSoloFacturas($q)
    {
        return $q->where('tipo_documento', '!=', self::NOTA_CREDITO);
    }

    /** Ítem de entrega al que pertenece esta factura (si ya fue entregada). */
    public function entregaItem()
    {
        return $this->hasOne(EntregaFacturaItem::class, 'id_factura');
    }
}
