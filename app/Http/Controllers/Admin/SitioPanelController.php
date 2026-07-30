<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sitio;
use App\Models\SitioEquipo;
use App\Models\SitioFoto;
use App\Services\GaleriaFotos;
use App\Services\SitiosCheckMk;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Dashboard de avance, descubrimiento de hosts, importación masiva y
 * levantamiento en terreno del módulo Sitios.
 */
class SitioPanelController extends Controller
{
    /** Columnas de la plantilla de importación, en orden. */
    private const COLUMNAS_IMPORT = [
        'codigo'             => 'Codigo',
        'nombre'             => 'Nombre',
        'tipo'               => 'Tipo (planta/campo/datacenter/oficina)',
        'estado_enlace'      => 'Estado enlace (sin_enlace/en_gestion/en_instalacion/operativo)',
        'empresa'            => 'Empresa',
        'region'             => 'Region',
        'comuna'             => 'Comuna',
        'direccion'          => 'Direccion',
        'enlace_tipo'        => 'Enlace (fibra/ptp/starlink/4g/satelital/ninguno)',
        'isp'                => 'ISP',
        'ancho_banda'        => 'Ancho de banda',
        'superficie_ha'      => 'Superficie ha',
        'especies'           => 'Especies',
        'usuarios_cant'      => 'Usuarios',
        'pcs_cant'           => 'PCs',
        'encargado_nombre'   => 'Encargado',
        'encargado_telefono' => 'Telefono encargado',
        'encargado_email'    => 'Email encargado',
        'notas'              => 'Notas',
    ];

    public function __construct(
        private SitiosCheckMk $puente = new SitiosCheckMk(),
        private GaleriaFotos $galeria = new GaleriaFotos(),
    ) {}

    /* ── Dashboard de avance ─────────────────────────────────────────────── */

    public function dashboard()
    {
        $sitios = Sitio::activos()->with(['hosts', 'equipos'])->get();

        // Avance del enlazamiento por tipo.
        $matriz = [];
        foreach (Sitio::TIPOS as $tipo => $label) {
            $delTipo = $sitios->where('tipo', $tipo);
            $fila = ['label' => $label, 'total' => $delTipo->count(), 'estados' => []];
            foreach (Sitio::ESTADOS_ENLACE as $est => $estLabel) {
                $fila['estados'][$est] = $delTipo->where('estado_enlace', $est)->count();
            }
            $matriz[$tipo] = $fila;
        }

        // Brechas: sin monitoreo (ficha sin hosts) y sin ficha (hosts huérfanos).
        $sinMonitoreo = $sitios->filter(fn(Sitio $s) => empty($s->todosLosHosts()))->values();

        try {
            $descubrimiento = $this->puente->hostsSinFicha();
            $hostsSinFicha  = count($descubrimiento['sitios']) + count($descubrimiento['equipos']) + count($descubrimiento['sin_clasificar']);
            $checkmkOk      = true;
        } catch (\Throwable) {
            $hostsSinFicha = 0;
            $checkmkOk     = false;
        }

        // Completitud de las fichas.
        $completitudes = $sitios->map(fn(Sitio $s) => $s->completitud);
        $incompletas   = $sitios->filter(fn(Sitio $s) => $s->completitud < 100)
            ->sortBy(fn(Sitio $s) => $s->completitud)
            ->take(12)
            ->values();

        // Equipamiento consolidado (para estandarizar compras).
        $porTipoEquipo = SitioEquipo::selectRaw('tipo, count(*) as n')->groupBy('tipo')->pluck('n', 'tipo');
        $porMarca      = SitioEquipo::whereNotNull('marca')->where('marca', '!=', '')
            ->selectRaw('marca, count(*) as n')->groupBy('marca')->orderByDesc('n')->limit(8)->pluck('n', 'marca');

        // Garantías por vencer en los próximos 90 días.
        $garantias = SitioEquipo::whereNotNull('garantia_hasta')
            ->whereBetween('garantia_hasta', [now(), now()->addDays(90)])
            ->with('sitio:id,nombre,codigo')
            ->orderBy('garantia_hasta')
            ->get();

        return view('admin.sitios.dashboard', [
            'sitios'        => $sitios,
            'matriz'        => $matriz,
            'operativos'    => $sitios->where('estado_enlace', 'operativo')->count(),
            'sinMonitoreo'  => $sinMonitoreo,
            'hostsSinFicha' => $hostsSinFicha,
            'checkmkOk'     => $checkmkOk,
            'completitudProm' => $completitudes->isEmpty() ? 0 : (int) round($completitudes->avg()),
            'incompletas'   => $incompletas,
            'porTipoEquipo' => $porTipoEquipo,
            'porMarca'      => $porMarca,
            'garantias'     => $garantias,
            'conGeo'        => $sitios->filter(fn($s) => $s->latitud && $s->longitud)->values(),
        ]);
    }

