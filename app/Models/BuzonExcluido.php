<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Buzón que no entra en el análisis de uso. Ver la migración para el porqué.
 *
 * El borrado es lógico: la lista se arma a mano y no hay manera de reconstruirla
 * desde los datos, así que quitar un buzón —o vaciarla sin querer— tiene que ser
 * reversible.
 */
class BuzonExcluido extends Model
{
    use SoftDeletes;

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

    /**
     * Agrega un buzón, resucitándolo si estaba en la papelera.
     *
     * `upn` es único en la tabla y el índice cuenta también las filas borradas,
     * así que un updateOrCreate normal chocaría contra un registro invisible y
     * fallaría sin explicar por qué.
     */
    public static function agregar(string $upn, string $motivo, ?int $userId = null): self
    {
        $upn = mb_strtolower(trim($upn));

        $buzon = static::withTrashed()->where('upn', $upn)->first();

        if ($buzon) {
            $buzon->restore();
            $buzon->update(['motivo' => $motivo, 'activo' => true, 'user_id' => $userId]);

            return $buzon;
        }

        return static::create(['upn' => $upn, 'motivo' => $motivo, 'activo' => true, 'user_id' => $userId]);
    }
}
