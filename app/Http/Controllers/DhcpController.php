<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\DhcpImportacion;
use App\Models\DhcpReserva;
use App\Models\DhcpScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DhcpController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function umbral(): int
    {
        return (int) (Configuracion::get('dhcp_umbral_dias') ?: 90);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $umbral   = $this->umbral();
        $scopes   = DhcpScope::orderBy('nombre')->get();
        $ultimaImp = DhcpImportacion::latest('recibido_at')->first();

        $totalReservas = DhcpReserva::where('activa', true)->count();

        // Reservas inactivas > umbral
        $limite = now()->subDays($umbral);
        $inactivas = DhcpReserva::where('activa', true)
            ->where(function ($q) use ($limite) {
                $q->where('ultima_actividad', '<', $limite)
                  ->orWhereNull('ultima_actividad');
            })
            ->count();

        // Vivas por ping pero sin lease DHCP (probables IP estáticas)
        $vivasPorPing = DhcpReserva::where('activa', true)
            ->where('visto_ping', true)
            ->where('visto_activa', false)
            ->count();

        // Datos "frescos" si el último snapshot llegó hace < 12h
        $datosFrescos = $ultimaImp && $ultimaImp->recibido_at->gt(now()->subHours(12));

        return view('dhcp.dashboard', compact(
            'scopes', 'ultimaImp', 'totalReservas', 'inactivas', 'vivasPorPing', 'umbral', 'datosFrescos'
        ));
    }

    // ── Listado de reservas ───────────────────────────────────────────────────

    public function reservas(Request $request)
    {
        $umbral = $this->umbral();
        $limite = now()->subDays($umbral);

        $query = DhcpReserva::with('scope');

        // Filtro por scope
        if ($request->filled('scope')) {
            $query->where('scope_id', $request->input('scope'));
        }

        // Filtro por estado
        $estado = $request->input('estado', 'todas');
        if ($estado === 'inactivas') {
            $query->where('activa', true)->where(function ($q) use ($limite) {
                $q->where('ultima_actividad', '<', $limite)->orWhereNull('ultima_actividad');
            });
        } elseif ($estado === 'activas') {
            $query->where('activa', true)->where('visto_activa', true);
        } elseif ($estado === 'eliminadas') {
            $query->where('activa', false);
        } else {
            $query->where('activa', true);
        }

        // Búsqueda
        if ($request->filled('buscar')) {
            $b = $request->input('buscar');
            $query->where(function ($q) use ($b) {
                $q->where('ip', 'like', "%$b%")
                  ->orWhere('mac', 'like', "%$b%")
                  ->orWhere('nombre', 'like', "%$b%")
                  ->orWhere('descripcion', 'like', "%$b%");
            });
        }

        // Orden: más inactivas primero (nulls primero = nunca vistas)
        $reservas = $query->orderByRaw('ultima_actividad IS NULL DESC')
            ->orderBy('ultima_actividad', 'asc')
            ->paginate(30)
            ->withQueryString();

        $scopes = DhcpScope::orderBy('nombre')->get();

        // Conteos para los filtros (respetan el scope seleccionado)
        $scopeSel = $request->input('scope');
        $base = fn() => DhcpReserva::when($scopeSel, fn($q) => $q->where('scope_id', $scopeSel));

        $countTodas      = $base()->where('activa', true)->count();
        $countInactivas  = $base()->where('activa', true)
            ->where(fn($q) => $q->where('ultima_actividad', '<', $limite)->orWhereNull('ultima_actividad'))
            ->count();
        $countActivas    = $base()->where('activa', true)->where('visto_activa', true)->count();
        $countEliminadas = $base()->where('activa', false)->count();

        return view('dhcp.reservas', compact(
            'reservas', 'scopes', 'estado', 'umbral',
            'countTodas', 'countInactivas', 'countActivas', 'countEliminadas'
        ));
    }

    // ── Configuración del módulo (token + umbral) ─────────────────────────────

    public function configuracion()
    {
        $token       = Configuracion::get('dhcp_token');
        $umbral      = $this->umbral();
        $endpoint    = url('api/dhcp/importar');

        return view('dhcp.configuracion', compact('token', 'umbral', 'endpoint'));
    }

    public function guardarConfig(Request $request)
    {
        if ($request->input('accion') === 'regenerar_token') {
            $token = Str::random(48);
            Configuracion::set('dhcp_token', $token);
            return back()->with('success', 'Token regenerado. Actualízalo en el script del servidor DHCP.');
        }

        $request->validate([
            'umbral_dias' => 'required|integer|between:1,3650',
        ]);
        Configuracion::set('dhcp_umbral_dias', $request->input('umbral_dias'));

        // Generar token la primera vez si no existe
        if (!Configuracion::get('dhcp_token')) {
            Configuracion::set('dhcp_token', Str::random(48));
        }

        return back()->with('success', 'Configuración DHCP guardada.');
    }
}
