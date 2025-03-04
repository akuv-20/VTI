<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    // Mostrar todas las empresas
    public function index()
    {
        $empresas = Empresa::all();
        return view('empresas.index', compact('empresas'));
    }

    // Mostrar el formulario para crear una nueva empresa
    public function create()
    {
        return view('empresas.create');
    }

    // Guardar una nueva empresa
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:empresas,nombre',
        ]);

        Empresa::create($validated);

        return redirect()->route('empresas.index')->with('success', 'Empresa creada exitosamente.');
    }

    // Mostrar los detalles de una empresa
    public function show(Empresa $empresa)
    {
        return view('empresas.show', compact('empresa'));
    }

    // Mostrar el formulario para editar una empresa
    public function edit(Empresa $empresa)
    {
        return view('empresas.edit', compact('empresa'));
    }

    // Actualizar una empresa
    public function update(Request $request, Empresa $empresa)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:empresas,nombre,' . $empresa->id,
        ]);

        $empresa->update($validated);

        return redirect()->route('empresas.index')->with('success', 'Empresa actualizada exitosamente.');
    }

    // Eliminar una empresa
    public function destroy(Empresa $empresa)
    {
        $empresa->delete();
        return redirect()->route('empresas.index')->with('success', 'Empresa eliminada exitosamente.');
    }
}