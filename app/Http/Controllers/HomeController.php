<?php

namespace App\Http\Controllers;

use App\Models\LineaTelefonica;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $hoy       = Carbon::now();
        $mes       = $hoy->month;
        $anio      = $hoy->year;
        $periodoLabel = ucfirst($hoy->locale('es')->isoFormat('MMMM YYYY'));

        // ── Telefonía ────────────────────────────────────────────────────
        $lineasEntel    = LineaTelefonica::where('estado', 'Activo')
                            ->whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%Entel%'))
                            ->count();
        $lineasMovistar = LineaTelefonica::where('estado', 'Activo')
                            ->whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%Movistar%'))
                            ->count();
        $lineasWOM      = LineaTelefonica::where('estado', 'Activo')
                            ->whereHas('emisor', fn($q) => $q->where('nombre', 'like', '%WOM%'))
                            ->count();
        $lineasHuerfanas = LineaTelefonica::where('estado', 'Activo')
                            ->whereNull('id_usuario')
                            ->count();

        // ── Facturación — mes en curso ────────────────────────────────────
        $serviciosPeriodicos      = Servicio::where('es_periodico', true)->count();
        $serviciosFacturadosMes   = Servicio::where('es_periodico', true)
                            ->whereHas('facturas', fn($q) => $q
                                ->whereMonth('fecha_emision', $mes)
                                ->whereYear('fecha_emision', $anio))
                            ->count();
        $serviciosSinFacturarMes  = Servicio::where('es_periodico', true)
                            ->whereDoesntHave('facturas', fn($q) => $q
                                ->whereMonth('fecha_emision', $mes)
                                ->whereYear('fecha_emision', $anio))
                            ->count();

        return view('home', compact(
            'periodoLabel',
            'lineasEntel', 'lineasMovistar', 'lineasWOM', 'lineasHuerfanas',
            'serviciosPeriodicos', 'serviciosFacturadosMes', 'serviciosSinFacturarMes'
        ));
    }
}
