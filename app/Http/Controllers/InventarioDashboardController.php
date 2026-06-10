<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventarioDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Usuario excluido de todos los indicadores (equipos sin conectividad gestionable)
    private const EXCLUIR_USER = 138;

    public function index()
    {
        $glpi    = DB::connection('glpi');
        $excluir = self::EXCLUIR_USER;
        $hoy     = Carbon::now();
        $hace90  = $hoy->copy()->subDays(90)->toDateTimeString();
        $hace30  = $hoy->copy()->subDays(30)->toDateTimeString();

        // ── KPIs principales ────────────────────────────────────────────────
        $totalEquipos = $glpi->table('glpi_computers')
            ->where('is_deleted', 0)->where('is_template', 0)
            ->where('users_id', '!=', $excluir)->count();

        $sinUsuario = $glpi->table('glpi_computers')
            ->where('is_deleted', 0)->where('is_template', 0)
            ->where('users_id', 0)->count();

        $sinUbicacion = $glpi->table('glpi_computers')
            ->where('is_deleted', 0)->where('is_template', 0)
            ->where('users_id', '!=', $excluir)
            ->where('locations_id', 0)->count();

        $conAgente = $glpi->table('glpi_agents as a')
            ->join('glpi_computers as c', 'c.id', '=', 'a.items_id')
            ->where('a.itemtype', 'Computer')
            ->where('c.users_id', '!=', $excluir)
            ->distinct()->count('a.items_id');

        $sinAgente = $totalEquipos - $conAgente;

        $agenteInactivo = $glpi->table('glpi_agents as a')
            ->join('glpi_computers as c', 'c.id', '=', 'a.items_id')
            ->where('a.itemtype', 'Computer')
            ->where('c.users_id', '!=', $excluir)
            ->where('a.last_contact', '<', $hace90)->count();

        // Duplicados: mismos serial (no vacíos)
        $duplicados = $glpi->table('glpi_computers')
            ->where('is_deleted', 0)->where('is_template', 0)
            ->where('users_id', '!=', $excluir)
            ->whereNotNull('serial')->where('serial', '!=', '')
            ->select('serial', DB::raw('COUNT(*) as total'))
            ->groupBy('serial')
            ->having('total', '>', 1)
            ->get();
        $cantDuplicados = $duplicados->count();

        $sinAntivirusQuery = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->leftJoin('glpi_itemantiviruses as av', function($j) {
                $j->on('av.items_id', '=', 'c.id')
                  ->where('av.itemtype', 'Computer')
                  ->where('av.is_deleted', 0);
            })
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->whereNull('av.id')
            ->select('c.name as equipo',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"),
                'loc.completename as ubicacion');

        $sinAntivirus     = $sinAntivirusQuery->count();
        $sinAntivirusLista = (clone $sinAntivirusQuery)->orderBy('c.name')->limit(20)->get();

        // ── Distribución por Sistema Operativo ──────────────────────────────
        $porSO = $glpi->table('glpi_items_operatingsystems as ios')
            ->join('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->join('glpi_computers as c', function($j) {
                $j->on('c.id', '=', 'ios.items_id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->select('os.name', DB::raw('COUNT(*) as total'))
            ->groupBy('os.id', 'os.name')
            ->orderByDesc('total')
            ->get();

        $totalConSO = $porSO->sum('total');

        // ── Distribución por versión de agente ──────────────────────────────
        $porVersionAgente = $glpi->table('glpi_agents as a')
            ->join('glpi_computers as c', 'c.id', '=', 'a.items_id')
            ->where('a.itemtype', 'Computer')
            ->where('c.users_id', '!=', $excluir)
            ->select('a.version', DB::raw('COUNT(*) as total'))
            ->groupBy('a.version')
            ->orderByDesc('total')
            ->get();

        // Versión más reciente
        $versionLatest = $porVersionAgente->max('version');

        // ── Equipos sin comunicación > 90 días ──────────────────────────────
        $inactivos = $glpi->table('glpi_agents as a')
            ->join('glpi_computers as c', 'c.id', '=', 'a.items_id')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->where('a.itemtype', 'Computer')
            ->where('a.last_contact', '<', $hace90)
            ->where('c.is_deleted', 0)
            ->where('c.users_id', '!=', $excluir)
            ->select('c.name as equipo', 'a.last_contact', 'a.version',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"),
                'loc.completename as ubicacion')
            ->orderBy('a.last_contact')
            ->limit(20)->get();

        // ── Equipos sin usuario ──────────────────────────────────────────────
        $sinUsuarioLista = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', 0)
            ->select('c.name as equipo', 'c.serial', 'loc.completename as ubicacion', 'c.date_mod')
            ->orderBy('c.name')->limit(20)->get();

        // ── Equipos sin ubicación ────────────────────────────────────────────
        $sinUbicacionLista = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->where('c.locations_id', 0)
            ->select('c.name as equipo', 'c.serial',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"))
            ->orderBy('c.name')->limit(20)->get();

        // ── Duplicados detalle ───────────────────────────────────────────────
        $duplicadosDetalle = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->whereIn('c.serial', $duplicados->pluck('serial'))
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->whereNotNull('c.serial')->where('c.serial', '!=', '')
            ->select('c.name as equipo', 'c.serial',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"))
            ->orderBy('c.serial')->orderBy('c.name')->limit(40)->get();

        // ── Equipos sin agente ───────────────────────────────────────────────
        $idsConAgente = $glpi->table('glpi_agents')
            ->where('itemtype', 'Computer')->pluck('items_id');

        $sinAgenteLista = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->whereNotIn('c.id', $idsConAgente)
            ->select('c.name as equipo', 'c.serial',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"),
                'loc.completename as ubicacion')
            ->orderBy('c.name')->limit(20)->get();

        // ── Top 10 equipos más antiguos ──────────────────────────────────────
        $masAntiguos = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->whereNotNull('c.date_creation')
            ->select('c.name as equipo', 'c.date_creation', 'man.name as marca',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"))
            ->orderBy('c.date_creation')->limit(10)->get();

        // ── Equipos recientes (último mes) ────────────────────────────────
        $recientes = $glpi->table('glpi_computers as c')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'c.users_id')
            ->leftJoin('glpi_manufacturers as man', 'man.id', '=', 'c.manufacturers_id')
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->where('c.date_creation', '>=', $hace30)
            ->select('c.name as equipo', 'c.date_creation', 'man.name as marca',
                DB::raw("TRIM(CONCAT(IFNULL(u.firstname,''),' ',IFNULL(u.realname,''))) as usuario"))
            ->orderByDesc('c.date_creation')->limit(10)->get();

        // ── Distribución por ubicación ────────────────────────────────────
        $porUbicacion = $glpi->table('glpi_computers as c')
            ->join('glpi_locations as loc', 'loc.id', '=', 'c.locations_id')
            ->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->where('c.users_id', '!=', $excluir)
            ->where('c.locations_id', '!=', 0)
            ->select('loc.completename as ubicacion', DB::raw('COUNT(*) as total'))
            ->groupBy('loc.id', 'loc.completename')
            ->orderByDesc('total')->limit(10)->get();

        return view('inventario_ti.dashboard', compact(
            'totalEquipos', 'sinUsuario', 'sinUbicacion', 'sinAgente',
            'agenteInactivo', 'cantDuplicados', 'sinAntivirus',
            'porSO', 'totalConSO',
            'porVersionAgente', 'versionLatest',
            'inactivos', 'sinUsuarioLista', 'sinUbicacionLista',
            'duplicadosDetalle', 'sinAgenteLista',
            'masAntiguos', 'recientes', 'porUbicacion',
            'sinAntivirusLista'
        ));
    }
}
