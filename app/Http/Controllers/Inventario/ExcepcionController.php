<?php

namespace App\Http\Controllers\Inventario;

use App\Models\InventarioExcepcion;
use App\Services\InventarioGlpi;
use Illuminate\Http\Request;

/**
 * Excepciones del indicador de antivirus.
 *
 * Se listan con cuántos equipos cubre cada una, y antes de guardar se puede
 * previsualizar el alcance: una regla como «contiene mac» también atraparía
 * MACARENA-PC, y eso hay que verlo antes y no semanas después.
 */
class ExcepcionController extends BaseController
{
    public function index()
    {
        $dom = $this->dominio();

        // Las reglas viven en la base de la aplicación, así que se pueden ver y
        // editar aunque GLPI esté caído. Lo único que se pierde es el alcance,
        // que sí sale de allá: se muestra la pantalla sin esos números.
        $reglas = InventarioExcepcion::delDominio($dom->clave)
            ->orderByDesc('activa')->orderBy('campo')->orderBy('valor')
            ->get();

        $alcance      = [];
        $totalActivas = null;
        $avisoGlpi    = null;

        try {
            $glpi = new InventarioGlpi($dom);

            // Cuántos equipos cubre cada regla por separado. Se calcula de a una
            // porque lo interesante es detectar la que sobra o la que barre de más.
            foreach ($reglas as $r) {
                $alcance[$r->id] = $this->contarAlcance($glpi, collect([$r]));
            }

            $totalActivas = $this->contarAlcance($glpi, $glpi->excepciones());

        } catch (\Throwable $e) {
            report($e);
            $avisoGlpi = $this->mensajeError($e, $dom);
        }

        return view('inventario.excepciones', compact(
            'dom', 'reglas', 'alcance', 'totalActivas', 'avisoGlpi'
        ));
    }

    public function store(Request $request)
    {
        $dom = $this->dominio();

        InventarioExcepcion::create($this->validar($request) + ['dominio' => $this->dominioDe($request, $dom)]);

        return redirect()->route("inventario.{$dom->clave}.excepciones")
            ->with('success', 'Excepción creada.');
    }

    public function update(Request $request, InventarioExcepcion $excepcion)
    {
        $dom = $this->dominio();
        $this->verificarDominio($excepcion, $dom);

        $excepcion->update($this->validar($request) + ['dominio' => $this->dominioDe($request, $dom)]);

        return redirect()->route("inventario.{$dom->clave}.excepciones")
            ->with('success', 'Excepción actualizada.');
    }

    public function toggle(InventarioExcepcion $excepcion)
    {
        $dom = $this->dominio();
        $this->verificarDominio($excepcion, $dom);

        $excepcion->update(['activa' => !$excepcion->activa]);

        return back()->with('success', $excepcion->activa ? 'Excepción activada.' : 'Excepción desactivada.');
    }

    public function destroy(InventarioExcepcion $excepcion)
    {
        $dom = $this->dominio();
        $this->verificarDominio($excepcion, $dom);

        $excepcion->delete();

        return redirect()->route("inventario.{$dom->clave}.excepciones")
            ->with('success', 'Excepción eliminada.');
    }

    /** Vista previa en JSON: qué equipos cubriría la regla que se está escribiendo. */
    public function previsualizar(Request $request)
    {
        $dom  = $this->dominio();
        $glpi = new InventarioGlpi($dom);

        $datos = $request->validate([
            'campo'    => ['required', 'in:' . implode(',', array_keys(InventarioExcepcion::CAMPOS))],
            'operador' => ['required', 'in:' . implode(',', array_keys(InventarioExcepcion::OPERADORES))],
            'valor'    => ['required', 'string', 'max:200'],
        ]);

        $regla = new InventarioExcepcion($datos);

        try {
            // Solo interesa el efecto real: equipos que HOY figuran sin el antivirus
            // corporativo y que la regla dejaría de contar.
            $q = $glpi->baseEquipos();
            $glpi->sinAntivirusCorporativo($q);
            InventarioExcepcion::aplicarA($q, collect([$regla]));

            $total   = (clone $q)->distinct()->count('c.id');
            $muestra = $q->select('c.name as equipo', 'os.name as so')
                ->orderBy('c.name')->limit(15)->get();

        } catch (\Throwable $e) {
            report($e);

            // JSON y no una página de error: quien llama es el fetch de la vista
            // previa, que espera JSON y ya sabe mostrar el fallo.
            return response()->json(['error' => $this->mensajeError($e, $dom)], 503);
        }

        return response()->json([
            'total'   => $total,
            'muestra' => $muestra,
        ]);
    }

    /* ── Apoyo ──────────────────────────────────────────────────────────── */

    private function contarAlcance(InventarioGlpi $glpi, $reglas): int
    {
        if ($reglas->isEmpty()) return 0;

        $q = $glpi->baseEquipos();
        $glpi->sinAntivirusCorporativo($q);
        InventarioExcepcion::aplicarA($q, $reglas);

        return $q->distinct()->count('c.id');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'campo'    => ['required', 'in:' . implode(',', array_keys(InventarioExcepcion::CAMPOS))],
            'operador' => ['required', 'in:' . implode(',', array_keys(InventarioExcepcion::OPERADORES))],
            'valor'    => ['required', 'string', 'max:200'],
            'motivo'   => ['required', 'string', 'max:300'],
            'activa'   => ['nullable', 'boolean'],
        ], [
            'motivo.required' => 'Indica por qué se exceptúan estos equipos.',
            'valor.required'  => 'Indica el texto a buscar.',
        ]) + ['activa' => $request->boolean('activa', true)];
    }

    /** null cuando la regla aplica a todos los dominios. */
    private function dominioDe(Request $request, $dom): ?string
    {
        return $request->boolean('todos_los_dominios') ? null : $dom->clave;
    }

    /**
     * Una regla de otro dominio no se toca desde acá: el permiso está en la
     * ruta, pero el id de la regla viene de la URL.
     */
    private function verificarDominio(InventarioExcepcion $excepcion, $dom): void
    {
        abort_unless(
            $excepcion->dominio === null || $excepcion->dominio === $dom->clave,
            404
        );
    }
}
