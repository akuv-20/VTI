<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Roaming extends Model
{
    protected $table = 'roamings';

    protected $fillable = [
        'id_linea_telefonica',
        'numero',
        'nombre_usuario',
        'carrier',
        'tipo',
        'pasaporte_dias',
        'fecha_inicio',
        'fecha_termino',
        'destino',
        'id_solicitud',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha_inicio'  => 'datetime',
        'fecha_termino' => 'datetime',
    ];

    public function lineaTelefonica()
    {
        return $this->belongsTo(LineaTelefonica::class, 'id_linea_telefonica');
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeMovistar($q)   { return $q->where('carrier', 'movistar'); }
    public function scopeEntel($q)       { return $q->where('carrier', 'entel'); }
    public function scopePasaportes($q)  { return $q->where('tipo', 'pasaporte'); }
    public function scopeRecurrentes($q) { return $q->where('tipo', 'recurrente'); }
    public function scopeActivos($q)     { return $q->where('estado', 'activo'); }

    /* ── Vigencia (para pasaportes con fecha_termino) ───────────────────── */

    /** Estado de vigencia calculado por fechas: programado | vigente | vencido. */
    public function getVigenciaAttribute(): string
    {
        if ($this->tipo !== 'pasaporte' || !$this->fecha_inicio) {
            return $this->estado; // recurrente / entel se rigen por estado
        }
        $ahora = now();
        if ($this->fecha_inicio->gt($ahora))                 return 'programado';
        if ($this->fecha_termino && $this->fecha_termino->lte($ahora)) return 'vencido';
        return 'vigente';
    }

    /** Calcula la fecha de término = inicio + N días (para pasaportes). */
    public static function calcularTermino(Carbon $inicio, int $dias): Carbon
    {
        return $inicio->copy()->addDays($dias);
    }
}
