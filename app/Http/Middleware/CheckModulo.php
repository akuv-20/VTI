<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulo
{
    /** Prefijos de ruta que no requieren comprobación de módulo */
    private const BYPASS = ['admin.', 'login', 'logout', 'register', 'password.', 'verification.'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Sin usuario autenticado → dejar pasar (el middleware auth ya protege)
        if (!$user) {
            return $next($request);
        }

        // Usuario inactivo → desloguear
        if (!$user->activo) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada.']);
        }

        $routeName = $request->route()?->getName() ?? '';

        // Rutas excluidas de comprobación
        foreach (self::BYPASS as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $next($request);
            }
        }

        // Rutas sin nombre o raíz → pasar
        if (!$routeName || $routeName === 'home' || $routeName === 'dashboard') {
            return $next($request);
        }

        if (!$user->tieneAcceso($routeName)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
