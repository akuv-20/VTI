<?php

namespace App\Http\Controllers;

use App\Models\UsuarioTelefonico;
use Illuminate\Http\Request;

class UsuarioTelefonicoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $buscar = $request->input('buscar');
        $usuarios = UsuarioTelefonico::when($buscar, fn($q) => $q->where('nombre', 'like', "%$buscar%"))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();
        return view('usuarios_telefonicos.index', compact('usuarios', 'buscar'));
    }

    public function create()
    {
        return view('usuarios_telefonicos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        UsuarioTelefonico::create($validated);

        return redirect()->route('usuarios_telefonicos.index')->with('success', 'Usuario telefónico creado exitosamente.');
    }

    public function show(UsuarioTelefonico $usuarios_telefonico)
    {
        return view('usuarios_telefonicos.show', compact('usuarios_telefonico'));
    }

    public function edit(UsuarioTelefonico $usuarios_telefonico)
    {
        return view('usuarios_telefonicos.edit', compact('usuarios_telefonico'));
    }

    public function update(Request $request, UsuarioTelefonico $usuarios_telefonico)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        $usuarios_telefonico->update($validated);

        return redirect()->route('usuarios_telefonicos.index')->with('success', 'Usuario telefónico actualizado exitosamente.');
    }

    public function destroy(UsuarioTelefonico $usuarios_telefonico)
    {
        $usuarios_telefonico->delete();
        return redirect()->route('usuarios_telefonicos.index')->with('success', 'Usuario telefónico eliminado exitosamente.');
    }
}
