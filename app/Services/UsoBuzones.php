<?php

namespace App\Services;

use App\Models\BuzonExcluido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Uso real de los buzones de Microsoft 365.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Qué cuenta como "sin uso" y por qué:
 *
 *  Solo el que NUNCA inició sesión. Es la única señal que no admite discusión:
 *  si nadie se autenticó jamás, nadie abrió el buzón por ningún medio.
 *
 *  Deliberadamente NO se usa el contador de leídos. Mucha gente trabaja con
 *  miles de correos sin marcar como leídos y usa su cuenta todos los días;
 *  tomar "sin leer" por "sin usar" da falsos positivos a montones.
 *
 *  Tampoco se usa "recibe correo": un buzón recibe solo, sin que nadie lo toque.
 *
 *  Los buzones compartidos y funcionales quedan fuera por lista (ver
 *  BuzonExcluido): no tienen inicio de sesión propio porque se acceden por
 *  delegación, así que el indicador los marcaría a todos como sin uso.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Las dos fuentes son distintas y tienen alcances distintos:
 *
 *  - signInActivity cubre toda la vida de la cuenta.
 *  - Los reportes de correo llegan hasta 180 días hacia atrás, que es el máximo
 *    que ofrece la API. Por eso el veredicto se apoya en el inicio de sesión y
 *    los contadores de correo solo matizan.
 */
class UsoBuzones
{
    private const CACHE_KEY = 'uso_buzones_analisis';

    // Construir el análisis toma ~40 s: son dos recorridos completos del
    // directorio más dos reportes de Graph. Demasiado para una petición web, así
    // que la caché dura un día —los reportes de Microsoft se refrescan cada 24 h,
    // no tiene sentido pedirlos más seguido— y el comando `buzones:analizar` la
    // deja caliente de madrugada para que nadie espere.
    private const CACHE_TTL = 86400;

    public const PERIODO = 'D180';

    /** clave => [etiqueta, descripción, color, ícono] */
    public const CLASES = [
        'nunca_activado' => ['Nunca activado', 'Sin ningún inicio de sesión registrado', '#a63a22', 'bi-x-octagon'],
        'sin_actividad'  => ['Sin actividad',  'Inició sesión, pero no movió correo en el período', '#b37d22', 'bi-pause-circle'],
        'solo_recibe'    => ['Solo recibe',    'Usa la cuenta, pero nunca envió un correo', '#7fa48d', 'bi-inbox'],
        'activo'         => ['Activo',         'Envía correo', '#22553d', 'bi-check-circle'],
    ];

    public function __construct(private GraphClient $graph = new GraphClient()) {}

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{buzones: Collection, resumen: array, generado: Carbon, nombresOcultos: ?bool}
     */
    public function analizar(): array
    {
        $datos = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->construir());

        // Las exclusiones se aplican FUERA de la caché: cambiarlas debe verse al
        // instante, sin esperar media hora ni volver a bajar los reportes.
        $excluidos = BuzonExcluido::activos();

        $buzones = collect($datos['buzones'])
            ->map(function (array $b) use ($excluidos) {
                $b['excluido'] = isset($excluidos[$b['upn']]);
                return $b;
            })
            ->map(fn ($b) => $this->rehidratar($b));

        $activos = $buzones->where('excluido', false);

