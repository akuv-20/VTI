<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de la API REST (v1, `apirest.php`) del GLPI de Unifrutti.
 *
 * Es el ÚNICO camino de escritura hacia GLPI: la conexión de base de datos es
 * de solo lectura y además, escribiendo por la API, GLPI hace lo suyo (cambia
 * el estado, envía las notificaciones, aplica reglas y deja el historial).
 *
 * Cada llamada va a nombre de una persona: se abre la sesión con SU token
 * personal, así el historial del ticket dice quién asignó. Además del token
 * personal, GLPI exige el App-Token del cliente de API, que es global y se
 * guarda cifrado en Admin → Configuración.
 */
class GlpiApi
{
    public const URL_POR_DEFECTO = 'https://helpdesk.unifrutti.com/apirest.php';

    private const TIMEOUT = 20; // segundos

    private ?string $sesion = null;

    public function __construct(private string $tokenPersonal) {}

    /** Si está el App-Token, sin el cual GLPI rechaza cualquier llamada. */
    public static function configurada(): bool
    {
        return (bool) Configuracion::get('glpiuni_api_app_token');
    }

    /**
     * Raíz web de GLPI (`https://helpdesk.unifrutti.com`), para enlazar
     * tickets y adjuntos. Sale de la URL de la API para no tener dos datos
     * que mantener: la API vive en `<raíz>/apirest.php`.
     */
    public static function urlWeb(): string
    {
        $api = rtrim(Configuracion::get('glpiuni_api_url') ?: self::URL_POR_DEFECTO, '/');

        return preg_replace('#/(apirest\.php|api\.php(/v\d[\d.]*)?)$#', '', $api);
    }

    private function url(string $ruta): string
    {
        return rtrim(Configuracion::get('glpiuni_api_url') ?: self::URL_POR_DEFECTO, '/') . '/' . ltrim($ruta, '/');
    }

    private function appToken(): string
    {
        $cifrado = Configuracion::get('glpiuni_api_app_token');
        if (!$cifrado) {
            throw new GlpiApiException('Falta el App-Token de la API de GLPI en Admin → Configuración.');
        }

        return Crypt::decryptString($cifrado);
    }

    private function http(array $extra = [])
    {
        return Http::timeout(self::TIMEOUT)
            ->acceptJson()
            ->withHeaders(['App-Token' => $this->appToken()] + $extra);
    }

    /**
     * Convierte una respuesta de error de GLPI en excepción. GLPI responde los
     * errores como `["ERROR_CODIGO", "mensaje"]`.
     */
    private function verificar($resp, string $accion)
    {
        if ($resp->successful()) return $resp;

        $cuerpo  = $resp->json();
        $codigo  = is_array($cuerpo) ? ($cuerpo[0] ?? null) : null;
        $mensaje = is_array($cuerpo) ? ($cuerpo[1] ?? $resp->body()) : $resp->body();

        throw new GlpiApiException("GLPI rechazó «{$accion}»: {$mensaje}", $codigo, $resp->status());
    }

    /* ── Sesión ───────────────────────────────────────────────────────────── */

    private function abrir(): string
    {
        if ($this->sesion) return $this->sesion;

        $resp = $this->verificar(
            $this->http(['Authorization' => 'user_token ' . $this->tokenPersonal])->get($this->url('initSession')),
            'iniciar sesión'
        );

        return $this->sesion = (string) $resp->json('session_token');
    }

    private function cerrar(): void
    {
        if (!$this->sesion) return;

        try {
            $this->http(['Session-Token' => $this->sesion])->get($this->url('killSession'));
        } catch (\Throwable) {
            // Si no se pudo cerrar, GLPI la expira solo.
        }
        $this->sesion = null;
    }

    /** Ejecuta las llamadas dentro de una sesión que siempre se cierra al final. */
    private function enSesion(\Closure $trabajo): mixed
    {
        try {
            $this->abrir();
            return $trabajo();
        } finally {
            $this->cerrar();
        }
    }

    private function conSesion()
    {
        return $this->http(['Session-Token' => $this->abrir()]);
    }

    /* ── Operaciones ──────────────────────────────────────────────────────── */

    /**
     * Id de GLPI del dueño del token. Sirve para comprobar, al guardar el
     * token, que es de la misma persona que está conectada en VTI.
     */
    public function idUsuario(): int
    {
        return $this->enSesion(function () {
            $resp = $this->verificar($this->conSesion()->get($this->url('getFullSession')), 'leer la sesión');

            return (int) $resp->json('session.glpiID');
        });
    }

    /**
     * Fija la ubicación de un usuario de GLPI. Se usa para darle país
     * (Chile/Perú) a un técnico que no lo tiene; exige que el perfil de quien
     * lo hace pueda editar usuarios en GLPI.
     */
    public function fijarUbicacion(int $usuarioId, int $ubicacionId): void
    {
        $this->enSesion(function () use ($usuarioId, $ubicacionId) {
            $this->verificar($this->conSesion()->put($this->url("User/{$usuarioId}"), [
                'input' => ['id' => $usuarioId, 'locations_id' => $ubicacionId],
            ]), 'cambiar la ubicación del técnico');
        });
    }

    /**
     * Asigna un técnico al ticket. Si el ticket estaba «Nuevo», GLPI lo pasa
     * solo a «En curso (asignado)» y avisa al técnico.
     *
     * @param int[] $quitar Vínculos técnico↔ticket (glpi_tickets_users.id) a
     *                      eliminar antes: es lo que convierte esto en reasignar.
     */
    public function asignar(int $ticketId, int $usuarioId, array $quitar = [], ?string $nota = null, bool $notaPrivada = false): void
    {
        $this->enSesion(function () use ($ticketId, $usuarioId, $quitar, $nota, $notaPrivada) {
            foreach ($quitar as $vinculo) {
                $this->verificar(
                    $this->conSesion()->delete($this->url("Ticket_User/{$vinculo}")),
                    'quitar al técnico anterior'
                );
            }

            $this->verificar($this->conSesion()->post($this->url('Ticket_User'), [
                'input' => ['tickets_id' => $ticketId, 'users_id' => $usuarioId, 'type' => 2],
            ]), 'asignar el técnico');

            if ($nota !== null && trim($nota) !== '') {
                $this->verificar($this->conSesion()->post($this->url('ITILFollowup'), [
                    'input' => [
                        'itemtype'   => 'Ticket',
                        'items_id'   => $ticketId,
                        'content'    => nl2br(e(trim($nota))),
                        'is_private' => $notaPrivada ? 1 : 0,
                    ],
                ]), 'agregar la nota');
            }
        });
    }
}
