<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LdapRecord\Models\ActiveDirectory\Computer;

/**
 * Cruza los equipos del Active Directory de un dominio contra el inventario
 * de su GLPI. Ambas conexiones salen del DominioInventario, así que el mismo
 * servicio sirve a Verfrut y a Unifrutti.
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
    private const CACHE_TTL = 300; // segundos

    /** Bandera de userAccountControl: cuenta deshabilitada. */
    private const UAC_DESHABILITADA = 0x0002;

    private int $diasBaja;
    private int $diasAgente;

    public function __construct(private DominioInventario $dominio)
    {
        $this->diasBaja   = (int) (Configuracion::get('cruce_dias_baja', 90)   ?: 90);
        $this->diasAgente = (int) (Configuracion::get('cruce_dias_agente', 90) ?: 90);
    }

    public function diasBaja(): int   { return $this->diasBaja; }
    public function diasAgente(): int { return $this->diasAgente; }

    /* ── Caché, separada por dominio ─────────────────────────────────────── */
    //
    // Las claves llevan el dominio: si fueran fijas, entrar al cruce de un
    // dominio serviría los equipos del otro desde la caché.

    private function claveAd(): string   { return "cruce_ad_{$this->dominio->clave}"; }
    private function claveGlpi(): string { return "cruce_glpi_{$this->dominio->clave}"; }

    /** Vacía la caché de ambos lados para este dominio (botón "Actualizar"). */
    public function olvidarCache(): void
    {
        Cache::forget($this->claveAd());
        Cache::forget($this->claveGlpi());
    }

    /**
     * Ejecuta el cruce completo.
     *
     * La tabla que devuelve son los equipos del AD MÁS los huérfanos: los que
     * están inventariados en GLPI y ya no tienen cuenta de dominio, con estado
     * `huerfano_glpi`. Por eso `resumen['total']` (el AD) y `resumen['filas']`
     * (lo que se ve en pantalla) no son el mismo número.
     *
     * @return array{
     *   equipos: Collection,
     *   resumen: array,
     *   generado: Carbon
     * }
     */
    public function analizar(): array
    {
        $equiposAd = Cache::remember($this->claveAd(), self::CACHE_TTL, fn() => $this->traerEquiposAd());
        $mapaGlpi  = Cache::remember($this->claveGlpi(), self::CACHE_TTL, fn() => $this->traerMapaGlpi());

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
                'glpi_id'         => $glpi['id'] ?? null,
                'glpi_nombre'     => $glpi['name'] ?? null,
                'last_contact'    => $lastContact,
                'dias_sin_reporte'=> $diasSinReporte,
                'estado'          => $estado,
            ];
        });

        // Se miden antes de sumar los huérfanos: son propiedades del lado AD.
        $totalAd  = $equiposAd->count();
        $enGlpiAd = $equipos->where('en_glpi', true)->count();

        // Huérfanos: están en el inventario de GLPI pero ya no tienen cuenta en
        // el AD. Entran como filas más a la tabla —con las columnas del lado AD
        // vacías, porque ese lado no existe— para que se puedan filtrar y abrir.
        $huerfanos = $mapaGlpi
            ->reject(fn($v, $clave) => isset($vistos[$clave]))
            ->map(function (array $g) use ($ahora) {
                $lastContact    = $g['last_contact'];
                $diasSinReporte = $lastContact ? (int) floor($lastContact->diffInDays($ahora)) : null;

                return [
                    'nombre'          => $g['name'],
                    'ou'              => null,
                    'so'              => $g['so'],
                    'deshabilitada'   => false,
                    'ultimo_login'    => null,
                    'dias_sin_login'  => null,
                    'en_glpi'         => true,
                    'glpi_id'         => $g['id'],
                    'glpi_nombre'     => $g['name'],
                    'last_contact'    => $lastContact,
                    'dias_sin_reporte'=> $diasSinReporte,
                    'estado'          => 'huerfano_glpi',
                ];
            })
            ->values();

        $equipos = $equipos->concat($huerfanos)->sortBy([
            // Primero lo que urge revisar; dentro de cada estado, lo más viejo arriba
            fn($a, $b) => $this->pesoEstado($a['estado']) <=> $this->pesoEstado($b['estado']),
            fn($a, $b) => ($b['dias_sin_login'] ?? -1) <=> ($a['dias_sin_login'] ?? -1),
        ])->values();

        $resumen = [
            // `total` es el AD, la lista maestra; `filas` incluye a los huérfanos,
            // que no salen de ahí. La tabla muestra `filas`.
            'total'          => $totalAd,
            'filas'          => $equipos->count(),
            'ok'             => $equipos->where('estado', 'ok')->count(),
            'falta_agente'   => $equipos->where('estado', 'falta_agente')->count(),
            'agente_mudo'    => $equipos->where('estado', 'agente_mudo')->count(),
            'posible_baja'   => $equipos->where('estado', 'posible_baja')->count(),
            'deshabilitado'  => $equipos->where('estado', 'deshabilitado')->count(),
            'huerfano_glpi'  => $huerfanos->count(),
            'en_glpi'        => $enGlpiAd,
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
            'huerfano_glpi' => 1,
            'falta_agente'  => 2,
            'agente_mudo'   => 3,
            'deshabilitado' => 4,
            'ok'            => 5,
            default         => 9,
        };
    }

    /* ── Lado AD ─────────────────────────────────────────────────────────── */

    private function traerEquiposAd(): Collection
    {
        // El dominio de Verfrut usa la conexión LDAP por defecto, que se pide
        // sin ::on(); los demás declaran la suya.
        $conexion = $this->dominio->ad();
        $consulta = $conexion ? Computer::on($conexion) : Computer::query();

        $computers = $consulta
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
        $filas = DB::connection($this->dominio->glpi())
            ->table('glpi_computers as c')
            ->leftJoin('glpi_agents as a', function ($j) {
                $j->on('a.items_id', '=', 'c.id')->where('a.itemtype', 'Computer');
            })
            ->leftJoin('glpi_items_operatingsystems as ios', function ($j) {
                $j->on('ios.items_id', '=', 'c.id')
                  ->where('ios.itemtype', 'Computer')
                  ->where('ios.is_deleted', 0);
            })
            ->leftJoin('glpi_operatingsystems as os', 'os.id', '=', 'ios.operatingsystems_id')
            ->where('c.is_deleted', 0)
            ->where('c.is_template', 0)
            ->groupBy('c.id', 'c.name', 'os.name')
            ->select('c.id', 'c.name', 'os.name as so', DB::raw('MAX(a.last_contact) as last_contact'))
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
                'so'           => $f->so ?: null,
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
