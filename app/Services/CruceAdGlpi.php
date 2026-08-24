<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LdapRecord\Models\ActiveDirectory\Computer;

/**
 * Cruza los equipos del Active Directory de Unifrutti (conexión `tertiary`)
 * contra el inventario del GLPI de Unifrutti (conexión `glpi_unifrutti`).
 *
 * El AD es la lista maestra: se recorren TODOS sus equipos y a cada uno se le
 * asigna un estado según si está inventariado, si el agente reporta y hace
 * cuánto que la máquina no se conecta al dominio.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Detalles de AD que hay que respetar o el resultado miente:
 *
 *  1. Para "última conexión" se usa `lastLogonTimestamp`, NO `lastLogon`:
 *     lastLogon no se replica entre controladores. lastLogonTimestamp sí, pero
 *     tiene un desfase de hasta ~14 días por diseño. Para un umbral de 90 días
 *     eso es irrelevante.
 *
 *  2. `lastLogonTimestamp` viene como FILETIME de Windows (100 ns desde 1601).
 *     Valores 0 o 0x7FFFFFFFFFFFFFFF significan "nunca".
 * ─────────────────────────────────────────────────────────────────────────────
 */
class CruceAdGlpi
{
    private const CONEXION_AD   = 'tertiary';
    private const CONEXION_GLPI = 'glpi_unifrutti';

    private const CACHE_AD_KEY   = 'cruce_ad_glpi_equipos_ad';
    private const CACHE_GLPI_KEY = 'cruce_ad_glpi_equipos_glpi';
    private const CACHE_TTL      = 300; // segundos

    /** Bandera de userAccountControl: cuenta deshabilitada. */
    private const UAC_DESHABILITADA = 0x0002;

    private int $diasBaja;
    private int $diasAgente;

    public function __construct()
    {
        $this->diasBaja   = (int) (Configuracion::get('cruce_dias_baja', 90)   ?: 90);
        $this->diasAgente = (int) (Configuracion::get('cruce_dias_agente', 90) ?: 90);
    }

    public function diasBaja(): int   { return $this->diasBaja; }
    public function diasAgente(): int { return $this->diasAgente; }

