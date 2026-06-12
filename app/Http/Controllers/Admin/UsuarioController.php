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

    /** Obtiene un token app-to-app de Microsoft Graph. Lanza excepción con mensaje legible. */
    private function graphToken(): string
    {
        $tenantId     = Configuracion::get('azure_tenant_id');
        $clientId     = Configuracion::get('azure_client_id');
        $clientSecret = Configuracion::get('azure_client_secret');

        if (!$tenantId || !$clientId || !$clientSecret) {
            throw new \RuntimeException('Faltan credenciales de Azure en Admin → Configuración → Microsoft 365.');
        }

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
            throw new \RuntimeException('No se pudo obtener el token de Microsoft: ' . ($tokenResp->json('error_description') ?? $tokenResp->status()));
        }

        return $tokenResp->json('access_token');
    }

    /** Sincroniza nombre y correo del usuario desde Entra ID (Microsoft Graph) */
    public function sincronizarAzure(User $usuario)
    {
        try {
            $token = $this->graphToken();

            // Buscar el usuario en Graph: por object id si existe, sino por email
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

    /** AJAX: busca usuarios en Entra ID por nombre o correo */
    public function buscarEntra(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['ok' => true, 'usuarios' => []]);
        }

        try {
            $token = $this->graphToken();

            $safe = str_replace("'", "''", $q);
            $graphResp = Http::withToken($token)->timeout(10)->get(
                'https://graph.microsoft.com/v1.0/users',
                [
                    '$filter' => "startswith(displayName,'{$safe}') or startswith(mail,'{$safe}') or startswith(userPrincipalName,'{$safe}')",
                    '$select' => 'id,displayName,mail,userPrincipalName,accountEnabled,department,jobTitle',
                    '$top'    => 15,
                ]
            );

            if (!$graphResp->ok()) {
                $msg = $graphResp->json('error.message') ?? ('HTTP ' . $graphResp->status());
                return response()->json(['ok' => false, 'message' => 'Microsoft Graph: ' . $msg]);
            }

            $resultados = collect($graphResp->json('value', []));

            // Marcar los que ya existen localmente (por email o provider_id)
            $emails      = $resultados->map(fn($u) => strtolower($u['mail'] ?? $u['userPrincipalName'] ?? ''))->filter();
            $ids         = $resultados->pluck('id');
            $existentes  = User::whereIn('provider_id', $ids)
                ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), $emails)
                ->get(['email', 'provider_id']);

            $usuarios = $resultados->map(function ($u) use ($existentes) {
                $email  = $u['mail'] ?? $u['userPrincipalName'] ?? null;
                $existe = $existentes->contains(fn($e) =>
                    $e->provider_id === $u['id'] ||
                    ($email && strcasecmp($e->email, $email) === 0)
                );
                return [
                    'id'          => $u['id'],
                    'nombre'      => $u['displayName'] ?? '—',
                    'email'       => $email,
                    'departamento'=> $u['department'] ?? null,
                    'cargo'       => $u['jobTitle'] ?? null,
                    'habilitado'  => $u['accountEnabled'] ?? true,
                    'existe'      => $existe,
                ];
            })->values();

            return response()->json(['ok' => true, 'usuarios' => $usuarios]);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Importa un usuario desde Entra ID como usuario local */
    public function importarEntra(Request $request)
    {
        $request->validate([
            'entra_id' => 'required|string',
            'activo'   => 'boolean',
        ]);

        try {
            $token = $this->graphToken();

            $graphResp = Http::withToken($token)->timeout(10)->get(
                'https://graph.microsoft.com/v1.0/users/' . urlencode($request->input('entra_id')),
                ['$select' => 'id,displayName,mail,userPrincipalName']
            );

            if (!$graphResp->ok()) {
                return back()->withErrors(['error' => 'No se pudo obtener el usuario desde Entra ID.']);
            }

            $datos = $graphResp->json();
            $email = $datos['mail'] ?? $datos['userPrincipalName'] ?? null;

            if (!$email) {
                return back()->withErrors(['error' => 'El usuario de Entra ID no tiene correo.']);
            }

            // Evitar duplicados
            $yaExiste = User::where('provider_id', $datos['id'])
                ->orWhereRaw('LOWER(email) = ?', [strtolower($email)])
                ->first();
            if ($yaExiste) {
                return back()->withErrors(['error' => "Ya existe un usuario con ese correo: «{$yaExiste->name}»."]);
            }

            $usuario = User::create([
                'name'        => $datos['displayName'] ?? $email,
                'email'       => $email,
                'provider'    => 'azure',
                'provider_id' => $datos['id'],
                'password'    => null,
                'es_admin'    => false,
                'activo'      => $request->boolean('activo', true),
            ]);

            return redirect()->route('admin.usuarios.edit', $usuario)
                ->with('success', 'Usuario «' . $usuario->name . '» importado desde Entra ID. Asigna sus módulos.');

        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Error al importar: ' . $e->getMessage()]);
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
