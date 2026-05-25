<?php

namespace App\Http\Controllers;

use App\Models\LineaTelefonica;
use App\Models\Emisor;
use App\Models\UsuarioTelefonico;
use App\Models\Empresa;
use App\Models\Ubicacion;
use App\Models\Aparato;
use App\Models\CentroCosto;
use App\Models\ImportacionMovistar;
use App\Models\ImportacionEntel;
use App\Models\LineaUsuarioHistorial;
use Illuminate\Http\Request;

class LineaTelefonicaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = LineaTelefonica::with(['emisor', 'usuario', 'empresa', 'ubicacion', 'centroCosto', 'aparato.marca', 'lastHistorialUsuario.usuarioAnterior']);

        // Últimas importaciones Movistar
        $ultimoMovil = ImportacionMovistar::ultimaPorTipo('Movil');
        $ultimoBAM   = ImportacionMovistar::ultimaPorTipo('BAM');

        $lineasMovistarMovil = $ultimoMovil
            ? $ultimoMovil->detalles()->whereNotNull('id_linea_telefonica')->pluck('id_linea_telefonica')->flip()
            : collect();
        $lineasMovistarBAM = $ultimoBAM
            ? $ultimoBAM->detalles()->whereNotNull('id_linea_telefonica')->pluck('id_linea_telefonica')->flip()
            : collect();

        // Últimas importaciones Entel
        $ultimoEntelMovil = ImportacionEntel::ultimaPorTipo('Movil');
        $ultimoEntelBAM   = ImportacionEntel::ultimaPorTipo('BAM');

        $lineasEntelMovil = $ultimoEntelMovil
            ? $ultimoEntelMovil->detalles()->whereNotNull('id_linea_telefonica')->pluck('id_linea_telefonica')->flip()
            : collect();
        $lineasEntelBAM = $ultimoEntelBAM
            ? $ultimoEntelBAM->detalles()->whereNotNull('id_linea_telefonica')->pluck('id_linea_telefonica')->flip()
            : collect();

        // IDs vigentes = aparecen en cualquier última importación (Movistar o Entel)
        $idsVigentes = $lineasMovistarMovil->keys()
            ->merge($lineasMovistarBAM->keys())
            ->merge($lineasEntelMovil->keys())
            ->merge($lineasEntelBAM->keys())
            ->unique();

        $estado = $request->input('estado', 'Activo');
        if ($estado !== 'Todos') {
            $query->where('estado', $estado);
        }

        $emisorFiltro = $request->input('emisor', 'Todos');
        if ($emisorFiltro !== 'Todos') {
            $query->whereHas('emisor', fn($q) => $q->where('nombre', 'like', "%$emisorFiltro%"));
        }

        $vigenciaFiltro = $request->input('vigencia', 'Todos');
        if ($vigenciaFiltro === 'Vigente') {
            $query->whereIn('id', $idsVigentes);
        } elseif ($vigenciaFiltro === 'No Vigente') {
            $query->whereNotIn('id', $idsVigentes->toArray());
        }

        if ($request->boolean('incompletas')) {
            $query->where(function ($q) {
                $q->whereNull('id_usuario')
                  ->orWhereNull('id_empresa')
                  ->orWhereNull('id_ubicacion')
                  ->orWhereNull('id_centro_costo');
            });
        }

        if ($request->filled('buscar')) {
            $b = $request->input('buscar');
            $query->where(function ($q) use ($b) {
                $q->where('linea', 'like', "%$b%")
                  ->orWhere('imei_equipo', 'like', "%$b%")
                  ->orWhere('imei_sim', 'like', "%$b%")
                  ->orWhere('observacion', 'like', "%$b%")
                  ->orWhereHas('emisor',        fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('usuario',       fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('empresa',       fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('ubicacion',     fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('aparato',       fn($q2) => $q2->where('modelo', 'like', "%$b%"))
                  ->orWhereHas('aparato.marca', fn($q2) => $q2->where('nombre', 'like', "%$b%"));
            });
        }

        // Guardar filtros activos en sesión para restaurarlos después de editar
        session(['lineas_filtros' => request()->getQueryString()]);

        $lineas = $query->paginate(20)->withQueryString();

        $soloIncompletas = $request->boolean('incompletas');

        // ── Conteos para badges de filtros (independientes de filtros activos) ──
        $totalLineas   = LineaTelefonica::count();
        $countActivo   = LineaTelefonica::where('estado', 'Activo')->count();
        $countInactivo = $totalLineas - $countActivo;

        $countVigente   = LineaTelefonica::whereIn('id', $idsVigentes)->count();
        $countNoVigente = $totalLineas - $countVigente;

        // Conteos por emisor (usando like para coincidir con el filtro)
        $countEntel    = LineaTelefonica::whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%Entel%'))->count();
        $countMovistar = LineaTelefonica::whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%Movistar%'))->count();
        $countWOM      = LineaTelefonica::whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%WOM%'))->count();

        $totalIncompletas = LineaTelefonica::where(function ($q) {
            $q->whereNull('id_usuario')
              ->orWhereNull('id_empresa')
              ->orWhereNull('id_ubicacion')
              ->orWhereNull('id_centro_costo');
        })->count();

        return view('lineas_telefonicas.index', compact(
            'lineas', 'estado', 'emisorFiltro', 'vigenciaFiltro', 'soloIncompletas',
            'ultimoMovil', 'ultimoBAM', 'lineasMovistarMovil', 'lineasMovistarBAM',
            'ultimoEntelMovil', 'ultimoEntelBAM', 'lineasEntelMovil', 'lineasEntelBAM',
            'totalLineas', 'countActivo', 'countInactivo',
            'countVigente', 'countNoVigente',
            'countEntel', 'countMovistar', 'countWOM',
            'totalIncompletas'
        ));
    }

    public function create()
    {
        $emisores    = Emisor::all();
        $usuarios    = UsuarioTelefonico::all();
        $empresas    = Empresa::orderBy('nombre')->get();
        $ubicaciones = Ubicacion::orderBy('nombre')->get();
        $aparatos    = Aparato::with('marca')->get();
        return view('lineas_telefonicas.create', compact('emisores', 'usuarios', 'empresas', 'ubicaciones', 'aparatos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estado'                 => 'required|in:Activo,Inactivo',
            'linea'                  => 'required|string',
            'id_emisor'              => 'nullable|exists:emisores,id',
            'id_usuario'             => 'nullable|exists:usuarios_telefonicos,id',
            'id_empresa'             => 'nullable|exists:empresas,id',
            'id_ubicacion'           => 'nullable|exists:ubicaciones,id',
            'id_centro_costo'        => 'nullable|exists:centros_costo,id',
            'id_aparato'             => 'nullable|exists:aparatos,id',
            'imei_equipo'            => 'nullable|string',
            'imei_sim'               => 'nullable|string',
            'fecha_entrega_sim'      => 'nullable|date',
            'fecha_renovacion_equipo'=> 'nullable|date',
            'observacion'            => 'nullable|string',
        ]);

        $linea = LineaTelefonica::create($validated);

        // Registrar asignación inicial si ya viene con usuario
        if (!empty($validated['id_usuario'])) {
            LineaUsuarioHistorial::create([
                'id_linea_telefonica' => $linea->id,
                'id_usuario_anterior' => null,
                'id_usuario_nuevo'    => $validated['id_usuario'],
            ]);
        }

        return $this->redirectIndex('Línea telefónica creada exitosamente.');
    }

    public function show(LineaTelefonica $lineas_telefonica)
    {
        $lineas_telefonica->load([
            'emisor', 'usuario', 'empresa', 'ubicacion',
            'aparato.marca', 'centroCosto',
            'historialUsuarios.usuarioAnterior',
            'historialUsuarios.usuarioNuevo',
        ]);
        return view('lineas_telefonicas.show', compact('lineas_telefonica'));
    }

    public function edit(LineaTelefonica $lineas_telefonica)
    {
        $emisores    = Emisor::all();
        $usuarios    = UsuarioTelefonico::all();
        $empresas    = Empresa::orderBy('nombre')->get();
        $ubicaciones = Ubicacion::orderBy('nombre')->get();
        $aparatos    = Aparato::with('marca')->get();
        return view('lineas_telefonicas.edit', compact('lineas_telefonica', 'emisores', 'usuarios', 'empresas', 'ubicaciones', 'aparatos'));
    }

    public function update(Request $request, LineaTelefonica $lineas_telefonica)
    {
        $validated = $request->validate([
            'estado'                 => 'required|in:Activo,Inactivo',
            'linea'                  => 'required|string',
            'id_emisor'              => 'nullable|exists:emisores,id',
            'id_usuario'             => 'nullable|exists:usuarios_telefonicos,id',
            'id_empresa'             => 'nullable|exists:empresas,id',
            'id_ubicacion'           => 'nullable|exists:ubicaciones,id',
            'id_centro_costo'        => 'nullable|exists:centros_costo,id',
            'id_aparato'             => 'nullable|exists:aparatos,id',
            'imei_equipo'            => 'nullable|string',
            'imei_sim'               => 'nullable|string',
            'fecha_entrega_sim'      => 'nullable|date',
            'fecha_renovacion_equipo'=> 'nullable|date',
            'observacion'            => 'nullable|string',
        ]);

        $usuarioAnterior = $lineas_telefonica->id_usuario;
        $usuarioNuevo    = $validated['id_usuario'] ?? null;

        $lineas_telefonica->update($validated);

        // Registrar cambio de usuario solo si realmente cambió
        if ($usuarioAnterior != $usuarioNuevo) {
            LineaUsuarioHistorial::create([
                'id_linea_telefonica' => $lineas_telefonica->id,
                'id_usuario_anterior' => $usuarioAnterior,
                'id_usuario_nuevo'    => $usuarioNuevo,
            ]);
        }

        return $this->redirectIndex('Línea telefónica actualizada exitosamente.');
    }

    public function destroy(LineaTelefonica $lineas_telefonica)
    {
        $lineas_telefonica->delete();
        return $this->redirectIndex('Línea telefónica eliminada exitosamente.');
    }

    public function reprocesarCentroCosto()
    {
        // Carga todos los centros de costo indexados por id_empresa + id_ubicacion
        $ccostos = CentroCosto::all()->keyBy(fn($cc) => $cc->id_empresa . '-' . $cc->id_ubicacion);

        // Líneas con empresa Y ubicación asignadas pero sin centro de costo
        $lineas = LineaTelefonica::whereNotNull('id_empresa')
            ->whereNotNull('id_ubicacion')
            ->whereNull('id_centro_costo')
            ->get();

        $asignadas = 0;
        foreach ($lineas as $linea) {
            $key = $linea->id_empresa . '-' . $linea->id_ubicacion;
            if ($ccostos->has($key)) {
                $linea->id_centro_costo = $ccostos[$key]->id;
                $linea->save();
                $asignadas++;
            }
        }

        $msg = $asignadas > 0
            ? "Se asignó centro de costo a {$asignadas} " . ($asignadas === 1 ? 'línea' : 'líneas') . ' automáticamente.'
            : 'No se encontraron líneas pendientes de asignación.';

        return $this->redirectIndex($msg);
    }

    private function redirectIndex(string $mensaje)
    {
        $qs = session('lineas_filtros');
        $url = route('lineas_telefonicas.index') . ($qs ? '?' . $qs : '');
        return redirect($url)->with('success', $mensaje);
    }
}
