<?php

namespace App\Http\Controllers;

use App\Models\KpiDisponibilidadMensual;
use App\Models\LineaTelefonica;
use App\Models\Roaming;
use App\Models\Servicio;
use App\Models\Sitio;
use App\Models\User;
use App\Services\KpiDisponibilidad;
use App\Services\ReferenciasCheckMk;
use App\Services\SitiosCheckMk;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard general de la plataforma.
 *
 * Arma paneles con gráficos por área y un centro de alertas accionables.
 * Reglas de la pantalla:
 *  - Solo se calcula lo que el usuario puede ver (sus módulos y gates).
 *  - Todo lo que dependa de un sistema externo (CheckMK, GLPI) va cacheado y
 *    dentro de try/catch: que se caiga una integración no puede tumbar el home.
 *  - Cada número lleva a su módulo con el filtro ya aplicado.
 */
class HomeController extends Controller
{
    private const CACHE_EXTERNO = 60;
    private const CACHE_GLPI    = 300;

    /** Paleta compartida por tarjetas y gráficos. */
    private const C_VERDE  = '#16a34a';
    private const C_ROJO   = '#dc2626';
    private const C_AMBAR  = '#d97706';
    private const C_AZUL   = '#0284c7';
    private const C_MORADO = '#7c3aed';
    private const C_GRIS   = '#94a3b8';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $hoy  = Carbon::now();
        $user = auth()->user();

        $panel = [
            'red'         => $this->red($user),
            'sitios'      => $this->sitios($user),
            'kpi'         => $this->kpi($user),
            'telefonia'   => $this->telefonia($user),
            'facturacion' => $this->facturacion($user, $hoy),
            'inventario'  => $this->inventario($user),
            'admin'       => $this->admin($user),
        ];

