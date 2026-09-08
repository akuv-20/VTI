<?php

namespace App\Http\Controllers;

use App\Models\UsuarioTelefonico;
use App\Models\Ubicacion;
use App\Models\Marca;
use App\Models\Aparato;
use Illuminate\Http\Request;

/**
 * Endpoints ligeros (JSON) para crear entidades "al vuelo" desde los modales
 * de creación rápida en los formularios (líneas, equipos).
 */
class CreacionRapidaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Solo quienes pueden gestionar líneas o equipos pueden crear estas entidades al vuelo.
        $this->middleware(function ($request, $next) {
            $u = auth()->user();
            abort_unless(
                $u && ($u->es_admin || $u->tieneAcceso('equipos.index') || $u->tieneAcceso('lineas_telefonicas.index')),
                403
            );
            return $next($request);
        });
    }

    public function usuario(Request $request)
    {
        $data = $request->validate(['nombre' => 'required|string|max:255']);
        $u = UsuarioTelefonico::create($data);
        return response()->json(['id' => $u->id, 'label' => $u->nombre]);
    }

    public function ubicacion(Request $request)
    {
        $data = $request->validate(['nombre' => 'required|string|max:255']);
        $u = Ubicacion::create($data);
        return response()->json(['id' => $u->id, 'label' => $u->nombre]);
    }

    public function marca(Request $request)
    {
        $data = $request->validate(['nombre' => 'required|string|max:255']);
        $m = Marca::create($data);
        return response()->json(['id' => $m->id, 'label' => $m->nombre]);
    }

    public function aparato(Request $request)
    {
        $data = $request->validate([
            'id_marca' => 'nullable|exists:marcas,id',
            'modelo'   => 'required|string|max:255',
        ]);
        $a = Aparato::create($data)->load('marca');
        $label = trim(($a->marca->nombre ?? '') . ' · ' . $a->modelo, ' ·');
        return response()->json(['id' => $a->id, 'label' => $label]);
    }
}
