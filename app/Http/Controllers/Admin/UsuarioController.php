<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