        return view('home', [
            'p'            => $panel,
            'alertas'      => $this->alertas($panel),
            'vacio'        => collect($panel)->filter()->isEmpty(),
            'periodoLabel' => ucfirst($hoy->locale('es')->isoFormat('MMMM [de] YYYY')),
            'fechaLarga'   => ucfirst($hoy->locale('es')->isoFormat('dddd D [de] MMMM')),
            'saludo'       => $this->saludo($hoy),
            'nombre'       => explode(' ', trim($user->name ?? ''))[0] ?? '',
        ]);
    }

    /* ── Red en vivo (CheckMK) ───────────────────────────────────────────── */

    private function red(User $user): ?array
    {
        if (!$user->can('acceso_monitoreo')) return null;

        // Se cachea siempre un array con 'ok': un null no se distingue de una
        // caché vacía y haría reintentar la consulta en cada carga.
        $d = Cache::remember('home_estado_red', self::CACHE_EXTERNO, function () {
            try {
                $hosts = (new SitiosCheckMk())->estados();
                if ($hosts->isEmpty()) return ['ok' => false];

                return [
                    'ok'       => true,
                    'total'    => $hosts->count(),
                    'caidos'   => $hosts->filter(fn($h) => !$h['downtime'] && $h['state'] !== 0)->count(),
                    'downtime' => $hosts->filter(fn($h) => $h['downtime'])->count(),
                ];
            } catch (\Throwable) {
                return ['ok' => false];
            }
        });

        $d['url'] = route('admin.monitoreo.mapas.index');

        if ($d['ok']) {
            $d['en_linea'] = $d['total'] - $d['caidos'] - $d['downtime'];
            $d['pct']      = $d['total'] ? round($d['en_linea'] / $d['total'] * 100, 1) : 0;
        }

        return $d;
    }

    /* ── Sitios ──────────────────────────────────────────────────────────── */

    private function sitios(User $user): ?array
    {
        if (!$user->can('acceso_sitios')) return null;

        // Completas de una vez: la completitud mira todos los campos.
        $sitios = Sitio::activos()->get();

        // Matriz tipo × estado, base del gráfico de barras apiladas.
        $porTipo = [];
        foreach (Sitio::TIPOS as $tipo => $label) {
            $delTipo = $sitios->where('tipo', $tipo);
            if ($delTipo->isEmpty()) continue;

            $porTipo[$tipo] = [
                'label'   => $label,
                'total'   => $delTipo->count(),
                'estados' => collect(Sitio::ESTADOS_ENLACE)
                    ->map(fn($_, $e) => $delTipo->where('estado_enlace', $e)->count())
                    ->all(),
            ];
        }

        $rotos = Cache::remember('home_enlaces_rotos', self::CACHE_EXTERNO, function () {
            try {
                $r = (new ReferenciasCheckMk())->huerfanos();
                return $r['ok'] ? ['ok' => true, 'n' => count($r['huerfanos'])] : ['ok' => false];
            } catch (\Throwable) {
                return ['ok' => false];
            }
        });

        return [
            'total'       => $sitios->count(),
            'por_tipo'    => $porTipo,
            'por_estado'  => collect(Sitio::ESTADOS_ENLACE)
                ->map(fn($_, $e) => $sitios->where('estado_enlace', $e)->count())->all(),
            'completitud' => $sitios->isEmpty() ? 0 : (int) round($sitios->avg(fn(Sitio $s) => $s->completitud)),
            'sin_ficha'   => $sitios->filter(fn(Sitio $s) => empty($s->todosLosHosts()))->count(),
            'rotos'       => $rotos,
            'url'         => route('admin.sitios.index'),
            // URLs ya resueltas: Blade no acepta closures dentro de @json.
            'url_estado'  => collect(Sitio::ESTADOS_ENLACE)
                ->map(fn($_, $e) => route('admin.sitios.index', ['estado' => $e]))->all(),
            'url_avance'  => route('admin.sitios.dashboard'),
            'url_enlaces' => route('admin.sitios.enlaces'),
        ];
    }

    /* ── KPI 1: disponibilidad ───────────────────────────────────────────── */

    private function kpi(User $user): ?array
    {
        if (!$user->can('acceso_kpi')) return null;

        $base = [
            'url'          => route('admin.kpi.disponibilidad.dashboard'),
            'url_informe'  => route('admin.kpi.disponibilidad.informe'),
            'url_servicios'=> route('admin.kpi.disponibilidad.servicios'),
            'meta'         => KpiDisponibilidad::META,
        ];

        $ultimo = KpiDisponibilidadMensual::orderByDesc('anio')->orderByDesc('mes')->first();
        if (!$ultimo) return $base + ['ok' => false];

        // Serie de los últimos 12 meses cerrados, para la evolución.
        $serie = KpiDisponibilidadMensual::selectRaw('anio, mes, AVG(pct) as pct, COUNT(*) as n')
            ->groupBy('anio', 'mes')->orderBy('anio')->orderBy('mes')->get()
            ->take(-12)
            ->map(fn($f) => [
                'label' => Carbon::create($f->anio, $f->mes, 1)->locale('es')->isoFormat('MMM YY'),
                'pct'   => round((float) $f->pct, 2),
            ])->values()->all();

        $mes   = KpiDisponibilidadMensual::anio($ultimo->anio)->mes($ultimo->mes)->get();
        $pct   = round((float) $mes->avg('pct'), 2);
        $nivel = KpiDisponibilidad::nivel($pct);

        return $base + [
            'ok'         => true,
            'pct'        => $pct,
            'nivel'      => $nivel,
            'nivel_txt'  => KpiDisponibilidad::NIVELES[$nivel],
            'color'      => KpiDisponibilidad::colorNivel($nivel),
            'mes'        => ucfirst(Carbon::create($ultimo->anio, $ultimo->mes, 1)->locale('es')->isoFormat('MMMM YYYY')),
            'servicios'  => $mes->count(),
            'peores'     => $mes->sortBy('pct')->take(5)->map(fn($f) => [
                'nombre' => $f->service_description ?: $f->host_name,
                'pct'    => round((float) $f->pct, 2),
            ])->values()->all(),
            'serie'      => $serie,
        ];
    }

    /* ── Telefonía ───────────────────────────────────────────────────────── */

    private function telefonia(User $user): ?array
    {
        if (!$user->tieneAcceso('lineas_telefonicas.index')) return null;

        $activas   = fn() => LineaTelefonica::where('estado', 'Activo');
        $porEmisor = fn(string $n) => $activas()
            ->whereHas('emisor', fn($q) => $q->where('nombre', 'like', "%{$n}%"))->count();

        $emisores = [];
        foreach (['Entel' => '#2563eb', 'Movistar' => '#0ea5e9', 'WOM' => '#a855f7'] as $nombre => $color) {
            $emisores[$nombre] = [
                'n'     => $porEmisor($nombre),
                'color' => $color,
                'url'   => route('lineas_telefonicas.index', ['emisor' => $nombre, 'estado' => 'Activo']),
            ];
        }

        $d = [
            'emisores'    => $emisores,
            'total'       => $activas()->count(),
            'inactivas'   => LineaTelefonica::where('estado', 'Inactivo')->count(),
            'sin_usuario' => $activas()->whereNull('id_usuario')->count(),
            'incompletas' => $activas()->where(function ($q) {
                $q->whereNull('id_usuario')->orWhereNull('id_empresa')
                  ->orWhereNull('id_ubicacion')->orWhereNull('id_centro_costo');
            })->count(),
            'url'         => route('lineas_telefonicas.index'),
            'url_sin_usuario' => route('lineas_telefonicas.index', ['sin_usuario' => 1, 'estado' => 'Activo']),
            'url_incompletas' => route('lineas_telefonicas.index', ['incompletas' => 1, 'estado' => 'Activo']),
        ];

        if ($user->tieneAcceso('roamings.index')) {
            $d['roaming']     = Roaming::activos()->count();
            $d['url_roaming'] = route('roamings.index');
        }

        return $d;
    }

    /* ── Facturación ─────────────────────────────────────────────────────── */

    private function facturacion(User $user, Carbon $hoy): ?array
    {
        if (!$user->tieneAcceso('servicios.index') && !$user->tieneAcceso('facturas.index')) return null;

        $delMes = fn($q) => $q->whereMonth('fecha_emision', $hoy->month)->whereYear('fecha_emision', $hoy->year);

        $periodicos = Servicio::where('es_periodico', true)->count();
        $facturados = Servicio::where('es_periodico', true)->whereHas('facturas', $delMes)->count();

        return [
            'periodicos' => $periodicos,
            'facturados' => $facturados,
            'pendientes' => $periodicos - $facturados,
            'pct'        => $periodicos ? round($facturados / $periodicos * 100) : 0,
            'url'        => route('servicios.index'),
            'url_pendientes' => $user->tieneAcceso('facturas.pendientes')
                ? route('facturas.pendientes', ['mes' => $hoy->month, 'anio' => $hoy->year])
                : route('servicios.index'),
        ];
    }

    /* ── Inventario TI (GLPI) ────────────────────────────────────────────── */

    /**
     * Tarjeta del inventario de Verfrut.
     *
     * Sigue mirando solo ese dominio a propósito: mezclar los conteos de dos
     * GLPI en un número daría un total que no se corresponde con ninguna
     * pantalla. El enlace lleva al listado de Verfrut.
     */
    private function inventario(User $user): ?array
    {
        $dom = \App\Services\DominioInventario::de('verfrut');

        if (!$dom || !$user->tieneAcceso('inventario.verfrut.equipos')) return null;

        $d = Cache::remember('home_inventario_glpi', self::CACHE_GLPI, function () use ($dom) {
            try {
                $base = function () use ($dom) {
                    $q = DB::connection($dom->glpi())->table('glpi_computers as c')
                        ->where('c.is_deleted', 0)->where('c.is_template', 0);

                    return $dom->sinUsuarioExcluido($q);
                };

                return [
                    'ok'        => true,
                    'total'     => $base()->count(),
                    'sin_dueno' => $base()->where('c.users_id', 0)->count(),
                ];
            } catch (\Throwable) {
                return ['ok' => false];
            }
        });

        return $d + ['url' => route('inventario.verfrut.equipos')];
    }

    /* ── Admin ───────────────────────────────────────────────────────────── */

    private function admin(User $user): ?array
    {
        if (!$user->can('admin')) return null;

        return [
            'activos'   => User::where('activo', true)->count(),
            'inactivos' => User::where('activo', false)->count(),
            'url'       => route('admin.usuarios.index'),
        ];
    }

    /* ── Centro de alertas ───────────────────────────────────────────────── */

    /** Lo que requiere acción hoy, ya enlazado a su vista filtrada. */
    private function alertas(array $p): array
    {
        $out = [];
        $add = function (int $n, string $texto, string $url, string $color, string $icono) use (&$out) {
            if ($n > 0) $out[] = compact('n', 'texto', 'url', 'color', 'icono');
        };

        if (($p['red']['ok'] ?? false)) {
            $add($p['red']['caidos'], 'hosts sin responder en CheckMK', $p['red']['url'], self::C_ROJO, 'bi-exclamation-octagon-fill');
        }
        if ($p['sitios']) {
            $add($p['sitios']['rotos']['n'] ?? 0, 'enlaces apuntan a hosts que ya no existen',
                $p['sitios']['url_enlaces'], self::C_ROJO, 'bi-link-45deg');
            $add($p['sitios']['por_estado']['sin_enlace'] ?? 0, 'sitios todavía sin conectividad',
                $p['sitios']['url_estado']['sin_enlace'], self::C_AMBAR, 'bi-plug');
            $add($p['sitios']['sin_ficha'], 'fichas sin ningún host monitoreado',
                $p['sitios']['url_avance'], self::C_AMBAR, 'bi-eye-slash');
        }
        if ($p['facturacion']) {
            $add($p['facturacion']['pendientes'], 'servicios sin facturar este mes',
                $p['facturacion']['url_pendientes'], self::C_ROJO, 'bi-receipt');
        }
        if ($p['telefonia']) {
            $add($p['telefonia']['sin_usuario'], 'líneas activas sin usuario asignado',
                $p['telefonia']['url_sin_usuario'], self::C_AMBAR, 'bi-person-x-fill');
            $add($p['telefonia']['incompletas'], 'líneas con datos incompletos',
                $p['telefonia']['url_incompletas'], self::C_AMBAR, 'bi-clipboard-x');
        }
        if ($p['inventario']['ok'] ?? false) {
            $add($p['inventario']['sin_dueno'], 'equipos de GLPI sin responsable',
                $p['inventario']['url'], self::C_AMBAR, 'bi-pc-display');
        }
        if ($p['kpi']['ok'] ?? false) {
            if ($p['kpi']['nivel'] < 3) {
                $out[] = [
                    'n' => $p['kpi']['pct'] . '%', 'texto' => 'de disponibilidad: bajo la meta de ' . $p['kpi']['meta'] . '%',
                    'url' => $p['kpi']['url'], 'color' => self::C_ROJO, 'icono' => 'bi-activity',
                ];
            }
        }

        return $out;
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    private function saludo(Carbon $hoy): string
    {
        return match (true) {
            $hoy->hour < 12 => 'Buenos días',
            $hoy->hour < 20 => 'Buenas tardes',
            default         => 'Buenas noches',
        };
    }
}
