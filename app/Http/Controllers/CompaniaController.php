<?php

namespace App\Http\Controllers;

use App\Models\Compania;
use Illuminate\Http\Request;

class CompaniaController extends Controller
{
    // Mostrar todas las compañías
    public function index()
    {
        $companias = Compania::all();
        return view('companias.index', compact('companias'));
    }

    // Mostrar el formulario para crear una nueva compañía
    public function create()
    {
        return view('companias.create');
    }

    // Guardar una nueva compañía
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:companias,nombre',
        ]);

        Compania::create($validated);

        return redirect()->route('companias.index')->with('success', 'Compañía creada exitosamente.');
    }

    // Mostrar los detalles de una compañía
    public function show(Compania $compania)
    {
        return view('companias.show', compact('compania'));
    }

    // Mostrar el formulario para editar una compañía
    public function edit(Compania $compania)
    {
        return view('companias.edit', compact('compania'));
    }

    // Actualizar una compañía
    public function update(Request $request, Compania $compania)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:companias,nombre,' . $compania->id,
        ]);

        $compania->update($validated);

        return redirect()->route('companias.index')->with('success', 'Compañía actualizada exitosamente.');
    }

    // Eliminar una compañía
    public function destroy(Compania $compania)
    {
        $compania->delete();
        return redirect()->route('companias.index')->with('success', 'Compañía eliminada exitosamente.');
    }
}