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
 * Cada parte se cachea según cuánto cambia, y todas las pantallas abiertas
 * comparten esa caché:
 *   - Hosts, 4 s: es lo que dispara la alarma, y CheckMK (smart ping) detecta
 *     una caída en segundos. Es una consulta liviana.
 *   - Métricas y servicios, 30 s: CheckMK solo los actualiza al chequear el
 *     servicio (normalmente una vez por minuto); pedirlos más seguido no trae
 *     nada nuevo y es la consulta más pesada.
 * El fallo se cachea igual que el éxito, para no golpear a CheckMK si está caído.
 */
class MuroCheckMk
{
    private const CACHE_HOSTS    = 4;  // segundos
    private const CACHE_METRICAS = 30;

    /** Servicios cuyas métricas se muestran en tablas, medidores y números. */
    private const SERVICIOS_METRICAS = ['CPU utilization', 'Memory', 'Filesystem C:/', 'Interface 1', 'Uptime'];

    public function __construct(private CheckMkClient $cmk = new CheckMkClient()) {}

    /** Estado completo para la pantalla: hosts al día y métricas de hace a lo más 30 s. */
    public function estado(): array
    {
        $hosts    = $this->recordar('muro_hosts', self::CACHE_HOSTS, fn() => $this->hosts());
        $metricas = $this->recordar('muro_metricas', self::CACHE_METRICAS, fn() => [
            'metricas'  => $this->metricas(),
            'servicios' => $this->serviciosPorCarpeta(),
        ]);

        if (!$hosts['ok'] || !$metricas['ok']) {
            return ['ok' => false, 'error' => $hosts['error'] ?? $metricas['error'], 'generado' => now()->timestamp];
        }

        return ['ok' => true, 'hosts' => $hosts['datos']] + $metricas['datos'] + ['generado' => $hosts['generado']];
    }

