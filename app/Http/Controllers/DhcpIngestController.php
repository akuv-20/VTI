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

        if (!$tokenConfig) {
            return response()->json(['ok' => false, 'error' => 'No hay token configurado en VTI. Genéralo en Redes → DHCP → Configuración.'], 401);
        }
        if (!$tokenHeader) {
            return response()->json(['ok' => false, 'error' => 'Falta la cabecera X-DHCP-Token en la petición.'], 401);
        }
        if (!hash_equals($tokenConfig, trim($tokenHeader))) {
            return response()->json(['ok' => false, 'error' => 'El token no coincide con el configurado en VTI.'], 401);
        }

        try {
            return $this->procesar($request);
        } catch (\Throwable $e) {
            // Devolver el mensaje real al script (en vez de un 500 genérico)
            \Illuminate\Support\Facades\Log::error('DHCP ingesta: ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Normaliza un valor de PowerShell a lista (PS 5.1 colapsa arrays de 1 elemento en objeto) */
    private function comoLista($valor): array
    {
        if (empty($valor)) return [];                 // null o "" (array vacío de PS)
        if (array_is_list($valor)) return $valor;     // ya es lista
        return [$valor];                              // objeto único → envolver
    }

    private function procesar(Request $request)
    {
        $scopesRaw = $this->comoLista($request->input('scopes'));

        if (empty($scopesRaw)) {
            return response()->json(['ok' => false, 'error' => 'No se recibieron scopes.'], 422);
        }

        $now       = now();
        $genRaw    = $request->input('generado_at');
        $generado  = !empty($genRaw) ? Carbon::parse($genRaw) : $now;
        $ipsVistas = [];
        $scopeIds  = [];
        $totalReservas = 0;
        $totalActivas  = 0;

        DB::transaction(function () use ($scopesRaw, $now, &$ipsVistas, &$scopeIds, &$totalReservas, &$totalActivas) {
            foreach ($scopesRaw as $scope) {
                $scopeId    = (string) ($scope['scope_id'] ?? '');
                if ($scopeId === '') continue;
                $scopeIds[] = $scopeId;
                $reservas   = $this->comoLista($scope['reservas'] ?? []);

                DhcpScope::updateOrCreate(
                    ['scope_id' => $scopeId],
                    [
                        'nombre'            => isset($scope['nombre'])      ? mb_substr((string) $scope['nombre'], 0, 150)      : null,
                        'descripcion'       => isset($scope['descripcion']) ? mb_substr((string) $scope['descripcion'], 0, 255) : null,
                        'subnet_mask'       => $scope['subnet_mask']       ?? null,
                        'rango_inicio'      => $scope['rango_inicio']      ?? null,
                        'rango_fin'         => $scope['rango_fin']         ?? null,
                        'estado'            => isset($scope['estado']) ? mb_substr((string) $scope['estado'], 0, 20) : 'Active',
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
                    $reserva->mac          = $r['mac'] ? mb_substr($r['mac'], 0, 40) : $reserva->mac;
                    $reserva->nombre       = isset($r['nombre'])      ? mb_substr((string) $r['nombre'], 0, 150)      : null;
                    $reserva->descripcion  = isset($r['descripcion']) ? mb_substr((string) $r['descripcion'], 0, 255) : null;
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
