<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\ActiveDirectory\User as AdUser;

/**
 * Diagnóstico de por qué una cuenta de Active Directory no puede entrar.
 *
 * Responde en un solo lugar lo que hoy obliga a abrir la consola de AD: si la
 * cuenta está bloqueada de verdad, si está pidiendo cambio de contraseña,
 * cuándo caduca y cuántos intentos fallidos lleva.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Tres cosas de AD que hay que respetar o el resultado miente:
 *
 *  1. `lockoutTime` NO alcanza para saber si está bloqueada. Guarda cuándo se
 *     bloqueó, pero no se limpia al liberarse: una cuenta bloqueada hace horas
 *     ya se desbloqueó sola al cumplirse la duración y ahí seguiría el valor.
 *     Lo confiable es `msDS-User-Account-Control-Computed`, que el controlador
 *     de dominio calcula al momento de la consulta.
 *
 *  2. Los atributos "computed" hay que pedirlos POR NOMBRE. No vienen en una
 *     búsqueda normal aunque se pidan todos los atributos.
 *
 *  3. `badPwdCount` NO se replica entre controladores: cada uno lleva su propia
 *     cuenta. Preguntándole a uno solo se puede ver 0 mientras otro tiene 3.
 *     Por eso se consulta host por host.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EstadoCuentaAd
{
    /** Banderas de userAccountControl que interesan. */
    private const UAC_DESHABILITADA   = 0x0002;
    private const UAC_NO_EXPIRA       = 0x10000;
    private const UAC_TARJETA         = 0x40000;

    /** Banderas del atributo calculado por el DC. */
    private const COMP_BLOQUEADA      = 0x0010;
    private const COMP_PASS_VENCIDA   = 0x800000;

    private const ATRIBUTOS = [
        'cn', 'displayname', 'samaccountname', 'mail', 'distinguishedname',
        'department', 'title', 'telephonenumber', 'mobile',
        'useraccountcontrol', 'pwdlastset', 'lockouttime', 'badpwdcount',
        'badpasswordtime', 'lastlogontimestamp', 'accountexpires', 'whencreated',
        'msds-user-account-control-computed',
        'msds-userpasswordexpirytimecomputed',
    ];

    public function __construct(private ?string $conexion = null) {}

    /**
     * Diagnóstico completo de una cuenta.
     *
     * @return array{encontrado:bool,usuario:array,resumen:array,senales:array,datos:array,politica:array,intentos:array,aviso:?string}
     */
    public function de(string $samaccountname): array
    {
        $consulta = $this->conexion
            ? AdUser::on($this->conexion)->select(self::ATRIBUTOS)
            : AdUser::query()->select(self::ATRIBUTOS);

        $u = $consulta->where('samaccountname', $samaccountname)->first();

        if (!$u) {
            return ['encontrado' => false] + $this->vacio();
        }

        $uac      = (int) $u->getFirstAttribute('useraccountcontrol');
        $computed = $u->getFirstAttribute('msds-user-account-control-computed');
        $computed = $computed === null ? null : (int) $computed;

        // pwdLastSet hay que mirarlo CRUDO: LdapRecord lo convierte a fecha y
        // ahí el 0 —que es justo lo que significa "debe cambiarla"— se pierde.
        $pwdCrudo = $this->crudo($u, 'pwdlastset');

        $politica = $this->politica();

        $deshabilitada = (bool) ($uac & self::UAC_DESHABILITADA);
        $bloqueada     = $computed !== null ? (bool) ($computed & self::COMP_BLOQUEADA) : $this->bloqueadaPorTiempo($u, $politica);
        $vencida       = $computed !== null ? (bool) ($computed & self::COMP_PASS_VENCIDA) : false;
        $debeCambiar   = ((string) $pwdCrudo === '0');
        $noExpira      = (bool) ($uac & self::UAC_NO_EXPIRA);

        $caduca      = $this->aFecha($u->getFirstAttribute('msds-userpasswordexpirytimecomputed'));
        $bloqueoDesde = $this->aFecha($u->getFirstAttribute('lockouttime'));
        $seLibera    = ($bloqueada && $bloqueoDesde && $politica['duracion_min'])
            ? $bloqueoDesde->copy()->addMinutes($politica['duracion_min'])
            : null;
        $expiraCuenta = $this->aFecha($u->getFirstAttribute('accountexpires'));

        $intentos = $this->intentosPorControlador($samaccountname);

        return [
            'encontrado' => true,
            'usuario' => [
                'nombre'       => $u->getFirstAttribute('displayname') ?: $u->getFirstAttribute('cn'),
                'sam'          => $u->getFirstAttribute('samaccountname'),
                'mail'         => $u->getFirstAttribute('mail'),
                'departamento' => $u->getFirstAttribute('department'),
                'cargo'        => $u->getFirstAttribute('title'),
                'telefono'     => $u->getFirstAttribute('telephonenumber') ?: $u->getFirstAttribute('mobile'),
                'dn'           => $u->getFirstAttribute('distinguishedname'),
            ],
            'resumen'  => $this->resumen($deshabilitada, $bloqueada, $debeCambiar, $vencida, $expiraCuenta, $seLibera, $caduca),
            'senales'  => $this->senales($deshabilitada, $bloqueada, $debeCambiar, $vencida, $noExpira,
                                         (bool) ($uac & self::UAC_TARJETA), $seLibera, $caduca, $expiraCuenta, $politica),
            'datos'    => [
                'Contraseña cambiada'   => $this->texto($this->aFecha($u->getFirstAttribute('pwdlastset'))),
                'Contraseña caduca'     => $noExpira ? 'Nunca (marcada para no expirar)' : $this->texto($caduca),
                'Último inicio de sesión' => $this->texto($this->aFecha($u->getFirstAttribute('lastlogontimestamp'))) . ' (aprox.)',
                'Último intento fallido' => $this->texto($this->aFecha($u->getFirstAttribute('badpasswordtime'))),
                'Bloqueada desde'       => $bloqueoDesde && $bloqueada ? $this->texto($bloqueoDesde) : '—',
                'La cuenta expira'      => $expiraCuenta ? $this->texto($expiraCuenta) : 'Nunca',
                'Cuenta creada'         => $this->texto($this->aFecha($u->getFirstAttribute('whencreated'))),
            ],
            'politica' => $politica,
            'intentos' => $intentos,
            'aviso'    => $computed === null
                ? 'El controlador de dominio no devolvió el estado calculado; el bloqueo se dedujo de la hora de bloqueo y puede no reflejar una liberación automática reciente.'
                : null,
        ];
    }

    /* ── Resumen y señales ───────────────────────────────────────────────── */

    private function resumen(bool $desh, bool $bloq, bool $cambiar, bool $venc, ?Carbon $expira, ?Carbon $libera, ?Carbon $caduca): array
    {
        if ($desh) {
            return ['nivel' => 'problema', 'titulo' => 'Cuenta deshabilitada',
                    'detalle' => 'Un administrador la deshabilitó. No podrá entrar hasta que se habilite.'];
        }
        if ($bloq) {
            return ['nivel' => 'problema', 'titulo' => 'Cuenta bloqueada',
                    'detalle' => $libera
                        ? 'Se libera sola a las ' . $libera->format('H:i') . ' (' . $this->relativo($libera) . ').'
                        : 'Bloqueada por intentos fallidos.'];
        }
        if ($expira && $expira->isPast()) {
            return ['nivel' => 'problema', 'titulo' => 'Cuenta expirada',
                    'detalle' => 'La cuenta tenía fecha de término: ' . $expira->format('d-m-Y') . '.'];
        }
        if ($venc) {
            return ['nivel' => 'problema', 'titulo' => 'Contraseña vencida',
                    'detalle' => 'Tiene que cambiarla para poder entrar.'];
        }
        if ($cambiar) {
            return ['nivel' => 'aviso', 'titulo' => 'Debe cambiar la contraseña',
                    'detalle' => 'Está marcada para cambiarse en el próximo inicio de sesión.'];
        }
        if ($caduca && $caduca->isFuture() && $caduca->diffInDays(now()) <= 7) {
            return ['nivel' => 'aviso', 'titulo' => 'La contraseña caduca pronto',
                    'detalle' => 'Caduca el ' . $caduca->format('d-m-Y H:i') . ' (' . $this->relativo($caduca) . ').'];
        }

        return ['nivel' => 'ok', 'titulo' => 'Sin problemas en la cuenta',
                'detalle' => 'Habilitada, sin bloqueo y con la contraseña vigente. Si aun así no puede entrar, el problema está fuera de Active Directory.'];
    }

    private function senales(bool $desh, bool $bloq, bool $cambiar, bool $venc, bool $noExpira, bool $tarjeta,
                             ?Carbon $libera, ?Carbon $caduca, ?Carbon $expira, array $politica): array
    {
        $s = [];

        $s[] = $desh
            ? ['nivel' => 'problema', 'icono' => 'bi-person-x-fill', 'titulo' => 'Deshabilitada', 'detalle' => 'La cuenta está apagada en AD.']
            : ['nivel' => 'ok', 'icono' => 'bi-person-check-fill', 'titulo' => 'Habilitada', 'detalle' => 'La cuenta está activa.'];

        $s[] = $bloq
            ? ['nivel' => 'problema', 'icono' => 'bi-lock-fill', 'titulo' => 'Bloqueada',
               'detalle' => $libera ? 'Se libera a las ' . $libera->format('H:i') . '.' : 'Por intentos fallidos.']
            : ['nivel' => 'ok', 'icono' => 'bi-unlock-fill', 'titulo' => 'Sin bloqueo', 'detalle' => 'No está bloqueada en este momento.'];

        if ($venc) {
            $s[] = ['nivel' => 'problema', 'icono' => 'bi-key-fill', 'titulo' => 'Contraseña vencida', 'detalle' => 'Debe cambiarla para entrar.'];
        } elseif ($cambiar) {
            $s[] = ['nivel' => 'aviso', 'icono' => 'bi-key-fill', 'titulo' => 'Debe cambiar la contraseña', 'detalle' => 'En el próximo inicio de sesión.'];
        } elseif ($noExpira) {
            $s[] = ['nivel' => 'aviso', 'icono' => 'bi-key', 'titulo' => 'La contraseña no expira', 'detalle' => 'Marcada para no caducar nunca.'];
        } elseif ($caduca) {
            $dias = (int) floor(now()->diffInDays($caduca, false));
            $s[] = [
                'nivel'   => $dias <= 7 ? 'aviso' : 'ok',
                'icono'   => 'bi-key',
                'titulo'  => 'Contraseña vigente',
                'detalle' => 'Caduca el ' . $caduca->format('d-m-Y')
                           . ($dias > 1 ? " (en {$dias} días)" : ($dias === 1 ? ' (mañana)' : ($dias === 0 ? ' (hoy)' : ''))),
            ];
        }

        if ($expira) {
            $s[] = [
                'nivel'   => $expira->isPast() ? 'problema' : 'aviso',
                'icono'   => 'bi-calendar-x',
                'titulo'  => $expira->isPast() ? 'Cuenta expirada' : 'Cuenta con fecha de término',
                'detalle' => $expira->format('d-m-Y'),
            ];
        }

        if ($tarjeta) {
            $s[] = ['nivel' => 'aviso', 'icono' => 'bi-credit-card-2-front', 'titulo' => 'Requiere tarjeta inteligente',
                    'detalle' => 'No puede entrar solo con contraseña.'];
        }

        return $s;
    }

    /* ── Política de bloqueo y contraseñas del dominio ───────────────────── */

    private function politica(): array
    {
        $vacia = ['intentos' => null, 'duracion_min' => null, 'vigencia_dias' => null, 'largo_min' => null];

        try {
            $consulta = $this->conexion ? Entry::on($this->conexion) : Entry::query();
            $base     = $this->baseDn();
            if (!$base) return $vacia;

            $raiz = $consulta->select(['lockoutduration', 'lockoutthreshold', 'maxpwdage', 'minpwdlength'])->find($base);
            if (!$raiz) return $vacia;

            // Los intervalos vienen negativos y en unidades de 100 ns.
            $aMinutos = function ($v) {
                $v = abs((float) $v);
                return $v > 0 ? (int) round($v / 10000000 / 60) : null;
            };

            $vigencia = $aMinutos($raiz->getFirstAttribute('maxpwdage'));

            return [
                'intentos'      => (int) $raiz->getFirstAttribute('lockoutthreshold') ?: null,
                'duracion_min'  => $aMinutos($raiz->getFirstAttribute('lockoutduration')),
                'vigencia_dias' => $vigencia ? (int) round($vigencia / 1440) : null,
                'largo_min'     => (int) $raiz->getFirstAttribute('minpwdlength') ?: null,
            ];
        } catch (\Throwable) {
            return $vacia;
        }
    }

    /**
     * Intentos fallidos preguntándole a cada controlador de dominio.
     *
     * Es la única forma de tener el número real: el contador es local a cada DC
     * y no se replica. Si un DC no responde se omite, no se cae la pantalla.
     */
    private function intentosPorControlador(string $sam): array
    {
        $conexionNombre = $this->conexion ?? 'default';
        $config = config("ldap.connections.{$conexionNombre}");

        // La conexión de Perú se registra en caliente, así que puede no estar
        // en config/: en ese caso se pide su configuración al contenedor.
        if (!$config) {
            try {
                $config = Container::getConnection($conexionNombre)->getConfiguration()->all();
            } catch (\Throwable) {
                return [];
            }
        }

        $hosts = (array) ($config['hosts'] ?? []);
        if (count($hosts) < 2) return [];   // con un solo DC no aporta nada

        $resultado = [];

        foreach ($hosts as $host) {
            try {
                // Conexión suelta a ESE controlador. Se consulta con su propio
                // query builder y no con el modelo, para no tener que registrar
                // ni desregistrar conexiones en el contenedor global.
                $conn = new Connection(['hosts' => [$host]] + $config);
                $conn->connect();

                $fila = $conn->query()->select(['badpwdcount'])
                    ->where('samaccountname', '=', $sam)
                    ->first();

                $resultado[$host] = $fila ? (int) ($fila['badpwdcount'][0] ?? 0) : null;
            } catch (\Throwable) {
                $resultado[$host] = null;   // DC caído o inalcanzable
            }
        }

        return $resultado;
    }

    /* ── Utilidades ──────────────────────────────────────────────────────── */

    private function baseDn(): ?string
    {
        $nombre = $this->conexion ?? 'default';
        $base   = config("ldap.connections.{$nombre}.base_dn");

        if (!$base) {
            try {
                $base = Container::getConnection($nombre)->getConfiguration()->get('base_dn');
            } catch (\Throwable) {
                return null;
            }
        }

        return $base ?: null;
    }

    /** Valor sin las conversiones de LdapRecord. */
    private function crudo($modelo, string $atributo)
    {
        return $modelo->getAttributes()[$atributo][0] ?? null;
    }

    /**
     * Acepta lo que devuelva LdapRecord: unas fechas ya vienen convertidas y
     * otras —las calculadas y lockoutTime— llegan como FILETIME de Windows.
     */
    private function aFecha($v): ?Carbon
    {
        if ($v === null) return null;
        if ($v instanceof \DateTimeInterface) return Carbon::instance($v);

        $ft = (float) $v;
        // 0 = nunca; el máximo de 64 bits = "no expira".
        if ($ft <= 0 || $ft >= 9223372036854775807) return null;

        return Carbon::createFromTimestamp((int) ($ft / 10000000 - 11644473600));
    }

    private function bloqueadaPorTiempo($u, array $politica): bool
    {
        $desde = $this->aFecha($u->getFirstAttribute('lockouttime'));
        if (!$desde) return false;
        if (!$politica['duracion_min']) return true;   // bloqueo hasta desbloqueo manual

        return $desde->copy()->addMinutes($politica['duracion_min'])->isFuture();
    }

    private function texto(?Carbon $f): string
    {
        return $f ? $f->format('d-m-Y H:i') : '—';
    }

    /**
     * "en 3 horas", "hace 2 días".
     *
     * El locale de la aplicación es `en`, así que hay que pedirle el español a
     * Carbon explícitamente o los técnicos leen "1 day from now" en medio de
     * una pantalla en castellano.
     */
    private function relativo(Carbon $f): string
    {
        return $f->copy()->locale('es')->diffForHumans();
    }

    private function vacio(): array
    {
        return [
            'usuario'  => [], 'resumen' => [], 'senales' => [], 'datos' => [],
            'politica' => ['intentos' => null, 'duracion_min' => null, 'vigencia_dias' => null, 'largo_min' => null],
            'intentos' => [], 'aviso' => null,
        ];
    }
}