    /* ── Descubrimiento de hosts ─────────────────────────────────────────── */

    public function descubrimiento()
    {
        try {
            $datos = $this->puente->hostsSinFicha();
            $error = null;
        } catch (\Throwable $e) {
            $datos = ['sitios' => [], 'equipos' => [], 'sin_clasificar' => []];
            $error = $e->getMessage();
        }

        // Nombre propuesto para cada host, listo para editar.
        foreach (['sitios', 'equipos', 'sin_clasificar'] as $grupo) {
            $datos[$grupo] = array_map(function ($f) {
                $f['nombre_sugerido'] = $this->puente->nombreDe($f['host_name']);
                return $f;
            }, $datos[$grupo]);
        }

        return view('admin.sitios.descubrimiento', [
            'datos'  => $datos,
            'error'  => $error,
            'sitios' => Sitio::activos()->ordenados()->get(['id', 'codigo', 'nombre', 'tipo']),
        ]);
    }

    /** Crea una ficha de sitio a partir de un host descubierto. */
    public function descubrimientoSitio(Request $request)
    {
        $data = $request->validate([
            'host_name' => ['required', 'string', 'max:255'],
            'nombre'    => ['required', 'string', 'max:255'],
            'tipo'      => ['required', 'in:' . implode(',', array_keys(Sitio::TIPOS))],
            'codigo'    => ['nullable', 'string', 'max:30'],
        ]);

        $sitio = Sitio::create([
            'codigo'        => $data['codigo'] ?: $this->puente->codigoDe($data['host_name']),
            'nombre'        => $data['nombre'],
            'tipo'          => $data['tipo'],
            'estado_enlace' => 'operativo', // ya está monitoreado, luego se ajusta
        ]);

        $rol = preg_match('/_VPN$/i', $data['host_name']) ? 'vpn' : 'enlace';
        $sitio->hosts()->create(['host_name' => $data['host_name'], 'rol' => $rol]);

        return redirect()
            ->route('admin.sitios.show', $sitio)
            ->with('success', "Ficha creada desde {$data['host_name']}. Completa los datos que falten.");
    }

    /** Agrega un host descubierto como equipo de un sitio existente. */
    public function descubrimientoEquipo(Request $request)
    {
        $data = $request->validate([
            'host_name' => ['required', 'string', 'max:255'],
            'sitio_id'  => ['required', 'integer', 'exists:sitios,id'],
            'tipo'      => ['required', 'in:' . implode(',', array_keys(SitioEquipo::TIPOS))],
            'nombre'    => ['required', 'string', 'max:255'],
        ]);

        SitioEquipo::create($data + ['estado' => 'operativo']);

        $sitio = Sitio::find($data['sitio_id']);

        return redirect()
            ->route('admin.sitios.show', $sitio)
            ->with('success', "{$data['host_name']} agregado como equipo de «{$sitio->nombre}».");
    }

