<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Estado de los servicios externos críticos para el panel «servicios» del
 * muro: Microsoft 365 (Exchange, Teams…), sitios sin página de estado como el
 * SII, y proveedores con página de estado pública (Claude, ChatGPT).
 *
 * Cada servicio se declara en config/muro.php con una de estas fuentes:
 *
 *   m365        Service Health de Microsoft Graph. `id` es el del servicio
 *               en healthOverviews (Exchange, MicrosoftTeams, SharePoint…).
 *               Requiere el permiso de aplicación ServiceHealth.Read.All.
 *   statuspage  Página de estado compatible con Statuspage (summary.json).
 *               Si se indica `componentes`, solo se miran los que contengan
 *               alguno de esos textos.
 *   http        Se pide la URL: responde bien y rápido, lento, o no responde.
 *
 * Todo va cacheado y el fallo se cachea igual que el éxito (más corto): con
 * un proveedor caído, cada sondeo esperaría el timeout.
 *
 * Estados normalizados: ok · aviso · critico · mant · desconocido.
 */
class ServiciosCriticos
{
    private const TTL       = 120;
    private const TTL_ERROR = 60;

    /** Sobre esto, un sitio que responde bien se marca como lento. */
    private const LENTO_MS = 3000;

    /** @param array<int, array> $servicios Declaraciones de config/muro.php */
    public function estado(array $servicios): array
    {
        return array_map(fn($s) => ['nombre' => $s['nombre']] + match ($s['fuente']) {
            'm365'       => $this->m365($s),
            'statuspage' => $this->statuspage($s),
            'http'       => $this->http($s),
            default      => ['estado' => 'desconocido', 'detalle' => 'fuente desconocida'],
        }, $servicios);
    }

    /* ── Caché ────────────────────────────────────────────────────────────── */

    /** La consulta devuelve ['ok' => bool, ...]; si lanza, se guarda el error. */
    private function recordar(string $clave, \Closure $consulta): array
    {
        $guardado = Cache::get($clave);
        if ($guardado !== null) return $guardado;

        try {
            $r = $consulta() + ['ok' => true];
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'error' => $e->getMessage()];
        }
        Cache::put($clave, $r, $r['ok'] ? self::TTL : self::TTL_ERROR);

