<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HoraLocal;

class User extends Authenticatable
{
    /** Las horas se guardan en UTC y se muestran en la zona de cada usuario. */
    use HoraLocal;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'es_admin', 'activo', 'zona_horaria',
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
        'glpi_api_token',
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
            // glpi_api_token va SIN cifrar a propósito: desarrollo y producción
            // comparten la base pero no la APP_KEY, y cifrado con una no se
            // podría leer desde la otra.
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
