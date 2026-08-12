<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Sitio;
use App\Models\Zona;
use App\Support\ColumnasSitio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Informes: la tabla completa de sitios, en pantalla y en Excel.
 *
 * Solo se muestran las columnas que tienen al menos un dato. Con 76 campos en la
 * ficha, mostrarlas todas daría una tabla con decenas de columnas vacías que hay
 * que cruzar a mano para encontrar la que sirve.
 */
class InformeController extends Controller
{
    /** Clave de sesión donde se recuerda el filtro entre visitas. */
    private const SESION_ZONAS = 'informes.zonas';

    public function sitios(Request $request)
    {
        [$zonas, $sitios] = $this->filtrar($request);

        return view('admin.informes.sitios', [
            'sitios'    => $sitios,
            'columnas'  => ColumnasSitio::conDatos($sitios),
            'totales'   => count(ColumnasSitio::todas()),
            'zonas'     => Zona::withCount('sitios')->ordenadas()->get(),
            'elegidas'  => $zonas,
            'sinZona'   => Sitio::activos()->whereNull('zona_id')->count(),
        ]);
    }

    public function sitiosExcel(Request $request)
    {
        [, $sitios] = $this->filtrar($request);
        $columnas = ColumnasSitio::conDatos($sitios);

        $libro = new Spreadsheet();
        $hoja  = $libro->getActiveSheet();
        $hoja->setTitle('Sitios');

        // Encabezados.
        $col = 1;
        foreach ($columnas as $c) {
            $hoja->setCellValue([$col, 1], $c[0]);
            $hoja->getColumnDimensionByColumn($col)->setWidth(max(12, min(40, strlen($c[0]) + 6)));
            $col++;
        }

        // Filas.
        $fila = 2;
        foreach ($sitios as $s) {
            $col = 1;
            foreach ($columnas as $c) {
                $v = $c[2]($s);
                // Las cadenas se fuerzan como texto: si no, Excel se come los
                // ceros a la izquierda y convierte «100/100» en una fecha.
                $hoja->setCellValueExplicit([$col, $fila], (string) ($v ?? ''),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $col++;
            }
            $fila++;
        }

        $fin = Coordinate::stringFromColumnIndex(max(1, count($columnas)));

        $hoja->getStyle("A1:{$fin}1")->getFont()->setBold(true);
        $hoja->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $hoja->getStyle("A1:{$fin}1")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(1)->setRowHeight(30);

        // Fijar la primera fila y las dos primeras columnas: con muchas columnas,
        // al desplazarse a la derecha se pierde de vista de qué sitio es la fila.
        $hoja->freezePane('C2');
        $hoja->setAutoFilter("A1:{$fin}" . max(1, $fila - 1));

        $tmp = tempnam(sys_get_temp_dir(), 'informe');
        (new Xlsx($libro))->save($tmp);

        return response()
            ->download($tmp, 'sitios_' . now()->format('Y-m-d') . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * Informe ejecutivo en PDF.
     *
     * Deliberadamente corto: doce columnas, las que gerencia necesita para
     * decidir. El detalle completo vive en la tabla y en el Excel.
     */
    public function sitiosPdf(Request $request)
    {
        [$zonas, $sitios] = $this->filtrar($request);

        $pdf = Pdf::loadView('admin.informes.sitios_pdf', [
            'sitios'  => $sitios,
            'zonas'   => $this->nombresDeZonas($zonas),
            'logo'    => $this->logoEmbebido(),
            'resumen' => $this->resumen($sitios),
            'emitido' => now(),
            'usuario' => $request->user()?->name,
        ])->setPaper('a4', 'landscape');

        $nombre = 'estado_conectividad_' . now()->format('Y-m-d') . '.pdf';

        // Por defecto se sirve en línea (Content-Disposition: inline) para poder
        // verlo dentro de la aplicación antes de bajarlo. `?descargar=1` lo manda
        // como adjunto, que es lo que hace el botón de descarga del modal.
        return $request->boolean('descargar')
            ? $pdf->download($nombre)
            : $pdf->stream($nombre);
    }

    /** Cómo se describe el filtro en la portada del informe. */
    private function nombresDeZonas(array $elegidas): string
    {
        if (!$elegidas) return 'Todas las zonas';

        $nombres = Zona::whereIn('id', array_filter($elegidas, fn($z) => $z !== 'sin'))
            ->ordenadas()->pluck('nombre')->all();

        if (in_array('sin', $elegidas, true)) $nombres[] = 'Sin zona';

        return implode(' · ', $nombres);
    }

    /** Conteos de la franja superior. */
    private function resumen($sitios): array
    {
        $porEstado = [];
        foreach (Sitio::ESTADOS_ENLACE as $k => $label) {
            $n = $sitios->where('estado_enlace', $k)->count();
            if ($n) $porEstado[$label] = [$n, Sitio::COLORES_ENLACE[$k]];
        }

        return [
            'sitios'   => $sitios->count(),
            'usuarios' => (int) $sitios->sum('usuarios_cant'),
            'pcs'      => (int) $sitios->sum('pcs_cant'),
            'estados'  => $porEstado,
        ];
    }

    /**
     * El logo como data URI.
     *
     * dompdf resuelve las rutas por su cuenta y desde la CLI no tiene sesión ni
     * host: una URL de `storage` no le llegaría. Embebido siempre funciona.
     */
    private function logoEmbebido(): ?string
    {
        $path = Configuracion::get('app_logo');
        if (!$path || !Storage::disk('public')->exists($path)) return null;

        $bytes = Storage::disk('public')->get($path);
        $mime  = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Resuelve el filtro por zona y devuelve [zonas elegidas, sitios].
     *
     * El filtro se recuerda en sesión para que no se pierda al recargar ni al
     * volver desde otra pantalla. Se distingue «no vino el parámetro» —hay que
     * restaurar lo guardado— de «vino vacío», que es el usuario destildando
     * todo a propósito; por eso el formulario manda siempre `filtrado=1`.
     *
     * @return array{0:array<int|string>,1:\Illuminate\Support\Collection}
     */
    private function filtrar(Request $request): array
    {
        if ($request->boolean('filtrado')) {
            $zonas = array_values(array_filter(
                (array) $request->input('zonas', []),
                fn($z) => $z === 'sin' || ctype_digit((string) $z)
            ));
            $request->session()->put(self::SESION_ZONAS, $zonas);
        } else {
            $zonas = (array) $request->session()->get(self::SESION_ZONAS, []);
        }

        $consulta = ColumnasSitio::consulta();

        // Sin nada marcado se muestran todos: una tabla vacía no le sirve a nadie.
        if ($zonas) {
            $ids     = array_values(array_filter($zonas, fn($z) => $z !== 'sin'));
            $incSin  = in_array('sin', $zonas, true);

            $consulta->where(function ($q) use ($ids, $incSin) {
                if ($ids)    $q->orWhereIn('zona_id', $ids);
                if ($incSin) $q->orWhereNull('zona_id');
            });
        }

        return [$zonas, $consulta->ordenados()->get()];
    }
}
