<?php

namespace App\Http\Controllers;

use App\Models\Aparato;
use App\Models\Marca;
use Illuminate\Http\Request;

class AparatoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $aparatos = Aparato::with('marca')->get();
        return view('aparatos.index', compact('aparatos'));
    }

    public function create()
    {
        $marcas = Marca::all();
        return view('aparatos.create', compact('marcas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_marca' => 'required|exists:marcas,id',
            'modelo'   => 'required|string',
        ]);

        Aparato::create($validated);

        return redirect()->route('aparatos.index')->with('success', 'Aparato creado exitosamente.');
    }

    public function show(Aparato $aparato)
    {
        return view('aparatos.show', compact('aparato'));
    }

    public function edit(Aparato $aparato)
    {
        $marcas = Marca::all();
        return view('aparatos.edit', compact('aparato', 'marcas'));
    }

    public function update(Request $request, Aparato $aparato)
    {
        $validated = $request->validate([
            'id_marca' => 'required|exists:marcas,id',
            'modelo'   => 'required|string',
        ]);

        $aparato->update($validated);

        return redirect()->route('aparatos.index')->with('success', 'Aparato actualizado exitosamente.');
    }

    public function destroy(Aparato $aparato)
    {
        $aparato->delete();
        return redirect()->route('aparatos.index')->with('success', 'Aparato eliminado exitosamente.');
    }
}
