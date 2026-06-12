<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $buscar = $request->input('buscar');
        $usuarios = User::when($buscar, fn($q) => $q->where('name', 'like', "%$buscar%")
                ->orWhere('email', 'like', "%$buscar%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.usuarios.index', compact('usuarios', 'buscar'));
    }

    public function create()
    {
        $modulos = Modulo::where('activo', true)->orderBy('orden')->get();
        return view('admin.usuarios.create', compact('modulos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'es_admin' => 'boolean',
            'activo'   => 'boolean',
            'modulos'  => 'array',
            'modulos.*'=> 'exists:modulos,id',
        ]);

        $usuario = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'es_admin' => $request->boolean('es_admin'),
            'activo'   => $request->boolean('activo', true),
        ]);

        $usuario->modulos()->sync($data['modulos'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario «' . $usuario->name . '» creado correctamente.');
    }

    public function show(User $usuario)
    {
        return redirect()->route('admin.usuarios.edit', $usuario);
    }

    public function edit(User $usuario)
    {
        $modulos   = Modulo::where('activo', true)->orderBy('orden')->get();
        $asignados = $usuario->modulos->pluck('id')->toArray();
        return view('admin.usuarios.edit', compact('usuario', 'modulos', 'asignados'));
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'es_admin' => 'boolean',
            'activo'   => 'boolean',
            'modulos'  => 'array',
            'modulos.*'=> 'exists:modulos,id',
        ]);

        $usuario->name     = $data['name'];
        $usuario->email    = $data['email'];
        $usuario->es_admin = $request->boolean('es_admin');
        $usuario->activo   = $request->boolean('activo');

        if (!empty($data['password'])) {
            $usuario->password = Hash::make($data['password']);
        }

        $usuario->save();
        $usuario->modulos()->sync($data['modulos'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario «' . $usuario->name . '» actualizado correctamente.');
    }

    /** Sincroniza nombre y correo del usuario desde Entra ID (Microsoft Graph) */
    public function sincronizarAzure(User $usuario)
    {
        $tenantId     = Configuracion::get('azure_tenant_id');
        $clientId     = Configuracion::get('azure_client_id');
        $clientSecret = Configuracion::get('azure_client_secret');

        if (!$tenantId || !$clientId || !$clientSecret) {
            return back()->withErrors(['error' => 'Faltan credenciales de Azure en Admin → Configuración → Microsoft 365.']);
        }

        try {
            // 1. Token app-to-app (client_credentials)
            $tokenResp = Http::asForm()->timeout(10)->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                ]
            );

            if (!$tokenResp->ok()) {
                return back()->withErrors(['error' => 'No se pudo obtener el token de Microsoft: ' . ($tokenResp->json('error_description') ?? $tokenResp->status())]);
            }

            $token = $tokenResp->json('access_token');

            // 2. Buscar el usuario en Graph: por object id si existe, sino por email
            $identificador = $usuario->provider_id ?: $usuario->email;
            $graphResp = Http::withToken($token)->timeout(10)->get(
                'https://graph.microsoft.com/v1.0/users/' . urlencode($identificador),
                ['$select' => 'id,displayName,mail,userPrincipalName,accountEnabled']
            );

            if ($graphResp->status() === 404) {
                return back()->withErrors(['error' => "No se encontró el usuario «{$identificador}» en Entra ID."]);
            }
            if (!$graphResp->ok()) {
                $msg = $graphResp->json('error.message') ?? ('HTTP ' . $graphResp->status());
                return back()->withErrors(['error' => 'Error de Microsoft Graph: ' . $msg . ' (verifica que la app tenga el permiso de aplicación User.Read.All)']);
            }

            $datos = $graphResp->json();

            // 3. Aplicar cambios
            $cambios = [];
            $nuevoNombre = $datos['displayName'] ?? null;
            $nuevoEmail  = $datos['mail'] ?? $datos['userPrincipalName'] ?? null;

            if ($nuevoNombre && $nuevoNombre !== $usuario->name) {
                $cambios[] = "nombre: «{$usuario->name}» → «{$nuevoNombre}»";
                $usuario->name = $nuevoNombre;
            }
            if ($nuevoEmail && strcasecmp($nuevoEmail, $usuario->email) !== 0) {
                // Evitar colisión con otro usuario local
                if (User::where('email', $nuevoEmail)->where('id', '!=', $usuario->id)->exists()) {
                    return back()->withErrors(['error' => "El correo «{$nuevoEmail}» de Entra ID ya está en uso por otro usuario del sistema."]);
                }
                $cambios[] = "correo: «{$usuario->email}» → «{$nuevoEmail}»";
                $usuario->email = $nuevoEmail;
            }
            if (!$usuario->provider_id && !empty($datos['id'])) {
                $usuario->provider    = 'azure';
                $usuario->provider_id = $datos['id'];
                $cambios[] = 'vinculado a Entra ID';
            }

            if (!$cambios) {
                return back()->with('success', 'El usuario ya está sincronizado con Entra ID, sin cambios.');
            }

            $usuario->save();
            return back()->with('success', 'Sincronizado desde Entra ID — ' . implode(' · ', $cambios) . '.');

        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Error al conectar con Microsoft: ' . $e->getMessage()]);
        }
    }

    public function destroy(User $usuario)
    {
        // No permitir eliminarse a sí mismo
        if ($usuario->id === auth()->id()) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario.']);
        }

        $nombre = $usuario->name ?? 'desconocido';
        $usuario->modulos()->detach();
        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario «' . $nombre . '» eliminado.');
    }
}
