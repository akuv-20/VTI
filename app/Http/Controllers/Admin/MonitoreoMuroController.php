<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\MesaAyuda;
use App\Services\MuroCheckMk;
use App\Services\RankingTickets;
use App\Services\ServiciosCriticos;
use Illuminate\Http\Request;

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
            'urlServicios' => route('admin.monitoreo.muro.servicios'),
            'urlCarpeta'   => route('admin.monitoreo.muro.carpeta'),
            'urlEnlaces'   => route('admin.monitoreo.muro.enlaces'),
            'urlMesa'      => route('admin.monitoreo.muro.mesa'),
            'urlEnlace'    => route('admin.monitoreo.muro.enlace'),
            // Enlace a CheckMK desde el modal; en modo TV no hay quien lo use.
            'urlCheckmk'   => ($u = rtrim((string) Configuracion::get('checkmk_url', ''), '/')) && ($s = trim((string) Configuracion::get('checkmk_site', '')))
                                ? "{$u}/{$s}/check_mk/" : null,
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

    /** Detalle de la carpeta de un hexágono, para el modal (ver MuroCheckMk::detalleCarpeta). */
    public function carpeta(Request $request, MuroCheckMk $muro)
    {
        $d = $muro->detalleCarpeta((string) $request->query('carpeta'));
        abort_if($d === null, 404);

        return response()->json($d);
    }

    public function enlaces(MuroCheckMk $muro)
    {
        return response()->json($muro->enlaces());
    }

    public function enlace(Request $request, MuroCheckMk $muro)
    {
        $d = $muro->serieEnlace((string) $request->query('host'), (int) $request->query('horas', 4));
        abort_if($d === null, 404);

        return response()->json($d);
    }

    public function mesa(MesaAyuda $mesa)
    {
        return response()->json($mesa->estado());
    }

    public function tvMesa(string $token, MesaAyuda $mesa)
    {
        $this->validarTokenTv($token);

        return response()->json($mesa->estado());
    }

    public function ranking()
    {
        return response()->json($this->podio());
    }

    public function servicios(ServiciosCriticos $servicios)
    {
        return response()->json($servicios->estado(config('muro.servicios', [])));
    }

    /* ── Modo TV (público, autenticado por token) ─────────────────────────── */

    public function tv(string $token)
    {
        $this->validarTokenTv($token);

        return view('admin.monitoreo.muro', [
            'urlEstado'   => route('monitoreo.tv.muro.estado', $token),
            'urlGraficos' => route('monitoreo.tv.muro.graficos', $token),
            'urlRanking'  => route('monitoreo.tv.muro.ranking', $token),
            'urlServicios' => route('monitoreo.tv.muro.servicios', $token),
            'urlCarpeta'   => route('monitoreo.tv.muro.carpeta', $token),
            'urlEnlaces'   => route('monitoreo.tv.muro.enlaces', $token),
            'urlMesa'      => route('monitoreo.tv.muro.mesa', $token),
            'urlEnlace'    => route('monitoreo.tv.muro.enlace', $token),
            'urlCheckmk'   => null,
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

    public function tvEnlaces(string $token, MuroCheckMk $muro)
    {
        $this->validarTokenTv($token);

        return $this->enlaces($muro);
    }

    public function tvEnlace(string $token, Request $request, MuroCheckMk $muro)
    {
        $this->validarTokenTv($token);

        return $this->enlace($request, $muro);
    }

    public function tvCarpeta(string $token, Request $request, MuroCheckMk $muro)
    {
        $this->validarTokenTv($token);

        return $this->carpeta($request, $muro);
    }

    public function tvServicios(string $token, ServiciosCriticos $servicios)
    {
        $this->validarTokenTv($token);

        return response()->json($servicios->estado(config('muro.servicios', [])));
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
