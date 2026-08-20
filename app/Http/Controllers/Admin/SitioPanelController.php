<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Empresa;
use App\Models\Sitio;
use App\Models\SitioEquipo;
use App\Models\SitioFoto;
use App\Models\Zona;
use App\Services\GaleriaFotos;
use App\Services\ReferenciasCheckMk;
use App\Services\SitiosCheckMk;
use App\Support\DpaChile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
        'empresa'            => 'Empresa (del mantenedor)',
        'region'             => 'Region',
        'comuna'             => 'Comuna',
        'maps_url'           => 'Link de Google Maps',
        'enlace_tipo'        => 'Enlace (fibra/ptp/starlink/4g/satelital/ninguno)',
        'isp'                => 'ISP (compania del mantenedor)',
        'ancho_banda'        => 'Ancho de banda Internet',
        'ancho_banda_mpls'   => 'Ancho de banda MPLS',
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
        private ReferenciasCheckMk $referencias = new ReferenciasCheckMk(),
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
            'huerfanos'     => $this->referencias->huerfanos(),
            'operativos'    => $sitios->where('estado_enlace', 'operativo')->count(),
            'sinMonitoreo'  => $sinMonitoreo,
            'hostsSinFicha' => $hostsSinFicha,
            'checkmkOk'     => $checkmkOk,
            'completitudProm' => $completitudes->isEmpty() ? 0 : (int) round($completitudes->avg()),
            'incompletas'   => $incompletas,
            'porTipoEquipo' => $porTipoEquipo,
            'porMarca'      => $porMarca,
            'garantias'     => $garantias,
            // El mapa se fue a su propio módulo, así que acá ya no se calculan
            // los sitios con coordenadas.
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

        // Rango de la última columna, para no quedar amarrado a un ancho fijo.
        $fin = Coordinate::stringFromColumnIndex(count(self::COLUMNAS_IMPORT));

        $hoja->getStyle("A1:{$fin}1")->getFont()->setBold(true);
        $hoja->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $hoja->getStyle("A1:{$fin}1")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(1)->setRowHeight(34);
        $hoja->freezePane('A2');

        // Fila de ejemplo para que se entienda el formato. Va por clave para que
        // no se desalinee si mañana se agrega o se mueve una columna.
        $ejemplo = [
            'codigo'             => '51',
            'nombre'             => 'Campo Las Palmas',
            'tipo'               => 'campo',
            'estado_enlace'      => 'sin_enlace',
            'empresa'            => 'VERFRUT',
            'region'             => "O'Higgins",
            'comuna'             => 'Peumo',
            'maps_url'           => 'https://maps.app.goo.gl/… o -34.39751,-71.17403',
            'enlace_tipo'        => 'ninguno',
            'ancho_banda'        => '100/100 Mbps',
            'ancho_banda_mpls'   => '20/20 Mbps',
            'superficie_ha'      => '45.5',
            'especies'           => 'Cereza',
            'usuarios_cant'      => '4',
            'pcs_cant'           => '2',
            'encargado_nombre'   => 'Juan Pérez',
            'encargado_telefono' => '+56 9 1234 5678',
            'encargado_email'    => 'jperez@ejemplo.cl',
            'notas'              => 'Sin enlace aún, se evalúa Starlink',
        ];

        $col = 1;
        foreach (array_keys(self::COLUMNAS_IMPORT) as $clave) {
            $hoja->setCellValue([$col++, 2], $ejemplo[$clave] ?? '');
        }
        $hoja->getStyle("A2:{$fin}2")->getFont()->getColor()->setRGB('94A3B8');

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

        // Los mantenedores se cargan una vez y se buscan por nombre normalizado.
        $empresas  = $this->indicePorNombre(Empresa::all(['id', 'nombre']));
        $companias = $this->indicePorNombre(Compania::all(['id', 'nombre']));

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

            // Empresa e ISP vienen por nombre: se resuelven contra los mantenedores.
            foreach ([['empresa', 'empresa_id', $empresas, 'empresa'], ['isp', 'isp_id', $companias, 'compañía']] as [$col, $fk, $indice, $rotulo]) {
                if (!isset($datos[$col])) continue;

                $id = $indice[$this->claveNombre($datos[$col])] ?? null;
                if (!$id) {
                    $errores[] = "Fila {$linea}: la {$rotulo} «{$datos[$col]}» no existe en el mantenedor, se dejó vacía.";
                }
                $datos[$fk] = $id;
                unset($datos[$col]);
            }

            // Región y comuna se ajustan a la división política oficial.
            if (isset($datos['comuna'])) {
                $comuna = DpaChile::normalizarComuna($datos['comuna']);
                if (!$comuna) {
                    $errores[] = "Fila {$linea}: la comuna «{$datos['comuna']}» no se reconoce.";
                }
                $datos['comuna'] = $comuna ?? $datos['comuna'];
            }
            $region = DpaChile::normalizarRegion($datos['region'] ?? null)
                ?: DpaChile::regionDeComuna($datos['comuna'] ?? null);
            if ($region) $datos['region'] = $region;

            // Coordenadas a partir del link de Maps.
            if (!empty($datos['maps_url']) && $coords = Sitio::coordenadasDesdeUrl($datos['maps_url'])) {
                [$datos['latitud'], $datos['longitud']] = $coords;
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
            . (count($errores) ? '. ' . count($errores) . ' avisos, revisa el detalle.' : '.');

        return redirect()->route('admin.sitios.index')
            ->with('success', $msg)
            ->with('import_errores', $errores);
    }

    /* ── Enlaces rotos con CheckMK ───────────────────────────────────────── */

    /** Fichas, equipos y nodos que apuntan a hosts que ya no existen. */
    public function enlaces()
    {
        $datos = $this->referencias->huerfanos();

        try {
            $hosts = $this->puente->estados()->keys()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        } catch (\Throwable) {
            $hosts = collect();
        }

        return view('admin.sitios.enlaces', [
            'ok'        => $datos['ok'],
            'error'     => $datos['error'],
            'total'     => $datos['total_hosts'],
            'huerfanos' => $datos['huerfanos'],
            'hosts'     => $hosts,
        ]);
    }

    /** Apunta todas las referencias de un host viejo hacia el host nuevo. */
    public function enlacesRemapear(Request $request)
    {
        $data = $request->validate([
            'viejo' => ['required', 'string', 'max:255'],
            'nuevo' => ['required', 'string', 'max:255', 'different:viejo'],
        ]);

        $r = $this->referencias->remapear($data['viejo'], $data['nuevo']);
        $tocado = array_sum($r);

        if (!$tocado) {
            return back()->withErrors(['nuevo' => "No quedaban referencias a «{$data['viejo']}»."]);
        }

        return back()->with('success', sprintf(
            'Listo: «%s» → «%s». Se actualizaron %s.',
            $data['viejo'], $data['nuevo'], $this->detalle($r)
        ));
    }

    /** Suelta las referencias a un host que ya no existe. */
    public function enlacesDesenlazar(Request $request)
    {
        $data = $request->validate(['host_name' => ['required', 'string', 'max:255']]);

        $r = $this->referencias->desenlazar($data['host_name']);

        return back()->with('success', sprintf(
            'Se soltó «%s»: %s. El equipamiento y los nodos se conservan, solo quedaron sin host.',
            $data['host_name'], $this->detalle($r)
        ));
    }

    /** "2 fichas, 1 equipo y 1 nodo" a partir del conteo por origen. */
    private function detalle(array $r): string
    {
        $rotulos = [
            'fichas'  => ['ficha', 'fichas'],
            'equipos' => ['equipo', 'equipos'],
            'nodos'   => ['nodo del mapa', 'nodos del mapa'],
        ];

        $partes = [];
        foreach ($rotulos as $clave => [$uno, $varios]) {
            if ($r[$clave] > 0) $partes[] = $r[$clave] . ' ' . ($r[$clave] === 1 ? $uno : $varios);
        }

        if (!$partes) return 'nada';
        if (count($partes) === 1) return $partes[0];

        $ultima = array_pop($partes);

        return implode(', ', $partes) . ' y ' . $ultima;
    }

    /* ── División política (API interna para los selectores) ─────────────── */

    /** Regiones y comunas de Chile: ['Región' => ['Comuna', …]]. */
    public function dpa()
    {
        return response()
            ->json(DpaChile::todo())
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** [nombre normalizado => id] para buscar empresas y compañías por nombre. */
    private function indicePorNombre($registros): array
    {
        $out = [];
        foreach ($registros as $r) {
            $out[$this->claveNombre($r->nombre)] = $r->id;
        }

        return $out;
    }

    private function claveNombre(string $nombre): string
    {
        $t = mb_strtolower(trim($nombre), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]/', '', $t);
    }

    /* ── Levantamiento en terreno (móvil) ────────────────────────────────── */

    /**
     * Listado del levantamiento en terreno.
     *
     * Trae SIEMPRE todos los sitios activos, sin filtrar por servidor. El
     * filtrado (texto y zona) ocurre en el navegador, y eso no es una
     * preferencia de estilo: en el campo no hay red para pedir otra página, y
     * la precarga se arma con lo que hay en el DOM. Si el servidor recortara
     * la lista, se saldría a terreno con solo una parte de las fichas
     * guardadas y el resto aparecería como «no está guardada» justo cuando ya
     * no se puede hacer nada.
     */
    public function terreno()
    {
        $sitios = Sitio::activos()
            ->with(['fotos', 'zona'])
            ->ordenados()
            ->get();

        return view('admin.sitios.terreno', [
            'sitios'  => $sitios,
            // Solo las zonas que alguien va a usar como filtro: las que tienen
            // al menos un sitio activo. Una zona recién creada y todavía vacía
            // sería un botón que no filtra nada.
            'zonas'   => Zona::ordenadas()
                ->whereHas('sitios', fn ($q) => $q->where('activo', true))
                ->get(['id', 'nombre']),
            'sinZona' => $sitios->whereNull('zona_id')->count(),
        ]);
    }

    /**
     * Latido para saber si hay salida real hacia la aplicación.
     *
     * Existe porque `navigator.onLine` miente en el campo: basta con estar
     * pegado a una antena o a un WiFi sin salida para que diga que sí. Lo que
     * importa no es tener señal sino poder llegar a la base de datos, así que
     * acá se hace una consulta trivial: si responde, se puede guardar.
     */
    public function terrenoPing()
    {
        try {
            DB::connection()->select('select 1');
            $bd = true;
        } catch (\Throwable $e) {
            $bd = false;
        }

        return response()
            ->json(['ok' => $bd, 'hora' => now()->toIso8601String()], $bd ? 200 : 503)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Comuna y región a partir de unas coordenadas — solo una sugerencia para
     * el formulario de terreno. Va por acá y no directo desde el navegador para
     * aprovechar el caché del servidor y no repetir consultas al servicio
     * externo por cada teléfono.
     */
    public function geocodificar(Request $request)
    {
        $datos = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json(
            (new \App\Services\Geocodificador())->comunaYRegion((float) $datos['lat'], (float) $datos['lon'])
        );
    }

    public function terrenoFicha(Sitio $sitio)
    {
        $sitio->load('fotos');

        return view('admin.sitios.terreno_ficha', [
            'sitio'     => $sitio,
            'companias' => Compania::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /** Guarda el levantamiento rápido desde el celular (datos clave + fotos). */
    public function terrenoGuardar(Request $request, Sitio $sitio)
    {
        $data = $request->validate([
            'estado_enlace'      => ['required', 'in:' . implode(',', array_keys(Sitio::ESTADOS_ENLACE))],
            'enlace_tipo'        => ['nullable', 'in:' . implode(',', array_keys(Sitio::ENLACE_TIPOS))],
            'latitud'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'           => ['nullable', 'numeric', 'between:-180,180'],
            'maps_url'           => ['nullable', 'string', 'max:2000'],
            'region'             => ['nullable', 'string', 'max:255'],

            // Solo se muestran cuando hay enlace, pero se validan siempre: si el
            // tipo es «ninguno» simplemente llegan vacíos.
            'ancho_banda'        => ['nullable', 'string', 'max:60'],
            'isp_id'             => ['nullable', 'integer', 'exists:companias,id'],

            // Tri-estado: null es «no lo respondí», que no es lo mismo que «no tiene».
            'vpn'                => ['nullable', 'in:0,1'],

            // Texto libre con atajo a «No hay»: sigue siendo varchar a propósito,
            // para poder escribir el modelo del gabinete o de la UPS.
            'gabinete'           => ['nullable', 'string', 'max:255'],
            'ups_modelo'         => ['nullable', 'string', 'max:255'],
            'comuna'             => ['nullable', 'string', 'max:255'],
            'usuarios_cant'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'pcs_cant'           => ['nullable', 'integer', 'min:0', 'max:9999'],
            'nota_nueva'         => ['nullable', 'string', 'max:5000'],
            'fotos'              => ['nullable', 'array', 'max:12'],
            'fotos.*'            => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'categoria'          => ['nullable', 'in:' . implode(',', array_keys(SitioFoto::CATEGORIAS))],

            // ── Evaluación de factibilidad (sitios sin enlace) ───────────────
            // Los tri-estado llegan como '' cuando no se respondió; Laravel los
            // convierte a null antes de validar, así que `nullable` los deja pasar
            // y se distinguen de un "no" explícito.
            //
            // Esta lista es DELIBERADAMENTE más corta que la de la ficha de
            // escritorio. Lo que salió del formulario móvil el 2026-08-12 —el
            // encargado, el acceso, la línea de vista, el uso previsto y la
            // conclusión de la visita— se edita allá, y si llegara igual acá no
            // debe entrar: el celular manda solo lo que se contesta en terreno.
            'cob_entel'          => ['nullable', 'in:' . implode(',', array_keys(Sitio::COBERTURA))],
            'cob_movistar'       => ['nullable', 'in:' . implode(',', array_keys(Sitio::COBERTURA))],
            'cob_wom'            => ['nullable', 'in:' . implode(',', array_keys(Sitio::COBERTURA))],
            'cob_claro'          => ['nullable', 'in:' . implode(',', array_keys(Sitio::COBERTURA))],
            'cob_notas'          => ['nullable', 'string', 'max:2000'],

            'eval_energia'         => ['nullable', 'in:' . implode(',', array_keys(Sitio::EVAL_ENERGIA))],
            'eval_energia_estable' => ['nullable', 'in:0,1'],

            'eval_cielo_despejado' => ['nullable', 'in:0,1'],
            'eval_fibra_zona'      => ['nullable', 'in:' . implode(',', array_keys(Sitio::EVAL_FIBRA_ZONA))],

            'eval_punto_montaje' => ['nullable', 'in:' . implode(',', array_keys(Sitio::EVAL_PUNTO_MONTAJE))],
            'eval_altura_m'      => ['nullable', 'numeric', 'min:0', 'max:999'],
            'eval_sala_equipos'  => ['nullable', 'in:' . implode(',', array_keys(Sitio::EVAL_SALA_EQUIPOS))],
        ]);

        @set_time_limit(0);

        $fotos = $data['fotos'] ?? [];
        unset($data['fotos'], $data['categoria']);

        // La nota se AGREGA fechada, no reemplaza. Desde el celular se escribe
        // poco y a las apuradas: pisar lo que ya había en la ficha de escritorio
        // sería perder trabajo previo sin darse cuenta.
        $nota = trim((string) ($data['nota_nueva'] ?? ''));
        unset($data['nota_nueva']);

        if ($nota !== '') {
            $sello = now()->format('d-m-Y H:i') . ' · ' . $request->user()->name;
            $data['notas'] = trim(($sitio->notas ? $sitio->notas . "\n\n" : '') . "[{$sello}]\n{$nota}");
        }

        // Si pegaron un link de Maps sin coordenadas, se sacan del propio link.
        if (empty($data['latitud']) && $coords = Sitio::coordenadasDesdeUrl($data['maps_url'] ?? null)) {
            [$data['latitud'], $data['longitud']] = $coords;
        }

        // Comuna y región: se sugieren desde las coordenadas solo si vienen
        // vacías. En terreno el navegador ya lo intenta, pero sin señal no puede;
        // acá se recupera cuando la ficha llega sincronizada horas después.
        $lat = $data['latitud']  ?? $sitio->latitud;
        $lon = $data['longitud'] ?? $sitio->longitud;

        $faltaRegion = empty($data['region']) && empty($sitio->region);
        $faltaComuna = empty($data['comuna']) && empty($sitio->comuna);

        if ($lat && $lon && ($faltaRegion || $faltaComuna)) {
            $geo = (new \App\Services\Geocodificador())->comunaYRegion((float) $lat, (float) $lon);

            if ($faltaRegion && $geo['region']) $data['region'] = $geo['region'];
            if ($faltaComuna && $geo['comuna']) $data['comuna'] = $geo['comuna'];
        }

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
