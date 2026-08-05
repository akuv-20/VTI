<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para la API REST de Veeam Backup & Replication (v12+).
 *
 * Autenticación: OAuth2 "password grant" contra /api/oauth2/token; el token
 * resultante se envía como  Authorization: Bearer <token>  en cada llamada.
 * Sirve una cuenta de dominio con rol de solo lectura en VBR
 * (Veeam Backup Viewer), en formato DOMINIO\usuario o usuario@dominio.
 *
 * Base de la API:  {url}/api/v1     — el puerto por defecto de VBR es el 9419.
 *
 * La configuración (url, usuario, contraseña) se guarda en la tabla
 * `configuraciones` y se administra desde Admin → Configuración → Veeam.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  NOTA sobre la cabecera x-api-version:
 *  VBR exige declarar la revisión de la API en cada request y la rechaza si no
 *  coincide con las que soporta la build instalada. Como el número cambia entre
 *  versiones (12.0 → 1.1-rev0/1, 12.1 → 1.2-rev0…), probarConexion() las recorre
 *  de la más nueva a la más vieja y guarda en `veeam_api_version` la primera que
 *  el servidor acepta. El resto del cliente reusa ese valor.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class VeeamClient
{
    /** Revisiones conocidas, de la más nueva a la más antigua. */
    private const VERSIONES_API = ['1.2-rev1', '1.2-rev0', '1.1-rev2', '1.1-rev1', '1.1-rev0', '1.0-rev2'];

    private const CACHE_TOKEN = 'veeam_access_token';

    private ?string $url;
    private ?string $user;
    private ?string $password;
    private string  $apiVersion;

    public function __construct(?string $url = null, ?string $user = null, ?string $password = null)
    {
        $this->url      = rtrim((string) ($url ?? Configuracion::get('veeam_url', env('VEEAM_URL', ''))), '/') ?: null;
        $this->user     = trim((string) ($user ?? Configuracion::get('veeam_user', env('VEEAM_USER', '')))) ?: null;
        $this->password = (string) ($password ?? Configuracion::get('veeam_password', env('VEEAM_PASSWORD', ''))) ?: null;

        $this->apiVersion = (string) Configuracion::get('veeam_api_version', '') ?: self::VERSIONES_API[0];
    }

    public function configurado(): bool
    {
        return (bool) ($this->url && $this->user && $this->password);
    }

    /** Base de la API v1 del servidor VBR. */
    private function apiBase(): string
    {
        return "{$this->url}/api/v1";
    }

    private function asegurarConfigurado(): void
    {
        if (!$this->configurado()) {
            throw new \RuntimeException('Veeam no está configurado. Completa URL, usuario y contraseña en Admin → Configuración.');
        }
    }

    /* ── Autenticación ───────────────────────────────────────────────────── */

    /**
     * Pide un token al servidor con la revisión de API indicada.
     *
     * @return array{ok:bool,token:?string,expires_in:int,error:?string}
     */
    private function pedirToken(string $apiVersion): array
    {
        try {
            $resp = Http::asForm()
                ->withHeaders(['x-api-version' => $apiVersion, 'Accept' => 'application/json'])
                ->timeout(20)
                ->withOptions(['verify' => false]) // VBR usa certificado autofirmado
                ->post($this->url . '/api/oauth2/token', [
                    'grant_type' => 'password',
                    'username'   => $this->user,
                    'password'   => $this->password,
                ]);

            if ($resp->successful() && $resp->json('access_token')) {
                return [
                    'ok'         => true,
                    'token'      => $resp->json('access_token'),
                    'expires_in' => (int) ($resp->json('expires_in') ?: 900),
                    'error'      => null,
                ];
            }

            return [
                'ok'         => false,
                'token'      => null,
                'expires_in' => 0,
                'error'      => $this->mensajeError($resp),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'token' => null, 'expires_in' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Token válido, cacheado hasta poco antes de que expire.
     *
     * El fallo NO se cachea: si las credenciales fallan queremos que el próximo
     * intento vuelva a preguntar, no arrastrar un null durante minutos.
     */
    private function token(): string
    {
        $this->asegurarConfigurado();

        $cacheado = Cache::get(self::CACHE_TOKEN);
        if (is_string($cacheado) && $cacheado !== '') {
            return $cacheado;
        }

        $r = $this->pedirToken($this->apiVersion);
        if (!$r['ok']) {
            throw new \RuntimeException('No se pudo autenticar contra Veeam: ' . $r['error']);
        }

        // Margen de 60 s para no usar un token que expira en pleno request.
        Cache::put(self::CACHE_TOKEN, $r['token'], max(60, $r['expires_in'] - 60));

        return $r['token'];
    }

    /** Invalida el token cacheado (tras cambiar credenciales, por ejemplo). */
    public static function olvidarToken(): void
    {
        Cache::forget(self::CACHE_TOKEN);
    }

    /** Cliente HTTP autenticado. */
    private function http(int $timeout = 30)
    {
        return Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token(),
                'x-api-version' => $this->apiVersion,
                'Accept'        => 'application/json',
            ])
            ->timeout($timeout)
            ->withOptions(['verify' => false]);
    }

    /** Extrae el mensaje más útil de una respuesta de error de VBR. */
    private function mensajeError($resp): string
    {
        $json = $resp->json();
        foreach (['message', 'error_description', 'errorMessage', 'detail', 'error'] as $k) {
            if (!empty($json[$k]) && is_string($json[$k])) {
                return $json[$k];
            }
        }
        return 'HTTP ' . $resp->status();
    }

    /* ── Conexión ────────────────────────────────────────────────────────── */

    /**
     * Prueba la conexión: negocia la revisión de API, autentica y lee la
     * identificación del servidor. Devuelve [ok, message].
     *
     * Si encuentra una revisión que funciona la persiste en `veeam_api_version`
     * para que el resto del cliente no tenga que volver a negociarla.
     */
    public function probarConexion(): array
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'message' => 'Completa URL, usuario y contraseña antes de probar.'];
        }

        // Probar primero la revisión guardada; luego el resto, de nueva a vieja.
        $candidatas = array_values(array_unique(array_merge([$this->apiVersion], self::VERSIONES_API)));

        $ultimoError = 'No hubo respuesta del servidor.';

        foreach ($candidatas as $version) {
            $r = $this->pedirToken($version);

            if (!$r['ok']) {
                $ultimoError = $r['error'];
                // Credenciales malas, MFA o servidor inalcanzable: no tiene
                // sentido seguir probando revisiones, el problema es otro.
                if ($this->esErrorDeMfa($r['error'])
                    || $this->esErrorDeCredenciales($r['error'])
                    || $this->esErrorDeRed($r['error'])) {
                    break;
                }
                continue;
            }

            // Autenticó: fijar la revisión y confirmar con una llamada real.
            $this->apiVersion = $version;
            Configuracion::set('veeam_api_version', $version);
            Cache::put(self::CACHE_TOKEN, $r['token'], max(60, $r['expires_in'] - 60));

            try {
                $info = $this->serverInfo();
                $nombre = $info['name'] ?? 'servidor VBR';
                $build  = $info['buildVersion'] ?? 'versión desconocida';

                return [
                    'ok'      => true,
                    'message' => "Conexión exitosa — {$nombre} (Veeam B&R {$build}, API {$version})",
                ];
            } catch (\Throwable $e) {
                return [
                    'ok'      => true,
                    'message' => "Autenticación exitosa (API {$version}), pero no se pudo leer la información del servidor: " . $e->getMessage(),
                ];
            }
        }

        // El password grant de VBR no admite cuentas con MFA. No es un problema
        // de credenciales ni de red, así que conviene decir qué hacer.
        if ($this->esErrorDeMfa($ultimoError)) {
            return [
                'ok'      => false,
                'message' => 'La cuenta tiene MFA activo y la API REST de Veeam no admite doble factor. '
                           . 'Hay que usar una cuenta marcada como "de servicio" en Veeam (Users and Roles), '
                           . 'con rol de solo lectura (Veeam Backup Viewer).',
            ];
        }

        return ['ok' => false, 'message' => 'No se pudo conectar: ' . $ultimoError];
    }

    private function esErrorDeMfa(?string $error): bool
    {
        $e = strtolower((string) $error);
        return str_contains($e, 'multifactor') || str_contains($e, 'multi-factor') || str_contains($e, 'mfa');
    }

    private function esErrorDeCredenciales(?string $error): bool
    {
        $e = strtolower((string) $error);
        return str_contains($e, 'credential')
            || str_contains($e, 'username')
            || str_contains($e, 'password')
            || str_contains($e, 'logon')
            || str_contains($e, 'unauthorized')
            || str_contains($e, 'access denied');
    }

    private function esErrorDeRed(?string $error): bool
    {
        $e = strtolower((string) $error);
        return str_contains($e, 'timed out')
            || str_contains($e, 'timeout')
            || str_contains($e, 'could not resolve')
            || str_contains($e, 'connection refused')
            || str_contains($e, 'failed to connect')
            || str_contains($e, 'ssl');
    }

    /** Identificación del servidor VBR. */
    public function serverInfo(): array
    {
        $resp = $this->http(20)->get($this->apiBase() . '/serverInfo');

        if (!$resp->successful()) {
            throw new \RuntimeException('Error al leer la información del servidor Veeam: ' . $this->mensajeError($resp));
        }

        return $resp->json() ?? [];
    }

    /* ── Jobs y sesiones ─────────────────────────────────────────────────── */

    /**
     * Trabajos configurados en VBR (nombre, tipo, si está habilitado, schedule).
     *
     * @return Collection<int,array>
     */
    public function jobs(): Collection
    {
        $this->asegurarConfigurado();

        return $this->paginar($this->apiBase() . '/jobs', ['limit' => 200]);
    }

    /**
     * Sesiones de trabajo en un rango de fechas.
     *
     * Cada sesión es una ejecución concreta: trae `jobName`, `sessionType`,
     * `state`, `result` (Success / Warning / Failed / None), `creationTime` y
     * `endTime`. Es la fuente para el KPI 2 — un libro de ejecuciones, no un
     * estado instantáneo.
     *
     * @param  string|null  $tipo  p.ej. 'BackupJob'; null = todas
     * @return Collection<int,array>
     */
    public function sesiones(Carbon $desde, Carbon $hasta, ?string $tipo = null): Collection
    {
        $this->asegurarConfigurado();

        $params = [
            'limit'                => 500,
            'createdAfterFilter'   => $desde->copy()->startOfDay()->toIso8601ZuluString(),
            'createdBeforeFilter'  => $hasta->copy()->endOfDay()->toIso8601ZuluString(),
        ];
        if ($tipo) {
            $params['typeFilter'] = $tipo;
        }

        return $this->paginar($this->apiBase() . '/sessions', $params);
    }

    /**
     * Recorre un endpoint paginado de VBR y devuelve todas las filas.
     *
     * La API responde { data: [...], pagination: { total, count, skip, limit } }.
     */
    private function paginar(string $endpoint, array $params): Collection
    {
        $filas = collect();
        $skip  = 0;
        $limit = (int) ($params['limit'] ?? 200);

        do {
            $resp = $this->http(60)->get($endpoint, $params + ['skip' => $skip]);

            if (!$resp->successful()) {
                throw new \RuntimeException('Error al consultar Veeam (' . $endpoint . '): ' . $this->mensajeError($resp));
            }

            $data = $resp->json('data') ?? [];
            $filas = $filas->concat($data);

            $total = (int) ($resp->json('pagination.total') ?? count($data));
            $skip += $limit;
        } while (count($data) > 0 && $skip < $total);

        return $filas;
    }
}
