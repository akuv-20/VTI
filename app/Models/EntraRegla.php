<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntraRegla extends Model
{
    protected $table = 'entra_reglas';

    protected $fillable = [
        'campo',
        'tipo',
        'etiqueta',
        'descripcion',
        'config',
        'severidad',
        'solo_habilitados',
        'activa',
        'orden',
    ];

    protected $casts = [
        'config'           => 'array',
        'solo_habilitados' => 'boolean',
        'activa'           => 'boolean',
    ];

    /* ── Catálogos ──────────────────────────────────────────────────────── */

    /** Tipos de regla disponibles: clave → [etiqueta, requiere_campo, ayuda]. */
    public const TIPOS = [
        'valores_permitidos' => [
            'etiqueta'       => 'Lista de valores permitidos',
            'requiere_campo' => true,
            'ayuda'          => 'El campo solo puede contener uno de los valores indicados.',
        ],
        'obligatorio' => [
            'etiqueta'       => 'Campo obligatorio',
            'requiere_campo' => true,
            'ayuda'          => 'El campo no puede estar vacío.',
        ],
        'formato_consistente' => [
            'etiqueta'       => 'Formato consistente',
            'requiere_campo' => true,
            'ayuda'          => 'Detecta el mismo valor escrito de distintas maneras (mayúsculas, espacios, tildes).',
        ],
        'sin_duplicados' => [
            'etiqueta'       => 'Sin duplicados',
            'requiere_campo' => false,
            'ayuda'          => 'Marca cuentas distintas que comparten la combinación de campos indicada.',
        ],
        'actividad_reciente' => [
            'etiqueta'       => 'Actividad reciente',
            'requiere_campo' => false,
            'ayuda'          => 'Marca cuentas sin inicio de sesión en los últimos N días. Requiere el permiso AuditLog.Read.All.',
        ],
    ];

    /** Campos Graph que se pueden evaluar: clave → etiqueta legible. */
    public const CAMPOS = [
        'displayName'       => 'Nombre completo',
        'givenName'         => 'Nombre de pila',
        'surname'           => 'Apellido',
        'mail'              => 'Correo electrónico',
        'jobTitle'          => 'Cargo',
        'department'        => 'Área / Departamento',
        'companyName'       => 'Empresa',
        'officeLocation'    => 'Oficina',
        'city'              => 'Ciudad',
        'state'             => 'Región / Estado',
        'country'           => 'País',
        'usageLocation'     => 'Ubicación de uso',
        'mobilePhone'       => 'Teléfono móvil',
        'userType'          => 'Tipo de cuenta',
    ];

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeActivas($q) { return $q->where('activa', true); }
    public function scopeOrdenadas($q) { return $q->orderBy('orden')->orderBy('id'); }

    /* ── Accesores ──────────────────────────────────────────────────────── */

    public function getTipoEtiquetaAttribute(): string
    {
        return self::TIPOS[$this->tipo]['etiqueta'] ?? $this->tipo;
    }

    public function getCampoEtiquetaAttribute(): ?string
    {
        return $this->campo ? (self::CAMPOS[$this->campo] ?? $this->campo) : null;
    }

    /** Necesita datos de inicio de sesión (permiso extra en Graph). */
    public function getRequiereSignInAttribute(): bool
    {
        return $this->tipo === 'actividad_reciente';
    }
}
