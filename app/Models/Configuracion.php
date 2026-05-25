<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table      = 'configuraciones';
    protected $primaryKey = 'clave';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = ['clave', 'valor'];

    /** Obtiene el valor de una clave, o el default si no existe */
    public static function get(string $clave, mixed $default = null): mixed
    {
        return static::find($clave)?->valor ?? $default;
    }

    /** Guarda o actualiza una clave */
    public static function set(string $clave, mixed $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
