<?php

namespace App\Http\Controllers;

use App\Models\ImportacionMovistar;
use App\Models\ImportacionEntel;
use App\Models\ImportacionMovistarDetalle;
use App\Models\ImportacionEntelDetalle;
use Illuminate\Http\Request;

class InformeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function telefonia(Request $request)
    {
        // Opciones de períodos disponibles (union de ambas operadoras)
        $periodosMovistar = ImportacionMovistar::selectRaw('periodo_anio, periodo_mes, tipo_servicio')
            ->groupBy('periodo_anio', 'periodo_mes', 'tipo_servicio')
            ->get();
        $periodosEntel = ImportacionEntel::selectRaw('periodo_anio, periodo_mes, tipo_servicio')
            ->groupBy('periodo_anio', 'periodo_mes', 'tipo_servicio')
            ->get();

        // Mapa servicio → último período disponible (para auto-selección en JS)
        $ultimoPorServicio = [];
        foreach ($periodosMovistar as $p) {
            $key = 'Movistar_' . $p->tipo_servicio;
            $val = $p->periodo_anio * 100 + $p->periodo_mes;
            if (!isset($ultimoPorServicio[$key]) || $val > $ultimoPorServicio[$key]['val']) {
                $ultimoPorServicio[$key] = ['anio' => $p->periodo_anio, 'mes' => $p->periodo_mes, 'val' => $val];
            }
        }
        foreach ($periodosEntel as $p) {
            $key = 'Entel_' . $p->tipo_servicio;
            $val = $p->periodo_anio * 100 + $p->periodo_mes;
            if (!isset($ultimoPorServicio[$key]) || $val > $ultimoPorServicio[$key]['val']) {
                $ultimoPorServicio[$key] = ['anio' => $p->periodo_anio, 'mes' => $p->periodo_mes, 'val' => $val];
            }
        }
        // Limpiar campo 'val' auxiliar
        foreach ($ultimoPorServicio as &$u) unset($u['val']);

        // Construir lista única de períodos para el selector
        $periodos = collect();
        foreach ($periodosMovistar as $p) {
            $periodos->push(['anio' => $p->periodo_anio, 'mes' => $p->periodo_mes]);
        }
        foreach ($periodosEntel as $p) {
            $periodos->push(['anio' => $p->periodo_anio, 'mes' => $p->periodo_mes]);
        }
        $periodos = $periodos->unique(fn($p) => $p['anio'].'-'.$p['mes'])
            ->sortByDesc(fn($p) => $p['anio'] * 100 + $p['mes'])
            ->values();

        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $datos        = null;
        $totalGeneral = 0;
        $importacion  = null;
        $servicio     = $request->input('servicio');
        $anio         = $request->integer('anio');
        $mes          = $request->integer('mes');

        if ($servicio && $anio && $mes) {
            [$operadora, $tipo] = explode('_', $servicio); // ej: Movistar_Movil

            if ($operadora === 'Movistar') {
                $importacion = ImportacionMovistar::where('tipo_servicio', $tipo)
                    ->where('periodo_anio', $anio)
                    ->where('periodo_mes', $mes)
                    ->first();

                $detalles = $importacion
                    ? ImportacionMovistarDetalle::with([
                        'lineaTelefonica.usuario',
                        'lineaTelefonica.empresa',
                        'lineaTelefonica.ubicacion',
                        'lineaTelefonica.centroCosto',
                    ])->where('id_importacion', $importacion->id)
                      ->whereNotNull('id_linea_telefonica')
                      ->get()
                    : collect();
            } else {
                $importacion = ImportacionEntel::where('tipo_servicio', $tipo)
                    ->where('periodo_anio', $anio)
                    ->where('periodo_mes', $mes)
                    ->first();

                $detalles = $importacion
                    ? ImportacionEntelDetalle::with([
                        'lineaTelefonica.usuario',
                        'lineaTelefonica.empresa',
                        'lineaTelefonica.ubicacion',
                        'lineaTelefonica.centroCosto',
                    ])->where('id_importacion', $importacion->id)
                      ->whereNotNull('id_linea_telefonica')
                      ->get()
                    : collect();
            }

            // Agrupar: empresa → ccosto → usuario (acumulando montos del mismo nombre)
            $datos = [];
            foreach ($detalles as $d) {
                $linea     = $d->lineaTelefonica;
                $empresa   = $linea->empresa->nombre   ?? 'Sin empresa';
                $ubicacion = $linea->ubicacion->nombre ?? 'Sin ubicación';
                $ccosto    = $linea->centroCosto?->ccosto ?? 'Sin CC';
                $usuario   = $linea->usuario->nombre   ?? $d->numero_servicio;
                $monto     = (float) $d->monto;

                $datos[$empresa][$ccosto]['ubicacion'] = $ubicacion;
                // Acumular monto por nombre de usuario (stacking de líneas con mismo nombre)
                $datos[$empresa][$ccosto]['lineas'][$usuario] = ($datos[$empresa][$ccosto]['lineas'][$usuario] ?? 0) + $monto;
                $datos[$empresa][$ccosto]['subtotal']         = ($datos[$empresa][$ccosto]['subtotal'] ?? 0) + $monto;
                $totalGeneral += $monto;
            }

            // Ordenar empresa → ccosto → usuario
            ksort($datos);
            foreach ($datos as $emp => &$ccostos) {
                ksort($ccostos);
                foreach ($ccostos as &$cc) {
                    ksort($cc['lineas']); // orden alfabético por nombre
                }
            }
            unset($ccostos, $cc);
        }

        $folio = $importacion->folio ?? null;

        return view('informes.telefonia', compact(
            'periodos', 'meses', 'datos', 'totalGeneral',
            'servicio', 'anio', 'mes', 'ultimoPorServicio', 'folio'
        ));
    }
}
