<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Traduce coordenadas a comuna y región.
 *
 * Se usa para *sugerir* esos datos cuando en terreno se toma el GPS: el que
 * levanta la ficha no siempre sabe en qué comuna está parado, sobre todo en
 * campos alejados. La sugerencia siempre queda editable — manda lo que escriba
 * la persona, no lo que diga el servicio.
 *
 * Usa Nominatim (OpenStreetMap): no necesita clave y para el orden de decenas
 * de consultas de un levantamiento va sobrado. Su política pide identificar la
 * aplicación en el User-Agent y no abusar, de ahí el caché largo.
 *
 * Si algún día hay clave de Google Maps, se reemplaza solo este servicio.
 */
class Geocodificador
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    /** Un mes: la comuna de un punto no cambia. */
    private const CACHE_SEGUNDOS = 2592000;

    /**
     * Devuelve ['region' => ..., 'comuna' => ...]; cualquiera puede venir null.
     *
     * El fallo se cachea igual que el acierto: si el servicio no responde para
     * un punto, no tiene sentido reintentar en cada guardado.
     */
    public function comunaYRegion(float $latitud, float $longitud): array
    {
        // ~11 m de precisión: suficiente para la comuna y evita repetir la
        // consulta por diferencias de metros entre dos capturas del mismo sitio.
        $clave = sprintf('geo_%.4f_%.4f', $latitud, $longitud);

        return Cache::remember($clave, self::CACHE_SEGUNDOS, function () use ($latitud, $longitud) {
            try {
                $resp = Http::withHeaders([
                        'User-Agent'      => 'VTI/1.0 (plataforma interna TI Unifrutti)',
                        'Accept-Language' => 'es',
                    ])
                    ->timeout(8)
                    ->get(self::ENDPOINT, [
                        'format'         => 'jsonv2',
                        'lat'            => $latitud,
                        'lon'            => $longitud,
                        'zoom'           => 10,      // nivel comuna
                        'addressdetails' => 1,
                    ]);

                if (!$resp->successful()) {
                    return ['region' => null, 'comuna' => null];
                }

                $dir = $resp->json('address') ?? [];

                return [
                    'region' => $this->primero($dir, ['region', 'state']),
                    // En Chile la comuna aparece con distintos nombres según la
                    // zona; se prueban en orden de lo más específico a lo menos.
                    'comuna' => $this->primero($dir, [
                        'city', 'town', 'village', 'municipality', 'county', 'city_district',
                    ]),
                ];
            } catch (\Throwable) {
                return ['region' => null, 'comuna' => null];
            }
        });
    }

    /** Primer valor no vacío de una lista de claves. */
    private function primero(array $datos, array $claves): ?string
    {
        foreach ($claves as $clave) {
            $v = trim((string) ($datos[$clave] ?? ''));
            if ($v !== '') return $v;
        }
        return null;
    }
}
