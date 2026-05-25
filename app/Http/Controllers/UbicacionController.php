<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use Illuminate\Http\Request;

class UbicacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $buscar = $request->input('buscar');
        $ubicaciones = Ubicacion::when($buscar, fn($q) => $q->where('nombre', 'like', "%$buscar%"))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();
        return view('ubicaciones.index', compact('ubicaciones', 'buscar'));
    }

    public function create()
    {
        return view('ubicaciones.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        Ubicacion::create($validated);

        return redirect()->route('ubicaciones.index')->with('success', 'Ubicación creada exitosamente.');
    }

    public function show(Ubicacion $ubicacion)
    {
        return view('ubicaciones.show', compact('ubicacion'));
    }

    public function edit(Ubicacion $ubicacion)
    {
        return view('ubicaciones.edit', compact('ubicacion'));
    }

    public function update(Request $request, Ubicacion $ubicacion)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        $ubicacion->update($validated);

        return redirect()->route('ubicaciones.index')->with('success', 'Ubicación actualizada exitosamente.');
    }

    public function destroy(Ubicacion $ubicacion)
    {
        try {
            $ubicacion->delete();
            return redirect()->route('ubicaciones.index')->with('success', 'Ubicación eliminada exitosamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('ubicaciones.index')->with('error', 'No se puede eliminar la ubicación porque tiene registros asociados.');
        }
    }
}
