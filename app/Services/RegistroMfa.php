<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Estado de registro de MFA de las cuentas de Entra ID.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Qué mide y qué NO mide:
 *
 *  Mide si la persona tiene un segundo factor CONFIGURADO —Authenticator, SMS,
 *  llave, Windows Hello—, que es lo que responde el reporte
 *  `authenticationMethods/userRegistrationDetails`.
 *
 *  NO mide si a esa persona se le EXIGE usarlo. La exigencia vive en las
 *  directivas de acceso condicional, y leerlas necesita el permiso
 *  Policy.Read.All, que la aplicación no tiene. Alguien puede tener MFA
 *  configurado y no tenerlo exigido, y al revés.
 *
 *  Así que "MFA activo" aquí significa "puede autenticarse con doble factor",
 *  no "está obligado a hacerlo". Para el caso de uso —ver quién está desnudo—
 *  el que no lo tiene configurado no puede usarlo aunque se lo exijan.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class RegistroMfa
{
    private const CACHE_KEY = 'registro_mfa';
    private const CACHE_TTL = 21600;   // 6 h: el reporte de Entra se refresca varias veces al día

    /** Nombres legibles de los métodos que devuelve Graph. */
    public const METODOS = [
        'microsoftAuthenticatorPush'   => 'Microsoft Authenticator',
        'softwareOneTimePasscode'      => 'Código de un solo uso',
        'mobilePhone'                  => 'SMS al teléfono',
        'windowsHelloForBusiness'      => 'Windows Hello',
        'email'                        => 'Correo alternativo',
        'alternateMobilePhone'         => 'Teléfono alternativo',
        'officePhone'                  => 'Teléfono de oficina',
        'fido2SecurityKey'             => 'Llave de seguridad FIDO2',
        'temporaryAccessPass'          => 'Pase de acceso temporal',
        'passKeyDeviceBound'           => 'Passkey',
        'macOsSecureEnclaveKey'        => 'Clave de macOS',
    ];

    public function __construct(private GraphClient $graph = new GraphClient()) {}

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{usuarios: Collection, resumen: array, generado: Carbon} */
    public function analizar(): array
    {
        $datos = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->construir());

        $usuarios = collect($datos['usuarios'])->map(function (array $u) {
            $u['actualizado'] = $u['actualizado'] ? Carbon::parse($u['actualizado']) : null;
            return $u;
        });

        return [
            'usuarios' => $usuarios,
            'resumen'  => $this->resumir($usuarios),
            'generado' => Carbon::parse($datos['generado']),
        ];
    }

    /* ── Construcción ────────────────────────────────────────────────────── */

    private function construir(): array
    {
        // El directorio manda: define quién entra al universo. El reporte de
        // registro solo aporta el estado de MFA de cada uno.
        $usuarios = $this->graph->paginar(
            GraphClient::BASE . '/users?$top=999&$select='
            . 'id,userPrincipalName,displayName,accountEnabled,userType,department,jobTitle,assignedLicenses'
        );

        $registro = $this->graph
            ->paginar(GraphClient::BASE . '/reports/authenticationMethods/userRegistrationDetails?$top=999')
            ->keyBy('id');

        // El universo son TODAS las cuentas internas habilitadas, tengan licencia
        // o no. Las cuentas administrativas suelen no tenerla, y son justamente
        // las que más importa vigilar: exigir licencia las dejaría fuera del
        // tablero y el número de admins sin MFA saldría tranquilizador y falso.
        $lista = $usuarios
            ->filter(fn ($u) => ($u['userType'] ?? '') === 'Member'
                             && ($u['accountEnabled'] ?? false))
            ->map(function ($u) use ($registro) {
                $r = $registro->get($u['id']);

                return [
                    'upn'          => mb_strtolower($u['userPrincipalName'] ?? ''),
                    'nombre'       => $u['displayName'] ?: $u['userPrincipalName'],
                    'departamento' => $u['department'] ?: null,
                    'cargo'        => $u['jobTitle'] ?: null,
                    'licenciado'   => !empty($u['assignedLicenses']),
                    // null cuando el usuario no aparece en el reporte: no es lo
                    // mismo "no tiene MFA" que "no hay dato sobre esta cuenta".
                    'mfa'          => $r ? (bool) ($r['isMfaCapable'] ?? false) : null,
                    'es_admin'     => (bool) ($r['isAdmin'] ?? false),
                    'sspr'         => (bool) ($r['isSsprRegistered'] ?? false),
                    'metodos'      => $r['methodsRegistered'] ?? [],
                    'preferido'    => $r['userPreferredMethodForSecondaryAuthentication'] ?? null,
                    'actualizado'  => $r['lastUpdatedDateTime'] ?? null,
                ];
            })
            ->sortBy('upn')
            ->values()
            ->all();

        return ['usuarios' => $lista, 'generado' => now()->toIso8601String()];
    }

    private function resumir(Collection $u): array
    {
        $con    = $u->where('mfa', true);
        $sin    = $u->where('mfa', false);
        $sinDato = $u->whereNull('mfa');
        $total  = $u->count();

        // El porcentaje se calcula sobre las cuentas con dato: meter las que no
        // lo tienen lo empujaría hacia abajo sin que eso signifique nada.
        $conDato = $con->count() + $sin->count();

        $admins = $u->where('es_admin', true);

        return [
            'total'         => $total,
            'con_mfa'       => $con->count(),
            'sin_mfa'       => $sin->count(),
            'sin_dato'      => $sinDato->count(),
            'con_mfa_pct'   => $conDato > 0 ? round($con->count() * 100 / $conDato, 1) : 0.0,
            'sin_mfa_pct'   => $conDato > 0 ? round($sin->count() * 100 / $conDato, 1) : 0.0,

            'admins'         => $admins->count(),
            'admins_sin_mfa' => $admins->where('mfa', false)->count(),

            // Las cuentas con licencia son las personas de la empresa; el resto
            // son de servicio, administrativas o de apoyo. Se separan porque el
            // "95% sin MFA" se lee distinto según de cuál se hable.
            'con_licencia'         => $u->where('licenciado', true)->count(),
            'con_licencia_sin_mfa' => $u->where('licenciado', true)->where('mfa', false)->count(),
            'sin_licencia'         => $u->where('licenciado', false)->count(),
            'sin_licencia_sin_mfa' => $u->where('licenciado', false)->where('mfa', false)->count(),

            'metodos' => $con->pluck('metodos')->flatten()
                ->countBy()->sortDesc()->all(),

            'departamentos' => $sin
                ->groupBy(fn ($x) => $x['departamento'] ?: '(sin departamento)')
                ->map->count()->sortDesc()->take(10)->all(),

            'sspr_sin' => $u->where('sspr', false)->count(),
        ];
    }
}
