<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\MuroCheckMk;

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

    /* ── Modo TV (público, autenticado por token) ─────────────────────────── */

    public function tv(string $token)
    {
        $this->validarTokenTv($token);

        return view('admin.monitoreo.muro', [
            'urlEstado'   => route('monitoreo.tv.muro.estado', $token),
            'urlGraficos' => route('monitoreo.tv.muro.graficos', $token),
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

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

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
