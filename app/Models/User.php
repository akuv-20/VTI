<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'es_admin', 'activo',
        // Preparado para Office 365 SSO (SAML/OAuth):
        // 'azure_id', 'azure_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'es_admin'          => 'boolean',
            'activo'            => 'boolean',
        ];
    }

    public function modulos()
    {
        return $this->belongsToMany(Modulo::class, 'modulo_user');
    }

    /** Admin tiene acceso a todo; usuarios normales solo a sus módulos asignados */
    public function tieneAcceso(string $routeName): bool
    {
        if ($this->es_admin) return true;
        return $this->modulos->contains(fn($m) => $m->matchesRoute($routeName));
    }
}
