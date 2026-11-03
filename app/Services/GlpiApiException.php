<?php

namespace App\Services;

/**
 * Error devuelto por la API de GLPI. Conserva el código de GLPI
 * (`ERROR_SESSION_TOKEN_INVALID`, `ERROR_WRONG_APP_TOKEN_PARAMETER`…) para
 * distinguir un token vencido de cualquier otro problema.
 */
class GlpiApiException extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $codigoGlpi = null, public readonly ?int $estadoHttp = null)
    {
        parent::__construct($mensaje);
    }

    /** El token personal no sirve (inválido, regenerado o de un usuario inactivo). */
    public function esTokenInvalido(): bool
    {
        return in_array($this->codigoGlpi, ['ERROR_LOGIN_PARAMETERS_MISSING', 'ERROR_GLPI_LOGIN_USER_TOKEN', 'ERROR_GLPI_LOGIN'], true)
            || ($this->estadoHttp === 401 && $this->codigoGlpi !== 'ERROR_WRONG_APP_TOKEN_PARAMETER');
    }
}
