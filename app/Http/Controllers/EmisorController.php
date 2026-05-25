<?php

namespace App\Http\Controllers;

use App\Models\Emisor;
use Illuminate\Http\Request;

class EmisorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $emisores = Emisor::all();
        return view('emisores.index', compact('emisores'));
    }

    public function create()
    {
        return view('emisores.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        Emisor::create($validated);

        return redirect()->route('emisores.index')->with('success', 'Emisor creado exitosamente.');
    }

    public function show(Emisor $emisor)
    {
        return view('emisores.show', compact('emisor'));
    }

    public function edit(Emisor $emisor)
    {
        return view('emisores.edit', compact('emisor'));
    }

    public function update(Request $request, Emisor $emisor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
        ]);

        $emisor->update($validated);

        return redirect()->route('emisores.index')->with('success', 'Emisor actualizado exitosamente.');
    }

    public function destroy(Emisor $emisor)
    {
        $emisor->delete();
        return redirect()->route('emisores.index')->with('success', 'Emisor eliminado exitosamente.');
    }
}
