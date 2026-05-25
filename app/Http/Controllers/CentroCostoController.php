<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Models\Empresa;
use App\Models\Ubicacion;
use Illuminate\Http\Request;

class CentroCostoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = CentroCosto::with(['empresa', 'ubicacion'])->orderBy('id_empresa');

        if ($request->filled('buscar')) {
            $b = $request->input('buscar');
            $query->where(function ($q) use ($b) {
                $q->where('codigo_b', 'like', "%$b%")
                  ->orWhere('codigo_c', 'like', "%$b%")
                  ->orWhereRaw("CONCAT(codigo_b, '-', codigo_c) like ?", ["%$b%"])
                  ->orWhereHas('empresa',   fn($q2) => $q2->where('nombre', 'like', "%$b%"))
                  ->orWhereHas('ubicacion', fn($q2) => $q2->where('nombre', 'like', "%$b%"));
            });
        }

        $centros = $query->paginate(30)->withQueryString();
        return view('centros_costo.index', compact('centros'));
    }

    public function create()
    {
        $empresas   = Empresa::orderBy('nombre')->get();
        $ubicaciones = Ubicacion::orderBy('nombre')->get();
        return view('centros_costo.create', compact('empresas', 'ubicaciones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_empresa'   => 'required|exists:empresas,id',
            'id_ubicacion' => 'required|exists:ubicaciones,id',
            'codigo_b'     => 'required|string',
            'codigo_c'     => 'required|string',
        ]);

        $request->validate([
            'id_empresa' => \Illuminate\Validation\Rule::unique('centros_costo')->where('id_ubicacion', $request->id_ubicacion),
        ], ['id_empresa.unique' => 'Ya existe un Centro de Costo para esa combinación de Empresa y Ubicación.']);

        CentroCosto::create($validated);

        return redirect()->route('centros_costo.index')->with('success', 'Centro de Costo creado exitosamente.');
    }

    public function edit(CentroCosto $centros_costo)
    {
        $empresas    = Empresa::orderBy('nombre')->get();
        $ubicaciones = Ubicacion::orderBy('nombre')->get();
        return view('centros_costo.edit', compact('centros_costo', 'empresas', 'ubicaciones'));
    }

    public function update(Request $request, CentroCosto $centros_costo)
    {
        $validated = $request->validate([
            'id_empresa'   => 'required|exists:empresas,id',
            'id_ubicacion' => 'required|exists:ubicaciones,id',
            'codigo_b'     => 'required|string',
            'codigo_c'     => 'required|string',
        ]);

        $centros_costo->update($validated);

        return redirect()->route('centros_costo.index')->with('success', 'Centro de Costo actualizado exitosamente.');
    }

    public function destroy(CentroCosto $centros_costo)
    {
        $centros_costo->delete();
        return redirect()->route('centros_costo.index')->with('success', 'Centro de Costo eliminado exitosamente.');
    }

    public function buscar(Request $request)
    {
        $cc = CentroCosto::where('id_empresa', $request->id_empresa)
            ->where('id_ubicacion', $request->id_ubicacion)
            ->first();

        if ($cc) {
            return response()->json([
                'id'     => $cc->id,
                'ccosto' => $cc->ccosto,
            ]);
        }

        return response()->json(null);
    }
}
