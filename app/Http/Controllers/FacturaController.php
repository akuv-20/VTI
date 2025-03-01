<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\Factura;
use Illuminate\Http\Request;

class FacturaController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $facturas = Factura::with('servicio')->get(); // Cargar datos relacionados
        return view('facturas.index', compact('facturas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $servicios = Servicio::all(); // Obtener todos los servicios
        return view('facturas.create', compact('servicios'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_servicio' => 'required|exists:servicios,id',
            'factura' => 'required|string',
            'valor_neto' => 'required|numeric|min:0',
            'valor_iva' => 'required|numeric|min:0',
            'fecha_emision' => 'required|date',
            'descripcion' => 'nullable|string',
        ]);

        Factura::create($validated);

        return redirect()->route('facturas.index')->with('success', 'Factura registrada exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Factura $factura)
{
    $servicios = Servicio::all(); // Obtener todos los servicios para el select
    return view('facturas.edit', compact('factura', 'servicios'));
}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Factura $factura)
{
    $validated = $request->validate([
        'id_servicio' => 'required|exists:servicios,id',
        'factura' => 'required|string',
        'valor_neto' => 'required|numeric|min:0',
        'valor_iva' => 'required|numeric|min:0',
        'fecha_emision' => 'required|date',
        'descripcion' => 'string',
    ]);

    $factura->update($validated);

    return redirect()->route('facturas.index')->with('success', 'Factura actualizada exitosamente.');
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Factura $factura)
{
    $factura->delete();
    return redirect()->route('facturas.index')->with('success', 'Factura eliminada exitosamente.');
}
}
