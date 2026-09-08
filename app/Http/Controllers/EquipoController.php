<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Aparato;
use App\Models\Marca;
use App\Models\UsuarioTelefonico;
use App\Models\Ubicacion;
use App\Models\LineaTelefonica;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    private const ESTADOS    = ['En uso', 'En bodega', 'De baja'];
    private const PROPIEDADES = ['Empresa', 'Personal'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Equipo::with(['aparato.marca', 'usuario', 'ubicacion', 'lineaTelefonica']);

        $propiedad = $request->input('propiedad', 'Todos');
        if ($propiedad !== 'Todos') {
            $query->where('propiedad', $propiedad);
        }

        $estado = $request->input('estado', 'Todos');
        if ($estado !== 'Todos') {
            $query->where('estado', $estado);
        }

        // Vínculo con línea
        $vinculo = $request->input('vinculo', 'Todos');
        if ($vinculo === 'Con línea') {
            $query->whereHas('lineaTelefonica');
        } elseif ($vinculo === 'Sin línea') {
            $query->whereDoesntHave('lineaTelefonica');
        }

        if ($request->filled('buscar')) {
            $b = $request->input('buscar');
            $query->where(function ($q) use ($b) {
                $q->where('imei', 'like', "%$b%")
                  ->orWhereHas('aparato',        fn($q2) => $q2->where('modelo', 'like', "%$b%"))
                  ->orWhereHas('aparato.marca',  fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('usuario',        fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('lineaTelefonica',fn($q2) => $q2->where('linea', 'like', "%$b%"));
            });
        }

        $equipos = $query->latest()->paginate(20)->withQueryString();

        // Conteos para badges
        $total        = Equipo::count();
        $countEmpresa = Equipo::where('propiedad', 'Empresa')->count();
        $countPersonal= Equipo::where('propiedad', 'Personal')->count();
        $countSinLinea= Equipo::whereDoesntHave('lineaTelefonica')->count();

        return view('equipos.index', compact(
            'equipos', 'propiedad', 'estado', 'vinculo',
            'total', 'countEmpresa', 'countPersonal', 'countSinLinea'
        ));
    }

    public function create()
    {
        return view('equipos.create', $this->selects());
    }

    // (helpers más abajo)

    public function store(Request $request)
    {
        $validated = $this->validar($request);
        $equipo = Equipo::create($validated);

        $this->sincronizarLinea($equipo, $request->input('id_linea'));

        return redirect()->route('equipos.index')->with('success', 'Equipo creado correctamente.');
    }

    public function show(Equipo $equipo)
    {
        $equipo->load(['aparato.marca', 'usuario', 'ubicacion', 'lineaTelefonica.emisor']);
        return view('equipos.show', compact('equipo'));
    }

    public function edit(Equipo $equipo)
    {
        return view('equipos.edit', array_merge(['equipo' => $equipo], $this->selects($equipo)));
    }

    public function update(Request $request, Equipo $equipo)
    {
        $validated = $this->validar($request);
        $equipo->update($validated);

        $this->sincronizarLinea($equipo, $request->input('id_linea'));

        return redirect()->route('equipos.index')->with('success', 'Equipo actualizado correctamente.');
    }

    /**
     * Enlaza el equipo con una línea (o lo desliga) y sincroniza usuario/ubicación.
     * El FK vive en lineas_telefonicas.id_equipo (una línea = un equipo).
     */
    private function sincronizarLinea(Equipo $equipo, $idLineaNueva): void
    {
        $idLineaNueva = $idLineaNueva ?: null;
        $lineaActual  = $equipo->lineaTelefonica; // línea que hoy apunta a este equipo

        // Sin cambios en el vínculo
        if ($lineaActual?->id == $idLineaNueva) {
            if ($lineaActual) {
                $lineaActual->update(['id_usuario' => $equipo->id_usuario, 'id_ubicacion' => $equipo->id_ubicacion]);
            }
            return;
        }

        // Desligar la línea anterior
        if ($lineaActual) {
            $lineaActual->update(['id_equipo' => null]);
        }

        // Ligar la nueva línea (liberando cualquier equipo que tuviera)
        if ($idLineaNueva) {
            $nueva = LineaTelefonica::find($idLineaNueva);
            if ($nueva) {
                $nueva->update([
                    'id_equipo'    => $equipo->id,
                    'id_usuario'   => $equipo->id_usuario,
                    'id_ubicacion' => $equipo->id_ubicacion,
                ]);
            }
        }
    }

    public function destroy(Equipo $equipo)
    {
        if ($equipo->lineaTelefonica) {
            return back()->with('error', 'No se puede eliminar: el equipo está ligado a la línea ' . $equipo->lineaTelefonica->linea . '. Desligalo primero.');
        }
        $equipo->delete();
        return back()->with('success', 'Equipo eliminado.');
    }

    private function validar(Request $request): array
    {
        $validated = $request->validate([
            'id_aparato'   => 'nullable|exists:aparatos,id',
            'imei'         => 'nullable|string|max:50',
            'propiedad'    => 'required|in:' . implode(',', self::PROPIEDADES),
            'id_usuario'   => 'nullable|exists:usuarios_telefonicos,id',
            'id_ubicacion' => 'nullable|exists:ubicaciones,id',
            'estado'       => 'required|in:' . implode(',', self::ESTADOS),
            'observacion'  => 'nullable|string|max:500',
            'id_linea'     => 'nullable|exists:lineas_telefonicas,id',
        ]);

        unset($validated['id_linea']); // se maneja aparte (FK vive en la línea)
        return $validated;
    }

    private function selects(?Equipo $equipo = null): array
    {
        // Líneas enlazables: las que no tienen equipo (+ la actual al editar)
        $lineasDisponibles = LineaTelefonica::with(['usuario', 'emisor'])
            ->where(function ($q) use ($equipo) {
                $q->whereNull('id_equipo');
                if ($equipo) $q->orWhere('id_equipo', $equipo->id);
            })
            ->orderBy('linea')
            ->get();

        return [
            'aparatos'          => Aparato::with('marca')->get(),
            'marcas'            => Marca::orderBy('nombre')->get(),
            'usuarios'          => UsuarioTelefonico::orderBy('nombre')->get(),
            'ubicaciones'       => Ubicacion::orderBy('nombre')->get(),
            'estados'           => self::ESTADOS,
            'propiedades'       => self::PROPIEDADES,
            'lineasDisponibles' => $lineasDisponibles,
            'lineaActualId'     => $equipo?->lineaTelefonica?->id,
        ];
    }
}
