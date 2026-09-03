<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Buzón que no entra en el análisis de uso. Ver la migración para el porqué. */
class BuzonExcluido extends Model
{
    protected $table = 'buzones_excluidos';

    protected $fillable = ['upn', 'motivo', 'activo', 'user_id'];

    protected $casts = ['activo' => 'boolean'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Los UPN se comparan siempre en minúsculas: Graph no es consistente. */
    public function setUpnAttribute($valor): void
    {
        $this->attributes['upn'] = mb_strtolower(trim((string) $valor));
    }

    /** Conjunto de UPN activos, listo para comprobar pertenencia. */
    public static function activos(): array
    {
        return static::query()->where('activo', true)->pluck('upn')->flip()->all();
    }
}
