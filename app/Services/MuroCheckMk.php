<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Datos del muro de monitoreo (réplica del «Main Dashboard for all» de
 * CheckMK, con aviso de caídas). Todo lo que se ve en vivo sale de tres
 * consultas a livestatus por la API REST:
 *
 *   1. Hosts: estado, si es firme (hard), desde cuándo, contadores de
 *      servicios en warning/crítico y su carpeta de WATO.
 *   2. Servicios con métricas para las tablas y medidores: CPU, memoria,
 *      disco C:, interfaz 1 y uptime.
 *   3. Servicios de las carpetas que muestran un hexágono de servicios (SSL).
 *
 * Qué carpetas y métricas pinta cada panel lo dice config/muro.php; acá solo
 * se juntan los datos, y la pantalla los reparte.
 *
 * Se cachea poco (10 s): es un muro de alarmas y tiene que estar al día, pero
 * si hay varias pantallas abiertas comparten la misma consulta. El fallo se
 * cachea igual que el éxito, para no golpear a CheckMK si está caído.
 */
class MuroCheckMk
{
    private const CACHE_TTL = 10; // segundos

    /** Servicios cuyas métricas se muestran en tablas, medidores y números. */
    private const SERVICIOS_METRICAS = ['CPU utilization', 'Memory', 'Filesystem C:/', 'Interface 1', 'Uptime'];

    public function __construct(private CheckMkClient $cmk = new CheckMkClient()) {}

    /** Estado completo para la pantalla, cacheado. */
    public function estado(): array
    {
        $guardado = Cache::get('muro_estado');
        if ($guardado !== null) return $guardado;

        try {
            $resultado = ['ok' => true] + $this->leer();
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['ok' => false, 'error' => $e->getMessage()];
        }

        $resultado['generado'] = now()->timestamp;
        Cache::put('muro_estado', $resultado, self::CACHE_TTL);

        return $resultado;
    }

    private function leer(): array
    {
        return [
            'hosts'     => $this->hosts(),
            'metricas'  => $this->metricas(),
            'servicios' => $this->serviciosPorCarpeta(),
        ];
    }

    /* ── Hosts ────────────────────────────────────────────────────────────── */

    /**
     * Un registro por host, por nombre.
     *
     * `cambio` es el último cambio de estado FIRME: es la identidad de una
     * caída. La pantalla avisa una sola vez por host+cambio, así que un host
     * que sigue caído no vuelve a sonar, y uno que vuelve y cae de nuevo sí.
     */
    private function hosts(): array
    {
        $filas = $this->cmk->consultarHosts([
            'name', 'state', 'state_type', 'has_been_checked', 'last_hard_state_change',
            'scheduled_downtime_depth', 'num_services_warn', 'num_services_crit', 'filename',
        ]);

        $hosts = [];
        foreach ($filas as $h) {
            $nombre = $h['name'] ?? '';
            if ($nombre === '') continue;

            $hosts[$nombre] = [
                'estado'  => self::estadoHost($h),
                'firme'   => (int) ($h['state_type'] ?? 1) === 1,
                'cambio'  => (int) ($h['last_hard_state_change'] ?? 0),
                'mant'    => (int) ($h['scheduled_downtime_depth'] ?? 0) > 0,
                'wa'      => (int) ($h['num_services_warn'] ?? 0),
                'cr'      => (int) ($h['num_services_crit'] ?? 0),
                'carpeta' => self::carpeta($h['filename'] ?? ''),
            ];
        }

        return $hosts;
    }

    private static function estadoHost(array $h): string
    {
        if (!(int) ($h['has_been_checked'] ?? 1)) return 'pend';

        return match ((int) ($h['state'] ?? 0)) {
            0       => 'up',
            1       => 'down',
            default => 'unreach',
        };
    }

    /** «/wato/server/bd/hosts.mk» → «/server/bd»; la raíz queda como «/». */
    public static function carpeta(string $filename): string
    {
        return preg_replace('#^/wato(/.*?)?/hosts\.mk$#', '$1', $filename) ?: '/';
    }

    /* ── Métricas ─────────────────────────────────────────────────────────── */

