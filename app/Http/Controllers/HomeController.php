<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\Factura;
use Illuminate\Http\Request; // Asegúrate de importar Request
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request) // Recibir la instancia de Request
    {
        $servicios = Servicio::with(['familia', 'empresa', 'compania', 'facturas'])->where('es_periodico',true)->get();

        // Establecer el locale para Carbon para nombres de meses en español
        Carbon::setLocale('es');

        // Obtener el mes y año de la solicitud, o usar el mes y año actual por defecto
        $mesSeleccionado = $request->input('mes', Carbon::now()->month);
        $anioSeleccionado = $request->input('anio', Carbon::now()->year);

        // Crear un objeto Carbon para el mes y año seleccionados
        $fechaSeleccionada = Carbon::createFromDate($anioSeleccionado, $mesSeleccionado, 1);

        // --- Datos para el MES SELECCIONADO (que antes era 'Mes Actual') ---
        $mesParaTablaActual = $fechaSeleccionada->month;
        $anioParaTablaActual = $fechaSeleccionada->year;
        $serviciosConEstadoFacturaActual = $this->getServiciosConEstadoFactura($servicios, $mesParaTablaActual, $anioParaTablaActual);

        // --- Datos para el MES ANTERIOR al SELECCIONADO ---
        $fechaMesAnteriorAlSeleccionado = $fechaSeleccionada->copy()->subMonth();
        $mesParaTablaAnterior = $fechaMesAnteriorAlSeleccionado->month;
        $anioParaTablaAnterior = $fechaMesAnteriorAlSeleccionado->year;
        $serviciosConEstadoFacturaAnterior = $this->getServiciosConEstadoFactura($servicios, $mesParaTablaAnterior, $anioParaTablaAnterior);

        // Preparar datos para los selectores de filtro
        $mesesDisponibles = [];
        for ($i = 1; $i <= 12; $i++) {
            $mesesDisponibles[$i] = Carbon::create(null, $i, 1)->isoFormat('MMMM'); // Nombre del mes
        }

        // Obtener los años disponibles de las facturas existentes (o un rango razonable)
        // Podrías ajustar esto para obtener años desde tus servicios o un rango fijo
        $añosDisponibles = [];
        $añoInicio = 2020; // Año desde el que quieres mostrar opciones
        $añoFin = Carbon::now()->year + 1; // Año actual + 1 para futuras opciones
        for ($i = $añoFin; $i >= $añoInicio; $i--) {
            $añosDisponibles[] = $i;
        }


        return view('home', compact(
            'serviciosConEstadoFacturaActual',
            'mesParaTablaActual',
            'anioParaTablaActual',
            'serviciosConEstadoFacturaAnterior',
            'mesParaTablaAnterior',
            'anioParaTablaAnterior',
            'mesesDisponibles', // Para el filtro
            'añosDisponibles',  // Para el filtro
            'mesSeleccionado',  // Para mantener el valor seleccionado en el filtro
            'anioSeleccionado'  // Para mantener el valor seleccionado en el filtro
        ));
    }

    /**
     * Función auxiliar para obtener servicios con el estado de sus facturas para un mes y año dados.
     *
     * @param \Illuminate\Database\Eloquent\Collection $servicios Colección de todos los servicios.
     * @param int $mes El número del mes (1-12).
     * @param int $anio El año.
     * @return \Illuminate\Support\Collection Colección de servicios con su estado de factura.
     */
    private function getServiciosConEstadoFactura($servicios, $mes, $anio)
    {
        $serviciosData = [];
        foreach ($servicios as $servicio) {
            $facturaPendiente = true;

            $diaFacturacionEsperado = 1;
            if ($servicio->fecha_facturacion === '1 de cada Mes') {
                $diaFacturacionEsperado = 1;
            } elseif ($servicio->fecha_facturacion === '15 de cada Mes') {
                $diaFacturacionEsperado = 15;
            } elseif ($servicio->fecha_facturacion === '30 de cada Mes') {
                $diaFacturacionEsperado = 30;
            }

            $fechaEsperada = Carbon::create($anio, $mes, min($diaFacturacionEsperado, Carbon::createFromDate($anio, $mes)->daysInMonth));

            foreach ($servicio->facturas as $factura) {
                $fechaEmisionFactura = Carbon::parse($factura->fecha_emision);
                if ($fechaEmisionFactura->month === $mes && $fechaEmisionFactura->year === $anio) {
                    $facturaPendiente = false;
                    break;
                }
            }

            $serviciosData[] = [
                'servicio' => $servicio,
                'factura_pendiente' => $facturaPendiente,
                'mes' => $mes,
                'anio' => $anio,
                'dia_facturacion_esperado' => $diaFacturacionEsperado,
                'fecha_esperada_factura' => $fechaEsperada->format('d/m/Y'),
            ];
        }

        return Collection::make($serviciosData);
    }
}