        return [
            'buzones'        => $buzones,
            'resumen'        => $this->resumir($activos, $buzones->where('excluido', true)->count()),
            'generado'       => Carbon::parse($datos['generado']),
            'nombresOcultos' => $datos['nombresOcultos'],
        ];
    }

    /* ── Construcción ────────────────────────────────────────────────────── */

    private function construir(): array
    {
        $ocultos = $this->graph->nombresOcultos();

        $usuarios = $this->graph->paginar(
            GraphClient::BASE . '/users?$top=999&$select=' . implode(',', [
                'id', 'userPrincipalName', 'displayName', 'accountEnabled', 'userType',
                'createdDateTime', 'department', 'jobTitle', 'assignedLicenses',
            ])
        );

        // signInActivity va en consulta aparte: Graph no la admite junto al resto
        // del $select en la misma llamada sin degradar el rendimiento.
        $firma = [];
        foreach ($this->graph->paginar(GraphClient::BASE . '/users?$top=999&$select=id,signInActivity') as $u) {
            $firma[$u['id']] = $u['signInActivity'] ?? null;
        }

        $correo = $this->graph->reporte('getEmailActivityUserDetail', self::PERIODO)
            ->keyBy(fn ($f) => mb_strtolower($f['User Principal Name'] ?? ''));

        $buzon = $this->graph->reporte('getMailboxUsageDetail', self::PERIODO)
            ->keyBy(fn ($f) => mb_strtolower($f['User Principal Name'] ?? ''));

        $sku = $this->skus();

        $buzones = $usuarios
            ->filter(fn ($u) => ($u['userType'] ?? '') === 'Member'
                             && ($u['accountEnabled'] ?? false)
                             && !empty($u['assignedLicenses'])
                             && $buzon->has(mb_strtolower($u['userPrincipalName'] ?? '')))
            ->map(function ($u) use ($firma, $correo, $buzon, $sku) {
                $upn = mb_strtolower($u['userPrincipalName']);
                $c   = $correo->get($upn, []);
                $b   = $buzon->get($upn, []);
                $s   = $firma[$u['id']] ?? null;

                $ultimoAcceso = $this->masReciente(
                    $s['lastSignInDateTime'] ?? null,
                    $s['lastNonInteractiveSignInDateTime'] ?? null
                );

                $enviados  = (int) ($c['Send Count'] ?? 0);
                $recibidos = (int) ($c['Receive Count'] ?? 0);

                return [
                    'upn'          => $upn,
                    'nombre'       => $u['displayName'] ?: $u['userPrincipalName'],
                    'departamento' => $u['department'] ?: null,
                    'cargo'        => $u['jobTitle'] ?: null,
                    'creado'       => $u['createdDateTime'] ?? null,
                    'ultimo_acceso' => $ultimoAcceso,
                    'enviados'     => $enviados,
                    'recibidos'    => $recibidos,
                    'ultima_actividad' => $c['Last Activity Date'] ?? null,
                    'mb'           => round(((int) ($b['Storage Used (Byte)'] ?? 0)) / 1048576, 1),
                    'items'        => (int) ($b['Item Count'] ?? 0),
                    'licencias'    => $this->licenciasDe($u['assignedLicenses'] ?? [], $sku),
                    'clase'        => $this->clasificar($ultimoAcceso, $enviados, $recibidos),
                ];
            })
            ->values()
            ->all();

        return [
            'buzones'        => $buzones,
            'generado'       => now()->toIso8601String(),
            'nombresOcultos' => $ocultos,
        ];
    }

    /**
     * El veredicto. Ver el bloque de arriba: manda el inicio de sesión, y los
     * contadores de correo solo separan a los que sí entraron.
     */
    private function clasificar(?string $ultimoAcceso, int $enviados, int $recibidos): string
    {
        if ($ultimoAcceso === null) {
            return 'nunca_activado';
        }
        if ($enviados > 0) {
            return 'activo';
        }

        return $recibidos > 0 ? 'solo_recibe' : 'sin_actividad';
    }

    private function resumir(Collection $buzones, int $excluidos): array
    {
        $total = $buzones->count();
        $r = ['total' => $total, 'excluidos' => $excluidos];

        foreach (array_keys(self::CLASES) as $clase) {
            $r[$clase] = $buzones->where('clase', $clase)->count();
        }

        $sinUso = $buzones->where('clase', 'nunca_activado');

        $r['sin_uso']          = $sinUso->count();
        $r['sin_uso_pct']      = $total > 0 ? round($sinUso->count() * 100 / $total, 1) : 0.0;
        $r['correo_al_vacio']  = $sinUso->sum('recibidos');
        $r['con_correo']       = $sinUso->where('recibidos', '>', 50)->count();
        $r['mb_sin_uso']       = round($sinUso->sum('mb') / 1024, 1);

        // Licencias retenidas por buzones que nunca se abrieron
        $lic = [];
        foreach ($sinUso as $b) {
            foreach ($b['licencias'] as $l) {
                $lic[$l] = ($lic[$l] ?? 0) + 1;
            }
        }
        arsort($lic);
        $r['licencias'] = $lic;

        // Cohortes de creación, que es donde se ve si el problema es una tanda
        $r['cohortes'] = $buzones
            ->filter(fn ($b) => $b['creado'])
            ->groupBy(fn ($b) => $b['creado']->format('Y-m'))
            ->map(fn ($g) => [
                'total'   => $g->count(),
                'sin_uso' => $g->where('clase', 'nunca_activado')->count(),
            ])
            ->sortKeys()
            ->all();

        $r['departamentos'] = $sinUso
            ->groupBy(fn ($b) => $b['departamento'] ?: '(sin departamento)')
            ->map->count()->sortDesc()->take(10)->all();

        return $r;
    }

    /* ── Apoyo ───────────────────────────────────────────────────────────── */

    /** Nombres de SKU por id, para no mostrar GUID en pantalla. */
    private function skus(): array
    {
        $r = $this->graph->get(GraphClient::BASE . '/subscribedSkus', 30);

        if (!$r->successful()) {
            return [];
        }

        $mapa = [];
        foreach ($r->json('value') ?? [] as $s) {
            $mapa[$s['skuId']] = $s['skuPartNumber'];
        }

        return $mapa;
    }

    private function licenciasDe(array $asignadas, array $sku): array
    {
        return collect($asignadas)
            ->map(fn ($l) => $sku[$l['skuId']] ?? mb_substr($l['skuId'], 0, 8))
            ->unique()->values()->all();
    }

    private function masReciente(?string $a, ?string $b): ?string
    {
        $fechas = array_filter([$a, $b]);

        return $fechas ? max($fechas) : null;
    }

    /** Fechas a Carbon al salir de la caché, que las guarda como texto. */
    private function rehidratar(array $b): array
    {
        foreach (['creado', 'ultimo_acceso', 'ultima_actividad'] as $campo) {
            $b[$campo] = $b[$campo] ? Carbon::parse($b[$campo]) : null;
        }

        $b['dias_sin_acceso'] = $b['ultimo_acceso']
            ? (int) floor($b['ultimo_acceso']->diffInDays(now()))
            : null;

        return $b;
    }
}
