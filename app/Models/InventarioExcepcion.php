<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Regla que exceptúa equipos del indicador de antivirus.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  La misma condición se evalúa en dos lugares y TIENEN que coincidir:
 *
 *   - `aplicarA()`  arma el SQL, porque los conteos e indicadores se calculan
 *                   sobre el universo completo y no sobre la página visible.
 *   - `coincide()`  evalúa una fila ya cargada, para pintar la insignia
 *                   "exceptuado" sin una consulta por equipo.
 *
 *  Si cambias los operadores, cambia los dos.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InventarioExcepcion extends Model
{
    protected $table = 'inventario_excepciones';

    protected $fillable = ['dominio', 'campo', 'operador', 'valor', 'motivo', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    /* ── Catálogos ──────────────────────────────────────────────────────── */

    public const CAMPOS = [
        'sistema_operativo' => 'Sistema operativo',
        'nombre_equipo'     => 'Nombre del equipo',
        'contact'           => 'Usuario alternativo',
    ];

    public const OPERADORES = [
        'contiene' => 'contiene',
        'empieza'  => 'empieza con',
        'igual'    => 'es igual a',
    ];

    /** Columna SQL de cada campo. La del SO viene del join de GLPI. */
    private const COLUMNAS = [
        'sistema_operativo' => 'os.name',
        'nombre_equipo'     => 'c.name',
        'contact'           => 'c.contact',
    ];

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('activa', true);
    }

    /** Reglas del dominio indicado más las que aplican a todos. */
    public function scopeDelDominio(Builder $q, string $dominio): Builder
    {
        return $q->where(fn($w) => $w->where('dominio', $dominio)->orWhereNull('dominio'));
    }

    /* ── Evaluación ─────────────────────────────────────────────────────── */

    /**
     * Agrega al query la condición "alguna regla coincide".
     *
     * @param  \Illuminate\Support\Collection<int,self>  $reglas
     * @param  bool  $negar  true = "ninguna regla coincide"
     */
    public static function aplicarA($query, $reglas, bool $negar = false)
    {
        // Sin reglas no hay nada que exceptuar: pedir "los exceptuados" no debe
        // devolver todo, y pedir "los no exceptuados" no debe devolver nada.
        if ($reglas->isEmpty()) {
            return $negar ? $query : $query->whereRaw('1 = 0');
        }

        $condicion = function ($w) use ($reglas) {
            foreach ($reglas as $r) {
                $col = self::COLUMNAS[$r->campo] ?? 'c.name';

                if ($r->operador === 'igual') {
                    $w->orWhere($col, $r->valor);
                } else {
                    $patron = $r->operador === 'empieza' ? "{$r->valor}%" : "%{$r->valor}%";
                    $w->orWhere($col, 'like', $patron);
                }
            }
        };

        return $negar
            ? $query->where(fn($w) => $w->whereNot($condicion))
            : $query->where($condicion);
    }

    /** Evalúa la regla contra una fila ya cargada. */
    public function coincide(?string $nombreEquipo, ?string $sistemaOperativo, ?string $contact = null): bool
    {
        $sujeto = match ($this->campo) {
            'nombre_equipo' => $nombreEquipo,
            'contact'       => $contact,
            default         => $sistemaOperativo,
        };
        $sujeto = (string) $sujeto;

        // MySQL compara sin distinguir mayúsculas con la collation del proyecto;
        // acá hay que pedirlo explícitamente para que los dos caminos coincidan.
        $s = mb_strtolower($sujeto);
        $v = mb_strtolower((string) $this->valor);

        if ($v === '') return false;

        return match ($this->operador) {
            'igual'   => $s === $v,
            'empieza' => str_starts_with($s, $v),
            default   => str_contains($s, $v),
        };
    }

    /** Si alguna de las reglas dadas cubre a este equipo. */
    public static function algunaCoincide($reglas, ?string $nombreEquipo, ?string $so, ?string $contact = null): bool
    {
        foreach ($reglas as $r) {
            if ($r->coincide($nombreEquipo, $so, $contact)) return true;
        }
        return false;
    }

    /* ── Accesores ──────────────────────────────────────────────────────── */

    public function getCampoEtiquetaAttribute(): string
    {
        return self::CAMPOS[$this->campo] ?? $this->campo;
    }

    public function getOperadorEtiquetaAttribute(): string
    {
        return self::OPERADORES[$this->operador] ?? $this->operador;
    }

    /** Lectura corrida de la regla: "Sistema operativo contiene macOS". */
    public function getResumenAttribute(): string
    {
        return "{$this->campo_etiqueta} {$this->operador_etiqueta} «{$this->valor}»";
    }
}
