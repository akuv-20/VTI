<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\Familia;
use App\Models\Empresa;
use App\Models\Compania;
use App\Models\CuentaContable;
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
        $servicios = Servicio::with('familia', 'empresa', 'compania', 'cuentaContable')->get();
        return view('servicios.index', compact('servicios'));
    }

    // Mostrar el formulario para crear un nuevo servicio
    public function create()
    {
        $familias = Familia::all();
        $empresas = Empresa::all();
        $companias = Compania::all();
        $cuentasContables = CuentaContable::all();
        return view('servicios.create', compact('familias','empresas','companias','cuentasContables'));
    }

    // Guardar un nuevo servicio
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_servicio' => 'nullable|string',
            'id_familia' => 'required|exists:familias,id',
            'id_empresa' => 'required|exists:empresas,id',
            'id_compania' => 'required|exists:companias,id',
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'servicio' => 'required|string',
            'fecha_facturacion' => 'required|string',
            'concepto' => 'required|string',
            'es_periodico' => 'boolean',
        ]);
        
        $validated['es_periodico'] = $request->has('es_periodico');

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
        $empresas = Empresa::all();
        $companias = Compania::all();
        $cuentasContables = CuentaContable::all();
        return view('servicios.edit', compact('servicio','familias','empresas','companias','cuentasContables'));
    }

    // Actualizar un servicio
    public function update(Request $request, Servicio $servicio)
    {
        $validated = $request->validate([
            'codigo_servicio' => 'nullable|string',
            'id_familia' => 'required|exists:familias,id',
            'id_empresa' => 'required|exists:empresas,id',
            'id_compania' => 'required|exists:companias,id',
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'servicio' => 'required|string',
            'fecha_facturacion' => 'required|string',
            'concepto' => 'required|string',
            'es_periodico' => 'boolean',
        ]);

        $validated['es_periodico'] = $request->has('es_periodico');
        
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