<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
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

        // Registrar provider de Azure AD para Socialite
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        // Gate para rutas de administración
        Gate::define('admin', fn($user) => $user->es_admin && $user->activo);

        // Gate para Active Directory: admins + usuarios con el módulo asignado
        Gate::define('acceso_ad', function ($user) {
            if (!$user->activo) return false;
            if ($user->es_admin) return true;
            return $user->modulos->contains(
                fn($m) => $m->matchesRoute('admin.active_directory.')
            );
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
            } catch (\Throwable) {
                $appNombre       = config('app.name');
                $appLogo         = null;
                $loginBackground = null;
                $azureEnabled    = false;
            }
            $view->with(compact('appNombre', 'appLogo', 'loginBackground', 'azureEnabled'));
        });
    }
}
