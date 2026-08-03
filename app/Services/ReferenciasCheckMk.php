<?php

namespace App\Services;

use App\Models\MapaNodo;
use App\Models\SitioEquipo;
use App\Models\SitioHost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Vigila que lo que la aplicación tiene enlazado siga existiendo en CheckMK.
 *
 * Si allá borran o renombran un host, la ficha y el nodo del mapa quedan
 * apuntando al vacío y dejan de tener estado en vivo sin avisar. Aquí se
 * detecta esa situación, se proponen reemplazos por parecido de nombre y se
 * puede remapear todas las referencias de una vez.
 */
class ReferenciasCheckMk
{
    private const CACHE = 'sitios_referencias_huerfanas';
    private const CACHE_TTL = 60;

    /** Puntaje mínimo para ofrecer un host como reemplazo. */
    private const UMBRAL_SUGERENCIA = 35;

    public function __construct(private SitiosCheckMk $puente = new SitiosCheckMk()) {}

    /**
     * Referencias que ya no tienen host en CheckMK.
     *
     * @return array{ok:bool,error:?string,total_hosts:int,huerfanos:array}
     */
    public function huerfanos(): array
    {
        return Cache::remember(self::CACHE, self::CACHE_TTL, fn() => $this->calcular());
    }

    /** Solo el número, para los avisos del dashboard y del mapa. */
    public function cuantos(): int
    {
        $r = $this->huerfanos();

        return $r['ok'] ? count($r['huerfanos']) : 0;
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE);
    }

    private function calcular(): array
    {
        try {
            $vivos = $this->puente->estados()->keys();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'total_hosts' => 0, 'huerfanos' => []];
        }

        // Sin hosts no se puede concluir nada: mejor no declarar huérfano a nadie.
        if ($vivos->isEmpty()) {
            return [
                'ok'    => false,
                'error' => 'CheckMK respondió sin hosts. No se revisan los enlaces para no dar falsas alarmas.',
                'total_hosts' => 0,
                'huerfanos'   => [],
            ];
        }

        $vivosMin = $vivos->map(fn($h) => mb_strtolower($h))->all();
        $usos = $this->usosPorHost();
        $enUso = array_map(fn($h) => mb_strtolower($h), array_keys($usos));

        $huerfanos = [];
        foreach ($usos as $host => $lista) {
            if (in_array(mb_strtolower($host), $vivosMin, true)) continue;

            $huerfanos[] = [
                'host_name'   => $host,
                'usos'        => $lista,
                'sugerencias' => $this->sugerencias($host, $vivos, $enUso),
            ];
        }

        usort($huerfanos, fn($a, $b) => strnatcasecmp($a['host_name'], $b['host_name']));

        return ['ok' => true, 'error' => null, 'total_hosts' => $vivos->count(), 'huerfanos' => $huerfanos];
    }

    /**
     * Dónde está enlazado cada host: fichas, equipos y nodos del mapa.
     *
     * @return array<string,array<int,array>>
     */
    private function usosPorHost(): array
    {
        $out = [];

        SitioHost::with('sitio:id,codigo,nombre,tipo')->get()->each(function ($h) use (&$out) {
            if (!$h->host_name || !$h->sitio) return;
            $out[$h->host_name][] = [
                'origen'   => 'ficha',
                'titulo'   => $h->sitio->titulo,
                'detalle'  => $h->rol_label,
                'sitio_id' => $h->sitio->id,
            ];
        });

        SitioEquipo::whereNotNull('host_name')->where('host_name', '!=', '')
            ->with('sitio:id,codigo,nombre')
            ->get()
            ->each(function ($e) use (&$out) {
                $out[$e->host_name][] = [
                    'origen'   => 'equipo',
                    'titulo'   => $e->nombre,
                    'detalle'  => $e->tipo_label . ($e->sitio ? ' · ' . $e->sitio->nombre : ''),
                    'sitio_id' => $e->sitio_id,
                ];
            });

        MapaNodo::whereNotNull('host_name')->where('host_name', '!=', '')
            ->with('mapa:id,nombre')
            ->get()
            ->each(function ($n) use (&$out) {
                $out[$n->host_name][] = [
                    'origen'  => 'nodo',
                    'titulo'  => $n->etiqueta ?: $n->host_name,
                    'detalle' => $n->mapa?->nombre ?? 'Mapa',
                    'mapa_id' => $n->mapa_id,
                ];
            });

        return $out;
    }

    /**
     * Candidatos a reemplazo, ordenados por parecido. Prioriza los hosts que
     * todavía no están enlazados a nada: un renombre deja al nuevo host libre.
     *
     * @return array<int,array{host_name:string,score:int,libre:bool}>
     */
    private function sugerencias(string $huerfano, Collection $vivos, array $enUso): array
    {
        $codigo = $this->codigo($huerfano);
        $base = $this->clave($huerfano);

        $candidatos = [];
        foreach ($vivos as $host) {
            similar_text($base, $this->clave($host), $pct);
            $score = $pct;

            if ($codigo && $codigo === $this->codigo($host)) $score += 25;

            $libre = !in_array(mb_strtolower($host), $enUso, true);
            if ($libre) $score += 10;

            if ($score < self::UMBRAL_SUGERENCIA) continue;

            $candidatos[] = ['host_name' => $host, 'score' => (int) min(100, round($score)), 'libre' => $libre];
        }

        usort($candidatos, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($candidatos, 0, 5);
    }

    /**
     * Apunta todas las referencias de un host viejo hacia uno nuevo.
     *
     * @return array{fichas:int,equipos:int,nodos:int}
     */
    public function remapear(string $viejo, string $nuevo): array
    {
        $resultado = [
            'fichas'  => SitioHost::where('host_name', $viejo)->update(['host_name' => $nuevo]),
            'equipos' => SitioEquipo::where('host_name', $viejo)->update(['host_name' => $nuevo]),
            'nodos'   => MapaNodo::where('host_name', $viejo)->update(['host_name' => $nuevo]),
        ];

        $this->olvidarCache();

        return $resultado;
    }

    /**
     * Suelta las referencias a un host: borra los enlaces de ficha y deja
     * equipos y nodos sin host (no se borra nada más, el inventario se conserva).
     *
     * @return array{fichas:int,equipos:int,nodos:int}
     */
    public function desenlazar(string $host): array
    {
        $resultado = [
            'fichas'  => SitioHost::where('host_name', $host)->delete(),
            'equipos' => SitioEquipo::where('host_name', $host)->update(['host_name' => null]),
            'nodos'   => MapaNodo::where('host_name', $host)->update(['host_name' => null]),
        ];

        $this->olvidarCache();

        return $resultado;
    }

    /** Prefijo numérico del nombre: "19.CAMPO_PORVENIR" → "19". */
    private function codigo(string $host): ?string
    {
        return preg_match('/^(\d+)\./', $host, $m) ? $m[1] : null;
    }

    /** Nombre comparable: sin prefijo, sin separadores y en minúsculas. */
    private function clave(string $host): string
    {
        $h = preg_replace('/^\d+\./', '', mb_strtolower($host));

        return preg_replace('/[^a-z0-9]/', '', $h);
    }
}
