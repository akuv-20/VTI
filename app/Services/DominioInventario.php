<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Un dominio del módulo de Inventario: el par GLPI + Active Directory que
 * comparte pantallas con los demás pero apunta a otras conexiones.
 *
 * Se construye siempre desde la clave de la URL (`verfrut`, `unifrutti`), y
 * de ahí salen las conexiones que usan los controladores. Las pantallas no
 * conocen nombres de conexión: le preguntan al dominio.
 */
class DominioInventario
{
    private function __construct(
        public readonly string $clave,
        public readonly array $config,
    ) {}

    /* ── Construcción ───────────────────────────────────────────────────── */

    /** Devuelve el dominio de esa clave, o null si no existe. */
    public static function de(?string $clave): ?self
    {
        if (!$clave) return null;

        $config = config("inventario.dominios.{$clave}");

        return $config ? new self($clave, $config) : null;
    }

    /** Igual que de(), pero aborta con 404 si la clave no existe. */
    public static function oFalla(?string $clave): self
    {
        $dominio = self::de($clave);
        abort_if(!$dominio, 404);

        return $dominio;
    }

    /** Todos los dominios declarados. */
    public static function todos(): Collection
    {
        return collect(array_keys(config('inventario.dominios', [])))
            ->map(fn($clave) => new self($clave, config("inventario.dominios.{$clave}")));
    }

    /**
     * Los dominios que el usuario puede ver.
     *
     * De esto depende toda la navegación: si tiene uno solo se entra directo
     * sin pasar por el selector, y si no tiene ninguno el módulo no aparece.
     */
    public static function permitidos(): Collection
    {
        return self::todos()->filter(fn(self $d) => Gate::allows($d->gate()))->values();
    }

    /* ── Accesores ──────────────────────────────────────────────────────── */

    public function label(): string   { return $this->config['label']; }
    public function dominio(): string { return $this->config['dominio']; }
    public function gate(): string    { return $this->config['gate']; }
    public function color(): string   { return $this->config['color'] ?? '#2563eb'; }
    public function icono(): string   { return $this->config['icono'] ?? 'bi-building'; }

    /** Nombre de la conexión de base de datos del GLPI de este dominio. */
    public function glpi(): string { return $this->config['glpi']; }

    /** Nombre de la conexión LDAP, o null si usa la de por defecto. */
    public function ad(): ?string { return $this->config['ad'] ?? null; }

    /**
     * Antivirus corporativo del dominio (Bitdefender, ESET…).
     *
     * No es cosmético: cada dominio despliega el suyo, y buscar el equivocado
     * daría "sin antivirus" en el 100% de los equipos.
     */
    public function antivirus(): ?string { return $this->config['antivirus'] ?? null; }

    /** Usuario de GLPI a excluir de los indicadores, si el dominio define uno. */
    public function excluirUser(): ?int
    {
        $v = $this->config['excluir_user'] ?? null;

        return $v === null ? null : (int) $v;
    }

    /** Si el usuario actual tiene acceso a este dominio. */
    public function permitido(): bool
    {
        return Gate::allows($this->gate());
    }

    /* ── Ayudas para las consultas ──────────────────────────────────────── */

    /**
     * Aplica el filtro de usuario excluido sobre una consulta de
     * glpi_computers aliasada como `c`. Si el dominio no excluye a nadie,
     * la consulta vuelve intacta.
     */
    public function sinUsuarioExcluido($query, string $alias = 'c')
    {
        $excluir = $this->excluirUser();

        return $excluir === null ? $query : $query->where("{$alias}.users_id", '!=', $excluir);
    }
}
