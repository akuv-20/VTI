<?php

namespace App\Services;

use App\Models\Sitio;
use App\Models\SitioEquipo;
use App\Models\SitioHost;
use Illuminate\Support\Facades\Cache;

/**
 * Puente entre las fichas de sitios y los hosts de CheckMK.
 *
 * Dos funciones:
 *  - Estado en vivo de los hosts de una ficha (reusa el caché del mapa de red).
 *  - Descubrimiento: qué hosts monitoreados aún no tienen ficha, proponiendo el
 *    tipo según la convención de nombres observada en el servidor real.
 */
class SitiosCheckMk
{
    private const CACHE_ESTADO = 8;

    /**
     * Patrones de clasificación derivados de los nombres reales en CheckMK.
     * El orden importa: la primera coincidencia gana.
     *
     * @var array<string,array{0:string,1:string}> patrón => [ámbito, tipo]
     */
    private const PATRONES = [
        '/^\d+\..*(DATACENTER|DC)/i'              => ['sitio', 'datacenter'],
        '/^\d+\..*PLANTA/i'                       => ['sitio', 'planta'],
        '/^\d+\..*(CAMPO|FDO|FUNDO|AGRICOLA)/i'   => ['sitio', 'campo'],
        '/^\d+\..*(INFORMATICA|ADMIN|OFICINA)/i'  => ['sitio', 'oficina'],
        '/^\d+\./'                                => ['sitio', 'campo'],   // resto de sitios numerados
        '/^AP[_\-\s]|_AP[_\-]|ACCESSPOINT/i'      => ['equipo', 'ap'],
        '/^(NVR|CAM)/i'                           => ['equipo', 'nvr'],
        '/(EMISOR|RECEPTOR|PTP|ANTENA)/i'         => ['equipo', 'ptp'],
        '/^(U|D|C|A)?SW[_\-]|SWITCH/i'            => ['equipo', 'switch'],
        '/(FIREWALL|FORTI|FW\d)/i'                => ['equipo', 'firewall'],
        '/(UPS|PDU)/i'                            => ['equipo', 'ups'],
        '/(ESX|SRV|STORAGE|^VF|^UF)/i'            => ['equipo', 'servidor'],
    ];

    public function __construct(private CheckMkClient $cmk = new CheckMkClient()) {}

    /** Estado en vivo de todos los hosts (cacheado, compartido con el mapa). */
    public function estados()
    {
        return Cache::remember('monitoreo_estado_hosts', self::CACHE_ESTADO, fn() => $this->cmk->estadoHosts());
    }

