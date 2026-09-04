<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuzonExcluido;
use App\Models\Configuracion;
use App\Services\ActividadBuzones;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Uso de los buzones de Microsoft 365.
 *
 * Dos pantallas sobre el mismo análisis: el informe, pensado para mostrarlo y
 * capturarlo, y el listado, pensado para trabajarlo y exportarlo.
 */
class ActividadBuzonesController extends Controller
{
    private const POR_PAGINA = 50;

    /**
     * Tramos de antigüedad del último acceso.
     *
     * «Nunca» va aparte y no se mezcla con los tramos de días: un buzón sin
     * ningún acceso no es «hace más de 90 días», es otra cosa, y sumarlo a los
     * tramos haría que los conteos se pisaran entre sí.
     */
    public const ACCESOS = [
        'nunca' => 'Nunca accedió',
        '30'    => 'Sin acceder hace +30 días',
        '60'    => 'Sin acceder hace +60 días',
        '90'    => 'Sin acceder hace +90 días',
    ];

    public function __construct(private ActividadBuzones $uso) {}

    /* ── Informe ─────────────────────────────────────────────────────────── */

    public function dashboard()
    {
        try {
            $d = $this->uso->analizar();
        } catch (\Throwable $e) {
            return $this->error($e, 'informe');
        }

        return view('admin.buzones.dashboard', [
            'resumen'        => $d['resumen'],
            'generado'       => $d['generado'],
            'nombresOcultos' => $d['nombresOcultos'],
        ]);
    }

    /**
     * Bloques del informe, en el orden en que salen.
     *
     * clave => [título, si viene marcado de entrada]
     *
     * Cada uno se marca o desmarca en pantalla para armar el PDF: no todas las
     * reuniones necesitan el mismo detalle. «Por dónde empezar» arranca apagado
     * porque es el detalle operativo de a quién llamar primero, y en la versión
     * que se muestra hacia arriba suele sobrar.
     */
    public const BLOQUES = [
        'veredicto'     => ['Cifra principal', true],
        'clasificacion' => ['Clasificación de los buzones', true],
        'prioridad'     => ['Por dónde empezar', false],
        'licencias'     => ['Licencias comprometidas', true],
        'cohortes'      => ['Sin uso según cuándo se creó la cuenta', true],
    ];

    /** Los bloques que salen cuando nadie eligió nada. */
    public static function bloquesPorDefecto(): array
    {
        return array_keys(array_filter(self::BLOQUES, fn ($b) => $b[1]));
    }

    /** El mismo informe en PDF, para adjuntarlo o imprimirlo. */
    public function pdf(Request $request)
    {
        @set_time_limit(180);

        try {
            $d = $this->uso->analizar();
        } catch (\Throwable $e) {
            return back()->withErrors($this->mensaje($e));
        }

        // Sin parámetro sale la selección por defecto, la misma que trae la
        // pantalla al abrirla. Si lo que llega no nombra ningún bloque real,
        // también: un PDF con la portada y nada más no le sirve a nadie.
        $pedidos = array_filter(explode(',', (string) $request->input('bloques')));
        $bloques = array_values(array_intersect(array_keys(self::BLOQUES), $pedidos))
                ?: self::bloquesPorDefecto();

        $pdf = Pdf::loadView('admin.buzones.dashboard_pdf', [
            'resumen'  => $d['resumen'],
            'generado' => $d['generado'],
            'logo'     => $this->logoEmbebido(),
            'emitido'  => now(),
            'usuario'  => $request->user()?->name,
            'bloques'  => $bloques,
        ])->setPaper('a4', 'portrait');

        $nombre = 'actividad_buzones_' . now()->format('Y-m-d') . '.pdf';

        // En línea por defecto, para revisarlo antes de mandarlo; el botón de
        // descarga agrega ?descargar=1.
        return $request->boolean('descargar')
            ? $pdf->download($nombre)
            : $pdf->stream($nombre);
    }

    /** El logo de la aplicación como data URI, que es lo que entiende dompdf. */
    private function logoEmbebido(): ?string
    {
        $path = Configuracion::get('app_logo');

        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $contenido = Storage::disk('public')->get($path);
        $tipo      = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:' . $tipo . ';base64,' . base64_encode($contenido);
    }

