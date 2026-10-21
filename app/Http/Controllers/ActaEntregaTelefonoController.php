<?php

namespace App\Http\Controllers;

use App\Models\ActaEntregaTelefono;
use App\Models\LineaTelefonica;
use App\Models\Equipo;
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

    /**
     * Búsqueda unificada para el modal: líneas (con o sin equipo) y
     * equipos del inventario que NO tienen línea (acta de "solo equipo").
     */
    public function buscarLineas(Request $request)
    {
        $q = trim($request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        // ── Líneas ──────────────────────────────────────────────────────────
        $lineas = LineaTelefonica::with([
                'usuario', 'empresa', 'ubicacion', 'emisor',
                'aparato.marca', 'equipo.aparato.marca', 'equipo.usuario',
            ])
            ->where(function ($query) use ($q) {
                $query->where('linea', 'like', "%$q%")
                      ->orWhereHas('usuario', fn($q2) => $q2->where('nombre', 'like', "%$q%"));
            })
            ->orderBy('linea')
            ->limit(12)
            ->get()
            ->map(function ($l) {
                // Aparato: primero el Equipo nuevo, luego el aparato legado
                $ap = $l->equipo?->aparato ?? $l->aparato;
                $tieneEquipo = (bool) ($l->equipo || $l->aparato);
                return [
                    'tipo'        => 'linea',
                    'target'      => route('actas_entrega_telefono.store', ['linea' => $l->id]),
                    'linea'       => $l->linea,
                    'usuario'     => $l->usuario->nombre ?? '(sin asignar)',
                    'empresa'     => $l->empresa->nombre ?? '',
                    'ubicacion'   => $l->ubicacion->nombre ?? '',
                    'emisor'      => $l->emisor->nombre ?? '—',
                    'equipo'      => $ap ? trim(($ap->marca->nombre ?? '') . ' ' . $ap->modelo) : '—',
                    'propiedad'   => $l->equipo?->propiedad ?? '',
                    'tiene_linea' => true,
                    'tiene_equipo'=> $tieneEquipo,
                ];
            });

        // ── Equipos sin línea ───────────────────────────────────────────────
        $equipos = Equipo::with(['aparato.marca', 'usuario', 'ubicacion'])
            ->whereDoesntHave('lineaTelefonica')
            ->where(function ($query) use ($q) {
                $query->where('imei', 'like', "%$q%")
                      ->orWhereHas('aparato',       fn($q2) => $q2->where('modelo', 'like', "%$q%"))
                      ->orWhereHas('aparato.marca', fn($q2) => $q2->where('nombre', 'like', "%$q%"))
                      ->orWhereHas('usuario',       fn($q2) => $q2->where('nombre', 'like', "%$q%"));
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function ($e) {
                return [
                    'tipo'        => 'equipo',
                    'target'      => route('actas_entrega_telefono.store_equipo', ['equipo' => $e->id]),
                    'linea'       => $e->imei ? ('IMEI ' . $e->imei) : ('Equipo #' . $e->id),
                    'usuario'     => $e->usuario->nombre ?? '(sin asignar)',
                    'empresa'     => '',
                    'ubicacion'   => $e->ubicacion->nombre ?? '',
                    'emisor'      => 'Sin línea',
                    'equipo'      => $e->modelo_completo,
                    'propiedad'   => $e->propiedad ?? '',
                    'tiene_linea' => false,
                    'tiene_equipo'=> true,
                ];
            });

        return response()->json($lineas->concat($equipos)->values());
    }

    /** Genera un acta a partir de una línea (que puede tener equipo enlazado). */
    public function store(Request $request, LineaTelefonica $linea)
    {
        $linea->load(['emisor', 'usuario', 'empresa', 'ubicacion', 'aparato.marca', 'equipo.aparato.marca', 'equipo.usuario']);

        $validated = $this->validarGeneracion($request);

        $receptor = $linea->usuario->nombre ?? null;
        if ($bloqueo = $this->bloqueoPorReceptorHoy($receptor)) {
            return $bloqueo;
        }

        $acta = ActaEntregaTelefono::create(
            $this->snapshot($linea, $linea->equipo, $validated)
        );

        return redirect()->route('actas_entrega_telefono.imprimir', $acta);
    }

    /** Genera un acta a partir de un equipo (con o sin línea). */
    public function storeEquipo(Request $request, Equipo $equipo)
    {
        $equipo->load(['aparato.marca', 'usuario', 'ubicacion', 'lineaTelefonica.emisor', 'lineaTelefonica.usuario', 'lineaTelefonica.empresa', 'lineaTelefonica.ubicacion']);

        $linea = $equipo->lineaTelefonica; // puede ser null (solo equipo)

        $validated = $this->validarGeneracion($request);

        $receptor = $linea?->usuario?->nombre ?? $equipo->usuario?->nombre;
        if ($bloqueo = $this->bloqueoPorReceptorHoy($receptor)) {
            return $bloqueo;
        }

        $acta = ActaEntregaTelefono::create(
            $this->snapshot($linea, $equipo, $validated)
        );

        return redirect()->route('actas_entrega_telefono.imprimir', $acta);
    }

    /** Reglas de validación del formulario de generación. */
    private function validarGeneracion(Request $request): array
    {
        return $request->validate([
            'tipo_acta'                       => 'required|in:equipo_sim,solo_sim,solo_equipo',
            'condicion'                       => 'nullable|in:Nuevo,Usado',
            'accesorios.cargador_usb'         => 'nullable|in:SI,NO',
            'accesorios.cargador_auto'        => 'nullable|in:SI,NO',
            'accesorios.manos_libres'         => 'nullable|in:SI,NO',
            'accesorios.cd_informacion'       => 'nullable|in:SI,NO',
            'documentacion.manual_propietario'=> 'nullable|in:SI,NO',
            'documentacion.procedimiento_uso' => 'nullable|in:SI,NO',
            'observacion'                     => 'nullable|string|max:500',
        ]);
    }

    /** Bloquea un segundo acta para el mismo receptor el mismo día. */
    private function bloqueoPorReceptorHoy(?string $receptor)
    {
        if (!$receptor) return null;

        $existe = ActaEntregaTelefono::where('nombre_receptor', $receptor)
            ->whereDate('fecha_emision', now()->toDateString())
            ->exists();

        if ($existe) {
            return back()->withErrors([
                'acta' => "Ya existe un acta de entrega generada hoy para {$receptor}. " .
                          "Edita la existente o genérala el día siguiente.",
            ]);
        }
        return null;
    }

    /**
     * Arma el snapshot del acta combinando línea y/o equipo.
     * Los datos físicos salen del Equipo nuevo; si no hay, caen al aparato legado.
     */
    private function snapshot(?LineaTelefonica $linea, ?Equipo $equipo, array $validated): array
    {
        $tipo        = $validated['tipo_acta'];
        $conEquipo   = $tipo === 'equipo_sim' || $tipo === 'solo_equipo'; // incluye equipo físico
        $conSim      = $tipo === 'equipo_sim' || $tipo === 'solo_sim';    // incluye línea/SIM

        // Aparato: Equipo nuevo primero, aparato legado como respaldo
        $aparato = $equipo?->aparato ?? $linea?->aparato;

        // Receptor y zona
        $receptor = $linea?->usuario?->nombre ?? $equipo?->usuario?->nombre;
        $empresa  = $linea?->empresa?->nombre;
        $ubic     = $linea?->ubicacion?->nombre ?? $equipo?->ubicacion?->nombre;
        $zona     = trim(($empresa ?? '') . ' - ' . ($ubic ?? ''), ' -');

        return [
            'id_linea_telefonica' => $linea?->id,
            'id_equipo'           => $equipo?->id,
            'tipo_acta'           => $tipo,
            'fecha_emision'       => now()->toDateString(),
            'numero_telefono'     => $conSim ? $linea?->linea : null,
            'nombre_receptor'     => $receptor,
            'zona'                => $zona ?: null,
            'marca'               => $conEquipo ? ($aparato?->marca?->nombre) : null,
            'modelo'              => $conEquipo ? ($aparato?->modelo) : null,
            'propiedad'           => $conEquipo ? ($equipo?->propiedad) : null,
            'compania'            => $conSim ? $linea?->emisor?->nombre : null,
            'imei_equipo'         => $conEquipo ? ($equipo?->imei ?? $linea?->imei_equipo) : null,
            'mac_wifi'            => $conEquipo ? $equipo?->mac_wifi : null,
            'imei_sim'            => $conSim ? $linea?->imei_sim : null,
            'condicion'           => $conEquipo ? ($validated['condicion'] ?? null) : null,
            'accesorios'          => $conEquipo ? ($validated['accesorios'] ?? []) : [],
            'documentacion'       => $conEquipo ? ($validated['documentacion'] ?? []) : [],
            'observacion'         => $validated['observacion'] ?? null,
            'impreso_por'         => auth()->user()->name,
        ];
    }

    public function imprimir(ActaEntregaTelefono $acta)
    {
        $acta->load('lineaTelefonica', 'equipo');

        $logoPath = Configuracion::get('app_logo');
        $appLogo  = $logoPath ? Storage::url($logoPath) : null;

        return view('actas_entrega_telefono.imprimir', compact('acta', 'appLogo'));
    }

    public function edit(ActaEntregaTelefono $acta)
    {
        if ($acta->bloqueadaParaEdicion()) {
            return redirect()->route('actas_entrega_telefono.index')
                ->with('error', 'El acta no puede editarse: fue emitida hace más de 2 días.');
        }
        return view('actas_entrega_telefono.edit', compact('acta'));
    }

    public function update(Request $request, ActaEntregaTelefono $acta)
    {
        if ($acta->bloqueadaParaEdicion()) {
            return redirect()->route('actas_entrega_telefono.index')
                ->with('error', 'El acta no puede editarse: fue emitida hace más de 2 días.');
        }

        $validated = $request->validate([
            'fecha_emision'                   => 'required|date',
            'numero_telefono'                 => 'nullable|string|max:50',
            'nombre_receptor'                 => 'nullable|string|max:150',
            'zona'                            => 'nullable|string|max:150',
            'marca'                           => 'nullable|string|max:100',
            'modelo'                          => 'nullable|string|max:100',
            'propiedad'                       => 'nullable|string|max:50',
            'compania'                        => 'nullable|string|max:100',
            'imei_equipo'                     => 'nullable|string|max:100',
            'mac_wifi'                        => 'nullable|string|max:40',
            'imei_sim'                        => 'nullable|string|max:100',
            'condicion'                       => 'nullable|in:Nuevo,Usado',
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
            'numero_telefono' => $validated['numero_telefono'] ?? null,
            'nombre_receptor' => $validated['nombre_receptor'] ?? null,
            'zona'            => $validated['zona'] ?? null,
            'marca'           => $validated['marca'] ?? null,
            'modelo'          => $validated['modelo'] ?? null,
            'propiedad'       => $validated['propiedad'] ?? null,
            'compania'        => $validated['compania'] ?? null,
            'imei_equipo'     => $validated['imei_equipo'] ?? null,
            'mac_wifi'        => $validated['mac_wifi'] ?? null,
            'imei_sim'        => $validated['imei_sim'] ?? null,
            'condicion'       => $validated['condicion'] ?? null,
            'accesorios'      => $validated['accesorios'] ?? [],
            'documentacion'   => $validated['documentacion'] ?? [],
            'observacion'     => $validated['observacion'] ?? null,
        ]);

        return redirect()->route('actas_entrega_telefono.index')
            ->with('success', 'Acta actualizada correctamente.');
    }

    public function destroy(ActaEntregaTelefono $acta)
    {
        $this->authorize('admin');
        $acta->delete();
        return back()->with('success', 'Acta eliminada.');
    }
}
