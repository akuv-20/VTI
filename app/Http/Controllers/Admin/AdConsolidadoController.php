<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConsolidadoAd;
use Illuminate\Http\Request;

/**
 * Consolidado AD: las dos sábanas del Excel «Inventario ADs Unifrutti»
 * (Entorno AD y Cuentas AD) armadas desde los dominios conectados.
 *
 * La pantalla pide cada dominio por separado y en paralelo: leer uno grande
 * toma ~30 s y juntarlos en una sola petición la haría lenta y frágil.
 */
class AdConsolidadoController extends Controller
{
    public function __construct(private ConsolidadoAd $consolidado) {}

    public function index()
    {
        $conexiones = $this->consolidado->conexiones();

        return view('admin.ad_consolidado.index', compact('conexiones'));
    }

    public function datos(string $conexion)
    {
        abort_unless(array_key_exists($conexion, $this->consolidado->conexiones()), 404);
        set_time_limit(180);

        return response()->json($this->consolidado->dominio($conexion));
    }

    public function actualizar(Request $request)
    {
        $this->consolidado->olvidar();

        return response()->json(['ok' => true]);
    }
}
