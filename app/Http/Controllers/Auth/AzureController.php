<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AzureController extends Controller
{
    /** Configura Socialite con las credenciales almacenadas en BD */
    private function configurarAzure(): void
    {
        config([
            'services.azure.client_id'     => Configuracion::get('azure_client_id'),
            'services.azure.client_secret' => Configuracion::get('azure_client_secret'),
            'services.azure.tenant'        => Configuracion::get('azure_tenant_id'),
            'services.azure.redirect'      => url('/auth/azure/callback'),
        ]);
    }

    /** Redirige al login de Microsoft */
    public function redirect()
    {
        $this->configurarAzure();
        return Socialite::driver('azure')->redirect();
    }

    /** Recibe el callback de Microsoft y autentica al usuario */
    public function callback()
    {
        $this->configurarAzure();

        try {
            $azureUser = Socialite::driver('azure')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['azure' => 'No se pudo completar el inicio de sesión con Microsoft. Intenta nuevamente.']);
        }

        // Buscar usuario existente por provider_id o por email
        $user = User::where('provider', 'azure')
                    ->where('provider_id', $azureUser->getId())
                    ->first();

        if (!$user) {
            $user = User::where('email', $azureUser->getEmail())->first();
        }

        if ($user) {
            // Actualizar provider si vino por email la primera vez
            if (!$user->provider) {
                $user->update([
                    'provider'    => 'azure',
                    'provider_id' => $azureUser->getId(),
                ]);
            }

            if (!$user->activo) {
                return redirect()->route('login')
                    ->withErrors(['azure' => 'Tu cuenta está pendiente de activación. Contacta al administrador.']);
            }

            Auth::login($user, true);
            return redirect()->intended('/home');
        }

        // Usuario nuevo — crear con activo = false (requiere aprobación del admin)
        $nuevoUsuario = User::create([
            'name'        => $azureUser->getName() ?? $azureUser->getEmail(),
            'email'       => $azureUser->getEmail(),
            'provider'    => 'azure',
            'provider_id' => $azureUser->getId(),
            'password'    => null,
            'es_admin'    => false,
            'activo'      => false,
        ]);

        return redirect()->route('login')->with(
            'azure_pendiente',
            'Tu cuenta ha sido registrada. Un administrador debe activarla antes de que puedas ingresar.'
        );
    }
}
