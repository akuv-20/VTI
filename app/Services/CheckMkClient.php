<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para la API REST de CheckMK 2.3.
 *
 * Autenticación: usuario de automatización + secret, enviados en la cabecera
 *   Authorization: Bearer <usuario> <secret>
 *
 * Base de la API:  {url}/{site}/check_mk/api/1.0
 *
 * La configuración (url, site, usuario, secret) se guarda en la tabla
 * `configuraciones` y se administra desde Admin → Configuración → CheckMK.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  NOTA sobre disponibilidad histórica (validar contra el servidor real):
 *  CheckMK 2.3 NO expone la disponibilidad ("availability") en la REST API.
 *  El método disponibilidadServicio() la obtiene mediante la exportación de la
 *  vista de disponibilidad (view.py ... output_format=json), que es la vía
 *  estable en 2.x. Los nombres exactos de parámetros de rango pueden variar
 *  según la versión/idioma del servidor, por lo que este método está aislado
 *  y debe validarse contra tu CheckMK. Mientras se afina, el módulo permite
 *  la carga/edición manual del snapshot mensual.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class CheckMkClient
{
    private ?string $url;
    private ?string $site;
    private ?string $user;
    private ?string $secret;

    public function __construct()
    {
        $this->url    = rtrim((string) Configuracion::get('checkmk_url', ''), '/') ?: null;
        $this->site   = trim((string) Configuracion::get('checkmk_site', '')) ?: null;
        $this->user   = trim((string) Configuracion::get('checkmk_user', '')) ?: null;
        $this->secret = (string) Configuracion::get('checkmk_secret', '') ?: null;
    }

    public function configurado(): bool
    {
        return $this->url && $this->site && $this->user && $this->secret;
    }

    /** Base de la API REST 1.0 del site. */
    private function apiBase(): string
    {
        return "{$this->url}/{$this->site}/check_mk/api/1.0";
    }

    /** Cliente HTTP con la cabecera de autenticación de automatización. */
    private function http(int $timeout = 20)
    {
        return Http::withHeaders([
                'Authorization' => "Bearer {$this->user} {$this->secret}",
                'Accept'        => 'application/json',
            ])
            ->timeout($timeout)
            ->withOptions(['verify' => false]); // instalaciones internas suelen usar cert propio
    }

    private function asegurarConfigurado(): void
    {
        if (!$this->configurado()) {
            throw new \RuntimeException('CheckMK no está configurado. Completa URL, site, usuario y secret en Admin → Configuración.');
        }
    }

    /* ── Conexión ────────────────────────────────────────────────────────── */

    /** Prueba la conexión leyendo la versión del servidor. Devuelve [ok, message]. */
    public function probarConexion(): array
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'message' => 'Completa los datos y guarda antes de probar.'];
        }

        try {
            $resp = $this->http(10)->get($this->apiBase() . '/version');

            if ($resp->successful()) {
                $version = $resp->json('versions.checkmk') ?? $resp->json('version') ?? 'desconocida';
                $edition = $resp->json('edition') ?? '';
                return ['ok' => true, 'message' => "Conexión exitosa — CheckMK {$version} {$edition} ({$this->site})"];
            }

            $msg = $resp->json('title') ?? $resp->json('detail') ?? ('HTTP ' . $resp->status());
            return ['ok' => false, 'message' => "No se pudo conectar: {$msg}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /* ── Inventario de hosts y servicios ─────────────────────────────────── */

    /**
     * Lista los hosts monitoreados.
     *
     * @return Collection<int,array{host_name:string}>
     */
    public function listarHosts(): Collection
    {
        $this->asegurarConfigurado();

        $resp = $this->http()->get($this->apiBase() . '/domain-types/host_config/collections/all', [
            'effective_attributes' => 'false',
        ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Error al listar hosts de CheckMK: ' . ($resp->json('title') ?? $resp->body()));
        }

        return collect($resp->json('value') ?? [])
            ->map(fn($h) => ['host_name' => $h['id'] ?? ($h['title'] ?? '')])
            ->filter(fn($h) => $h['host_name'] !== '')
            ->sortBy('host_name')
            ->values();
    }

    /**
     * Lista los servicios monitoreados, opcionalmente filtrados por host.
     *
     * @return Collection<int,array{host_name:string,service_description:string,state:?int}>
     */
    public function listarServicios(?string $host = null): Collection
    {
        $this->asegurarConfigurado();

        // CheckMK espera el parámetro `columns` repetido (columns=a&columns=b),
        // no como array indexado (columns[0]=a…), que devuelve 400 Bad Request.
        // Por eso construimos el querystring a mano.
        $qs = 'columns=host_name&columns=description&columns=state';
        if ($host) {
            $qs .= '&host_name=' . urlencode($host);
        }

        $resp = $this->http()->get($this->apiBase() . '/domain-types/service/collections/all?' . $qs);

        if (!$resp->successful()) {
            throw new \RuntimeException('Error al listar servicios de CheckMK: ' . ($resp->json('title') ?? $resp->body()));
        }

        return collect($resp->json('value') ?? [])
            ->map(function ($s) {
                $ext = $s['extensions'] ?? [];
                return [
                    'host_name'           => $ext['host_name'] ?? '',
                    'service_description'  => $ext['description'] ?? ($s['title'] ?? ''),
                    'state'               => $ext['state'] ?? null,
                ];
            })
            ->filter(fn($s) => $s['host_name'] !== '' && $s['service_description'] !== '')
            ->sortBy(fn($s) => $s['host_name'] . '|' . $s['service_description'])
            ->values();
    }

    /**
     * Estado EN VIVO de todos los hosts monitoreados (para el mapa de red).
     *
     * Usa el endpoint de monitoreo (livestatus) — no el de configuración — y
     * trae por host: estado (0=UP, 1=DOWN, 2=UNREACH), si está en downtime
     * programado, desde cuándo está en el estado actual y la salida del check.
     *
     * @return Collection<string,array{state:int,downtime:bool,since:?int,output:string}> keyed por host_name
     */
    public function estadoHosts(): Collection
    {
        $this->asegurarConfigurado();

        // Igual que en listarServicios: `columns` repetido, querystring a mano.
        $qs = 'columns=name&columns=state&columns=scheduled_downtime_depth&columns=last_state_change&columns=plugin_output';

        $resp = $this->http(15)->get($this->apiBase() . '/domain-types/host/collections/all?' . $qs);

        if (!$resp->successful()) {
            throw new \RuntimeException('Error al consultar estado de hosts en CheckMK: ' . ($resp->json('title') ?? $resp->body()));
        }

        return collect($resp->json('value') ?? [])
            ->mapWithKeys(function ($h) {
                $ext  = $h['extensions'] ?? [];
                $name = $ext['name'] ?? ($h['id'] ?? '');
                if ($name === '') return [];
                return [$name => [
                    'state'    => (int) ($ext['state'] ?? 0),
                    'downtime' => ((int) ($ext['scheduled_downtime_depth'] ?? 0)) > 0,
                    'since'    => isset($ext['last_state_change']) ? (int) $ext['last_state_change'] : null,
                    'output'   => (string) ($ext['plugin_output'] ?? ''),
                ]];
            });
    }

    /* ── Disponibilidad histórica ────────────────────────────────────────── */

    /**
     * Disponibilidad de un objeto (host o servicio) en un rango de fechas.
     *
     * CheckMK 2.3 no expone la disponibilidad en la REST API, así que se usa la
     * vista de disponibilidad de view.py (mode=availability). Detalles validados
     * contra el servidor real:
     *   - Con `output_format` presente, view.py devuelve SOLO la tabla de datos.
     *   - Host  → view_name=allhosts (columnas UP/DOWN/UNREACH/Flapping/Downtime/N/A)
     *   - Servicio → view_name=host  (columnas OK/WARN/CRIT/UNKNOWN/Flapping/H.Down/Downtime/N/A)
     *   - Rango: avoptions=set + avo_rangespec_sel=17 (rango de fechas) con los
     *     subcampos año/mes/día de inicio (…_17_0_…) y fin exclusivo (…_17_1_…).
     *
     * Las celdas vienen como porcentajes del periodo; se convierten a segundos
     * usando la duración del rango para que el promedio anual (ponderado por
     * tiempo) sea correcto entre meses de distinto largo.
     *
     * @param  string       $host
     * @param  string|null  $service  null = disponibilidad UP/DOWN del host
     * @return array{up_seconds:int,down_seconds:int,unmonitored_seconds:int,downtime_seconds:int}
     */
    public function disponibilidadServicio(string $host, ?string $service, Carbon $desde, Carbon $hasta): array
    {
        $this->asegurarConfigurado();

        $esServicio = !empty($service);

        // Rango [desde 00:00, until 00:00) — CheckMK trata el fin como exclusivo,
        // así que redondeamos el "hasta" al inicio del día siguiente para incluir
        // el último día completo (o el día en curso hasta ahora).
        $from  = $desde->copy()->startOfDay();
        $until = $hasta->copy()->startOfDay()->addDay();
        $periodo = max(1, $until->timestamp - $from->timestamp);

        $objeto = $this->objetoQuery($host, $service);

        // Flujo de dos pasos: fijar el rango (persiste server-side) y traer la tabla.
        $this->fijarRango($objeto, $from, $until);

        // (output_format fuerza el render solo-tabla; el valor en sí se ignora.)
        $resp = Http::withOptions(['verify' => false])->timeout(90)
            ->get($this->viewEndpoint(), $this->authQuery() + $objeto + ['output_format' => 'json']);

        if (!$resp->successful()) {
            throw new \RuntimeException('Error al obtener disponibilidad de CheckMK: HTTP ' . $resp->status());
        }

        $html = $resp->body();
        if (str_contains($html, 'page_handler_error')) {
            throw new \RuntimeException('CheckMK no devolvió la vista de disponibilidad esperada para ' . $host . '.');
        }

        return $this->parsearDisponibilidad($html, $host, $service, $periodo);
    }

    /* ── Helpers de la vista de disponibilidad ───────────────────────────── */

    private function viewEndpoint(): string
    {
        return "{$this->url}/{$this->site}/check_mk/view.py";
    }

    private function authQuery(): array
    {
        return ['_username' => $this->user, '_secret' => $this->secret];
    }

    private function objetoQuery(string $host, ?string $service): array
    {
        $objeto = ['view_name' => $service ? 'host' : 'allhosts', 'mode' => 'availability', 'host' => $host];
        if ($service) {
            $objeto['service'] = $service;
        }
        return $objeto;
    }

    /**
     * Paso 1 del flujo: fija el rango de disponibilidad (avoptions). CheckMK lo
     * aplica y lo PERSISTE server-side para el usuario de automatización, de modo
     * que el request siguiente (con output_format) usa ese rango.
     */
    private function fijarRango(array $objeto, Carbon $from, Carbon $until): void
    {
        Http::withOptions(['verify' => false])->timeout(90)->get($this->viewEndpoint(), $this->authQuery() + $objeto + [
            'filled_in'                => 'avoptions_display',
            'avoptions'                => 'set',
            'avo_rangespec_sel'        => 17, // "Date range"
            'avo_rangespec_17_0_year'  => $from->year,
            'avo_rangespec_17_0_month' => $from->month,
            'avo_rangespec_17_0_day'   => $from->day,
            'avo_rangespec_17_1_year'  => $until->year,
            'avo_rangespec_17_1_month' => $until->month,
            'avo_rangespec_17_1_day'   => $until->day,
        ]);
    }

    /**
     * Extrae la fila del objeto de la tabla de disponibilidad y reparte el
     * periodo entre las categorías del KPI:
     *   up        = UP            (host)  / OK, WARN                     (servicio)
     *   down      = DOWN, UNREACH, Flapping (host) / CRIT, UNKNOWN, Flapping, H.Down (servicio)
     *   downtime  = Downtime  (mantenimiento programado → excluido del %)
     *   na        = N/A       (sin datos → excluido del %)
     */
    private function parsearDisponibilidad(string $html, string $host, ?string $service, int $periodo): array
    {
        $filas = $this->extraerTablaDisponibilidad($html);
        if (count($filas) < 2) {
            throw new \RuntimeException('No se encontró la tabla de disponibilidad en la respuesta de CheckMK.');
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), $filas[0]);
        $iHost   = array_search('host', $headers, true);
        $iSvc    = array_search('service', $headers, true);

        // Buscar la fila del objeto (ignorando la fila "Summary").
        $fila = null;
        foreach (array_slice($filas, 1) as $row) {
            $hostCol = trim($row[$iHost] ?? '');
            if (strcasecmp($hostCol, 'Summary') === 0) continue;
            if ($iHost !== false && strcasecmp($hostCol, $host) !== 0) continue;
            if ($service !== null && $iSvc !== false && strcasecmp(trim($row[$iSvc] ?? ''), $service) !== 0) continue;
            $fila = $row;
            break;
        }
        if ($fila === null) {
            throw new \RuntimeException("Sin datos de disponibilidad para {$host}" . ($service ? " / {$service}" : '') . ' en el periodo.');
        }

        $pct = ['up' => 0.0, 'down' => 0.0, 'downtime' => 0.0, 'na' => 0.0];
        foreach ($headers as $i => $col) {
            $p = $this->pctAFloat($fila[$i] ?? '');
            if ($p === null) continue;

            match ($col) {
                'up', 'ok', 'warn'                              => $pct['up']       += $p,
                'down', 'unreach', 'crit', 'unknown',
                'flapping', 'h.down'                            => $pct['down']     += $p,
                'downtime'                                      => $pct['downtime'] += $p,
                'n/a'                                           => $pct['na']       += $p,
                default                                         => null,
            };
        }

        return [
            'up_seconds'          => (int) round($pct['up']       / 100 * $periodo),
            'down_seconds'        => (int) round($pct['down']     / 100 * $periodo),
            'downtime_seconds'    => (int) round($pct['downtime'] / 100 * $periodo),
            'unmonitored_seconds' => (int) round($pct['na']       / 100 * $periodo),
        ];
    }

    /** Extrae la tabla de disponibilidad (class="availability") como matriz de celdas. */
    private function extraerTablaDisponibilidad(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xp = new \DOMXPath($dom);

        $table = $xp->query("//table[contains(@class,'availability')]")->item(0);
        if (!$table) return [];

        $filas = [];
        foreach ($xp->query('.//tr', $table) as $tr) {
            $celdas = [];
            foreach ($xp->query('./th|./td', $tr) as $cell) {
                $celdas[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }
            if (count(array_filter($celdas, fn($v) => $v !== '')) > 0) {
                $filas[] = $celdas;
            }
        }
        return $filas;
    }

    /** Convierte una celda "99.93%" a float 99.93; null si no es un porcentaje. */
    private function pctAFloat(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '' || !str_contains($valor, '%')) return null;
        $num = str_replace([',', '%', ' '], ['.', '', ''], $valor);
        return is_numeric($num) ? (float) $num : null;
    }
}