    /** Enlaza un host descubierto a un sitio existente (ej: su túnel VPN). */
    public function descubrimientoHost(Request $request)
    {
        $data = $request->validate([
            'host_name' => ['required', 'string', 'max:255'],
            'sitio_id'  => ['required', 'integer', 'exists:sitios,id'],
            'rol'       => ['required', 'in:enlace,vpn,respaldo,otro'],
        ]);

        $sitio = Sitio::findOrFail($data['sitio_id']);
        $sitio->hosts()->firstOrCreate(['host_name' => $data['host_name']], ['rol' => $data['rol']]);

        return back()->with('success', "{$data['host_name']} enlazado a «{$sitio->nombre}».");
    }

    /* ── Importación masiva ──────────────────────────────────────────────── */

    public function importar()
    {
        return view('admin.sitios.importar');
    }

    /** Descarga la plantilla Excel con las columnas esperadas y un ejemplo. */
    public function importarPlantilla()
    {
        $libro = new Spreadsheet();
        $hoja  = $libro->getActiveSheet();
        $hoja->setTitle('Sitios');

        $col = 1;
        foreach (self::COLUMNAS_IMPORT as $titulo) {
            $hoja->setCellValue([$col, 1], $titulo);
            $hoja->getColumnDimensionByColumn($col)->setWidth(max(14, min(38, strlen($titulo) + 4)));
            $col++;
        }

        $hoja->getStyle('A1:S1')->getFont()->setBold(true);
        $hoja->getStyle('A1:S1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $hoja->getStyle('A1:S1')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(1)->setRowHeight(34);
        $hoja->freezePane('A2');

        // Fila de ejemplo para que se entienda el formato.
        $ejemplo = ['51', 'Campo Las Palmas', 'campo', 'sin_enlace', 'Unifrutti', 'O\'Higgins', 'Peumo',
            'Parcela 12, camino a Peumo', 'ninguno', '', '', '45.5', 'Cereza', '4', '2',
            'Juan Pérez', '+56 9 1234 5678', 'jperez@ejemplo.cl', 'Sin enlace aún, se evalúa Starlink'];
        foreach ($ejemplo as $i => $v) {
            $hoja->setCellValue([$i + 1, 2], $v);
        }
        $hoja->getStyle('A2:S2')->getFont()->getColor()->setRGB('94A3B8');

        $tmp = tempnam(sys_get_temp_dir(), 'sitios');
        (new Xlsx($libro))->save($tmp);

        return response()->download($tmp, 'plantilla_sitios.xlsx')->deleteFileAfterSend(true);
    }

    /** Procesa el Excel: crea sitios nuevos y actualiza los existentes por código. */
    public function importarProcesar(Request $request)
    {
        $request->validate([
            'archivo'     => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'actualizar'  => ['nullable', 'boolean'],
        ]);

        @set_time_limit(0);

        $hoja  = IOFactory::load($request->file('archivo')->getRealPath())->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, false);

        if (count($filas) < 2) {
            return back()->withErrors(['archivo' => 'El archivo no tiene filas de datos.']);
        }

        $claves = array_keys(self::COLUMNAS_IMPORT);
        $creados = 0; $actualizados = 0; $errores = [];
        $actualizar = $request->boolean('actualizar');

        foreach (array_slice($filas, 1) as $n => $fila) {
            $linea = $n + 2;

            $datos = [];
            foreach ($claves as $i => $clave) {
                $v = isset($fila[$i]) ? trim((string) $fila[$i]) : '';
                if ($v !== '') $datos[$clave] = $v;
            }

            if (empty($datos['nombre'])) continue; // fila vacía

            // Normalizar y validar los campos con dominio fijo.
            $datos['tipo'] = strtolower($datos['tipo'] ?? 'campo');
            if (!array_key_exists($datos['tipo'], Sitio::TIPOS)) {
                $errores[] = "Fila {$linea}: tipo «{$datos['tipo']}» no válido.";
                continue;
            }

            $datos['estado_enlace'] = strtolower($datos['estado_enlace'] ?? 'sin_enlace');
            if (!array_key_exists($datos['estado_enlace'], Sitio::ESTADOS_ENLACE)) {
                $datos['estado_enlace'] = 'sin_enlace';
            }

            if (isset($datos['enlace_tipo'])) {
                $datos['enlace_tipo'] = strtolower($datos['enlace_tipo']);
                if (!array_key_exists($datos['enlace_tipo'], Sitio::ENLACE_TIPOS)) {
                    unset($datos['enlace_tipo']);
                }
            }

            foreach (['superficie_ha', 'usuarios_cant', 'pcs_cant'] as $num) {
                if (isset($datos[$num])) {
                    $limpio = str_replace(',', '.', $datos[$num]);
                    $datos[$num] = is_numeric($limpio) ? $limpio : null;
                }
            }

            // Buscar existente por código, o por nombre si no trae código.
            $existente = null;
            if (!empty($datos['codigo'])) {
                $existente = Sitio::where('codigo', $datos['codigo'])->first();
            }
            $existente ??= Sitio::whereRaw('LOWER(nombre) = ?', [mb_strtolower($datos['nombre'])])->first();

            if ($existente) {
                if ($actualizar) {
                    $existente->update($datos);
                    $actualizados++;
                }
                continue;
            }

            Sitio::create($datos);
            $creados++;
        }

        $msg = "Importación lista: {$creados} sitios creados"
            . ($actualizar ? ", {$actualizados} actualizados" : '')
            . (count($errores) ? '. ' . count($errores) . ' filas con problemas.' : '.');

        return redirect()->route('admin.sitios.index')
            ->with('success', $msg)
            ->with('import_errores', $errores);
    }