    /* ── Listado ─────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        try {
            $d = $this->uso->analizar();
        } catch (\Throwable $e) {
            return $this->error($e, 'listado');
        }

        $filtrados = $this->filtrar($d['buzones'], $request);

        return view('admin.buzones.index', [
            'buzones'      => $this->paginar($filtrados, $request),
            'resumen'      => $d['resumen'],
            'generado'     => $d['generado'],
            'total'        => $filtrados->count(),
            'filtros'      => $this->valoresDeFiltro($d['buzones'], $request),
            'excluidos'    => BuzonExcluido::orderBy('upn')->get(),
            'papelera'     => BuzonExcluido::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    public function excel(Request $request)
    {
        // PhpSpreadsheet mantiene el libro entero en memoria y cada celda cuesta
        // caro: con ~3.000 buzones en siete hojas el pico medido es de 102 MB,
        // contra los 128 MB que trae PHP por defecto. Sin este margen el informe
        // muere a mitad de camino y el usuario recibe una descarga corrupta.
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $d = $this->uso->analizar();
        } catch (\Throwable $e) {
            return back()->withErrors($this->mensaje($e));
        }

        // El informe es el desglose por estado, así que ignora los filtros de
        // estado —clase, último acceso, excluidos— y respeta los de alcance
        // —departamento, licencia, cohorte, búsqueda—. De otro modo se pediría
        // "solo activos" y saldría un libro con cinco hojas vacías.
        return $this->libro($this->acotar($d['buzones'], $request), $d['generado']);
    }

    /* ── Exclusiones ─────────────────────────────────────────────────────── */

    /**
     * Excluye uno o varios buzones.
     *
     * Acepta una lista pegada de golpe —separada por comas, espacios o saltos de
     * línea— porque armar la lista de a uno es lento y es justo el trabajo que no
     * conviene tener que rehacer.
     */
    public function excluir(Request $request)
    {
        $datos = $request->validate([
            'upn'    => ['required', 'string', 'max:4000'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'upn.required'    => 'Indica el correo del buzón.',
            'motivo.required' => 'Indica por qué se excluye.',
        ]);

        $correos = collect(preg_split('/[\s,;]+/', $datos['upn'], -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($c) => mb_strtolower(trim($c)))
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))
            ->unique();

        if ($correos->isEmpty()) {
            return back()->withErrors('No se reconoció ningún correo válido en lo que escribiste.')->withInput();
        }

        foreach ($correos as $upn) {
            BuzonExcluido::agregar($upn, $datos['motivo'], $request->user()->id);
        }

        return back()->with('success', $correos->count() === 1
            ? 'Buzón excluido del análisis.'
            : "{$correos->count()} buzones excluidos del análisis.");
    }

    /** Saca un buzón de la lista. Queda en la papelera, no se pierde. */
    public function incluir(BuzonExcluido $excluido)
    {
        $excluido->delete();

        return back()->with('success',
            "«{$excluido->upn}» vuelve al análisis. Queda en la papelera por si lo necesitas de vuelta.");
    }

    /** Devuelve a la lista un buzón que estaba en la papelera. */
    public function restaurar(int $id)
    {
        $buzon = BuzonExcluido::onlyTrashed()->findOrFail($id);
        $buzon->restore();

        return back()->with('success', "«{$buzon->upn}» vuelve a estar excluido.");
    }

    public function refrescar()
    {
        // Reconstruir toma ~40 s y el límite por defecto de PHP es 30.
        @set_time_limit(300);

        try {
            $this->uso->olvidarCache();
            $this->uso->analizar();
        } catch (\Throwable $e) {
            return back()->withErrors($this->mensaje($e));
        }

        return back()->with('success', 'Datos actualizados desde Microsoft Graph.');
    }

    /* ── Filtros ─────────────────────────────────────────────────────────── */

