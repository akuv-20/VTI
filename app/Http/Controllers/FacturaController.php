<?php

namespace App\Http\Controllers;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\Factura;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FacturaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Factura::with([
            'servicio.empresa', 'servicio.compania', 'servicio.cuentaContable',
            'cuentaContable',
        ]);

        // Filtro tipo
        if ($request->filled('tipo') && $request->input('tipo') !== 'Todas') {
            $query->where('tipo', $request->input('tipo'));
        }

        // Filtro período
        if ($request->filled('anio')) {
            $query->whereYear('fecha_emision', $request->input('anio'));
            if ($request->filled('mes')) {
                $query->whereMonth('fecha_emision', $request->input('mes'));
            }
        }

        // Filtro cuenta contable
        if ($request->filled('cuenta_contable')) {
            $idCC = $request->input('cuenta_contable');
            $query->where(function ($q) use ($idCC) {
                $q->where('id_cuenta_contable', $idCC)
                  ->orWhereHas('servicio', fn($q2) => $q2->where('id_cuenta_contable', $idCC));
            });
        }

        // Búsqueda texto
        if ($request->filled('buscar')) {
            $b = $request->input('buscar');
            $query->where(function ($q) use ($b) {
                $q->where('factura',    'like', "%$b%")
                  ->orWhere('proveedor','like', "%$b%")
                  ->orWhere('descripcion','like', "%$b%")
                  ->orWhereHas('servicio.compania', fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('servicio.empresa',  fn($q2) => $q2->where('nombre', 'like', "%$b%"));
            });
        }

        // Filtro por servicio
        if ($request->filled('id_servicio')) {
            $query->where('id_servicio', $request->input('id_servicio'));
        }

        // Totales del filtro actual (antes de paginar)
        $totalNeto = (clone $query)->sum('valor_neto');
        $totalIva  = (clone $query)->sum('valor_iva');

        $facturas         = $query->orderBy('fecha_emision', 'desc')->paginate(25)->withQueryString();
        $cuentasContables = CuentaContable::orderBy('numero_cuenta')->get();
        $servicios        = Servicio::with(['empresa', 'compania'])->orderBy('codigo_servicio')->get();

        // Años disponibles para el filtro
        $aniosDisponibles = Factura::selectRaw('YEAR(fecha_emision) as anio')
            ->groupBy('anio')->orderByDesc('anio')->pluck('anio');

        $meses = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return view('facturas.index', compact(
            'facturas', 'cuentasContables', 'servicios', 'aniosDisponibles', 'meses',
            'totalNeto', 'totalIva'
        ));
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function create()
    {
        $servicios        = Servicio::with(['empresa', 'compania'])->orderBy('codigo_servicio')->get();
        $cuentasContables = CuentaContable::orderBy('numero_cuenta')->get();
        $companias        = Compania::orderBy('nombre')->get();
        return view('facturas.create', compact('servicios', 'cuentasContables', 'companias'));
    }

    public function store(Request $request)
    {
        $tipo = $request->input('tipo', 'Mensual');

        // Quitar separadores de miles antes de validar (ej: "1.899.975" → "1899975")
        $request->merge([
            'valor_neto' => preg_replace('/\D/', '', $request->input('valor_neto', '')),
            'valor_iva'  => preg_replace('/\D/', '', $request->input('valor_iva',  '')),
        ]);

        $rules = [
            'tipo'          => 'required|in:Mensual,Esporádica',
            'factura'       => 'required|string|max:100',
            'oc'            => 'nullable|string|max:100',
            'valor_neto'    => 'required|numeric|min:0',
            'valor_iva'     => 'required|numeric|min:0',
            'fecha_emision' => 'required|date',
            'descripcion'   => 'nullable|string|max:500',
        ];

        if ($tipo === 'Mensual') {
            $rules['id_servicio'] = 'required|exists:servicios,id';
        } else {
            $rules['id_cuenta_contable'] = 'required|exists:cuentas_contables,id';
            $rules['proveedor']          = 'required|string|max:150';
        }

        $validated = $request->validate($rules, [
            'id_servicio.required'        => 'Debes seleccionar un servicio.',
            'id_cuenta_contable.required' => 'Debes seleccionar una cuenta contable.',
            'proveedor.required'          => 'El proveedor es obligatorio para facturas esporádicas.',
        ]);

        // Limpiar campo no aplicable
        if ($tipo === 'Mensual') {
            $validated['id_cuenta_contable'] = null;
            $validated['proveedor']          = null;
        } else {
            $validated['id_servicio'] = null;
        }

        Factura::create($validated);

        return redirect()->route('facturas.index')->with('success', 'Factura registrada exitosamente.');
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(Factura $factura)
    {
        $servicios        = Servicio::with(['empresa', 'compania'])->orderBy('codigo_servicio')->get();
        $cuentasContables = CuentaContable::orderBy('numero_cuenta')->get();
        $companias        = Compania::orderBy('nombre')->get();
        return view('facturas.edit', compact('factura', 'servicios', 'cuentasContables', 'companias'));
    }

    public function update(Request $request, Factura $factura)
    {
        $tipo = $request->input('tipo', $factura->tipo);

        // Quitar separadores de miles antes de validar (ej: "1.899.975" → "1899975")
        $request->merge([
            'valor_neto' => preg_replace('/\D/', '', $request->input('valor_neto', '')),
            'valor_iva'  => preg_replace('/\D/', '', $request->input('valor_iva',  '')),
        ]);

        $rules = [
            'tipo'          => 'required|in:Mensual,Esporádica',
            'factura'       => 'required|string|max:100',
            'oc'            => 'nullable|string|max:100',
            'valor_neto'    => 'required|numeric|min:0',
            'valor_iva'     => 'required|numeric|min:0',
            'fecha_emision' => 'required|date',
            'descripcion'   => 'nullable|string|max:500',
        ];

        if ($tipo === 'Mensual') {
            $rules['id_servicio'] = 'required|exists:servicios,id';
        } else {
            $rules['id_cuenta_contable'] = 'required|exists:cuentas_contables,id';
            $rules['proveedor']          = 'required|string|max:150';
        }

        $validated = $request->validate($rules);

        if ($tipo === 'Mensual') {
            $validated['id_cuenta_contable'] = null;
            $validated['proveedor']          = null;
        } else {
            $validated['id_servicio'] = null;
        }

        $factura->update($validated);

        return redirect()->route('facturas.index')->with('success', 'Factura actualizada exitosamente.');
    }

    public function destroy(Factura $factura)
    {
        $factura->delete();
        return redirect()->route('facturas.index')->with('success', 'Factura eliminada exitosamente.');
    }

    // ── Pendientes ────────────────────────────────────────────────────────────

    public function pendientes(Request $request)
    {
        Carbon::setLocale('es');

        $servicios = Servicio::with(['familia', 'empresa', 'compania', 'facturas', 'cuentaContable'])
            ->where('es_periodico', true)
            ->get();

        $mesSeleccionado  = $request->input('mes',  Carbon::now()->month);
        $anioSeleccionado = $request->input('anio', Carbon::now()->year);

        $fechaSeleccionada = Carbon::createFromDate($anioSeleccionado, $mesSeleccionado, 1);

        $mesActual  = $fechaSeleccionada->month;
        $anioActual = $fechaSeleccionada->year;
        $serviciosActual = $this->getServiciosConEstado($servicios, $mesActual, $anioActual);

        $fechaAnterior     = $fechaSeleccionada->copy()->subMonth();
        $mesAnterior       = $fechaAnterior->month;
        $anioAnterior      = $fechaAnterior->year;
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

    // ── Resumen por cuenta contable ───────────────────────────────────────────

    public function resumen(Request $request)
    {
        $anio = $request->integer('anio', Carbon::now()->year);

        $aniosDisponibles = Factura::selectRaw('YEAR(fecha_emision) as anio')
            ->groupBy('anio')->orderByDesc('anio')->pluck('anio');

        // Traer todas las facturas del año con sus relaciones
        $facturas = Factura::with([
            'cuentaContable',
            'servicio.cuentaContable',
        ])->whereYear('fecha_emision', $anio)->get();

        $meses = ['', 'Ene','Feb','Mar','Abr','May','Jun',
                       'Jul','Ago','Sep','Oct','Nov','Dic'];

        // Construir matriz: CC → mes → total neto
        $matriz  = [];   // [id_cc => [mes => total]]
        $ccs     = [];   // [id_cc => CuentaContable]
        $totalesCol = array_fill(1, 12, 0.0);  // total por mes (todas las CC)

        foreach ($facturas as $f) {
            $cc = $f->cuenta_contable_efectiva;
            if (!$cc) continue;

            $id  = $cc->id;
            $mes = $f->fecha_emision->month;

            $ccs[$id] = $cc;
            $matriz[$id][$mes] = ($matriz[$id][$mes] ?? 0) + $f->valor_neto;
            $totalesCol[$mes]  = ($totalesCol[$mes] ?? 0) + $f->valor_neto;
        }

        // Ordenar cuentas contables por numero_cuenta
        uasort($ccs, fn($a, $b) => strcmp($a->numero_cuenta, $b->numero_cuenta));

        return view('facturas.resumen', compact(
            'matriz', 'ccs', 'meses', 'anio', 'aniosDisponibles', 'totalesCol'
        ));
    }
}
