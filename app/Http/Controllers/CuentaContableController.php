<?php

namespace App\Http\Controllers;

use App\Models\CuentaContable; // Importa el modelo CuentaContable
use Illuminate\Http\Request;

class CuentaContableController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth'); // Protege todas las rutas del controlador con autenticación
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $cuentasContables = CuentaContable::all();
        return view('cuentas_contables.index', compact('cuentasContables'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cuentas_contables.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_cuenta' => 'required|string|unique:cuentas_contables,numero_cuenta',
            'nombre_cuenta' => 'required|string',
        ]);

        CuentaContable::create($validated);

        return redirect()->route('cuentas_contables.index')->with('success', 'Cuenta contable creada exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Este método no se usará directamente para este mantenedor simple
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CuentaContable $cuentas_contable) // Laravel inyecta el modelo automáticamente
    {
        return view('cuentas_contables.edit', compact('cuentas_contable'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CuentaContable $cuentas_contable) // Laravel inyecta el modelo automáticamente
    {
        $validated = $request->validate([
            'numero_cuenta' => 'required|string|unique:cuentas_contables,numero_cuenta,' . $cuentas_contable->id, // Ignorar el ID actual al validar unicidad
            'nombre_cuenta' => 'required|string',
        ]);

        $cuentas_contable->update($validated);

        return redirect()->route('cuentas_contables.index')->with('success', 'Cuenta contable actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CuentaContable $cuentas_contable) // Laravel inyecta el modelo automáticamente
    {
        try {
            $cuentas_contable->delete();
            return redirect()->route('cuentas_contables.index')->with('success', 'Cuenta contable eliminada exitosamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            // Si hay servicios asociados, la eliminación fallará debido a la restricción de clave foránea (onDelete('set null'))
            // En este caso, el onDelete('set null') debería manejarlo, pero si hubiera onDelete('restrict') o similar,
            // esta excepción sería útil. Para onDelete('set null'), la eliminación debería ser exitosa.
            return redirect()->route('cuentas_contables.index')->with('error', 'No se puede eliminar la cuenta contable porque está asociada a uno o más servicios.');
        }
    }
}