    private function filtrar(Collection $buzones, Request $request): Collection
    {
        // Los excluidos quedan fuera salvo que se pidan expresamente: son el
        // ruido que la lista existe para sacar del medio.
        $buzones = $request->boolean('ver_excluidos')
            ? $buzones->where('excluido', true)
            : $buzones->where('excluido', false);

        if ($clase = $request->input('clase')) {
            $buzones = $buzones->where('clase', $clase);
        }

        if ($dep = $request->input('departamento')) {
            $buzones = $dep === '(sin departamento)'
                ? $buzones->filter(fn ($b) => !$b['departamento'])
                : $buzones->where('departamento', $dep);
        }

        if ($lic = $request->input('licencia')) {
            $buzones = $buzones->filter(fn ($b) => in_array($lic, $b['licencias'], true));
        }

        if ($mes = $request->input('cohorte')) {
            $buzones = $buzones->filter(fn ($b) => $b['creado'] && $b['creado']->format('Y-m') === $mes);
        }

        if (($tramo = $request->input('correo')) && isset(ActividadBuzones::TRAMOS[$tramo])) {
            [, $min, $max] = ActividadBuzones::TRAMOS[$tramo];
            $buzones = $buzones->filter(fn ($b) => $b['recibidos'] >= $min
                                               && ($max === null || $b['recibidos'] <= $max));
        }

        if ($acceso = $request->input('acceso')) {
            $buzones = $acceso === 'nunca'
                ? $buzones->filter(fn ($b) => $b['ultimo_acceso'] === null)
                : $buzones->filter(fn ($b) => $b['dias_sin_acceso'] !== null
                                           && $b['dias_sin_acceso'] > (int) $acceso);
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $q = mb_strtolower($q);
            $buzones = $buzones->filter(fn ($b) =>
                str_contains($b['upn'], $q)
                || str_contains(mb_strtolower($b['nombre']), $q)
                || str_contains(mb_strtolower((string) $b['departamento']), $q)
            );
        }

        return $this->ordenar($buzones, $request)->values();
    }

    private function ordenar(Collection $buzones, Request $request): Collection
    {
        $campo = $request->input('orden', 'recibidos');
        $desc  = $request->input('dir', 'desc') === 'desc';

        $permitidos = ['upn', 'nombre', 'departamento', 'creado', 'ultimo_acceso',
                       'enviados', 'recibidos', 'mb', 'clase'];

        if (!in_array($campo, $permitidos, true)) {
            $campo = 'recibidos';
        }

        // Las fechas nulas siempre al final, ordene como ordene: un buzón sin
        // acceso no debe colarse entre los más recientes por ser null.
        $clave = fn ($b) => $b[$campo] instanceof \DateTimeInterface
            ? $b[$campo]->getTimestamp()
            : ($b[$campo] ?? ($desc ? -PHP_INT_MAX : PHP_INT_MAX));

        return $desc ? $buzones->sortByDesc($clave) : $buzones->sortBy($clave);
    }