    /** Consulta cacheada; el error también se guarda, el mismo tiempo. */
    private function recordar(string $clave, int $ttl, \Closure $consulta): array
    {
        $guardado = Cache::get($clave);
        if ($guardado !== null) return $guardado;

        try {
            $resultado = ['ok' => true, 'datos' => $consulta(), 'generado' => now()->timestamp];
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['ok' => false, 'error' => $e->getMessage()];
        }

        Cache::put($clave, $resultado, $ttl);

        return $resultado;
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

    /* ── Enlaces (panel «enlaces») ────────────────────────────────────────── */
    //
    // Cada enlace es un host de CheckMK con servicio PING. Del servicio se
    // leen dos métricas: `pl` (pérdida, %) y `rta` (latencia, en segundos).
    // El estado UP/DOWN del host no se pide aquí: la pantalla ya lo recibe
    // cada 5 s en estado().

    private const BLOQUES_ENLACE = 48;   // 4 h en bloques de 5 min
    private const RANGOS_ENLACE  = [4, 24, 168];

    /**
     * Resumen de los enlaces para el panel: todos los hosts de la carpeta de
     * CheckMK 'enlaces.carpeta' de config/muro.php (con sus subcarpetas), así
     * que un host nuevo en esa carpeta aparece solo, en a lo más un minuto.
     * El nombre en pantalla sale de 'enlaces.nombres'; si el host no está ahí,
     * del alias de CheckMK, y si no tiene alias, del nombre del host.
     */
    public function enlaces(): array
    {
        $r = $this->recordar('muro_enlaces', 55, function () {
            $carpeta = trim((string) config('muro.enlaces.carpeta', ''), '/');
            if ($carpeta === '') throw new \RuntimeException('Falta enlaces.carpeta en config/muro.php');
            $nombres = config('muro.enlaces.nombres', []);

            $hosts = $this->cmk->consultarHosts(
                ['name', 'alias', 'address', 'last_hard_state_change'],
                ['op' => '~', 'left' => 'filename', 'right' => '^/wato/' . preg_quote($carpeta) . '/']
            )->keyBy('name');
            $lista = $hosts->map(fn($h) => [
                'host'   => $h['name'],
                'nombre' => $nombres[$h['name']] ?? (($h['alias'] ?? '') !== '' ? $h['alias'] : $h['name']),
            ])->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();

            $hasta = now()->timestamp;
            $pedidos = [];
            foreach ($lista as $i => $e) {
                foreach (['pl', 'rta'] as $m) {
                    $pedidos["{$i}|{$m}"] = ['host' => $e['host'], 'servicio' => 'PING', 'metrica' => $m, 'desde' => $hasta - 4 * 3600, 'hasta' => $hasta];
                }
            }
            $series = $pedidos ? $this->cmk->seriesHistoricas($pedidos) : [];

            return ['enlaces' => $lista->map(function ($e, $i) use ($series, $hosts) {
                $pl  = $series["{$i}|pl"]['datos'] ?? [];
                $rta = array_map(fn($v) => $v === null ? null : round($v * 1000, 1), $series["{$i}|rta"]['datos'] ?? []);

                // Bloques de 5 min con la peor pérdida del bloque: un corte de
                // un minuto tiene que verse, y el promedio lo diluiría.
                $porBloque = max(1, intdiv(count($pl), self::BLOQUES_ENLACE) ?: 1);
                $bloques = [];
                foreach (array_chunk(array_slice($pl, -$porBloque * self::BLOQUES_ENLACE), $porBloque) as $trozo) {
                    $v = array_filter($trozo, fn($x) => $x !== null);
                    $bloques[] = $v ? (int) round(max($v)) : null;
                }
                $conDatos = array_filter($pl, fn($x) => $x !== null);
                $lat = array_slice($rta, -60);   // la última hora, minuto a minuto
                $latValidas = array_filter($rta, fn($x) => $x !== null);
                $h = $hosts[$e['host']] ?? [];

                return [
                    'nombre'    => $e['nombre'],
                    'host'      => $e['host'],
                    'proveedor' => $e['proveedor'] ?? null,
                    'ip'        => $h['address'] ?? null,
                    'desde'     => (int) ($h['last_hard_state_change'] ?? 0) ?: null,
                    'bloques'   => array_pad($bloques, -self::BLOQUES_ENLACE, null),
                    'disponible' => $conDatos ? round(100 - array_sum($conDatos) / count($conDatos), 2) : null,
                    'latencia'  => $lat,
                    'lat_ult'   => self::ultimo($rta),
                    'lat_prom'  => $latValidas ? round(array_sum($latValidas) / count($latValidas), 1) : null,
                    'sin_datos' => !$conDatos,
                ];
            })->values()->all()];
        });

        return $r['ok'] ? ['ok' => true] + $r['datos'] + ['generado' => $r['generado']] : $r;
    }

    /** Serie de un enlace para el modal: pérdida y latencia en 4 h, 24 h o 7 días. */
    public function serieEnlace(string $host, int $horas): ?array
    {
        // Solo hosts del panel: el modo TV es público.
        $todos = $this->enlaces();
        if (!$todos['ok'] || !collect($todos['enlaces'])->contains('host', $host)) return null;
        if (!in_array($horas, self::RANGOS_ENLACE, true)) $horas = 4;

        $r = $this->recordar('muro_enlace_' . md5("{$host}|{$horas}"), $horas === 4 ? 55 : 300, function () use ($host, $horas) {
            $hasta = now()->timestamp;
            $s = $this->cmk->seriesHistoricas([
                'pl'  => ['host' => $host, 'servicio' => 'PING', 'metrica' => 'pl',  'desde' => $hasta - $horas * 3600, 'hasta' => $hasta],
                'rta' => ['host' => $host, 'servicio' => 'PING', 'metrica' => 'rta', 'desde' => $hasta - $horas * 3600, 'hasta' => $hasta],
            ]);
            if (!$s['pl']) throw new \RuntimeException('CheckMK no entregó la serie de pérdida');

            return [
                'inicio' => $s['pl']['inicio'],
                'paso'   => $s['pl']['paso'],
                'pl'     => array_map(fn($v) => $v === null ? null : round($v, 1), $s['pl']['datos']),
                'rta'    => array_map(fn($v) => $v === null ? null : round($v * 1000, 1), $s['rta']['datos'] ?? []),
            ];
        });

        return $r['ok'] ? ['ok' => true, 'horas' => $horas] + $r['datos'] : $r;
    }

    /** Último valor no vacío (los últimos minutos suelen venir sin consolidar). */
    private static function ultimo(array $serie): ?float
    {
        for ($i = count($serie) - 1; $i >= 0; $i--) if ($serie[$i] !== null) return $serie[$i];

        return null;
    }

    /* ── Detalle de un hexágono (modal) ───────────────────────────────────── */

    private const CACHE_DETALLE = 10;

    /**
     * Detalle de la carpeta de un hexágono, para el modal que se abre al
     * pulsarlo: cada host con su IP, salida del chequeo, latencia y servicios
     * con problemas (hexágono de hosts), o cada servicio (hexágono de
     * servicios). Solo se aceptan carpetas que estén en config/muro.php: el
     * modo TV es público y no debe servir para recorrer todo CheckMK.
     *
     * @return array|null  null si la carpeta no es de ningún hexágono
     */
    public function detalleCarpeta(string $carpeta): ?array
    {
        $tipo = match (true) {
            $this->carpetasDeTipo('hexagono_hosts')->contains($carpeta)     => 'hosts',
            $this->carpetasDeTipo('hexagono_servicios')->contains($carpeta) => 'servicios',
            default => null,
        };
        if ($tipo === null) return null;

        $r = $this->recordar('muro_detalle_' . md5($carpeta), self::CACHE_DETALLE,
            fn() => $tipo === 'hosts' ? $this->detalleHosts($carpeta) : $this->detalleServicios($carpeta));

        return $r['ok'] ? ['ok' => true, 'tipo' => $tipo] + $r['datos'] + ['generado' => $r['generado']] : $r;
    }

    private static function filtroCarpeta(string $columna, string $carpeta): array
    {
        return ['op' => '~', 'left' => $columna, 'right' => '^/wato' . preg_quote($carpeta, '/') . '/'];
    }

    private const ESTADO_SERVICIO = [0 => 'ok', 1 => 'warn', 2 => 'crit', 3 => 'unknown'];

    private function detalleHosts(string $carpeta): array
    {
        $filas = $this->cmk->consultarHosts([
            'name', 'alias', 'address', 'state', 'has_been_checked', 'plugin_output', 'perf_data',
            'last_hard_state_change', 'last_check', 'scheduled_downtime_depth',
            'num_services', 'num_services_ok', 'num_services_warn', 'num_services_crit', 'num_services_unknown',
        ], self::filtroCarpeta('filename', $carpeta));

        // Solo los servicios con problemas: el detalle es para ver qué falla.
        $problemas = $this->cmk->consultarServicios(
            ['host_name', 'description', 'state', 'plugin_output', 'last_state_change'],
            ['op' => 'and', 'expr' => [self::filtroCarpeta('host_filename', $carpeta), ['op' => '!=', 'left' => 'state', 'right' => '0']]]
        )->groupBy('host_name');

        $hosts = $filas->map(function ($h) use ($problemas) {
            $perf = self::perfData($h['perf_data'] ?? '');

            return [
                'nombre'    => $h['name'],
                'alias'     => $h['alias'] !== $h['name'] ? $h['alias'] : null,
                'ip'        => $h['address'] ?? null,
                'estado'    => self::estadoHost($h),
                'mant'      => (int) ($h['scheduled_downtime_depth'] ?? 0) > 0,
                'desde'     => (int) ($h['last_hard_state_change'] ?? 0) ?: null,
                'ultimo'    => (int) ($h['last_check'] ?? 0) ?: null,
                'salida'    => $h['plugin_output'] ?? '',
                'rta_ms'    => self::latenciaMs($h['perf_data'] ?? ''),
                'perdida'   => $perf['pl']['valor'] ?? null,
                'servicios' => [
                    'total' => (int) $h['num_services'], 'ok' => (int) $h['num_services_ok'],
                    'warn'  => (int) $h['num_services_warn'], 'crit' => (int) $h['num_services_crit'],
                    'unknown' => (int) $h['num_services_unknown'],
                ],
                'problemas' => ($problemas[$h['name']] ?? collect())->map(fn($s) => [
                    'servicio' => $s['description'],
                    'estado'   => self::ESTADO_SERVICIO[(int) $s['state']] ?? 'unknown',
                    'salida'   => $s['plugin_output'] ?? '',
                    'desde'    => (int) ($s['last_state_change'] ?? 0) ?: null,
                ])->sortByDesc(fn($s) => ['crit' => 3, 'unknown' => 2, 'warn' => 1][$s['estado']] ?? 0)->values()->all(),
            ];
        })->values()->all();

        return ['hosts' => $hosts];
    }

    private function detalleServicios(string $carpeta): array
    {
        $filas = $this->cmk->consultarServicios(
            ['host_name', 'description', 'state', 'plugin_output', 'last_state_change', 'scheduled_downtime_depth', 'host_state'],
            self::filtroCarpeta('host_filename', $carpeta)
        );

        return ['servicios' => $filas->map(fn($s) => [
            'host'       => $s['host_name'],
            'servicio'   => $s['description'],
            'estado'     => self::ESTADO_SERVICIO[(int) $s['state']] ?? 'unknown',
            'mant'       => (int) ($s['scheduled_downtime_depth'] ?? 0) > 0,
            'host_caido' => (int) ($s['host_state'] ?? 0) !== 0,
            'salida'     => $s['plugin_output'] ?? '',
            'desde'      => (int) ($s['last_state_change'] ?? 0) ?: null,
        ])->values()->all()];
    }

    /** Latencia del chequeo del host en ms: el ping la informa en ms o en s según la versión. */
    private static function latenciaMs(string $perf): ?float
    {
        if (!preg_match('/\brta=([\d.]+)(ms|s)?/', $perf, $m)) return null;

        return round((float) $m[1] * (($m[2] ?? 'ms') === 's' ? 1000 : 1), 1);
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
        // Los paneles «servidor» piden las mismas cinco series que antes eran
        // cinco gráficos sueltos: CPU y RAM de 30 min y 8 h, y ancho de banda.
        $paneles  = $this->panelesDeTipo('grafico')->filter(fn($p) => isset($p['clase'], $p['host']))
            ->concat($this->panelesDeTipo('servidor')->flatMap(fn($p) => array_map(
                fn($g) => ['titulo' => "srv:{$p['host']}:{$g[0]}", 'clase' => $g[1], 'host' => $p['host'], 'minutos' => $g[2]],
                [['cpu30', 'cpu', 30], ['cpu480', 'cpu', 480], ['ram30', 'ram', 30], ['ram480', 'ram', 480], ['bw60', 'ancho_banda', 60]]
            )))
            ->values();
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