    /**
     * Métricas actuales por host, ya en las unidades que muestra la pantalla:
     * porcentajes, bits por segundo y segundos de uptime.
     */
    private function metricas(): array
    {
        $filtro = [
            'op'   => 'or',
            'expr' => array_map(fn($d) => ['op' => '=', 'left' => 'description', 'right' => $d], self::SERVICIOS_METRICAS),
        ];

        $res = [];
        foreach ($this->cmk->consultarServicios(['host_name', 'description', 'perf_data'], $filtro) as $s) {
            $p    = self::perfData($s['perf_data'] ?? '');
            $host = $s['host_name'] ?? '';
            if ($host === '' || !$p) continue;

            $m = &$res[$host];
            switch ($s['description']) {
                case 'CPU utilization':
                    $m['cpu'] = self::redondear($p['util']['valor'] ?? null);
                    break;

                case 'Memory':
                    if (isset($p['mem_used_percent'])) {
                        $m['ram'] = self::redondear($p['mem_used_percent']['valor']);
                    }
                    // Los ESXi informan bytes y no porcentaje. Sus umbrales por
                    // defecto son 80 % y 90 % del total, así que el total sale
                    // del umbral crítico; con eso se dibuja la barra.
                    if (isset($p['mem_used'])) {
                        $m['ram_bytes'] = (float) $p['mem_used']['valor'];
                        $crit = $p['mem_used']['crit'] ?? null;
                        if ($crit && !isset($m['ram'])) {
                            $m['ram_total'] = $crit / 0.9;
                        }
                    }
                    break;

                case 'Filesystem C:/':
                    $m['disco'] = self::redondear($p['fs_used_percent']['valor'] ?? null);
                    break;

                case 'Interface 1':
                    // CheckMK guarda bytes/s; la pantalla muestra bits/s.
                    $m['in_bps']  = isset($p['in']) ? $p['in']['valor'] * 8 : null;
                    $m['out_bps'] = isset($p['out']) ? $p['out']['valor'] * 8 : null;
                    $m['vel_bps'] = isset($p['in']['max']) ? $p['in']['max'] * 8 : null;
                    break;

                case 'Uptime':
                    $m['uptime'] = (int) ($p['uptime']['valor'] ?? 0);
                    break;
            }
            unset($m);
        }

        return $res;
    }

    /**
     * Interpreta el perf_data de Nagios: «nombre=valor;warn;crit;min;max …».
     *
     * @return array<string,array{valor:float,warn:?float,crit:?float,min:?float,max:?float}>
     */
    public static function perfData(string $perf): array
    {
        $res = [];
        preg_match_all("/('[^']+'|[^\\s=]+)=([^\\s]+)/", $perf, $m, PREG_SET_ORDER);

        foreach ($m as [, $nombre, $resto]) {
            $partes = explode(';', $resto);
            $num    = fn($i) => isset($partes[$i]) && is_numeric(rtrim($partes[$i], '%sBbc')) ? (float) rtrim($partes[$i], '%sBbc') : null;

            $valor = $num(0);
            if ($valor === null) continue;

            $res[trim($nombre, "'")] = ['valor' => $valor, 'warn' => $num(1), 'crit' => $num(2), 'min' => $num(3), 'max' => $num(4)];
        }

        return $res;
    }

    private static function redondear(?float $v): ?float
    {
        return $v === null ? null : round($v, 2);
    }

    /* ── Hexágonos de servicios ───────────────────────────────────────────── */

    /**
     * Conteo de servicios por carpeta, para los paneles «hexágono de
     * servicios» de config/muro.php (hoy, SSL Sitios Verfrut).
     */
    private function serviciosPorCarpeta(): array
    {
        $res = [];
        foreach ($this->carpetasDeTipo('hexagono_servicios') as $carpeta) {
            $filas = $this->cmk->consultarServicios(
                ['state', 'scheduled_downtime_depth', 'host_state'],
                ['op' => '~', 'left' => 'host_filename', 'right' => '^/wato' . preg_quote($carpeta, '/') . '/']
            );

            $c = ['ok' => 0, 'warn' => 0, 'crit' => 0, 'unknown' => 0, 'mant' => 0, 'host_caido' => 0, 'total' => $filas->count()];
            foreach ($filas as $s) {
                if ((int) $s['scheduled_downtime_depth'] > 0) { $c['mant']++; continue; }
                if ((int) $s['host_state'] !== 0)             { $c['host_caido']++; continue; }
                $c[[0 => 'ok', 1 => 'warn', 2 => 'crit', 3 => 'unknown'][(int) $s['state']] ?? 'unknown']++;
            }
            $res[$carpeta] = $c;
        }

        return $res;
    }