    private function paginar(Collection $buzones, Request $request): LengthAwarePaginator
    {
        $pagina = max(1, (int) $request->input('page', 1));

        return new LengthAwarePaginator(
            $buzones->forPage($pagina, self::POR_PAGINA)->values(),
            $buzones->count(),
            self::POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /** Valores disponibles en cada desplegable, calculados sobre los datos reales. */
    private function valoresDeFiltro(Collection $buzones, Request $request): array
    {
        $vivos = $buzones->where('excluido', false);

        return [
            'departamentos' => $vivos->map(fn ($b) => $b['departamento'] ?: '(sin departamento)')
                ->unique()->sort()->values()->all(),
            'licencias'     => $vivos->pluck('licencias')->flatten()->unique()->sort()->values()->all(),
            'cohortes'      => $vivos->filter(fn ($b) => $b['creado'])
                ->map(fn ($b) => $b['creado']->format('Y-m'))
                ->unique()->sortDesc()->values()->all(),
            'accesos'       => $this->conteosDeAcceso($vivos),
            'activos'       => $request->only(['clase', 'departamento', 'licencia', 'cohorte', 'acceso', 'correo', 'q', 'ver_excluidos', 'orden', 'dir']),
        ];
    }

    /**
     * Los filtros de alcance, sin los de estado. Para el informe por estado:
     * deja acotar a un departamento o a una tanda, pero conserva todos los
     * buzones de ese recorte, incluidos los excluidos.
     */
    private function acotar(Collection $buzones, Request $request): Collection
    {
        if ($dep = $request->input('departamento')) {
            $buzones = $dep === '(sin departamento)'
                ? $buzones->filter(fn ($b) => !$b['departamento'])
                : $buzones->where('departamento', $dep);
        }

        if ($lic = $request->input('licencia')) {
            $buzones = $buzones->filter(fn ($b) => in_array($lic, $b['licencias'], true));
        }

        if ($mes = $request->input('cohorte')) {
            $buzones = $buzones->filter(fn ($b) => $b['creado'] && $b['creado']->format('Y-m') === $mes);
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $q = mb_strtolower($q);
            $buzones = $buzones->filter(fn ($b) =>
                str_contains($b['upn'], $q)
                || str_contains(mb_strtolower($b['nombre']), $q)
                || str_contains(mb_strtolower((string) $b['departamento']), $q)
            );
        }

        return $buzones->sortBy('upn')->values();
    }

    /** Cuántos buzones caen en cada tramo, para mostrarlo en el desplegable. */
    private function conteosDeAcceso(Collection $buzones): array
    {
        $conteos = ['nunca' => $buzones->whereNull('ultimo_acceso')->count()];

        foreach (['30', '60', '90'] as $dias) {
            $conteos[$dias] = $buzones
                ->filter(fn ($b) => $b['dias_sin_acceso'] !== null && $b['dias_sin_acceso'] > (int) $dias)
                ->count();
        }

        return $conteos;
    }

    /* ── Excel ───────────────────────────────────────────────────────────── */

    /**
     * Los estados del informe, en ORDEN DE PRIORIDAD.
     *
     * Cada buzón cae en uno y solo uno, de modo que la suma de las hojas es el
     * total de correos. El orden importa: «+90 días» va antes que las clases de
     * correo porque llevar tres meses sin entrar pesa más que haber enviado
     * algo dentro de la ventana de 180 días.
     *
     * clave => [nombre de la hoja, color de la pestaña, descripción, prueba]
     */
    private function estados(): array
    {
        return [
            'excluido' => ['Excluidos', '94A3B8',
                'Buzones compartidos o funcionales, fuera del análisis',
                fn ($b) => $b['excluido']],

            'nunca' => ['Nunca Activado', 'A63A22',
                'Nunca registraron un inicio de sesión',
                fn ($b) => $b['ultimo_acceso'] === null],

            'mas90' => ['+90 Dias', 'D97706',
                'Entraron alguna vez, pero hace más de 90 días',
                fn ($b) => $b['dias_sin_acceso'] > 90],

            'sin_actividad' => ['Sin Actividad', 'B37D22',
                'Acceden, pero no enviaron ni recibieron correo en 180 días',
                fn ($b) => $b['enviados'] === 0 && $b['recibidos'] === 0],

            'solo_recibe' => ['Solo Recibe', '7FA48D',
                'Usan la cuenta, pero nunca enviaron un correo',
                fn ($b) => $b['enviados'] === 0],

            'activo' => ['Activo', '22553D',
                'Enviaron correo en los últimos 180 días',
                fn ($b) => true],
        ];
    }

    private function libro(Collection $buzones, $generado)
    {
        $estados = $this->estados();
        $motivos = BuzonExcluido::pluck('motivo', 'upn')->all();

        // Cada buzón al primer estado cuya prueba pasa.
        $porEstado = array_fill_keys(array_keys($estados), []);
        foreach ($buzones as $b) {
            foreach ($estados as $clave => [, , , $prueba]) {
                if ($prueba($b)) {
                    $porEstado[$clave][] = $b;
                    break;
                }
            }
        }

        $columnas = [
            ['Correo',            fn ($b) => $b['upn']],
            ['Nombre',            fn ($b) => $b['nombre']],
            ['Departamento',      fn ($b) => $b['departamento'] ?: ''],
            ['Cargo',             fn ($b) => $b['cargo'] ?: ''],
            ['Creado',            fn ($b) => $b['creado']?->format('d/m/Y') ?: ''],
            ['Último acceso',     fn ($b) => $b['ultimo_acceso']?->format('d/m/Y') ?: 'nunca'],
            ['Días sin acceso',   fn ($b) => $b['dias_sin_acceso'] ?? ''],
            ['Enviados (180 d)',  fn ($b) => $b['enviados']],
            ['Recibidos (180 d)', fn ($b) => $b['recibidos']],
            ['Tamaño (MB)',       fn ($b) => $b['mb']],
            ['Elementos',         fn ($b) => $b['items']],
            ['Licencias',         fn ($b) => implode(', ', $b['licencias'])],
            ['Motivo de exclusión', fn ($b) => $motivos[$b['upn']] ?? ''],
        ];

        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);

        $this->hojaResumen($libro, $estados, $porEstado, $buzones->count(), $generado);

        foreach ($estados as $clave => [$titulo, $color]) {
            $this->hojaEstado($libro, $titulo, $color, $porEstado[$clave], $columnas);
        }

        $libro->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'buzones');
        (new Xlsx($libro))->save($tmp);

        return response()
            ->download($tmp, 'actividad_buzones_' . now()->format('Y-m-d') . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    /** Portada: los totales y qué significa cada estado. */
    private function hojaResumen(Spreadsheet $libro, array $estados, array $porEstado, int $total, $generado): void
    {
        $h = $libro->createSheet()->setTitle('Resumen');

        $h->setCellValue('A1', 'Actividad de buzones · unifrutti.com');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $h->setCellValue('A2', 'Datos de Microsoft Graph al ' . $generado->format('d/m/Y H:i')
            . '. Actividad de correo medida sobre los últimos 180 días, el máximo que entrega la API.');
        $h->setCellValue('A3', 'Cada buzón aparece en una sola hoja. Los estados se evalúan en el orden de esta '
            . 'tabla y gana el primero que aplica, así que la suma es el total de buzones.');
        $h->getStyle('A2:A3')->getFont()->setSize(9)->getColor()->setRGB('64748B');

        foreach (['Estado', 'Buzones', '% del total', 'Qué significa'] as $i => $t) {
            $h->setCellValue([$i + 1, 5], $t);
        }
        $h->getStyle('A5:D5')->getFont()->setBold(true);
        $h->getStyle('A5:D5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $fila = 6;
        foreach ($estados as $clave => [$titulo, $color, $desc]) {
            $n = count($porEstado[$clave]);
            $h->setCellValueExplicit([1, $fila], $titulo, DataType::TYPE_STRING);
            $h->setCellValue([2, $fila], $n);
            $h->setCellValue([3, $fila], $total > 0 ? round($n * 100 / $total, 1) / 100 : 0);
            $h->setCellValueExplicit([4, $fila], $desc, DataType::TYPE_STRING);
            $h->getStyle([1, $fila])->getFont()->getColor()->setRGB($color);
            $h->getStyle([3, $fila])->getNumberFormat()->setFormatCode('0.0%');
            $fila++;
        }

        $h->setCellValueExplicit([1, $fila], 'TOTAL', DataType::TYPE_STRING);
        $h->setCellValue([2, $fila], $total);
        $h->getStyle("A{$fila}:D{$fila}")->getFont()->setBold(true);
        $h->getStyle("A{$fila}:D{$fila}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

        $h->setCellValue([1, $fila + 2], 'Se considera «sin uso» únicamente a quien nunca inició sesión. '
            . 'No se usa el contador de correos leídos: mucha gente trabaja con miles de correos sin marcar '
            . 'como leídos y sí usa su cuenta a diario.');
        $h->getStyle([1, $fila + 2])->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

        foreach ([46, 10, 12, 62] as $i => $ancho) {
            $h->getColumnDimensionByColumn($i + 1)->setWidth($ancho);
        }
    }

    private function hojaEstado(Spreadsheet $libro, string $titulo, string $color, array $filas, array $columnas): void
    {
        $h = $libro->createSheet()->setTitle($titulo);
        $h->getTabColor()->setRGB($color);

        foreach ($columnas as $i => $c) {
            $h->setCellValue([$i + 1, 1], $c[0]);
            $h->getColumnDimensionByColumn($i + 1)->setWidth(max(12, min(38, strlen($c[0]) + 8)));
        }

        $fila = 2;
        foreach ($filas as $b) {
            foreach ($columnas as $i => $c) {
                $v = $c[1]($b);

                // Los números van como números para poder sumarlos en Excel; el
                // resto como texto, para que no convierta correos ni fechas.
                if (is_int($v) || is_float($v)) {
                    $h->setCellValue([$i + 1, $fila], $v);
                } else {
                    $h->setCellValueExplicit([$i + 1, $fila], (string) $v, DataType::TYPE_STRING);
                }
            }
            $fila++;
        }

        $fin = Coordinate::stringFromColumnIndex(count($columnas));

        $h->getStyle("A1:{$fin}1")->getFont()->setBold(true);
        $h->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $h->getStyle("A1:{$fin}1")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $h->getRowDimension(1)->setRowHeight(30);
        $h->freezePane('C2');
        $h->setAutoFilter("A1:{$fin}" . max(1, $fila - 1));

        if ($filas === []) {
            $h->setCellValueExplicit('A2', 'Ningún buzón en este estado.', DataType::TYPE_STRING);
            $h->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        }
    }

    /* ── Errores ─────────────────────────────────────────────────────────── */

    private function error(\Throwable $e, string $pantalla)
    {
        report($e);

        return view('admin.buzones.error', [
            'pantalla' => $pantalla,
            'error'    => $this->mensaje($e),
        ]);
    }

    private function mensaje(\Throwable $e): string
    {
        $m = $e->getMessage();

        // Los mensajes de GraphClient ya vienen redactados para mostrarse.
        return str_contains($m, 'Graph') || str_contains($m, 'Azure')
            ? $m
            : 'No se pudo obtener la actividad de los buzones: ' . $m;
    }
}
