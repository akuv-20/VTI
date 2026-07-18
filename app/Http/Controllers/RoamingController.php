<?php

namespace App\Http\Controllers;

use App\Models\Roaming;
use App\Models\LineaTelefonica;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RoamingController extends Controller
{
    /** Días de pasaporte Movistar válidos. */
    private const PASAPORTES = [1, 3, 7, 15, 21];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // ① Movistar — pasaportes (histórico, más recientes primero)
        $pasaportes = Roaming::with('lineaTelefonica')
            ->movistar()->pasaportes()
            ->orderByDesc('fecha_inicio')
            ->paginate(20, ['*'], 'pas')
            ->withQueryString();

        // ② Movistar — recurrentes activos
        $recurrentes = Roaming::with('lineaTelefonica')
            ->movistar()->recurrentes()->activos()
            ->orderByDesc('fecha_inicio')
            ->get();

        // ③ Entel — por uso (activos primero)
        $entel = Roaming::with('lineaTelefonica')
            ->entel()
            ->orderByRaw("estado = 'activo' desc")
            ->orderByDesc('fecha_inicio')
            ->paginate(20, ['*'], 'ent')
            ->withQueryString();

        return view('roamings.index', compact('pasaportes', 'recurrentes', 'entel'));
    }

    /** Búsqueda de líneas filtradas por carrier (movistar|entel). */
    public function buscarLineas(Request $request)
    {
        $q       = trim($request->input('q', ''));
        $carrier = $request->input('carrier', 'movistar');
        $emisor  = $carrier === 'entel' ? 'Entel' : 'Movistar';

        if ($q === '') {
            return response()->json([]);
        }

        $lineas = LineaTelefonica::with(['usuario', 'empresa', 'ubicacion', 'emisor'])
            ->whereHas('emisor', fn($e) => $e->where('nombre', 'like', "%{$emisor}%"))
            ->where(function ($query) use ($q) {
                $query->where('linea', 'like', "%$q%")
                      ->orWhereHas('usuario', fn($q2) => $q2->where('nombre', 'like', "%$q%"));
            })
            ->orderBy('linea')
            ->limit(15)
            ->get();

        return response()->json($lineas->map(fn($l) => [
            'id'                => $l->id,
            'linea'             => $l->linea,
            'usuario'           => $l->usuario->nombre ?? '(sin asignar)',
            'empresa'           => $l->empresa->nombre ?? '',
            'ubicacion'         => $l->ubicacion->nombre ?? '',
            'emisor'            => $l->emisor->nombre ?? '—',
            // ¿La línea ya tiene un recurrente activo? (bloquea agendar Movistar)
            'recurrente_activo' => Roaming::where('id_linea_telefonica', $l->id)
                                    ->recurrentes()->activos()->exists(),
        ]));
    }

    public function store(Request $request, LineaTelefonica $linea)
    {
        $linea->load(['usuario', 'emisor']);

        $validated = $request->validate([
            'carrier'        => 'required|in:movistar,entel',
            'tipo'           => 'required|in:pasaporte,recurrente,entel_uso',
            'pasaporte_dias' => 'nullable|integer|in:' . implode(',', self::PASAPORTES),
            'fecha_inicio'   => 'required|date',
            'destino'        => 'nullable|string|max:255',
            'id_solicitud'   => 'nullable|string|max:255',
            'observacion'    => 'nullable|string|max:500',
        ]);

        // Coherencia carrier ↔ emisor de la línea
        $emisorNombre = strtolower($linea->emisor->nombre ?? '');
        if (!str_contains($emisorNombre, $validated['carrier'])) {
            return back()->with('error', 'La línea seleccionada no pertenece al operador ' . ucfirst($validated['carrier']) . '.');
        }

        // Regla: si la línea tiene un recurrente activo, no se puede agendar (Movistar)
        if ($validated['carrier'] === 'movistar') {
            $tieneRecurrente = Roaming::where('id_linea_telefonica', $linea->id)
                ->recurrentes()->activos()->exists();
            if ($tieneRecurrente) {
                return back()->with('error', 'La línea tiene un roaming recurrente activo. Desactívalo antes de agendar un nuevo roaming.');
            }
        }

        $inicio  = Carbon::parse($validated['fecha_inicio']);
        $esPasaporte = $validated['carrier'] === 'movistar' && $validated['tipo'] === 'pasaporte';

        if ($esPasaporte && empty($validated['pasaporte_dias'])) {
            return back()->with('error', 'Debes seleccionar los días del pasaporte.');
        }

        $termino = $esPasaporte
            ? Roaming::calcularTermino($inicio, (int) $validated['pasaporte_dias'])
            : null;

        Roaming::create([
            'id_linea_telefonica' => $linea->id,
            'numero'              => $linea->linea,
            'nombre_usuario'      => $linea->usuario->nombre ?? null,
            'carrier'             => $validated['carrier'],
            'tipo'                => $validated['tipo'],
            'pasaporte_dias'      => $esPasaporte ? (int) $validated['pasaporte_dias'] : null,
            'fecha_inicio'        => $inicio,
            'fecha_termino'       => $termino,
            'destino'             => $validated['destino'] ?? null,
            'id_solicitud'        => $validated['id_solicitud'] ?? null,
            'estado'              => 'activo',
            'observacion'         => $validated['observacion'] ?? null,
        ]);

        return redirect()->route('roamings.index')->with('success', 'Roaming agendado correctamente.');
    }

    /** Cierra/desactiva un roaming (recurrente Movistar o activación Entel). */
    public function cerrar(Roaming $roaming)
    {
        $roaming->update(['estado' => 'cerrado']);
        return back()->with('success', 'Roaming desactivado.');
    }

    /** Archiva un pasaporte (lo saca de la vista principal sin borrarlo). */
    public function archivar(Roaming $roaming)
    {
        $roaming->update(['estado' => 'archivado']);
        return back()->with('success', 'Roaming archivado.');
    }

    public function destroy(Roaming $roaming)
    {
        $this->authorize('admin');
        $roaming->delete();
        return back()->with('success', 'Roaming eliminado.');
    }
}
