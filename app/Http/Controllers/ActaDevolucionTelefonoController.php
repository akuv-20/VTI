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

        // Bloqueo: no permitir un acta para el mismo usuario el mismo día
        $receptor = $linea->usuario->nombre ?? null;
        if ($receptor) {
            $existe = ActaDevolucionTelefono::where('nombre_receptor', $receptor)
                ->whereDate('fecha_emision', now()->toDateString())
                ->exists();
            if ($existe) {
                return back()->withErrors([
                    'acta' => "Ya existe un acta de devolución generada hoy para {$receptor}. " .
                              "Edita la existente o genérala el día siguiente.",
                ]);
            }
        }

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

    public function edit(ActaDevolucionTelefono $acta)
    {
        if ($acta->bloqueadaParaEdicion()) {
            return redirect()->route('actas_devolucion_telefono.index')
                ->with('error', 'El acta no puede editarse: fue emitida hace más de 2 días.');
        }
        return view('actas_devolucion_telefono.edit', compact('acta'));
    }

    public function update(Request $request, ActaDevolucionTelefono $acta)
    {
        if ($acta->bloqueadaParaEdicion()) {
            return redirect()->route('actas_devolucion_telefono.index')
                ->with('error', 'El acta no puede editarse: fue emitida hace más de 2 días.');
        }

        $validated = $request->validate([
            'fecha_emision'                   => 'required|date',
            'numero_telefono'                 => 'required|string|max:50',
            'nombre_receptor'                 => 'nullable|string|max:150',
            'zona'                            => 'nullable|string|max:150',
            'marca'                           => 'nullable|string|max:100',
            'modelo'                          => 'nullable|string|max:100',
            'compania'                        => 'nullable|string|max:100',
            'imei_equipo'                     => 'nullable|string|max:100',
            'imei_sim'                        => 'nullable|string|max:100',
            'condicion'                       => 'required|in:Nuevo,Usado',
            'accesorios.cargador_usb'         => 'nullable|in:SI,NO',
            'accesorios.cargador_auto'        => 'nullable|in:SI,NO',
            'accesorios.manos_libres'         => 'nullable|in:SI,NO',
            'accesorios.cd_informacion'       => 'nullable|in:SI,NO',
            'documentacion.manual_propietario'=> 'nullable|in:SI,NO',
            'documentacion.procedimiento_uso' => 'nullable|in:SI,NO',
            'observacion'                     => 'nullable|string|max:500',
        ]);

        $acta->update([
            'fecha_emision'   => $validated['fecha_emision'],
            'numero_telefono' => $validated['numero_telefono'],
            'nombre_receptor' => $validated['nombre_receptor'] ?? null,
            'zona'            => $validated['zona'] ?? null,
            'marca'           => $validated['marca'] ?? null,
            'modelo'          => $validated['modelo'] ?? null,
            'compania'        => $validated['compania'] ?? null,
            'imei_equipo'     => $validated['imei_equipo'] ?? null,
            'imei_sim'        => $validated['imei_sim'] ?? null,
            'condicion'       => $validated['condicion'],
            'accesorios'      => $validated['accesorios'] ?? [],
            'documentacion'   => $validated['documentacion'] ?? [],
            'observacion'     => $validated['observacion'] ?? null,
        ]);

        return redirect()->route('actas_devolucion_telefono.index')
            ->with('success', 'Acta actualizada correctamente.');
    }

    public function destroy(ActaDevolucionTelefono $acta)
    {
        $this->authorize('admin');
        $acta->delete();
        return back()->with('success', 'Acta eliminada.');
    }
}
