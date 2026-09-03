<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Acceso a Microsoft Graph con credenciales de aplicación.
 *
 * Las credenciales salen de la tabla `configuraciones`, no del .env, igual que
 * el resto de las integraciones. El token se comparte con EntraIDController
 * usando la misma clave de caché: son el mismo token para el mismo tenant y
 * pedirlo dos veces solo gasta cuota.
 */
class GraphClient
{
    public const BASE = 'https://graph.microsoft.com/v1.0';

    /** Los tokens de Graph duran 3600 s; se renuevan antes para no apurar el borde. */
    private const CACHE_TOKEN = 'entra_id_token';
    private const TTL_TOKEN   = 3500;

    public function token(): string
    {
        $tenant = Configuracion::get('azure_tenant_id');
        $id     = Configuracion::get('azure_client_id');
        $secret = Configuracion::get('azure_client_secret');

        if (!$tenant || !$id || !$secret) {
            throw new \RuntimeException(
                'Azure no está configurado. Completa client_id, client_secret y tenant_id en Admin → Configuración.'
            );
        }

        return Cache::remember(self::CACHE_TOKEN, self::TTL_TOKEN, function () use ($tenant, $id, $secret) {
            $r = Http::asForm()->timeout(30)->post(
                "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
                [
                    'client_id'     => $id,
                    'client_secret' => $secret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ]
            );

            if (!$r->successful()) {
                throw new \RuntimeException('No se pudo obtener el token de Azure: ' . $r->json('error_description', $r->body()));
            }

            return $r->json('access_token');
        });
    }

    public function get(string $url, int $timeout = 180)
    {
        return Http::withHeaders(['Authorization' => 'Bearer ' . $this->token()])
            ->timeout($timeout)
            ->get($url);
    }

    /**
     * Recorre una colección paginada siguiendo @odata.nextLink.
     *
     * El tope de páginas es una red de seguridad: si Graph devolviera un
     * nextLink que no avanza, esto evita un bucle infinito en una petición web.
     */
    public function paginar(string $url, int $maxPaginas = 20): Collection
    {
        $todo = collect();

        for ($i = 0; $i < $maxPaginas && $url; $i++) {
            $r = $this->get($url);

            if (!$r->successful()) {
                throw new \RuntimeException($this->mensaje($r));
            }

            $d    = $r->json();
            $todo = $todo->concat($d['value'] ?? []);
            $url  = $d['@odata.nextLink'] ?? null;
        }

        return $todo;
    }

    /**
     * Un reporte de uso de Microsoft 365. Vienen en CSV, no en JSON.
     *
     * Si el tenant tiene encendida la ofuscación de nombres, las columnas de
     * usuario llegan con identificadores en vez de correos y el reporte no
     * sirve para identificar a nadie; por eso se comprueba antes.
     */
    public function reporte(string $nombre, string $periodo = 'D180'): Collection
    {
        $r = $this->get(self::BASE . "/reports/{$nombre}(period='{$periodo}')");

        if (!$r->successful()) {
            throw new \RuntimeException($this->mensaje($r));
        }

        return $this->csv($r->body());
    }

    /** ¿Están ofuscados los nombres en los reportes? null si no se puede saber. */
    public function nombresOcultos(): ?bool
    {
        $r = $this->get(self::BASE . '/admin/reportSettings', 30);

        return $r->successful() ? (bool) $r->json('displayConcealedNames') : null;
    }

    /** CSV a colección de arreglos asociativos, indexados por encabezado. */
    private function csv(string $cuerpo): Collection
    {
        $cuerpo = preg_replace('/^\xEF\xBB\xBF/', '', $cuerpo);
        $lineas = preg_split('/\r\n|\n/', trim($cuerpo));

        if (!$lineas || count($lineas) < 2) {
            return collect();
        }

        $cab   = str_getcsv(array_shift($lineas));
        $filas = collect();

        foreach ($lineas as $linea) {
            if ($linea === '') {
                continue;
            }
            $v = str_getcsv($linea);
            if (count($v) === count($cab)) {
                $filas->push(array_combine($cab, $v));
            }
        }

        return $filas;
    }

    /** El error de Graph traducido; el cuerpo crudo es JSON anidado ilegible. */
    private function mensaje($respuesta): string
    {
        $codigo = $respuesta->json('error.code', '');
        $texto  = $respuesta->json('error.message', '');

        if ($respuesta->status() === 403 || str_contains((string) $codigo, 'PermissionMissing')) {
            return 'Microsoft Graph rechazó la consulta por falta de permisos. '
                 . 'La aplicación necesita Reports.Read.All y AuditLog.Read.All con consentimiento de administrador.';
        }
        if ($respuesta->status() === 401) {
            return 'Azure rechazó las credenciales. Revisa client_id y client_secret en Admin → Configuración.';
        }

        return 'Microsoft Graph respondió ' . $respuesta->status()
             . ($texto ? ': ' . mb_substr(strip_tags($texto), 0, 200) : '');
    }
}
