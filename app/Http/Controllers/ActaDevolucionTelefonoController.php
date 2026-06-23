<?php

namespace App\Http\Controllers;

use App\Models\ActaDevolucionTelefono;
use App\Models\LineaTelefonica;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActaDevolucionTelefonoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $actas = ActaDevolucionTelefono::with('lineaTelefonica')
            ->latest()
            ->paginate(25);

        return view('actas_devolucion_telefono.index', compact('actas'));
    }

    /** Búsqueda de líneas por número o nombre de usuario (autocomplete del modal). */
    public function buscarLineas(Request $request)
    {
        $q = trim($request->input('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $lineas = LineaTelefonica::with(['usuario', 'empresa', 'ubicacion', 'emisor', 'aparato.marca'])
            ->where(function ($query) use ($q) {
                $query->where('linea', 'like', "%$q%")
                      ->orWhereHas('usuario', fn($q2) => $q2->where('nombre', 'like', "%$q%"));
            })
            ->orderBy('linea')
            ->limit(15)
            ->get();

        return response()->json($lineas->map(fn($l) => [
            'id'        => $l->id,
            'linea'     => $l->linea,
            'usuario'   => $l->usuario->nombre ?? '(sin asignar)',
            'empresa'   => $l->empresa->nombre ?? '',
            'ubicacion' => $l->ubicacion->nombre ?? '',
            'emisor'    => $l->emisor->nombre ?? '—',
            'equipo'    => trim(($l->aparato?->marca?->nombre ?? '') . ' ' . ($l->aparato?->modelo ?? '')) ?: '—',
        ]));
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

        $acta = ActaDevolucionTelefono::create([
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

        return redirect()->route('actas_devolucion_telefono.imprimir', $acta);
    }

    public function imprimir(ActaDevolucionTelefono $acta)
    {
        $acta->load('lineaTelefonica');

        $logoPath = Configuracion::get('app_logo');
        $appLogo  = $logoPath ? Storage::url($logoPath) : null;

        return view('actas_devolucion_telefono.imprimir', compact('acta', 'appLogo'));
    }

    public function destroy(ActaDevolucionTelefono $acta)
    {
        $this->authorize('admin');
        $acta->delete();
        return back()->with('success', 'Acta eliminada.');
    }
}
