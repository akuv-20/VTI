<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $marcas = Marca::all();
        return view('marcas.index', compact('marcas'));
    }

    public function create()
    {
        return view('marcas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        Marca::create($validated);

        return redirect()->route('marcas.index')->with('success', 'Marca creada exitosamente.');
    }

    public function show(Marca $marca)
    {
        return view('marcas.show', compact('marca'));
    }

    public function edit(Marca $marca)
    {
        return view('marcas.edit', compact('marca'));
    }

    public function update(Request $request, Marca $marca)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        $marca->update($validated);

        return redirect()->route('marcas.index')->with('success', 'Marca actualizada exitosamente.');
    }

    public function destroy(Marca $marca)
    {
        $marca->delete();
        return redirect()->route('marcas.index')->with('success', 'Marca eliminada exitosamente.');
    }
}
