<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mantenedor de zonas.
 *
 * Responde JSON en vez de redirigir porque se usa desde un modal montado encima
 * de la ficha del sitio: un redirect se llevaría por delante todo lo que el
 * técnico lleve escrito y no haya guardado todavía.
 */
class ZonaController extends Controller
{
    /** Todas las zonas con cuántos sitios usan cada una. */
    public function index(): JsonResponse
    {
        return response()->json(['zonas' => $this->lista()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('zonas', 'nombre')],
            'orden'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], ['nombre' => 'nombre de la zona']);

        $zona = Zona::create($data + ['orden' => $data['orden'] ?? 0]);

        return response()->json([
            'zona'  => ['id' => $zona->id, 'nombre' => $zona->nombre, 'orden' => $zona->orden, 'sitios' => 0],
            'zonas' => $this->lista(),
        ], 201);
    }

    public function update(Request $request, Zona $zona): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('zonas', 'nombre')->ignore($zona)],
            'orden'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], ['nombre' => 'nombre de la zona']);

        $zona->update($data + ['orden' => $data['orden'] ?? $zona->orden]);

        return response()->json(['zonas' => $this->lista()]);
    }

    /**
     * Borra la zona. Si hay sitios usándola se rechaza y se dice cuántos: la
     * FK es nullOnDelete, así que el borrado no fallaría solo pasaría en
     * silencio dejando esos sitios sin zona, que es justo lo que sorprende.
     */
    public function destroy(Request $request, Zona $zona): JsonResponse
    {
        $enUso = $zona->sitios()->count();

        if ($enUso > 0 && !$request->boolean('forzar')) {
            return response()->json([
                'error'  => sprintf('«%s» está asignada a %d %s.', $zona->nombre, $enUso, $enUso === 1 ? 'sitio' : 'sitios'),
                'en_uso' => $enUso,
            ], 409);
        }

        $zona->delete();

        return response()->json(['zonas' => $this->lista(), 'liberados' => $enUso]);
    }

    /** @return array<int,array{id:int,nombre:string,orden:int,sitios:int}> */
    private function lista(): array
    {
        return Zona::withCount('sitios')->ordenadas()->get()
            ->map(fn(Zona $z) => [
                'id'     => $z->id,
                'nombre' => $z->nombre,
                'orden'  => $z->orden,
                'sitios' => $z->sitios_count,
            ])
            ->all();
    }
}
