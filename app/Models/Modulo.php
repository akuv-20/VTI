<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $fillable = ['nombre', 'label', 'descripcion', 'route_prefixes', 'orden', 'activo'];

    protected $casts = [
        'route_prefixes' => 'array',
        'activo'         => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'modulo_user');
    }

    /** Comprueba si la ruta actual pertenece a este módulo */
    public function matchesRoute(string $routeName): bool
    {
        foreach ($this->route_prefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) return true;
        }
        return false;
    }
}