        return $r;
    }

    /* ── Microsoft 365 ────────────────────────────────────────────────────── */

    private function m365(array $s): array
    {
        $datos = $this->recordar('servicios_criticos_m365', function () {
            $r = (new GraphClient())->get(GraphClient::BASE . '/admin/serviceAnnouncement/healthOverviews', 20);
            if ($r->status() === 403) return ['ok' => false, 'error' => 'permiso'];
            if (!$r->successful()) throw new \RuntimeException('Graph respondió ' . $r->status());

            return ['servicios' => collect($r->json('value') ?? [])
                ->mapWithKeys(fn($v) => [mb_strtolower($v['id']) => $v['status']])->all()];
        });

        if (!$datos['ok']) {
            return $datos['error'] === 'permiso'
                ? ['estado' => 'desconocido', 'detalle' => 'sin permiso Azure']
                : ['estado' => 'desconocido', 'detalle' => 'sin conexión con Microsoft'];
        }

        $estado = $datos['servicios'][mb_strtolower($s['id'])] ?? null;
        if ($estado === null) return ['estado' => 'desconocido', 'detalle' => 'servicio no encontrado'];

        return match ($estado) {
            'serviceOperational', 'serviceRestored', 'falsePositive', 'postIncidentReviewPublished', 'investigationSuspended'
                                  => ['estado' => 'ok',      'detalle' => 'Operativo'],
            'serviceInterruption' => ['estado' => 'critico', 'detalle' => 'Interrupción'],
            'investigating'       => ['estado' => 'aviso',   'detalle' => 'En investigación'],
            'restoringService', 'extendedRecovery'
                                  => ['estado' => 'aviso',   'detalle' => 'Restableciendo'],
            'verifyingService'    => ['estado' => 'aviso',   'detalle' => 'Verificando'],
            default               => ['estado' => 'aviso',   'detalle' => 'Degradado'],
        };
    }

    /* ── Páginas de estado (Statuspage) ───────────────────────────────────── */

    private const ESTADO_COMPONENTE = [
        'operational'          => [0, 'ok'],
        'under_maintenance'    => [1, 'mant'],
        'degraded_performance' => [2, 'aviso'],
        'partial_outage'       => [3, 'aviso'],
        'major_outage'         => [4, 'critico'],
    ];
    private const TEXTO_COMPONENTE = [
        'under_maintenance'    => 'mantención',
        'degraded_performance' => 'degradado',
        'partial_outage'       => 'caída parcial',
        'major_outage'         => 'caída',
    ];

    private function statuspage(array $s): array
    {
        $datos = $this->recordar('servicios_criticos_sp_' . md5($s['url']), function () use ($s) {
            $r = Http::timeout(8)->withHeaders(['User-Agent' => 'VTI-Monitoreo'])->get($s['url']);
            if (!$r->successful()) throw new \RuntimeException('respondió ' . $r->status());

            return [
                'componentes' => collect($r->json('components') ?? [])
                    ->reject(fn($c) => $c['group'] ?? false)
                    ->map(fn($c) => ['nombre' => $c['name'], 'estado' => $c['status']])
                    ->values()->all(),
                // Incidentes abiertos, del más grave al más leve. El componente
                // marcado suele ser amplio («claude.ai») aunque falle una parte
                // chica; el nombre del incidente dice qué es lo que falla.
                'incidentes' => collect($r->json('incidents') ?? [])
                    ->reject(fn($i) => in_array($i['status'] ?? '', ['resolved', 'postmortem'], true))
                    ->sortByDesc(fn($i) => ['critical' => 3, 'major' => 2, 'minor' => 1][$i['impact'] ?? ''] ?? 0)
                    ->pluck('name')->values()->all(),
            ];
        });

        if (!$datos['ok']) return ['estado' => 'desconocido', 'detalle' => 'sin conexión'];
        $incidentes = $datos['incidentes'] ?? [];

        $filtro = $s['componentes'] ?? null;
        $peores = collect($datos['componentes'])
            ->filter(fn($c) => !$filtro || collect($filtro)->contains(fn($f) => stripos($c['nombre'], $f) !== false))
            ->reject(fn($c) => $c['estado'] === 'operational')
            ->sortByDesc(fn($c) => self::ESTADO_COMPONENTE[$c['estado']][0] ?? 2)
            ->values();

        if ($peores->isEmpty()) {
            // Un incidente sin componentes afectados (avisos, investigaciones) se informa sin alarmar.
            return ['estado' => 'ok', 'detalle' => $incidentes ? 'Operativo · ' . $incidentes[0] : 'Operativo'];
        }

        $peor = $peores->first();
        if ($incidentes) {
            $detalle = $incidentes[0] . (count($incidentes) > 1 ? ' (+' . (count($incidentes) - 1) . ')' : '');
        } else {
            $detalle = $peor['nombre'] . ': ' . (self::TEXTO_COMPONENTE[$peor['estado']] ?? $peor['estado']);
            if ($peores->count() > 1) $detalle .= ' (+' . ($peores->count() - 1) . ')';
        }

        return ['estado' => self::ESTADO_COMPONENTE[$peor['estado']][1] ?? 'aviso', 'detalle' => $detalle];
    }

    /* ── Sitios sin página de estado ──────────────────────────────────────── */

    private function http(array $s): array
    {
        // Un corte aislado (un timeout suelto) no pinta de rojo: hacen falta
        // dos consultas fallidas seguidas, unos 2 minutos.
        $claveFallos = 'servicios_criticos_http_fallos_' . md5($s['url']);

        $datos = $this->recordar('servicios_criticos_http_' . md5($s['url']), function () use ($s, $claveFallos) {
            $t0 = microtime(true);
            try {
                $r = Http::connectTimeout(5)->timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0 (VTI-Monitoreo)'])->get($s['url']);
                $resultado = ['codigo' => $r->status(), 'ms' => (int) round((microtime(true) - $t0) * 1000)];
            } catch (\Throwable) {
                // Que no responda es un resultado, no un error de la consulta.
                $resultado = ['codigo' => 0, 'ms' => null];
            }

            $fallo = $resultado['codigo'] === 0 || $resultado['codigo'] >= 400;
            $fallos = $fallo ? (int) Cache::get($claveFallos, 0) + 1 : 0;
            Cache::put($claveFallos, $fallos, 3600);

            return $resultado + ['fallos' => $fallos];
        });

        $falla = !$datos['ok'] ? 'No responde'
            : ($datos['codigo'] === 0 ? 'No responde' : ($datos['codigo'] >= 400 ? 'Error ' . $datos['codigo'] : null));
        if ($falla !== null) {
            return ($datos['fallos'] ?? 2) >= 2
                ? ['estado' => 'critico', 'detalle' => $falla]
                : ['estado' => 'aviso', 'detalle' => $falla . ' · reintentando'];
        }
        if ($datos['ms'] > self::LENTO_MS) return ['estado' => 'aviso', 'detalle' => 'Lento · ' . number_format($datos['ms'] / 1000, 1, ',', '') . ' s'];

        return ['estado' => 'ok', 'detalle' => $datos['ms'] . ' ms'];
    }
}
