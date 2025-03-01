<?php

namespace App\Http\Controllers;

use App\Models\Familia;
use Illuminate\Http\Request;

class FamiliaController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    
    // Mostrar todas las familias
    public function index()
    {
        $familias = Familia::all();
        return view('familias.index', compact('familias'));
    }

    // Mostrar el formulario para crear una nueva familia
    public function create()
    {
        return view('familias.create');
    }

    // Guardar una nueva familia
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:familias,nombre',
        ]);

        Familia::create($validated);

        return redirect()->route('familias.index')->with('success', 'Familia creada exitosamente.');
    }

    // Mostrar los detalles de una familia
    public function show(Familia $familia)
    {
        return view('familias.show', compact('familia'));
    }

    // Mostrar el formulario para editar una familia
    public function edit(Familia $familia)
    {
        return view('familias.edit', compact('familia'));
    }

    // Actualizar una familia
    public function update(Request $request, Familia $familia)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:familias,nombre,' . $familia->id,
        ]);

        $familia->update($validated);

        return redirect()->route('familias.index')->with('success', 'Familia actualizada exitosamente.');
    }

    // Eliminar una familia
    public function destroy(Familia $familia)
    {
        $familia->delete();
        return redirect()->route('familias.index')->with('success', 'Familia eliminada exitosamente.');
    }
}