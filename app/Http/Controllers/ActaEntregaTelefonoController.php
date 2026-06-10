<?php

namespace App\Http\Controllers;

use App\Models\ActaEntregaTelefono;
use App\Models\LineaTelefonica;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActaEntregaTelefonoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $actas = ActaEntregaTelefono::with('lineaTelefonica')
            ->latest()
            ->paginate(25);

        return view('actas_entrega_telefono.index', compact('actas'));
    }

    public function store(Request $request, LineaTelefonica $linea)
    {
        $linea->load(['emisor', 'usuario', 'empresa', 'ubicacion', 'aparato.marca']);

        $validated = $request->validate([
            'condicion'                       => 'required|in:Nuevo,Usado',
            'accesorios.cargador_usb'         => 'nullable|in:SI,NO',
            'accesorios.cargador_auto'        => 'nullable|in:SI,NO',
            'accesorios.manos_libres'         => 'nullable|in:SI,NO',
            'accesorios.cd_informacion'       => 'nullable|in:SI,NO',
            'documentacion.manual_propietario'=> 'nullable|in:SI,NO',
            'documentacion.procedimiento_uso' => 'nullable|in:SI,NO',
            'observacion'                     => 'nullable|string|max:500',
        ]);

        $zona = trim(
            ($linea->empresa->nombre ?? '') . ' - ' . ($linea->ubicacion->nombre ?? '')
        , ' -');

        $acta = ActaEntregaTelefono::create([
            'id_linea_telefonica' => $linea->id,
            'fecha_emision'       => now()->toDateString(),
            'numero_telefono'     => $linea->linea,
            'nombre_receptor'     => $linea->usuario->nombre ?? null,
            'zona'                => $zona ?: null,
            'marca'               => $linea->aparato?->marca?->nombre,
            'modelo'              => $linea->aparato?->modelo,
            'compania'            => $linea->emisor?->nombre,
            'imei_equipo'         => $linea->imei_equipo,
            'imei_sim'            => $linea->imei_sim,
            'condicion'           => $validated['condicion'],
            'accesorios'          => $validated['accesorios'] ?? [],
            'documentacion'       => $validated['documentacion'] ?? [],
            'observacion'         => $validated['observacion'] ?? null,
            'impreso_por'         => auth()->user()->name,
        ]);

        return redirect()->route('actas_entrega_telefono.imprimir', $acta);
    }

    public function imprimir(ActaEntregaTelefono $acta)
    {
        $acta->load('lineaTelefonica');

        $logoPath = Configuracion::get('app_logo');
        $appLogo  = $logoPath ? Storage::url($logoPath) : null;

        return view('actas_entrega_telefono.imprimir', compact('acta', 'appLogo'));
    }

    public function destroy(ActaEntregaTelefono $acta)
    {
        $this->authorize('admin');
        $acta->delete();
        return back()->with('success', 'Acta eliminada.');
    }
}
