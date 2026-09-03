<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuzonExcluido;
use App\Services\UsoBuzones;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Uso de los buzones de Microsoft 365.
 *
 * Dos pantallas sobre el mismo análisis: el informe, pensado para mostrarlo y
 * capturarlo, y el listado, pensado para trabajarlo y exportarlo.
 */
class UsoBuzonesController extends Controller
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

    public function __construct(private UsoBuzones $uso) {}

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
        ]);
    }

    public function excel(Request $request)
    {
        try {
            $d = $this->uso->analizar();
        } catch (\Throwable $e) {
            return back()->withErrors($this->mensaje($e));
        }

        return $this->libro($this->filtrar($d['buzones'], $request));
    }

    /* ── Exclusiones ─────────────────────────────────────────────────────── */

    public function excluir(Request $request)
    {
        $datos = $request->validate([
            'upn'    => ['required', 'string', 'max:255'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'upn.required'    => 'Indica el correo del buzón.',
            'motivo.required' => 'Indica por qué se excluye.',
        ]);

        BuzonExcluido::updateOrCreate(
            ['upn' => mb_strtolower(trim($datos['upn']))],
            ['motivo' => $datos['motivo'], 'activo' => true, 'user_id' => $request->user()->id]
        );

        return back()->with('success', 'Buzón excluido del análisis.');
    }

    public function incluir(BuzonExcluido $excluido)
    {
        $excluido->delete();

        return back()->with('success', 'Buzón devuelto al análisis.');
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
            'activos'       => $request->only(['clase', 'departamento', 'licencia', 'cohorte', 'acceso', 'q', 'ver_excluidos', 'orden', 'dir']),
        ];
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

    private function libro(Collection $buzones)
    {
        $columnas = [
            ['Correo',            fn ($b) => $b['upn']],
            ['Nombre',            fn ($b) => $b['nombre']],
            ['Departamento',      fn ($b) => $b['departamento'] ?: ''],
            ['Cargo',             fn ($b) => $b['cargo'] ?: ''],
            ['Clasificación',     fn ($b) => UsoBuzones::CLASES[$b['clase']][0] ?? $b['clase']],
            ['Creado',            fn ($b) => $b['creado']?->format('d/m/Y') ?: ''],
            ['Último acceso',     fn ($b) => $b['ultimo_acceso']?->format('d/m/Y') ?: 'nunca'],
            ['Días sin acceso',   fn ($b) => $b['dias_sin_acceso'] ?? ''],
            ['Enviados (180 d)',  fn ($b) => $b['enviados']],
            ['Recibidos (180 d)', fn ($b) => $b['recibidos']],
            ['Tamaño (MB)',       fn ($b) => $b['mb']],
            ['Elementos',         fn ($b) => $b['items']],
            ['Licencias',         fn ($b) => implode(', ', $b['licencias'])],
        ];

        $libro = new Spreadsheet();
        $hoja  = $libro->getActiveSheet();
        $hoja->setTitle('Uso de buzones');

        foreach ($columnas as $i => $c) {
            $hoja->setCellValue([$i + 1, 1], $c[0]);
            $hoja->getColumnDimensionByColumn($i + 1)->setWidth(max(12, min(38, strlen($c[0]) + 8)));
        }

        $fila = 2;
        foreach ($buzones as $b) {
            foreach ($columnas as $i => $c) {
                $v = $c[1]($b);

                // Los números van como números para poder sumarlos en Excel; el
                // resto como texto, para que no convierta correos ni fechas.
                if (is_int($v) || is_float($v)) {
                    $hoja->setCellValue([$i + 1, $fila], $v);
                } else {
                    $hoja->setCellValueExplicit([$i + 1, $fila], (string) $v, DataType::TYPE_STRING);
                }
            }
            $fila++;
        }

        $fin = Coordinate::stringFromColumnIndex(count($columnas));

        $hoja->getStyle("A1:{$fin}1")->getFont()->setBold(true);
        $hoja->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $hoja->getStyle("A1:{$fin}1")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->freezePane('C2');
        $hoja->setAutoFilter("A1:{$fin}" . max(1, $fila - 1));

        $tmp = tempnam(sys_get_temp_dir(), 'buzones');
        (new Xlsx($libro))->save($tmp);

        return response()
            ->download($tmp, 'uso_buzones_' . now()->format('Y-m-d') . '.xlsx')
            ->deleteFileAfterSend(true);
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
            : 'No se pudo obtener el uso de buzones: ' . $m;
    }
}
