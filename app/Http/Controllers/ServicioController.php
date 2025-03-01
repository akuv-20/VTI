<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\Familia;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    // Mostrar todos los servicios
    public function index()
    {
        // $servicios = Servicio::all();
        $servicios = Servicio::with('familia')->get();
        return view('servicios.index', compact('servicios'));
    }

    // Mostrar el formulario para crear un nuevo servicio
    public function create()
    {
        $familias = Familia::all();
        return view('servicios.create', compact('familias'));
    }

    // Guardar un nuevo servicio
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_servicio' => 'nullable|string',
            'id_familia' => 'required|exists:familias,id',
            'empresa' => 'required|string',
            'compania' => 'required|string',
            'servicio' => 'required|string',
            'fecha_facturacion' => 'required|string',
            'concepto' => 'required|string',
        ]);

        Servicio::create($validated);

        return redirect()->route('servicios.index')->with('success', 'Servicio creado exitosamente.');
    }

    // Mostrar los detalles de un servicio
    public function show(Servicio $servicio)
    {
        return view('servicios.show', compact('servicio'));
    }

    // Mostrar el formulario para editar un servicio
    public function edit(Servicio $servicio)
    {
        $familias = Familia::all();
        return view('servicios.edit', compact('servicio','familias'));
    }

    // Actualizar un servicio
    public function update(Request $request, Servicio $servicio)
    {
        $validated = $request->validate([
            'codigo_servicio' => 'string',
            'id_familia' => 'required|exists:familias,id',
            'empresa' => 'required|string',
            'compania' => 'required|string',
            'servicio' => 'required|string',
            'fecha_facturacion' => 'required|string',
            'concepto' => 'required|string',
        ]);

        $servicio->update($validated);

        return redirect()->route('servicios.index')->with('success', 'Servicio actualizado exitosamente.');
    }

    // Eliminar un servicio
    public function destroy(Servicio $servicio)
    {
        $servicio->delete();
        return redirect()->route('servicios.index')->with('success', 'Servicio eliminado exitosamente.');
    }
}