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
        $dom  = $this->dominio();
        $glpi = new InventarioGlpi($dom);

        $reglas = InventarioExcepcion::delDominio($dom->clave)
            ->orderByDesc('activa')->orderBy('campo')->orderBy('valor')
            ->get();

        // Cuántos equipos cubre cada regla por separado. Se calcula de a una
        // porque lo interesante es detectar la que sobra o la que barre de más.
        $alcance = [];
        foreach ($reglas as $r) {
            $alcance[$r->id] = $this->contarAlcance($glpi, collect([$r]));
        }

        return view('inventario.excepciones', [
            'dom'        => $dom,
            'reglas'     => $reglas,
            'alcance'    => $alcance,
            'totalActivas' => $this->contarAlcance($glpi, $glpi->excepciones()),
        ]);
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

        // Solo interesa el efecto real: equipos que HOY figuran sin el antivirus
        // corporativo y que la regla dejaría de contar.
        $q = $glpi->baseEquipos();
        $glpi->sinAntivirusCorporativo($q);
        InventarioExcepcion::aplicarA($q, collect([$regla]));

        $total   = (clone $q)->distinct()->count('c.id');
        $muestra = $q->select('c.name as equipo', 'os.name as so')
            ->orderBy('c.name')->limit(15)->get();

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
