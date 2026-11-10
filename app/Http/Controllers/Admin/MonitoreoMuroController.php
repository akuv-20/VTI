<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\MuroCheckMk;
use App\Services\RankingTickets;

/**
 * Muro de monitoreo: réplica del dashboard principal de CheckMK para la TV,
 * con aviso de caídas (la fila late, crece y suena una sola vez).
 *
 * Dos formas de abrirlo:
 *   - Con sesión, desde el menú de Monitoreo.
 *   - En modo TV, sin login, con el mismo token global que ya usan los mapas
 *     de red (Configuración `monitoreo_tv_token`).
 */
class MonitoreoMuroController extends Controller
{
    public function index()
    {
        $token = (string) Configuracion::get('monitoreo_tv_token', '');

        return view('admin.monitoreo.muro', [
            'urlEstado'   => route('admin.monitoreo.muro.estado'),
            'urlGraficos' => route('admin.monitoreo.muro.graficos'),
            'urlRanking'  => route('admin.monitoreo.muro.ranking'),
            'urlTv'       => $token !== '' ? route('monitoreo.tv.muro', $token) : null,
            'modoTv'    => false,
        ] + $this->config());
    }

    public function estado(MuroCheckMk $muro)
    {
        return response()->json($muro->estado());
    }

    public function graficos(MuroCheckMk $muro)
    {
        return response()->json($muro->graficos());
    }

    public function ranking()
    {
        return response()->json($this->podio());
    }

    /* ── Modo TV (público, autenticado por token) ─────────────────────────── */

    public function tv(string $token)
    {
        $this->validarTokenTv($token);

        return view('admin.monitoreo.muro', [
            'urlEstado'   => route('monitoreo.tv.muro.estado', $token),
            'urlGraficos' => route('monitoreo.tv.muro.graficos', $token),
            'urlRanking'  => route('monitoreo.tv.muro.ranking', $token),
            'urlTv'       => null,
            'modoTv'    => true,
        ] + $this->config());
    }

    public function tvEstado(string $token, MuroCheckMk $muro)
    {
        $this->validarTokenTv($token);

        return response()->json($muro->estado());
    }

    public function tvGraficos(string $token, MuroCheckMk $muro)
    {
        $this->validarTokenTv($token);

        return response()->json($muro->graficos());
    }

    public function tvRanking(string $token)
    {
        $this->validarTokenTv($token);

        return response()->json($this->podio());
    }

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

    /**
     * Podio del mes en curso por país, para el panel «ranking» del muro: los
     * tres primeros de cada uno, con lo justo para pintarlos. El servicio ya
     * cachea las consultas a GLPI.
     */
    private function podio(): array
    {
        $hoy = now(RankingTickets::ZONA);
        $servicio = new RankingTickets();
        $paises = [];

        foreach (array_keys(RankingTickets::PAISES) as $pais) {
            $r = $servicio->calcular($hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth(), null, $pais);
            $paises[$pais] = [
                'error'    => $r['error'] !== null,
                'tecnicos' => $r['tecnicos']->take(3)->map(fn($t) => [
                    'nombre'    => $t['nombre'],
                    'iniciales' => $t['iniciales'],
                    'total'     => $t['total'],
                    'puesto'    => $t['puesto'],
                    // Mismo color que en la pantalla del ranking.
                    'color'     => 'hsl(' . (crc32((string) $t['clave']) % 360) . ', 55%, 45%)',
                ])->values()->all(),
            ];
        }

        return [
            'mes'    => $hoy->copy()->locale('es')->isoFormat('MMMM'),
            'paises' => $paises,
        ];
    }

    private function config(): array
    {
        return [
            'areas'     => config('muro.areas'),
            'intervalo' => (int) config('muro.intervalo', 15),
            'resaltado' => (int) config('muro.resaltado', 12),
        ];
    }

    /** Mismo criterio que el modo TV de los mapas: token global, y 404 si no calza. */
    private function validarTokenTv(string $token): void
    {
        $real = (string) Configuracion::get('monitoreo_tv_token', '');
        abort_if($real === '' || !hash_equals($real, $token), 404);
    }
}
