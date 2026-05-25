<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion;

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

        // Gate para rutas de administración
        Gate::define('admin', fn($user) => $user->es_admin && $user->activo);

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
            } catch (\Throwable) {
                $appNombre       = config('app.name');
                $appLogo         = null;
                $loginBackground = null;
            }
            $view->with(compact('appNombre', 'appLogo', 'loginBackground'));
        });
    }
}
