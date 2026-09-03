<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RegistroMfa;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Estado de MFA de las cuentas de Entra ID: resumen y listado. */
class MfaController extends Controller
{
    private const POR_PAGINA = 50;

    public function __construct(private RegistroMfa $mfa) {}

    public function dashboard()
    {
        try {
            $d = $this->mfa->analizar();
        } catch (\Throwable $e) {
            return $this->error($e, 'resumen de MFA');
        }

        return view('admin.mfa.dashboard', [
            'resumen'  => $d['resumen'],
            'generado' => $d['generado'],
        ]);
    }

    public function index(Request $request)
    {
        try {
            $d = $this->mfa->analizar();
        } catch (\Throwable $e) {
            return $this->error($e, 'listado de MFA');
        }

        $filtrados = $this->filtrar($d['usuarios'], $request);

        return view('admin.mfa.index', [
            'usuarios' => $this->paginar($filtrados, $request),
            'resumen'  => $d['resumen'],
            'generado' => $d['generado'],
            'total'    => $filtrados->count(),
            'filtros'  => [
                'departamentos' => $d['usuarios']->map(fn ($u) => $u['departamento'] ?: '(sin departamento)')
                    ->unique()->sort()->values()->all(),
                'activos' => $request->only(['estado', 'departamento', 'solo_admins', 'licencia', 'q', 'orden', 'dir']),
            ],
        ]);
    }

    public function excel(Request $request)
    {
        try {
            $d = $this->mfa->analizar();
        } catch (\Throwable $e) {
            return back()->withErrors($this->mensaje($e));
        }

        return $this->libro($this->filtrar($d['usuarios'], $request));
    }

    public function refrescar()
    {
        @set_time_limit(300);

        try {
            $this->mfa->olvidarCache();
            $this->mfa->analizar();
        } catch (\Throwable $e) {
            return back()->withErrors($this->mensaje($e));
        }

        return back()->with('success', 'Estado de MFA actualizado desde Microsoft Graph.');
    }

    /* ── Filtros ─────────────────────────────────────────────────────────── */

    private function filtrar(Collection $usuarios, Request $request): Collection
    {
        $estado = $request->input('estado');

        if ($estado === 'con')      $usuarios = $usuarios->where('mfa', true);
        if ($estado === 'sin')      $usuarios = $usuarios->where('mfa', false);
        if ($estado === 'sin_dato') $usuarios = $usuarios->whereNull('mfa');

        if ($request->boolean('solo_admins')) {
            $usuarios = $usuarios->where('es_admin', true);
        }

        if ($lic = $request->input('licencia')) {
            $usuarios = $usuarios->where('licenciado', $lic === 'con');
        }

        if ($dep = $request->input('departamento')) {
            $usuarios = $dep === '(sin departamento)'
                ? $usuarios->filter(fn ($u) => !$u['departamento'])
                : $usuarios->where('departamento', $dep);
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $q = mb_strtolower($q);
            $usuarios = $usuarios->filter(fn ($u) =>
                str_contains($u['upn'], $q)
                || str_contains(mb_strtolower($u['nombre']), $q)
                || str_contains(mb_strtolower((string) $u['departamento']), $q)
            );
        }

        return $this->ordenar($usuarios, $request)->values();
    }

    private function ordenar(Collection $usuarios, Request $request): Collection
    {
        $campo = $request->input('orden', 'upn');
        $desc  = $request->input('dir', 'asc') === 'desc';

        if (!in_array($campo, ['upn', 'nombre', 'departamento', 'mfa', 'actualizado'], true)) {
            $campo = 'upn';
        }

        $clave = fn ($u) => $u[$campo] instanceof \DateTimeInterface
            ? $u[$campo]->getTimestamp()
            : ($u[$campo] ?? ($desc ? -PHP_INT_MAX : PHP_INT_MAX));

        return $desc ? $usuarios->sortByDesc($clave) : $usuarios->sortBy($clave);
    }

    private function paginar(Collection $usuarios, Request $request): LengthAwarePaginator
    {
        $pagina = max(1, (int) $request->input('page', 1));

        return new LengthAwarePaginator(
            $usuarios->forPage($pagina, self::POR_PAGINA)->values(),
            $usuarios->count(),
            self::POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /* ── Excel ───────────────────────────────────────────────────────────── */

    private function libro(Collection $usuarios)
    {
        $columnas = [
            ['Correo',         fn ($u) => $u['upn']],
            ['Nombre',         fn ($u) => $u['nombre']],
            ['Departamento',   fn ($u) => $u['departamento'] ?: ''],
            ['Cargo',          fn ($u) => $u['cargo'] ?: ''],
            ['MFA',            fn ($u) => $u['mfa'] === null ? 'Sin dato' : ($u['mfa'] ? 'Activo' : 'Inactivo')],
            ['Administrador',  fn ($u) => $u['es_admin'] ? 'Sí' : 'No'],
            ['Métodos',        fn ($u) => implode(', ', array_map(
                fn ($m) => RegistroMfa::METODOS[$m] ?? $m, $u['metodos']))],
            ['Autoservicio de contraseña', fn ($u) => $u['sspr'] ? 'Sí' : 'No'],
            ['Actualizado',    fn ($u) => $u['actualizado']?->format('d/m/Y') ?: ''],
        ];

        $libro = new Spreadsheet();
        $hoja  = $libro->getActiveSheet();
        $hoja->setTitle('MFA');

        foreach ($columnas as $i => $c) {
            $hoja->setCellValue([$i + 1, 1], $c[0]);
            $hoja->getColumnDimensionByColumn($i + 1)->setWidth(max(12, min(42, strlen($c[0]) + 8)));
        }

        $fila = 2;
        foreach ($usuarios as $u) {
            foreach ($columnas as $i => $c) {
                $hoja->setCellValueExplicit([$i + 1, $fila], (string) $c[1]($u), DataType::TYPE_STRING);
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

        $tmp = tempnam(sys_get_temp_dir(), 'mfa');
        (new Xlsx($libro))->save($tmp);

        return response()
            ->download($tmp, 'mfa_' . now()->format('Y-m-d') . '.xlsx')
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

        return str_contains($m, 'Graph') || str_contains($m, 'Azure')
            ? $m
            : 'No se pudo obtener el estado de MFA: ' . $m;
    }
}
