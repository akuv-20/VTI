<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion;
use LdapRecord\Container as LdapContainer;
use LdapRecord\Connection  as LdapConnection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // ── LDAP: deshabilitar verificación de cert SSL (CA interna no confiable) ──
        // Aplica globalmente ANTES de cualquier conexión LDAP.
        // En producción con cert público válido se puede cambiar a LDAP_OPT_X_TLS_DEMAND.
        if (extension_loaded('ldap')) {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }

        // ── Conexión LDAP dinámica desde BD ──────────────────────────────────
        // Si existen credenciales guardadas en configuraciones, reemplazan las de .env
        try {
            $ldapHost = Configuracion::get('ldap_host');
            $ldapUser = Configuracion::get('ldap_username');
            $ldapPass = Configuracion::get('ldap_password');
            $ldapBase = Configuracion::get('ldap_base_dn', env('LDAP_BASE_DN', 'DC=verfrut,DC=cl'));
            $ldapPort = (int)(Configuracion::get('ldap_port') ?: env('LDAP_PORT', 389));

            if ($ldapHost && $ldapUser && $ldapPass) {
                $hosts = array_values(array_filter(array_map('trim', explode(',', $ldapHost))));
                LdapContainer::addConnection(
                    new LdapConnection([
                        'hosts'        => $hosts,
                        'username'     => $ldapUser,
                        'password'     => $ldapPass,
                        'base_dn'      => $ldapBase,
                        'port'         => $ldapPort,
                        'timeout'      => 5,
                        'use_tls'      => $ldapPort === 636,
                        'use_starttls' => false,
                    ])
                );
            }
        } catch (\Throwable) {
            // BD no disponible (ej: primera migración) — usa .env como fallback
        }

        // ── Conexión LDAP secundaria (Grupo Verfrut Perú) ────────────────────
        try {
            $ldap2Host = Configuracion::get('ldap2_host');
            $ldap2User = Configuracion::get('ldap2_username');
            $ldap2Pass = Configuracion::get('ldap2_password');
            $ldap2Base = Configuracion::get('ldap2_base_dn', '');
            $ldap2Port = (int)(Configuracion::get('ldap2_port') ?: 389);

            if ($ldap2Host && $ldap2User && $ldap2Pass) {
                $hosts2 = array_values(array_filter(array_map('trim', explode(',', $ldap2Host))));
                LdapContainer::addConnection(
                    new LdapConnection([
                        'hosts'        => $hosts2,
                        'username'     => $ldap2User,
                        'password'     => $ldap2Pass,
                        'base_dn'      => $ldap2Base,
                        'port'         => $ldap2Port,
                        'timeout'      => 5,
                        'use_tls'      => $ldap2Port === 636,
                        'use_starttls' => false,
                    ]),
                    'secondary'
                );
            }
        } catch (\Throwable) {
            // Segunda conexión no configurada aún
        }

        // ── Conexión a la base de GLPI desde BD ──────────────────────────────
        // Lo guardado en Admin → Configuración manda sobre el .env.
        // Va aquí y no en config/database.php porque allá la base de datos
        // todavía no existe: el cierre fallaba siempre y caía al .env en
        // silencio, así que lo que se grababa en la pantalla nunca se aplicaba.
        try {
            $glpi = Configuracion::whereIn('clave', [
                'glpi_db_host', 'glpi_db_port', 'glpi_db_database',
                'glpi_db_username', 'glpi_db_password',
            ])->pluck('valor', 'clave');

            $campos = [
                'glpi_db_host'     => 'host',
                'glpi_db_port'     => 'port',
                'glpi_db_database' => 'database',
                'glpi_db_username' => 'username',
                'glpi_db_password' => 'password',
            ];

            $aplicados = 0;
            foreach ($campos as $clave => $campo) {
                $valor = $glpi[$clave] ?? null;
                if ($valor === null || $valor === '') continue;  // sin valor guardado, queda el del .env
                config(["database.connections.glpi.{$campo}" => $valor]);
                $aplicados++;
            }

            // Si la conexión ya se había abierto con los valores viejos, se descarta.
            if ($aplicados) {
                DB::purge('glpi');
            }
        } catch (\Throwable) {
            // BD no disponible (ej: primera migración) — usa .env como fallback
        }

        // Registrar provider de Azure AD para Socialite
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        // Gate para rutas de administración
        Gate::define('admin', fn($user) => $user->es_admin && $user->activo);

        // Gates para Active Directory: admins + usuarios con el módulo asignado
        Gate::define('acceso_ad', function ($user) {
            if (!$user->activo) return false;
            return $user->tieneAcceso('admin.active_directory.index');
        });

        Gate::define('acceso_ad2', function ($user) {
            if (!$user->activo) return false;
            return $user->tieneAcceso('admin.active_directory2.index');
        });

        Gate::define('acceso_entra', function ($user) {
            if (!$user->activo) return false;
            return $user->tieneAcceso('admin.entra_id.index');
        });

        Gate::define('acceso_kpi', function ($user) {
            if (!$user->activo) return false;
            return $user->tieneAcceso('admin.kpi.disponibilidad.dashboard');
        });

        Gate::define('acceso_monitoreo', function ($user) {
            if (!$user->activo) return false;
            return $user->tieneAcceso('admin.monitoreo.mapas.index');
        });

        Gate::define('acceso_sitios', function ($user) {
            if (!$user->activo) return false;
            return $user->tieneAcceso('admin.sitios.index');
        });

        // Compartir configuraciones globales con todas las vistas
        View::composer('*', function ($view) {
            try {
                $appNombre = Configuracion::get('app_nombre') ?: config('app.name');

                $logoPath = Configuracion::get('app_logo');
                $appLogo  = ($logoPath && Storage::disk('public')->exists($logoPath))
                    ? Storage::url($logoPath)
                    : null;

                $bgPath        = Configuracion::get('login_background');
                $loginBackground = ($bgPath && Storage::disk('public')->exists($bgPath))
                    ? Storage::url($bgPath)
                    : null;

                $azureEnabled = (bool) Configuracion::get('azure_enabled', false);

                $faviconPath = Configuracion::get('favicon');
                $favicon     = ($faviconPath && Storage::disk('public')->exists($faviconPath))
                    ? Storage::url($faviconPath)
                    : null;
            } catch (\Throwable) {
                $appNombre       = config('app.name');
                $appLogo         = null;
                $loginBackground = null;
                $azureEnabled    = false;
                $favicon         = null;
            }
            $view->with(compact('appNombre', 'appLogo', 'loginBackground', 'azureEnabled', 'favicon'));
        });
    }
}
