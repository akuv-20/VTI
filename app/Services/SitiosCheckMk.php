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
                'estado'  => $h['downtime'] ? 'downtime' : ($h['state'] === 0 ? 'up' : 'down'),
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
}