    /**
     * Estado en vivo de los hosts de una ficha.
     *
     * Distingue «ausente» (el host ya no está en CheckMK, hay que remapear) de
     * «na» (no se pudo consultar), que son dos problemas muy distintos.
     *
     * @return array<string,array{estado:string,detalle:?string,desde:?string}>
     */
    public function estadoDeSitio(Sitio $sitio): array
    {
        try {
            $estados = $this->estados();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($sitio->todosLosHosts() as $host) {
            $h = $estados->get($host);
            if (!$h) {
                $out[$host] = ['estado' => 'ausente', 'detalle' => 'Ya no existe en CheckMK', 'desde' => null];
                continue;
            }
            $out[$host] = [
                'estado'  => $this->clasificarEstado($h),
                'detalle' => \Illuminate\Support\Str::limit($h['output'], 120),
                'desde'   => $h['since'] ? \Carbon\Carbon::createFromTimestamp($h['since'])->locale('es')->diffForHumans() : null,
            ];
        }

        return $out;
    }

    /**
     * Hosts monitoreados que todavía no están en ninguna ficha, con el tipo
     * propuesto según su nombre. Alimenta la pantalla de descubrimiento.
     *
     * @return array{sitios:array,equipos:array,sin_clasificar:array}
     */
    public function hostsSinFicha(): array
    {
        $usados = collect(SitioHost::pluck('host_name'))
            ->merge(SitioEquipo::whereNotNull('host_name')->pluck('host_name'))
            ->map(fn($h) => strtolower($h))
            ->unique()
            ->all();

        $sitios = [];
        $equipos = [];
        $resto = [];

        foreach ($this->estados() as $host => $info) {
            if (in_array(strtolower($host), $usados, true)) continue;

            $fila = [
                'host_name' => $host,
                'estado'    => $info['downtime'] ? 'downtime' : ($info['state'] === 0 ? 'up' : 'down'),
                'codigo'    => $this->codigoDe($host),
                'sugerido'  => null,
            ];

            [$ambito, $tipo] = $this->clasificar($host);
            $fila['sugerido'] = $tipo;

            if ($ambito === 'sitio')       $sitios[] = $fila;
            elseif ($ambito === 'equipo')  $equipos[] = $fila;
            else                           $resto[] = $fila;
        }

        $orden = fn(&$arr) => usort($arr, fn($a, $b) => strnatcasecmp($a['host_name'], $b['host_name']));
        $orden($sitios); $orden($equipos); $orden($resto);

        return ['sitios' => $sitios, 'equipos' => $equipos, 'sin_clasificar' => $resto];
    }

    /** [ámbito, tipo] propuestos para un host; ['', null] si no se reconoce. */
    public function clasificar(string $host): array
    {
        // Una IP suelta no es un sitio aunque empiece con números.
        if (filter_var($host, FILTER_VALIDATE_IP)) return ['', null];

        foreach (self::PATRONES as $re => $par) {
            if (preg_match($re, $host)) return $par;
        }

        return ['', null];
    }

    /** Extrae el prefijo numérico del nombre ("19.CAMPO_PORVENIR" → "19"). */
    public function codigoDe(string $host): ?string
    {
        return preg_match('/^(\d+)\./', $host, $m) ? $m[1] : null;
    }

    /**
     * Nombre legible a partir del host: "19.CAMPO_PORVENIR" → "Campo Porvenir".
     * Se usa como valor inicial al crear una ficha desde el descubrimiento.
     */
    public function nombreDe(string $host): string
    {
        $n = preg_replace('/^\d+\./', '', $host);          // quita el prefijo numérico
        $n = preg_replace('/_VPN$/i', '', $n);             // quita el sufijo de túnel
        $n = str_replace(['_', '-', '.'], ' ', $n);
        $n = preg_replace('/\s+/', ' ', trim($n));

        return \Illuminate\Support\Str::title(mb_strtolower($n));
    }

    /* ── Estado en vivo para el listado ──────────────────────────────────── */

    /**
     * Los cinco estados que puede tener un sitio, y su orden de gravedad.
     *
     * `sin_ip` existe porque CheckMK reporta DOWN a un host que no pudo medir:
     * si no tiene IP explicita ni nombre resoluble, queda en 0.0.0.0 y se
     * informa caido sin haberlo tocado nunca. Llamar a eso «offline» es decir
     * que el sitio esta sin enlace cuando en realidad esta sin vigilancia, que
     * es un problema distinto y se arregla en otra parte.
     */
    public const ESTADOS = [
        'down'     => ['Offline',    '#dc2626'],
        'sin_ip'   => ['Sin medir',  '#d97706'],
        'ausente'  => ['Host borrado', '#a855f7'],
        'downtime' => ['Mantencion', '#0284c7'],
        'up'       => ['Online',     '#16a34a'],
    ];

    /**
     * Cual de los hosts de un sitio manda, cuando hay varios.
     *
     * Hoy ningun sitio tiene mas de uno, asi que esto no cambia nada todavia;
     * se define igual para que el dia que aparezca el segundo host el listado
     * no empiece a mostrar el que venga primero por azar. El rol ya existia en
     * `sitio_hosts`, de modo que no hace falta inventar un «favorito»: el
     * enlace principal es el que dice si el sitio esta en linea, y un respaldo
     * caido es otra conversacion, que la ficha si muestra.
     */
    private const PRECEDENCIA_ROL = ['enlace', 'respaldo', 'vpn', 'otro'];

    /**
     * Estado en vivo de muchos sitios de una vez, para el listado.
     *
     * Una sola llamada a CheckMK para todos: el endpoint devuelve los 166
     * hosts juntos, asi que agregar sitios no agrega consultas.
     *
     * @param  iterable<Sitio>  $sitios  con `hosts` y `equipos` ya cargados
     * @return array{ok:bool,error:?string,sitios:array<int,array>}
     */
    public function estadoDeSitios(iterable $sitios): array
    {
        try {
            $estados = $this->estados();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sitios' => []];
        }

        $out = [];
        foreach ($sitios as $sitio) {
            $hosts = $this->hostsOrdenados($sitio);
            if (!$hosts) continue;

            $principal = $hosts[0];
            $info      = $estados->get($principal['host']);

            $out[$sitio->id] = [
                'estado'  => $this->clasificarEstado($info),
                'host'    => $principal['host'],
                'rol'     => $principal['rol'],
                'detalle' => $info ? \Illuminate\Support\Str::limit($info['output'], 120) : 'Ya no existe en CheckMK',
                'desde'   => $info && $info['since']
                    ? \Carbon\Carbon::createFromTimestamp($info['since'])->locale('es')->diffForHumans()
                    : null,
                // Si hay mas hosts, el listado lo dice en vez de esconderlos.
                'otros'   => array_map(
                    fn($h) => ['host' => $h['host'], 'estado' => $this->clasificarEstado($estados->get($h['host']))],
                    array_slice($hosts, 1)
                ),
            ];
        }

        return ['ok' => true, 'error' => null, 'sitios' => $out];
    }

    /**
     * Traduce lo que devuelve CheckMK a uno de los ESTADOS.
     *
     * El orden de las comprobaciones importa: `downtime` gana sobre todo
     * porque es una decision humana, y `sin_ip` gana sobre `down` porque un
     * host en 0.0.0.0 nunca se midio y su DOWN no significa nada.
     */
    private function clasificarEstado(?array $info): string
    {
        if (!$info)                              return 'ausente';
        if ($info['downtime'])                   return 'downtime';
        if (($info['address'] ?? '') === '0.0.0.0') return 'sin_ip';

        return $info['state'] === 0 ? 'up' : 'down';
    }

    /**
     * Los hosts de un sitio, el que manda primero.
     *
     * @return array<int,array{host:string,rol:string}>
     */
    private function hostsOrdenados(Sitio $sitio): array
    {
        $lista = [];

        foreach ($sitio->hosts as $h) {
            if ($h->host_name) $lista[] = ['host' => $h->host_name, 'rol' => $h->rol ?: 'otro'];
        }
        // Los hosts que cuelgan de un equipo van despues: describen un aparato
        // dentro del sitio, no el enlace del sitio.
        foreach ($sitio->equipos as $e) {
            if ($e->host_name) $lista[] = ['host' => $e->host_name, 'rol' => 'equipo'];
        }

        $peso = fn(string $rol) => ($i = array_search($rol, self::PRECEDENCIA_ROL, true)) === false
            ? count(self::PRECEDENCIA_ROL) + 1
            : $i;

        usort($lista, fn($a, $b) => $peso($a['rol']) <=> $peso($b['rol'])
            ?: strnatcasecmp($a['host'], $b['host']));

        // Un mismo host enlazado dos veces no cuenta dos veces.
        $vistos = [];
        return array_values(array_filter($lista, function ($x) use (&$vistos) {
            $k = strtolower($x['host']);
            if (isset($vistos[$k])) return false;
            $vistos[$k] = true;
            return true;
        }));
    }

}