    /** Carpetas de todos los paneles de un tipo. */
    private function carpetasDeTipo(string $tipo): Collection
    {
        return $this->panelesDeTipo($tipo)->pluck('carpeta')->unique()->values();
    }

    /** Todos los paneles de un tipo, recorriendo las filas anidadas. */
    private function panelesDeTipo(string $tipo): Collection
    {
        $res = collect();
        $recorrer = function (array $paneles) use (&$recorrer, &$res, $tipo) {
            foreach ($paneles as $p) {
                if (($p['tipo'] ?? null) === 'fila') { $recorrer($p['paneles']); continue; }
                if (($p['tipo'] ?? null) === $tipo)  { $res->push($p); }
            }
        };
        foreach (config('muro.areas', []) as $paneles) $recorrer($paneles);

        return $res;
    }

    /* ── Gráficos históricos ──────────────────────────────────────────────── */

    /**
     * Qué se dibuja en cada clase de gráfico: el servicio de CheckMK y sus
     * series. Una serie con varias métricas es su suma; la CPU total va así
     * porque la API no entrega `util` como serie (ver seriesHistoricas()).
     */
    private const CLASES_GRAFICO = [
        'cpu'         => ['servicio' => 'CPU utilization', 'series' => ['CPU utilization' => ['user', 'privileged']]],
        'ram'         => ['servicio' => 'Memory',          'series' => ['RAM used' => ['mem_used']]],
        'ancho_banda' => ['servicio' => 'Interface 1',     'series' => ['Entrada' => ['if_in_bps'], 'Salida' => ['if_out_bps']]],
        'perdida'     => ['servicio' => 'PING',            'series' => ['Packet loss' => ['pl']]],
    ];

    /**
     * Series de todos los gráficos del muro, por título del panel.
     *
     * Se piden todas en paralelo y se cachean un minuto: los gráficos de
     * CheckMK avanzan de a un punto por minuto, así que pedirlas más seguido
     * no muestra nada nuevo.
     */
    public function graficos(): array
    {
        $guardado = Cache::get('muro_graficos');
        if ($guardado !== null) return $guardado;

        try {
            $resultado = ['ok' => true, 'graficos' => $this->leerGraficos()];
            $ttl = 60;
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['ok' => false, 'error' => $e->getMessage()];
            $ttl = 30;
        }

        Cache::put('muro_graficos', $resultado, $ttl);

        return $resultado;
    }

    private function leerGraficos(): array
    {
        $hasta    = now()->timestamp;
        $paneles  = $this->panelesDeTipo('grafico')->filter(fn($p) => isset($p['clase'], $p['host']))->values();
        $pedidos  = [];

        foreach ($paneles as $i => $p) {
            $clase = self::CLASES_GRAFICO[$p['clase']];
            foreach ($clase['series'] as $metricas) {
                foreach ($metricas as $m) {
                    $pedidos["{$i}|{$m}"] = [
                        'host' => $p['host'], 'servicio' => $clase['servicio'], 'metrica' => $m,
                        'desde' => $hasta - $p['minutos'] * 60, 'hasta' => $hasta,
                    ];
                }
            }
        }

        $series = $this->cmk->seriesHistoricas($pedidos);

        $res = [];
        foreach ($paneles as $i => $p) {
            $clase  = self::CLASES_GRAFICO[$p['clase']];
            $salida = ['clase' => $p['clase'], 'inicio' => null, 'paso' => null, 'series' => []];

            foreach ($clase['series'] as $nombre => $metricas) {
                $partes = array_map(fn($m) => $series["{$i}|{$m}"] ?? null, $metricas);
                if (in_array(null, $partes, true)) continue;   // falta alguna métrica: la serie no se dibuja

                $salida['inicio'] ??= $partes[0]['inicio'];
                $salida['paso']   ??= $partes[0]['paso'];
                $salida['series'][] = ['nombre' => $nombre, 'datos' => self::sumar(array_column($partes, 'datos'))];
            }

            $res[$p['titulo']] = $salida;
        }

        return $res;
    }

    /** Suma punto a punto; si a una métrica le falta un punto, ese punto queda vacío. */
    private static function sumar(array $listas): array
    {
        if (count($listas) === 1) return $listas[0];

        $largo = min(array_map('count', $listas));
        $res   = [];
        for ($k = 0; $k < $largo; $k++) {
            $valores = array_column($listas, $k);
            $res[]   = in_array(null, $valores, true) ? null : array_sum($valores);
        }

        return $res;
    }
}
