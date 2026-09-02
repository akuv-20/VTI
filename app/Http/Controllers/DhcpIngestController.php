<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\DhcpImportacion;
use App\Models\DhcpReserva;
use App\Models\DhcpScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DhcpIngestController extends Controller
{
    /** Recibe el snapshot del script PowerShell del servidor DHCP */
    public function store(Request $request)
    {
        // ── Autenticación por token ──────────────────────────────────────────
        $tokenConfig = Configuracion::get('dhcp_token');
        $tokenHeader = $request->header('X-DHCP-Token') ?: $request->input('token');

        if (!$tokenConfig || !$tokenHeader || !hash_equals($tokenConfig, $tokenHeader)) {
            return response()->json(['ok' => false, 'error' => 'Token inválido.'], 401);
        }

        $data = $request->validate([
            'generado_at'          => 'nullable|date',
            'scopes'               => 'required|array|min:1',
            'scopes.*.scope_id'    => 'required|string|max:20',
            'scopes.*.reservas'    => 'nullable|array',
        ]);

        $now       = now();
        $generado  = !empty($data['generado_at']) ? Carbon::parse($data['generado_at']) : $now;
        $ipsVistas = [];
        $scopeIds  = [];
        $totalReservas = 0;
        $totalActivas  = 0;

        DB::transaction(function () use ($request, $now, $generado, &$ipsVistas, &$scopeIds, &$totalReservas, &$totalActivas) {
            foreach ($request->input('scopes') as $scope) {
                $scopeId    = $scope['scope_id'];
                $scopeIds[] = $scopeId;
                $reservas   = $scope['reservas'] ?? [];

                DhcpScope::updateOrCreate(
                    ['scope_id' => $scopeId],
                    [
                        'nombre'            => $scope['nombre']            ?? null,
                        'descripcion'       => $scope['descripcion']       ?? null,
                        'subnet_mask'       => $scope['subnet_mask']       ?? null,
                        'rango_inicio'      => $scope['rango_inicio']      ?? null,
                        'rango_fin'         => $scope['rango_fin']         ?? null,
                        'estado'            => $scope['estado']            ?? 'Active',
                        'total_direcciones' => (int) ($scope['total_direcciones'] ?? 0),
                        'en_uso'            => (int) ($scope['en_uso']     ?? 0),
                        'libres'            => (int) ($scope['libres']     ?? 0),
                        'porcentaje_uso'    => (float) ($scope['porcentaje_uso'] ?? 0),
                        'reservas_count'    => count($reservas),
                        'actualizado_at'    => $now,
                    ]
                );

                foreach ($reservas as $r) {
                    if (empty($r['ip'])) continue;
                    $ip          = $r['ip'];
                    $ipsVistas[] = $ip;
                    $activa      = (bool) ($r['activa'] ?? false);
                    $leaseExp    = !empty($r['lease_expira']) ? Carbon::parse($r['lease_expira']) : null;
                    $totalReservas++;
                    if ($activa) $totalActivas++;

                    $reserva = DhcpReserva::firstOrNew(['ip' => $ip]);
                    $esNueva = !$reserva->exists;

                    $reserva->scope_id     = $scopeId;
                    $reserva->mac          = $r['mac']         ?? $reserva->mac;
                    $reserva->nombre       = $r['nombre']      ?? null;
                    $reserva->descripcion  = $r['descripcion'] ?? null;
                    $reserva->visto_activa = $activa;
                    $reserva->lease_expira = $leaseExp;
                    $reserva->activa       = true;

                    if ($esNueva) {
                        $reserva->primera_vez_visto = $now;
                        // Semilla de última actividad: ahora si activa, si no el lease_expira conocido
                        $reserva->ultima_actividad = $activa ? $now : $leaseExp;
                    } elseif ($activa) {
                        $reserva->ultima_actividad = $now;
                    }
                    // Si existe e inactiva: se conserva la última_actividad previa

                    $reserva->save();
                }
            }

            // Reservas de los scopes recibidos que ya no llegaron → eliminadas del DHCP
            DhcpReserva::whereIn('scope_id', $scopeIds)
                ->when(!empty($ipsVistas), fn($q) => $q->whereNotIn('ip', $ipsVistas))
                ->update(['activa' => false]);
        });

        DhcpImportacion::create([
            'recibido_at'      => $now,
            'generado_at'      => $generado,
            'scopes_count'     => count($scopeIds),
            'reservas_count'   => $totalReservas,
            'reservas_activas' => $totalActivas,
            'origen_ip'        => $request->ip(),
        ]);

        return response()->json([
            'ok'       => true,
            'scopes'   => count($scopeIds),
            'reservas' => $totalReservas,
            'activas'  => $totalActivas,
        ]);
    }
}
