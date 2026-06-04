<?php

namespace App\Http\Controllers;

use App\Models\ImportacionMovistar;
use App\Models\ImportacionMovistarDetalle;
use App\Models\LineaTelefonica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class ImportacionMovistarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $importaciones = ImportacionMovistar::withSum('detalles as total_monto', 'monto')
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->orderByDesc('id')
            ->paginate(20);

        return view('importaciones_movistar.index', compact('importaciones'));
    }

    public function create()
    {
        return view('importaciones_movistar.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:zip|max:20480',
        ]);

        // Extraer ZIP y obtener el .xlsx
        $zip = new ZipArchive();
        $zipPath = $request->file('archivo')->getRealPath();
        $originalName = $request->file('archivo')->getClientOriginalName();

        if ($zip->open($zipPath) !== true) {
            return back()->withErrors(['archivo' => 'No se pudo abrir el archivo ZIP.']);
        }

        $xlsxName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.xlsx')) {
                $xlsxName = $name;
                break;
            }
        }

        if (!$xlsxName) {
            $zip->close();
            return back()->withErrors(['archivo' => 'El ZIP no contiene ningún archivo .xlsx.']);
        }

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'movistar_') . '.xlsx';
        file_put_contents($tmpXlsx, $zip->getFromName($xlsxName));
        $zip->close();

        // Detectar tipo de servicio desde el nombre del xlsx
        $codigoServicio = explode('_', basename($xlsxName))[0];
        $tipoServicio = $codigoServicio === '11502573' ? 'BAM' : 'Movil';

        try {
            $spreadsheet = IOFactory::load($tmpXlsx);
            $sheet = $spreadsheet->getSheetByName('BASE');

            if (!$sheet) {
                return back()->withErrors(['archivo' => 'No se encontró la hoja BASE en el Excel.']);
            }

            $rows = $sheet->toArray(null, true, true, false);

            if (count($rows) < 2) {
                return back()->withErrors(['archivo' => 'El archivo no contiene datos.']);
            }

            // Leer metadatos de la primera fila de datos (fila 2, índice 1)
            $meta = $rows[1];
            $folio          = (string) ($meta[4] ?? '');
            $fechaEmisionRaw = (string) ($meta[5] ?? '');
            $periodoCobro   = (string) ($meta[7] ?? '');

            // Parsear fecha dd-mm-yyyy
            $fechaEmision = \Carbon\Carbon::createFromFormat('d-m-Y', $fechaEmisionRaw)->toDateString();

            // Extraer año y mes del periodo: "01/04/26 hasta 30/04/26"
            preg_match('/(\d{2})\/(\d{2})\/(\d{2})/', $periodoCobro, $m);
            $periodoMes  = (int) $m[2];
            $periodoAnio = 2000 + (int) $m[3];

            // Verificar duplicado
            if (ImportacionMovistar::where('folio', $folio)->where('tipo_servicio', $tipoServicio)->exists()) {
                return back()->withErrors(['archivo' => "El folio $folio ($tipoServicio) ya fue importado anteriormente."]);
            }

            DB::transaction(function () use (
                $folio, $tipoServicio, $codigoServicio, $fechaEmision,
                $periodoCobro, $periodoMes, $periodoAnio, $originalName, $rows
            ) {
                $importacion = ImportacionMovistar::create([
                    'folio'           => $folio,
                    'tipo_servicio'   => $tipoServicio,
                    'codigo_servicio' => $codigoServicio,
                    'fecha_emision'   => $fechaEmision,
                    'periodo_cobro'   => $periodoCobro,
                    'periodo_anio'    => $periodoAnio,
                    'periodo_mes'     => $periodoMes,
                    'archivo_nombre'  => $originalName,
                    'total_lineas'    => 0,
                ]);

                // Agrupar por número de servicio: sumar Total Neto (col 21, índice 20)
                // y conservar plan/producto de la primera fila de cada línea
                $agrupado = [];
                foreach (array_slice($rows, 1) as $row) {
                    $numeroServicio = (string) ($row[8] ?? '');
                    if (!$numeroServicio) continue;

                    if (!isset($agrupado[$numeroServicio])) {
                        $agrupado[$numeroServicio] = [
                            'plan_tarifario' => substr((string) ($row[3] ?? ''), 0, 255) ?: null,
                            'producto'       => substr((string) ($row[10] ?? ''), 0, 255) ?: null,
                            'monto'          => 0,
                        ];
                    }
                    // Sumar Total Neto (índice 20 = columna 21 del Excel)
                    $totalNeto = $row[20] ?? null;
                    if (is_numeric($totalNeto)) {
                        $agrupado[$numeroServicio]['monto'] += (float) $totalNeto;
                    }
                }

                $detalles = [];
                foreach ($agrupado as $numeroServicio => $datos) {
                    // Normalizar: quitar prefijo 56 si el número empieza con él
                    $numeroNormalizado = preg_match('/^56(\d{9})$/', $numeroServicio, $nm)
                        ? $nm[1]
                        : $numeroServicio;

                    $linea = LineaTelefonica::where('linea', $numeroNormalizado)->first()
                          ?? LineaTelefonica::where('linea', $numeroServicio)->first();

                    $detalles[] = [
                        'id_importacion'      => $importacion->id,
                        'numero_servicio'     => $numeroServicio,
                        'plan_tarifario'      => $datos['plan_tarifario'],
                        'producto'            => $datos['producto'],
                        'monto'               => $datos['monto'],
                        'id_linea_telefonica' => $linea?->id,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }

                // Insertar en lotes
                foreach (array_chunk($detalles, 200) as $chunk) {
                    ImportacionMovistarDetalle::insert($chunk);
                }

                $importacion->update(['total_lineas' => count($detalles)]);
            });

        } finally {
            @unlink($tmpXlsx);
        }

        $importacion = ImportacionMovistar::where('folio', $folio)
            ->where('tipo_servicio', $tipoServicio)
            ->first();

        return redirect()->route('importaciones_movistar.show', $importacion)
            ->with('success', "Importación procesada: $folio ($tipoServicio).");
    }

    public function show(ImportacionMovistar $importaciones_movistar)
    {
        $importacion = $importaciones_movistar;
        $detalles = $importacion->detalles()
            ->with('lineaTelefonica.usuario', 'lineaTelefonica.empresa')
            ->orderBy('numero_servicio')
            ->paginate(50);

        $totalEncontradas   = $importacion->detalles()->whereNotNull('id_linea_telefonica')->count();
        $totalNoEncontradas = $importacion->total_lineas - $totalEncontradas;

        // IDs de líneas cruzadas en esta importación
        $idsEnImportacion = $importacion->detalles()
            ->whereNotNull('id_linea_telefonica')
            ->pluck('id_linea_telefonica');

        // IDs de líneas que alguna vez aparecieron en importaciones del mismo tipo (Movil o BAM)
        $idsConHistorial = ImportacionMovistarDetalle::whereHas(
                'importacion', fn($q) => $q->where('tipo_servicio', $importacion->tipo_servicio)
            )
            ->whereNotNull('id_linea_telefonica')
            ->pluck('id_linea_telefonica')
            ->unique();

        // Líneas Movistar activas del mismo tipo que NO aparecen en esta importación
        // (las inactivas se excluyen: si fueron dadas de baja, es esperado que no estén)
        $lineasSinImportar = LineaTelefonica::with(['usuario', 'empresa'])
            ->whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%Movistar%'))
            ->whereIn('id', $idsConHistorial)
            ->whereNotIn('id', $idsEnImportacion)
            ->where('estado', 'Activo')
            ->orderBy('linea')
            ->get();

        // Líneas inactivas en el sistema que SÍ aparecen en esta importación
        $lineasInactivasFacturadas = $importacion->detalles()
            ->whereNotNull('id_linea_telefonica')
            ->whereHas('lineaTelefonica', fn($q) => $q->where('estado', 'Inactivo'))
            ->with('lineaTelefonica.usuario', 'lineaTelefonica.empresa')
            ->get();

        return view('importaciones_movistar.show', compact(
            'importacion', 'detalles', 'totalEncontradas', 'totalNoEncontradas',
            'lineasSinImportar', 'lineasInactivasFacturadas'
        ));
    }

    public function recruzar(ImportacionMovistar $importaciones_movistar)
    {
        $detalles = $importaciones_movistar->detalles()->get();

        foreach ($detalles as $detalle) {
            $numeroNormalizado = preg_match('/^56(\d{9})$/', $detalle->numero_servicio, $nm)
                ? $nm[1]
                : $detalle->numero_servicio;

            $linea = LineaTelefonica::where('linea', $numeroNormalizado)->first()
                  ?? LineaTelefonica::where('linea', $detalle->numero_servicio)->first();

            $detalle->update(['id_linea_telefonica' => $linea?->id]);
        }

        return redirect()->route('importaciones_movistar.show', $importaciones_movistar)
            ->with('success', 'Cruce re-procesado correctamente.');
    }

    public function destroy(ImportacionMovistar $importaciones_movistar)
    {
        $importaciones_movistar->delete();
        return redirect()->route('importaciones_movistar.index')
            ->with('success', 'Importación eliminada.');
    }
}
