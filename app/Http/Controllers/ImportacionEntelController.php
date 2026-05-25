<?php

namespace App\Http\Controllers;

use App\Models\ImportacionEntel;
use App\Models\ImportacionEntelDetalle;
use App\Models\LineaTelefonica;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportacionEntelController extends Controller
{
    // Códigos de servicio Entel
    const CODIGO_MOVIL = '1.17882753';
    const CODIGO_BAM   = '1.10290392';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $importaciones = ImportacionEntel::withSum('detalles as total_monto', 'monto')
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->orderByDesc('id')
            ->paginate(20);

        return view('importaciones_entel.index', compact('importaciones'));
    }

    public function create()
    {
        return view('importaciones_entel.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'archivo'       => 'required|file|mimes:xls,xlsx,application/vnd.ms-excel|max:20480',
            'periodo_anio'  => 'required|integer|min:2020|max:2099',
            'periodo_mes'   => 'required|integer|min:1|max:12',
        ]);

        $archivo = $request->file('archivo');
        $nombreArchivo = $archivo->getClientOriginalName();

        // Extraer folio del nombre: resumen_FOLIO.xls
        if (!preg_match('/resumen_(\d+)\.xls/i', $nombreArchivo, $m)) {
            return back()->withErrors(['archivo' => 'El nombre del archivo debe tener formato resumen_XXXXXXXX.xls']);
        }
        $folio = $m[1];

        // Cargar SOLO la hoja necesaria para evitar memory exhausted
        ini_set('memory_limit', '512M');
        $reader = IOFactory::createReaderForFile($archivo->getRealPath());
        $reader->setLoadSheetsOnly(['Detalle Cobros por Movil']);
        $spreadsheet = $reader->load($archivo->getRealPath());

        $shDetalle = $spreadsheet->getSheetByName('Detalle Cobros por Movil');
        if (!$shDetalle) {
            return back()->withErrors(['archivo' => 'No se encontró la hoja "Detalle Cobros por Movil"']);
        }

        $rows = $shDetalle->toArray(null, true, true, false);
        // Detectar código servicio de la primera fila de datos (fila 1 = headers, fila 2 = primer dato)
        $codigoServicio = null;
        foreach (array_slice($rows, 1) as $row) {
            $cuenta = trim((string) ($row[0] ?? ''));
            if ($cuenta && $cuenta !== 'Cuenta' && is_numeric(str_replace('.', '', $cuenta))) {
                $codigoServicio = $cuenta;
                break;
            }
        }

        if (!$codigoServicio) {
            return back()->withErrors(['archivo' => 'No se pudo detectar el código de servicio en el archivo.']);
        }

        $tipoServicio = match(true) {
            str_contains($codigoServicio, self::CODIGO_MOVIL) => 'Movil',
            str_contains($codigoServicio, self::CODIGO_BAM)   => 'BAM',
            default => null,
        };

        if (!$tipoServicio) {
            return back()->withErrors(['archivo' => "Código de servicio no reconocido: {$codigoServicio}. Se esperaba " . self::CODIGO_MOVIL . " (Móvil) o " . self::CODIGO_BAM . " (BAM)"]);
        }

        // Verificar si ya existe esta combinación folio + tipo
        if (ImportacionEntel::where('folio', $folio)->where('tipo_servicio', $tipoServicio)->exists()) {
            return back()->withErrors(['archivo' => "Ya existe una importación {$tipoServicio} con el folio {$folio}."]);
        }

        $periodoMes  = (int) $request->periodo_mes;
        $periodoAnio = (int) $request->periodo_anio;
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $periodoCobro = $meses[$periodoMes] . ' ' . $periodoAnio;

        // Crear la importación
        $importacion = ImportacionEntel::create([
            'folio'           => $folio,
            'tipo_servicio'   => $tipoServicio,
            'codigo_servicio' => $codigoServicio,
            'periodo_cobro'   => $periodoCobro,
            'periodo_anio'    => $periodoAnio,
            'periodo_mes'     => $periodoMes,
            'archivo_nombre'  => $nombreArchivo,
            'total_lineas'    => 0,
        ]);

        // Procesar detalles — cada línea tiene una sola fila en Entel
        $detalles = [];
        foreach (array_slice($rows, 1) as $row) {
            $numero = trim((string) ($row[1] ?? ''));

            // Saltar filas sin número válido o con valor 0 (totales/resumen)
            if (!$numero || $numero === '0' || $numero === 'Movil' || !is_numeric($numero)) continue;

            $monto = is_numeric($row[9] ?? null) ? (float) $row[9] : 0;

            // Normalizar número: quitar prefijo 56
            $numeroNormalizado = preg_match('/^56(\d{9})$/', $numero, $nm) ? $nm[1] : $numero;
            $linea = LineaTelefonica::where('linea', $numeroNormalizado)->first()
                  ?? LineaTelefonica::where('linea', $numero)->first();

            $detalles[] = [
                'id_importacion'      => $importacion->id,
                'numero_servicio'     => $numero,
                'plan_tarifario'      => substr(trim((string) ($row[2] ?? '')), 0, 255) ?: null,
                'monto'               => $monto,
                'id_linea_telefonica' => $linea?->id,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }

        foreach (array_chunk($detalles, 500) as $chunk) {
            ImportacionEntelDetalle::insert($chunk);
        }

        $importacion->update(['total_lineas' => count($detalles)]);

        return redirect()->route('importaciones_entel.show', $importacion)
            ->with('success', "Importación Entel {$tipoServicio} procesada: " . count($detalles) . " líneas.");
    }

    public function show(ImportacionEntel $importaciones_entel)
    {
        $importacion = $importaciones_entel->load('detalles.lineaTelefonica.usuario', 'detalles.lineaTelefonica.empresa');
        $enSistema   = $importacion->detalles->whereNotNull('id_linea_telefonica')->count();
        $sinSistema  = $importacion->detalles->whereNull('id_linea_telefonica')->count();

        // IDs de líneas que ya están cruzadas en esta importación
        $idsEnImportacion = $importacion->detalles
            ->whereNotNull('id_linea_telefonica')
            ->pluck('id_linea_telefonica');

        // IDs de líneas que alguna vez aparecieron en importaciones del mismo tipo (Movil o BAM)
        // Así no mezclamos líneas Móvil con BAM ni viceversa
        $idsConHistorial = ImportacionEntelDetalle::whereHas(
                'importacion', fn($q) => $q->where('tipo_servicio', $importacion->tipo_servicio)
            )
            ->whereNotNull('id_linea_telefonica')
            ->pluck('id_linea_telefonica')
            ->unique();

        // Líneas Entel del mismo tipo que NO aparecen en esta importación
        // Se muestran activas e inactivas para detectar casos donde una línea
        // inactiva sigue siendo facturada o una activa dejó de aparecer
        $lineasSinImportar = LineaTelefonica::with(['usuario', 'empresa'])
            ->whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%Entel%'))
            ->whereIn('id', $idsConHistorial)
            ->whereNotIn('id', $idsEnImportacion)
            ->orderByRaw("FIELD(estado, 'Activo', 'Inactivo')")
            ->orderBy('linea')
            ->get();

        // Líneas inactivas en el sistema que SÍ aparecen en esta importación
        // (se las siguen cobrando aunque estén dadas de baja)
        $lineasInactivasFacturadas = $importacion->detalles
            ->whereNotNull('id_linea_telefonica')
            ->filter(fn($d) => $d->lineaTelefonica?->estado === 'Inactivo');

        return view('importaciones_entel.show', compact(
            'importacion', 'enSistema', 'sinSistema', 'lineasSinImportar', 'lineasInactivasFacturadas'
        ));
    }

    public function recruzar(ImportacionEntel $importaciones_entel)
    {
        foreach ($importaciones_entel->detalles()->get() as $detalle) {
            $numeroNormalizado = preg_match('/^56(\d{9})$/', $detalle->numero_servicio, $nm)
                ? $nm[1] : $detalle->numero_servicio;

            $linea = LineaTelefonica::where('linea', $numeroNormalizado)->first()
                  ?? LineaTelefonica::where('linea', $detalle->numero_servicio)->first();

            $detalle->update(['id_linea_telefonica' => $linea?->id]);
        }

        return redirect()->route('importaciones_entel.show', $importaciones_entel)
            ->with('success', 'Cruce re-procesado correctamente.');
    }

    public function destroy(ImportacionEntel $importaciones_entel)
    {
        $importaciones_entel->delete();
        return redirect()->route('importaciones_entel.index')
            ->with('success', 'Importación eliminada correctamente.');
    }
}