    /* ── Levantamiento en terreno (móvil) ────────────────────────────────── */

    public function terreno(Request $request)
    {
        $sitios = Sitio::activos()
            ->buscar($request->input('q'))
            ->with('fotos')
            ->ordenados()
            ->get();

        return view('admin.sitios.terreno', [
            'sitios' => $sitios,
            'q'      => $request->input('q'),
        ]);
    }

    public function terrenoFicha(Sitio $sitio)
    {
        $sitio->load('fotos');

        return view('admin.sitios.terreno_ficha', ['sitio' => $sitio]);
    }

    /** Guarda el levantamiento rápido desde el celular (datos clave + fotos). */
    public function terrenoGuardar(Request $request, Sitio $sitio)
    {
        $data = $request->validate([
            'estado_enlace'      => ['required', 'in:' . implode(',', array_keys(Sitio::ESTADOS_ENLACE))],
            'enlace_tipo'        => ['nullable', 'in:' . implode(',', array_keys(Sitio::ENLACE_TIPOS))],
            'latitud'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'           => ['nullable', 'numeric', 'between:-180,180'],
            'acceso'             => ['nullable', 'string', 'max:2000'],
            'encargado_nombre'   => ['nullable', 'string', 'max:255'],
            'encargado_telefono' => ['nullable', 'string', 'max:60'],
            'usuarios_cant'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'pcs_cant'           => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notas'              => ['nullable', 'string', 'max:5000'],
            'fotos'              => ['nullable', 'array', 'max:12'],
            'fotos.*'            => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'categoria'          => ['nullable', 'in:' . implode(',', array_keys(SitioFoto::CATEGORIAS))],
        ]);

        @set_time_limit(0);

        $fotos = $data['fotos'] ?? [];
        unset($data['fotos'], $data['categoria']);

        $sitio->update($data + [
            'levantado_at'  => now(),
            'levantado_por' => $request->user()->id,
        ]);

        $n = 0;
        foreach ($fotos as $archivo) {
            $this->galeria->guardar($sitio, $archivo, ['categoria' => $request->input('categoria')]);
            $n++;
        }

        return redirect()
            ->route('admin.sitios.terreno')
            ->with('success', "«{$sitio->nombre}» actualizado" . ($n ? " con {$n} foto" . ($n > 1 ? 's' : '') : '') . '. Completitud: ' . $sitio->fresh()->completitud . '%');
    }
}
