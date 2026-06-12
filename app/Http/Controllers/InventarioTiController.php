<?php

namespace App\Http\Controllers;

use App\Models\ActaEntregaEquipo;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventarioTiController extends Controller
{
    private const EXCLUIR_USER = 138;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Obtiene procesador, RAM total y disco principal de un equipo GLPI */
    private function hardwareDe(int $computerId): array
    {
        $glpi = DB::connection('glpi');

        $procesador = $glpi->table('glpi_items_deviceprocessors as ip')
            ->join('glpi_deviceprocessors as dp', 'dp.id', '=', 'ip.deviceprocessors_id')
            ->where('ip.itemtype', 'Computer')
            ->where('ip.items_id', $computerId)
            ->where('ip.is_deleted', 0)
            ->value('dp.designation');

        $ramMb = $glpi->table('glpi_items_devicememories')
            ->where('itemtype', 'Computer')
            ->where('items_id', $computerId)
            ->where('is_deleted', 0)
            ->sum('size');

        $discoMb = $glpi->table('glpi_items_deviceharddrives')
            ->where('itemtype', 'Computer')
            ->where('items_id', $computerId)
            ->where('is_deleted', 0)
            ->max('capacity');

        return [
            'procesador' => $procesador,
            'ram'        => $ramMb ? $this->formatoCapacidad((int) $ramMb) : null,
            'disco'      => $discoMb ? $this->formatoCapacidad((int) $discoMb) : null,
        ];
    }

    /** Convierte MB a GB o TB redondeado (ej: 32768 → "32 GB", 1000204 → "1 TB") */
    private function formatoCapacidad(int $mb): string
    {
        // Discos comerciales usan base decimal: 1 TB ≈ 1.000.000 MB (≈976 GB binario)
        if ($mb >= 950000) {
            return round($mb / 1000000, $mb < 1500000 ? 0 : 1) . ' TB';
        }
        $gb = $mb / 1024;
        if ($gb >= 1) {
            // Redondear al valor comercial más cercano (120/128, 240/256, 480/500, 512…)
            return round($gb) . ' GB';
        }
        return $mb . ' MB';
    }

    // ── Listado de computadores desde GLPI ───────────────────────────────────
    public function index(Request $request)
    {
        $search = $request->input('q');

        $query = DB::connection('glpi')
            ->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
            ->leftJoin('glpi_computermodels as cm', 'cm.id', '=', 'c.computermodels_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                $j->on('ios.items_id', '=', 'c.id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->select([
                'c.id',
                'c.name as nombre_equipo',
                'c.serial as numero_serie',
                'c.comment',
                DB::raw("CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,'')) as nombre_usuario"),
                'man.name as marca',
                'cm.name as modelo',
                'loc.completename as ubicacion',
                'os.name as sistema_operativo',
            ])
            ->where('c.is_deleted', 0)
            ->where('c.is_template', 0)
            ->where('c.users_id', '!=', self::EXCLUIR_USER)
            ->groupBy('c.id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('c.name', 'like', "%{$search}%")
                  ->orWhere('c.serial', 'like', "%{$search}%")
                  ->orWhere('u.name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,'')) like ?", ["%{$search}%"]);
            });
        }

        $computadores = $query->orderBy('c.name')->paginate(25)->withQueryString();

        return view('inventario_ti.index', compact('computadores', 'search'));
    }

    // ── Ficha de un equipo ───────────────────────────────────────────────────
    public function show($id)
    {
        $equipo = DB::connection('glpi')
            ->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
            ->leftJoin('glpi_computermodels as cm', 'cm.id', '=', 'c.computermodels_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->leftJoin('glpi_computertypes as ct', 'ct.id', '=', 'c.computertypes_id')
            ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                $j->on('ios.items_id', '=', 'c.id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->select([
                'c.id',
                'c.name as nombre_equipo',
                'c.serial as numero_serie',
                'c.otherserial as numero_inventario',
                'c.comment',
                'c.date_creation',
                'c.date_mod',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,''))) as nombre_usuario"),
                'u.phone as telefono_usuario',
                'man.name as marca',
                'cm.name as modelo',
                'loc.completename as ubicacion',
                'os.name as sistema_operativo',
                'ct.name as tipo',
            ])
            ->where('c.id', $id)
            ->where('c.is_deleted', 0)
            ->groupBy('c.id')
            ->first();

        abort_if(!$equipo, 404);

        $hardware = $this->hardwareDe((int) $equipo->id);

        $actas = ActaEntregaEquipo::where('glpi_computer_id', $id)
            ->latest()
            ->get();

        return view('inventario_ti.show', compact('equipo', 'actas', 'hardware'));
    }

    // ── Guardar acta de entrega ──────────────────────────────────────────────
    public function storeActa(Request $request, $id)
    {
        $equipo = DB::connection('glpi')
            ->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
            ->leftJoin('glpi_computermodels as cm', 'cm.id', '=', 'c.computermodels_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                $j->on('ios.items_id', '=', 'c.id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->select([
                'c.id',
                'c.name as nombre_equipo',
                'c.serial as numero_serie',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,''))) as nombre_usuario"),
                'man.name as marca',
                'cm.name as modelo',
                'loc.completename as ubicacion',
                'os.name as sistema_operativo',
            ])
            ->where('c.id', $id)
            ->where('c.is_deleted', 0)
            ->groupBy('c.id')
            ->first();

        abort_if(!$equipo, 404);

        // No generar acta si faltan datos críticos del equipo en GLPI
        $faltantes = [];
        if (!trim($equipo->nombre_usuario ?? '')) $faltantes[] = 'usuario asignado';
        if (!$equipo->ubicacion)                  $faltantes[] = 'ubicación';
        if ($faltantes) {
            return back()->withErrors([
                'acta' => 'No es posible generar el acta: el equipo no tiene ' . implode(' ni ', $faltantes) . ' en GLPI.',
            ]);
        }

        $validated = $request->validate([
            'condicion'           => 'required|in:Nuevo,Usado',
            'accesorios.monitor'  => 'nullable|in:SI,NO',
            'accesorios.mouse'    => 'nullable|in:SI,NO',
            'accesorios.teclado'  => 'nullable|in:SI,NO',
            'accesorios.mochila'  => 'nullable|in:SI,NO',
            'observacion'         => 'nullable|string|max:500',
        ]);

        $hardware = $this->hardwareDe($equipo->id);

        $acta = ActaEntregaEquipo::create([
            'glpi_computer_id'  => $equipo->id,
            'fecha_emision'     => now()->toDateString(),
            'nombre_equipo'     => $equipo->nombre_equipo,
            'nombre_receptor'   => $equipo->nombre_usuario ?: null,
            'ubicacion'         => $equipo->ubicacion,
            'marca'             => $equipo->marca,
            'modelo'            => $equipo->modelo,
            'numero_serie'      => $equipo->numero_serie,
            'sistema_operativo' => $equipo->sistema_operativo,
            'procesador'        => $hardware['procesador'],
            'ram'               => $hardware['ram'],
            'disco'             => $hardware['disco'],
            'condicion'         => $validated['condicion'],
            'accesorios'        => $validated['accesorios'] ?? [],
            'observacion'       => $validated['observacion'] ?? null,
            'entregado_por'     => auth()->user()->name,
        ]);

        return redirect()->route('inventario_ti.actas.imprimir', $acta);
    }

    // ── Imprimir acta ────────────────────────────────────────────────────────
    public function imprimirActa(ActaEntregaEquipo $acta)
    {
        $logoPath = Configuracion::get('app_logo');
        $appLogo  = $logoPath ? Storage::url($logoPath) : null;

        return view('inventario_ti.imprimir', compact('acta', 'appLogo'));
    }

    // ── Listado de actas ─────────────────────────────────────────────────────
    public function actas()
    {
        $actas = ActaEntregaEquipo::latest()->paginate(25);

        return view('inventario_ti.actas', compact('actas'));
    }

    // ── Eliminar acta (solo admin) ───────────────────────────────────────────
    public function destroyActa(ActaEntregaEquipo $acta)
    {
        $this->authorize('admin');
        $acta->delete();
        return back()->with('success', 'Acta eliminada.');
    }
}