    /** Vacía la caché de ambos lados (para el botón "Actualizar"). */
    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_AD_KEY);
        Cache::forget(self::CACHE_GLPI_KEY);
    }

    /**
     * Ejecuta el cruce completo.
     *
     * @return array{
     *   equipos: Collection,
     *   resumen: array,
     *   generado: Carbon,
     *   huerfanos_glpi: int
     * }
     */
    public function analizar(): array
    {
        $equiposAd = Cache::remember(self::CACHE_AD_KEY, self::CACHE_TTL, fn() => $this->traerEquiposAd());
        $mapaGlpi  = Cache::remember(self::CACHE_GLPI_KEY, self::CACHE_TTL, fn() => $this->traerMapaGlpi());

        $ahora = now();
        $vistos = [];   // nombres normalizados encontrados en AD (para huérfanos GLPI)

        $equipos = $equiposAd->map(function (array $eq) use ($mapaGlpi, $ahora, &$vistos) {
            $clave         = $eq['clave'];
            $vistos[$clave] = true;

            $enGlpi      = $mapaGlpi->has($clave);
            $glpi        = $enGlpi ? $mapaGlpi->get($clave) : null;
            $ultimoLogin = $eq['ultimo_login'];      // Carbon|null
            $lastContact = $glpi['last_contact'] ?? null; // Carbon|null

            $diasSinLogin   = $ultimoLogin ? (int) floor($ultimoLogin->diffInDays($ahora)) : null;
            $diasSinReporte = $lastContact ? (int) floor($lastContact->diffInDays($ahora)) : null;

            $estado = $this->clasificar($eq['deshabilitada'], $diasSinLogin, $enGlpi, $diasSinReporte);

            return [
                'nombre'          => $eq['nombre'],
                'ou'              => $eq['ou'],
                'so'              => $eq['so'],
                'deshabilitada'   => $eq['deshabilitada'],
                'ultimo_login'    => $ultimoLogin,
                'dias_sin_login'  => $diasSinLogin,
                'en_glpi'         => $enGlpi,
                'glpi_nombre'     => $glpi['name'] ?? null,
                'last_contact'    => $lastContact,
                'dias_sin_reporte'=> $diasSinReporte,
                'estado'          => $estado,
            ];
        })->sortBy([
            // Primero lo que urge revisar; dentro de cada estado, lo más viejo arriba
            fn($a, $b) => $this->pesoEstado($a['estado']) <=> $this->pesoEstado($b['estado']),
            fn($a, $b) => ($b['dias_sin_login'] ?? -1) <=> ($a['dias_sin_login'] ?? -1),
        ])->values();

        // Huérfanos: equipos en GLPI que no existen en el AD
        $huerfanos = $mapaGlpi->reject(fn($v, $clave) => isset($vistos[$clave]))->count();

        $resumen = [
            'total'          => $equipos->count(),
            'ok'             => $equipos->where('estado', 'ok')->count(),
            'falta_agente'   => $equipos->where('estado', 'falta_agente')->count(),
            'agente_mudo'    => $equipos->where('estado', 'agente_mudo')->count(),
            'posible_baja'   => $equipos->where('estado', 'posible_baja')->count(),
            'deshabilitado'  => $equipos->where('estado', 'deshabilitado')->count(),
            'en_glpi'        => $equipos->where('en_glpi', true)->count(),
            'huerfanos_glpi' => $huerfanos,
            'total_glpi'     => $mapaGlpi->count(),
        ];

        return [
            'equipos'  => $equipos,
            'resumen'  => $resumen,
            'generado' => now(),
        ];
    }

    /* ── Clasificación ───────────────────────────────────────────────────── */

    private function clasificar(bool $deshabilitada, ?int $diasSinLogin, bool $enGlpi, ?int $diasSinReporte): string
    {
        // El orden es la prioridad del mensaje que se le muestra al técnico.
        if ($deshabilitada) {
            return 'deshabilitado';
        }
        if ($diasSinLogin === null || $diasSinLogin > $this->diasBaja) {
            return 'posible_baja';
        }
        if (!$enGlpi) {
            return 'falta_agente';
        }
        if ($diasSinReporte === null || $diasSinReporte > $this->diasAgente) {
            return 'agente_mudo';
        }
        return 'ok';
    }

    private function pesoEstado(string $estado): int
    {
        return match ($estado) {
            'posible_baja'  => 0,
            'falta_agente'  => 1,
            'agente_mudo'   => 2,
            'deshabilitado' => 3,
            'ok'            => 4,
            default         => 9,
        };
    }

    /* ── Lado AD ─────────────────────────────────────────────────────────── */

    private function traerEquiposAd(): Collection
    {
        $computers = Computer::on(self::CONEXION_AD)
            ->select([
                'cn', 'dnshostname', 'operatingsystem',
                'lastlogontimestamp', 'pwdlastset',
                'useraccountcontrol', 'distinguishedname',
            ])
            ->get();

        return collect($computers)->map(function ($c) {
            $nombre = $c->getFirstAttribute('cn')
                   ?? $c->getFirstAttribute('name')
                   ?? $c->getFirstAttribute('dnshostname')
                   ?? '';

            $uac = (int) $c->getFirstAttribute('useraccountcontrol');
            $dn  = (string) $c->getFirstAttribute('distinguishedname');

            return [
                'nombre'        => $nombre,
                'clave'         => $this->normalizar($nombre),
                'ou'            => $this->ouDesdeDn($dn),
                'so'            => $c->getFirstAttribute('operatingsystem') ?: null,
                'deshabilitada' => (bool) ($uac & self::UAC_DESHABILITADA),
                'ultimo_login'  => $this->filetimeACarbon($c->getFirstAttribute('lastlogontimestamp')),
            ];
        })->filter(fn($e) => $e['clave'] !== '')->values();
    }

    /* ── Lado GLPI ───────────────────────────────────────────────────────── */

    private function traerMapaGlpi(): Collection
    {
        $filas = DB::connection(self::CONEXION_GLPI)
            ->table('glpi_computers as c')
            ->leftJoin('glpi_agents as a', function ($j) {
                $j->on('a.items_id', '=', 'c.id')->where('a.itemtype', 'Computer');
            })
            ->where('c.is_deleted', 0)
            ->where('c.is_template', 0)
            ->groupBy('c.id', 'c.name')
            ->select('c.id', 'c.name', DB::raw('MAX(a.last_contact) as last_contact'))
            ->get();

        $mapa = collect();

        foreach ($filas as $f) {
            $clave = $this->normalizar($f->name);
            if ($clave === '') continue;

            $lastContact = $f->last_contact ? Carbon::parse($f->last_contact) : null;

            // Si un nombre aparece más de una vez, se queda el reporte más reciente
            if ($mapa->has($clave)) {
                $prev = $mapa->get($clave);
                if ($prev['last_contact'] && $lastContact && $prev['last_contact']->gte($lastContact)) {
                    continue;
                }
            }

            $mapa->put($clave, [
                'id'           => $f->id,
                'name'         => $f->name,
                'last_contact' => $lastContact,
            ]);
        }

        return $mapa;
    }

    /* ── Utilidades ──────────────────────────────────────────────────────── */

    /** Nombre comparable: mayúsculas, sin `$` final, sin sufijo de dominio. */
    private function normalizar(mixed $nombre): string
    {
        $n = strtoupper(trim((string) $nombre));
        $n = rtrim($n, '$');
        $n = explode('.', $n)[0];   // por si viniera un FQDN
        return trim($n);
    }

    /** OU inmediata (y su ruta) a partir del distinguishedName. */
    private function ouDesdeDn(string $dn): ?string
    {
        if ($dn === '') return null;

        preg_match_all('/OU=([^,]+)/i', $dn, $m);
        if (empty($m[1])) return null;

        // De más específica a más general, unidas con " / "
        return collect($m[1])
            ->map(fn($ou) => trim(str_replace('\\,', ',', $ou)))
            ->join(' / ');
    }

    /** FILETIME de Windows → Carbon, o null si es "nunca". */
    private function filetimeACarbon(mixed $ft): ?Carbon
    {
        $ft = (int) $ft;
        if ($ft <= 0 || $ft === 9223372036854775807) return null;

        $unix = intdiv($ft, 10_000_000) - 11_644_473_600;
        if ($unix <= 0) return null;

        return Carbon::createFromTimestamp($unix);
    }
}
