<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\Factura;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FacturaController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Factura::with('servicio');

        if ($request->filled('buscar')) {
            $busqueda = $request->input('buscar');
            $query->where(function ($q) use ($busqueda) {
                $q->where('factura', 'like', "%$busqueda%")
                    ->orWhere('descripcion', 'like', "%$busqueda%")
                    ->orWhereHas('servicio.compania', function ($q2) use ($busqueda) {
                    $q2->where('nombre', 'like', "%$busqueda%");
                }
                );
            });
        }

        $facturas = $query->orderBy('fecha_emision', 'desc')->paginate(20);
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
            'oc' => 'nullable|string',
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
            'oc' => 'nullable|string',
            'valor_neto' => 'required|numeric|min:0',
            'valor_iva' => 'required|numeric|min:0',
            'fecha_emision' => 'required|date',
            'descripcion' => 'nullable|string',
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

    public function pendientes(Request $request)
    {
        Carbon::setLocale('es');

        $servicios = Servicio::with(['familia', 'empresa', 'compania', 'facturas', 'cuentacontable'])
            ->where('es_periodico', true)
            ->get();

        $mesSeleccionado  = $request->input('mes',  Carbon::now()->month);
        $anioSeleccionado = $request->input('anio', Carbon::now()->year);

        $fechaSeleccionada = Carbon::createFromDate($anioSeleccionado, $mesSeleccionado, 1);

        $mesActual  = $fechaSeleccionada->month;
        $anioActual = $fechaSeleccionada->year;
        $serviciosActual = $this->getServiciosConEstado($servicios, $mesActual, $anioActual);

        $fechaAnterior  = $fechaSeleccionada->copy()->subMonth();
        $mesAnterior    = $fechaAnterior->month;
        $anioAnterior   = $fechaAnterior->year;
        $serviciosAnterior = $this->getServiciosConEstado($servicios, $mesAnterior, $anioAnterior);

        $mesesDisponibles = [];
        for ($i = 1; $i <= 12; $i++) {
            $mesesDisponibles[$i] = Carbon::create(null, $i, 1)->isoFormat('MMMM');
        }

        $añosDisponibles = [];
        for ($i = Carbon::now()->year + 1; $i >= 2020; $i--) {
            $añosDisponibles[] = $i;
        }

        return view('facturas.pendientes', compact(
            'serviciosActual', 'mesActual', 'anioActual',
            'serviciosAnterior', 'mesAnterior', 'anioAnterior',
            'mesesDisponibles', 'añosDisponibles',
            'mesSeleccionado', 'anioSeleccionado'
        ));
    }

    private function getServiciosConEstado($servicios, $mes, $anio): Collection
    {
        $data = [];
        foreach ($servicios as $servicio) {
            $dia = match($servicio->fecha_facturacion) {
                '15 de cada Mes' => 15,
                '30 de cada Mes' => 30,
                default          => 1,
            };
            $fechaEsperada = Carbon::create($anio, $mes, min($dia, Carbon::createFromDate($anio, $mes)->daysInMonth));

            $facturaPendiente = true;
            $facturaDelMes    = null;
            foreach ($servicio->facturas as $factura) {
                $fe = Carbon::parse($factura->fecha_emision);
                if ($fe->month === $mes && $fe->year === $anio) {
                    $facturaPendiente = false;
                    $facturaDelMes    = $factura;
                    break;
                }
            }

            $data[] = [
                'servicio'               => $servicio,
                'factura_pendiente'      => $facturaPendiente,
                'factura'                => $facturaDelMes,
                'fecha_esperada_factura' => $fechaEsperada->format('d/m/Y'),
            ];
        }
        return Collection::make($data);
    }
}
