<?php

namespace App\Http\Controllers;

use App\Models\ImportacionWom;
use App\Models\ImportacionWomDetalle;
use App\Models\LineaTelefonica;
use App\Models\WomPlantilla;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ImportacionWomController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index()
    {
        $importaciones = ImportacionWom::orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->orderByDesc('id')
            ->paginate(20);

        return view('importaciones_wom.index', compact('importaciones'));
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function create()
    {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $anioActual = Carbon::now()->year;
        $mesActual  = Carbon::now()->month;

        return view('importaciones_wom.create', compact('meses', 'anioActual', 'mesActual'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'factura'       => 'required|string|max:100',
            'periodo_mes'   => 'required|integer|between:1,12',
            'periodo_anio'  => 'required|integer|min:2000',
            'fecha_emision' => 'nullable|date',
            'observacion'   => 'nullable|string|max:500',
            'lineas'        => 'required|array|min:1',
            'lineas.*.id'   => 'required|exists:lineas_telefonicas,id',
            'lineas.*.monto'=> 'required|numeric|min:0',
        ], [
            'lineas.required' => 'Debes agregar al menos una línea.',
            'lineas.min'      => 'Debes agregar al menos una línea.',
        ]);

        // Normalizar montos (quitar puntos de miles si vienen como string)
        $lineasData = collect($request->input('lineas'))->map(function ($item) {
            return [
                'id'    => $item['id'],
                'monto' => (float) preg_replace('/[^\d.]/', '', $item['monto']),
            ];
        });

        $importacion = ImportacionWom::create([
            'factura'       => $request->input('factura'),
            'periodo_mes'   => $request->input('periodo_mes'),
            'periodo_anio'  => $request->input('periodo_anio'),
            'fecha_emision' => $request->input('fecha_emision') ?: null,
            'observacion'   => $request->input('observacion') ?: null,
            'total_lineas'  => $lineasData->count(),
        ]);

        $detalles = $lineasData->map(fn($l) => [
            'id_importacion'      => $importacion->id,
            'id_linea_telefonica' => $l['id'],
            'monto'               => $l['monto'],
            'created_at'          => now(),
            'updated_at'          => now(),
        ])->values()->all();

        ImportacionWomDetalle::insert($detalles);

        return redirect()->route('importaciones_wom.show', $importacion)
            ->with('success', 'Importación WOM registrada exitosamente.');
    }

    // ── Detalle / Resumen ─────────────────────────────────────────────────────

    public function show(ImportacionWom $importaciones_wom)
    {
        return $this->mostrarResumen($importaciones_wom, 'importaciones_wom.show');
    }

    public function imprimir(ImportacionWom $importaciones_wom)
    {
        return $this->mostrarResumen($importaciones_wom, 'importaciones_wom.print');
    }

    private function mostrarResumen(ImportacionWom $importacion, string $view)
    {
        $detalles = $importacion->detalles()->with([
            'lineaTelefonica.empresa',
            'lineaTelefonica.centroCosto',
            'lineaTelefonica.ubicacion',
            'lineaTelefonica.usuario',
        ])->get();

        // Agrupar: empresa → CC → ubicación → usuario → suma montos
        $agrupado = [];
        $totalGeneral = 0;

        foreach ($detalles as $d) {
            $linea   = $d->lineaTelefonica;
            $empresa = $linea->empresa->nombre   ?? '—';
            $cc      = $linea->centroCosto->ccosto ?? '—';
            $ubi     = $linea->ubicacion->nombre ?? '—';
            $usuario = $linea->usuario->nombre   ?? '—';

            $agrupado[$empresa][$cc][$ubi][$usuario] =
                ($agrupado[$empresa][$cc][$ubi][$usuario] ?? 0) + $d->monto;

            $totalGeneral += $d->monto;
        }

        // Ordenar cada nivel alfabéticamente
        ksort($agrupado);
        foreach ($agrupado as &$ccs) {
            ksort($ccs);
            foreach ($ccs as &$ubis) {
                ksort($ubis);
                foreach ($ubis as &$usuarios) {
                    // ya es un escalar (monto), no hace falta ordenar
                }
            }
        }

        return view($view, compact('importacion', 'agrupado', 'totalGeneral'));
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function destroy(ImportacionWom $importaciones_wom)
    {
        $importaciones_wom->delete();
        return redirect()->route('importaciones_wom.index')
            ->with('success', 'Importación WOM eliminada.');
    }

    // ── AJAX buscar líneas WOM ────────────────────────────────────────────────

    public function buscarLineas(Request $request)
    {
        $q = $request->input('q', '');

        $lineas = LineaTelefonica::with(['usuario', 'empresa', 'centroCosto', 'ubicacion', 'emisor'])
            ->whereHas('emisor', fn($query) => $query->where('nombre', 'like', '%WOM%'))
            ->where(function ($query) use ($q) {
                $query->where('linea', 'like', "%$q%")
                      ->orWhereHas('usuario',  fn($q2) => $q2->where('nombre', 'like', "%$q%"))
                      ->orWhereHas('empresa',  fn($q2) => $q2->where('nombre', 'like', "%$q%"))
                      ->orWhereHas('ubicacion',fn($q2) => $q2->where('nombre', 'like', "%$q%"));
            })
            ->limit(15)
            ->get();

        return response()->json($lineas->map(fn($l) => [
            'id'          => $l->id,
            'linea'       => $l->linea,
            'usuario'     => $l->usuario->nombre    ?? '—',
            'empresa'     => $l->empresa->nombre     ?? '—',
            'cc'          => $l->centroCosto->ccosto ?? '—',
            'ubicacion'   => $l->ubicacion->nombre   ?? '—',
        ]));
    }

    // ── Plantilla ─────────────────────────────────────────────────────────────

    public function plantilla()
    {
        $lineasPlantilla = WomPlantilla::with([
            'lineaTelefonica.empresa',
            'lineaTelefonica.centroCosto',
            'lineaTelefonica.ubicacion',
            'lineaTelefonica.usuario',
            'lineaTelefonica.emisor',
        ])->get()->sortBy(fn($p) => $p->lineaTelefonica->empresa->nombre ?? '');

        return view('importaciones_wom.plantilla', compact('lineasPlantilla'));
    }

    public function plantillaAgregar(Request $request)
    {
        $request->validate(['id_linea_telefonica' => 'required|exists:lineas_telefonicas,id']);

        $registro = WomPlantilla::firstOrCreate([
            'id_linea_telefonica' => $request->input('id_linea_telefonica'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $registro->id]);
        }
        return back()->with('success', 'Línea agregada a la plantilla.');
    }

    public function plantillaQuitar(WomPlantilla $plantilla)
    {
        $plantilla->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Línea eliminada de la plantilla.');
    }

    public function plantillaActualizarMonto(Request $request, WomPlantilla $plantilla)
    {
        $request->validate(['monto' => 'required|numeric|min:0']);
        $plantilla->update(['monto' => $request->input('monto')]);
        return response()->json(['ok' => true]);
    }

    /** AJAX: devuelve todas las líneas de la plantilla para pre-cargar en el create */
    public function plantillaLineas()
    {
        $lineas = WomPlantilla::with([
            'lineaTelefonica.empresa',
            'lineaTelefonica.centroCosto',
            'lineaTelefonica.ubicacion',
            'lineaTelefonica.usuario',
        ])->get();

        return response()->json($lineas->map(fn($p) => [
            'id'       => $p->lineaTelefonica->id,
            'linea'    => $p->lineaTelefonica->linea,
            'usuario'  => $p->lineaTelefonica->usuario->nombre    ?? '—',
            'empresa'  => $p->lineaTelefonica->empresa->nombre     ?? '—',
            'cc'       => $p->lineaTelefonica->centroCosto->ccosto ?? '—',
            'ubicacion'=> $p->lineaTelefonica->ubicacion->nombre   ?? '—',
            'monto'    => (float) $p->monto,
        ]));
    }
}
