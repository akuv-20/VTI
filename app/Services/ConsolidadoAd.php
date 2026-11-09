<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use LdapRecord\Container;

/**
 * Consolidado AD: arma, para cada dominio conectado, las dos "sábanas" del
 * Excel «Inventario ADs Unifrutti»:
 *
 *   - Entorno AD  → una fila por dominio (columnas A–K del Excel).
 *   - Cuentas AD  → una fila por objeto: usuarios, equipos y grupos (A–N).
 *
 * Las filas salen en el mismo orden de columnas que el Excel para que se
 * puedan pegar tal cual. Los valores de las columnas con lista desplegable
 * (tipo, estado, sincronización, ¿requiere migración?) usan exactamente los
 * textos de esas listas.
 *
 * Todo es solo lectura. Cada dominio se consulta por separado y se cachea,
 * incluido el error, para que una conexión caída no haga reintentar en cada
 * carga (ver CLAUDE.md).
 */
class ConsolidadoAd
{
    /** Conexión LdapRecord => grupo empresarial y país, que AD no sabe. */
    public const CONEXIONES = [
        'default'    => ['grupo' => 'Verfrut',   'pais' => 'Chile', 'config' => 'ldap'],
        'secondary'  => ['grupo' => 'Verfrut',   'pais' => 'Perú',  'config' => 'ldap2'],
        'tertiary'   => ['grupo' => 'Unifrutti', 'pais' => 'Chile / Perú', 'config' => 'ldap3'],
        'quaternary' => ['grupo' => 'Unifrutti', 'pais' => '',      'config' => 'ldap4'],
    ];

    private const TTL       = 1800;  // 30 min
    private const TTL_ERROR = 120;
    private const CACHE_ENTRA = 'ad_consolidado_entra';

    // userAccountControl
    private const UAC_DESHABILITADA   = 0x2;
    private const UAC_NO_EXPIRA       = 0x10000;
    private const UAC_SIN_CLAVE       = 0x20;
    private const UAC_DC              = 0x2000;
    private const UAC_DELEGACION      = 0x80000;

    /** Grupos privilegiados (nombres en inglés y en español). */
    private const PRIVILEGIADOS = [
        'domain admins', 'admins. del dominio', 'enterprise admins', 'administradores de empresas',
        'schema admins', 'administradores de esquema', 'administrators', 'administradores',
        'account operators', 'opers. de cuentas', 'server operators', 'opers. de servidores',
        'backup operators', 'operadores de copia de seguridad', 'print operators', 'opers. de impresión',
        'dnsadmins', 'group policy creator owners', 'propietarios del creador de directivas de grupo',
        'key admins', 'enterprise key admins', 'administradores clave', 'administradores clave de la organización',
        'cert publishers', 'publicadores de certificados', 'remote desktop users', 'usuarios de escritorio remoto',
    ];

    private const SISTEMA = ['krbtgt', 'guest', 'invitado', 'defaultaccount', 'wdagutilityaccount', 'support_388945a0'];

    private const UAC_CONFIANZA = 0x800;  // INTERDOMAIN_TRUST_ACCOUNT

    private const RX_SERVICIO = '/(^|[^a-z])(svc|srv|service|servicio|sql|sqlagent\w*|backup|veeam|scan|scanner|ldap|sync|adsync|msol|app|api|bot|robot|task|tarea|sap|erp|iis|web|ftp|smtp|relay|print|impres|aspnet)([^a-z]|$)|^(iwam|iusr|ivam)_|^tsinternetuser$/i';
    // Ojo con apellidos comunes (Mesa, Sala, Central…): no van aquí.
    private const RX_GENERICA = '/(recepci|bodega|porter[ií]a|reuni[oó]n|compartid|gen[eé]ric|soporte|contacto|ventas|rrhh|turno|guardia|capacitaci|kiosko|kiosk|tablet|totem|laboratorio|despacho|packing|frigor[ií]f|c[aá]mara|b[aá]scula|casino|helpdesk|prueba|test[0-9_.]|^test|demo|usuario[0-9_.]|scanner|escaner)/i';

    /** Conexiones LdapRecord que tienen credenciales guardadas. */
    public function conexiones(): array
    {
        $lista = [];
        foreach (self::CONEXIONES as $nombre => $meta) {
            try {
                Container::getConnection($nombre);
                $lista[$nombre] = $meta;
            } catch (\Throwable) {
                // sin credenciales: no se registró en AppServiceProvider
            }
        }
        return $lista;
    }

    public function olvidar(?string $conexion = null): void
    {
        foreach ($conexion ? [$conexion] : array_keys(self::CONEXIONES) as $c) {
            Cache::forget("ad_consolidado_{$c}");
        }
        Cache::forget(self::CACHE_ENTRA);
    }

    /**
     * Entorno y cuentas de un dominio.
     *
     * @return array{entorno: array, cuentas: array, generado: string, error?: string}
     */
    public function dominio(string $conexion): array
    {
        $clave = "ad_consolidado_{$conexion}";
        if ($guardado = Cache::get($clave)) return $guardado;

        try {
            $datos = $this->leer($conexion);
            Cache::put($clave, $datos, self::TTL);
        } catch (\Throwable $e) {
            $datos = ['error' => $e->getMessage(), 'entorno' => null, 'cuentas' => [], 'generado' => now()->toIso8601String()];
            Cache::put($clave, $datos, self::TTL_ERROR);
        }

        return $datos;
    }

    private function leer(string $conexion): array
    {
        $meta = self::CONEXIONES[$conexion] ?? throw new \InvalidArgumentException('Conexión desconocida.');
        $c    = Container::getConnection($conexion);
        $c->connect();
        $base = $c->getConfiguration()->get('base_dn');

        $raiz    = $this->rootDse($c);
        $base    = $raiz['defaultnamingcontext'] ?: $base;
        $dominio = $this->dnADns($base);
        $bosque  = $this->dnADns($raiz['rootdomainnamingcontext'] ?: $base);

        $politica = $this->politicaBloqueo($c, $base);
        $entra    = $this->entra();
        $entraDom = $entra['por_dominio'][strtolower($dominio)] ?? [];

        // ── Objetos ─────────────────────────────────────────────────────────
        $usuarios = $this->buscar($c, $base, '(&(objectCategory=person)(objectClass=user))', [
            'samaccountname', 'userprincipalname', 'mail', 'displayname', 'givenname', 'sn', 'description',
            'department', 'company', 'title', 'manager', 'distinguishedname', 'memberof', 'useraccountcontrol',
            'lockouttime', 'lastlogontimestamp', 'pwdlastset', 'whencreated', 'admincount', 'serviceprincipalname',
            'accountexpires',
        ]);
        $msa = $this->buscar($c, $base, '(|(objectClass=msDS-GroupManagedServiceAccount)(objectClass=msDS-ManagedServiceAccount))', [
            'samaccountname', 'description', 'distinguishedname', 'memberof', 'useraccountcontrol',
            'lastlogontimestamp', 'whencreated', 'serviceprincipalname', 'objectclass',
        ]);
        $equipos = $this->buscar($c, $base, '(&(objectCategory=computer)(!(objectClass=msDS-GroupManagedServiceAccount))(!(objectClass=msDS-ManagedServiceAccount)))', [
            'samaccountname', 'cn', 'dnshostname', 'operatingsystem', 'operatingsystemversion', 'description',
            'managedby', 'distinguishedname', 'memberof', 'useraccountcontrol', 'lastlogontimestamp',
            'pwdlastset', 'whencreated',
        ]);
        $grupos = $this->buscar($c, $base, '(objectCategory=group)', [
            'samaccountname', 'cn', 'mail', 'description', 'managedby', 'distinguishedname', 'grouptype',
            'member', 'memberof', 'admincount', 'whencreated',
        ]);
        $ous = $this->buscar($c, $base, '(objectClass=organizationalUnit)', ['dn']);

        $filas = [];
        $ctx   = ['grupo' => $meta['grupo'], 'dominio' => $dominio, 'politica' => $politica, 'entra' => $entraDom];
        foreach ($usuarios as $u) $filas[] = $this->filaUsuario($u, $ctx);
        foreach ($msa as $u)      $filas[] = $this->filaMsa($u, $ctx);
        foreach ($equipos as $e)  $filas[] = $this->filaEquipo($e, $ctx);
        foreach ($grupos as $g)   $filas[] = $this->filaGrupo($g, $ctx);

        // ── Entorno ─────────────────────────────────────────────────────────
        $dcs = array_values(array_filter($equipos, fn($e) => ((int) $this->uno($e, 'useraccountcontrol')) & self::UAC_DC));

        $versiones = [];
        foreach ($dcs as $d) {
            $so = trim($this->uno($d, 'operatingsystem') ?: 'Desconocido');
            $versiones[$so] = ($versiones[$so] ?? 0) + 1;
        }
        $versionesTxt = implode(', ', array_map(fn($so, $n) => $n > 1 ? "$so (×$n)" : $so, array_keys($versiones), $versiones));

        $sitios = [];
        if ($raiz['configurationnamingcontext']) {
            foreach ($this->buscar($c, 'CN=Sites,' . $raiz['configurationnamingcontext'], '(objectClass=site)', ['name']) as $s) {
                $sitios[] = $this->uno($s, 'name');
            }
        }

        $trusts = [];
        $dir    = [1 => 'entrante', 2 => 'saliente', 3 => 'bidireccional'];
        foreach ($this->buscar($c, "CN=System,$base", '(objectClass=trustedDomain)', ['trustpartner', 'trustdirection', 'trustattributes']) as $t) {
            $attr = (int) $this->uno($t, 'trustattributes');
            $tipo = $attr & 0x8 ? 'bosque' : ($attr & 0x20 ? 'dentro del bosque' : 'externa');
            $trusts[] = $this->uno($t, 'trustpartner') . ' (' . ($dir[(int) $this->uno($t, 'trustdirection')] ?? '?') . ", $tipo)";
        }

        $msol = array_values(array_filter(array_map(fn($u) => $this->uno($u, 'samaccountname'), $usuarios),
            fn($s) => preg_match('/^(MSOL_|Sync_|AAD_)/i', (string) $s)));
        $sincronizados = count($entraDom);

        if ($sincronizados > 0 || $msol)       $sync = 'Sí';
        elseif ($entra['error'] === null)      $sync = 'No';
        else                                   $sync = 'En evaluación';

        $obsSync = [];
        if ($msol) $obsSync[] = 'Entra Connect (cuenta ' . implode(', ', $msol) . ')';
        if ($sincronizados) $obsSync[] = "$sincronizados usuarios sincronizados con Entra ID" . ($entra['tenant'] ? " (tenant {$entra['tenant']})" : '');
        elseif ($entra['error'] === null) $obsSync[] = 'Sin usuarios sincronizados en el tenant ' . ($entra['tenant'] ?: 'conectado');

        $nivel = fn($n) => [0 => 'Windows 2000', 1 => 'Windows Server 2003 interino', 2 => 'Windows Server 2003',
            3 => 'Windows Server 2008', 4 => 'Windows Server 2008 R2', 5 => 'Windows Server 2012',
            6 => 'Windows Server 2012 R2', 7 => 'Windows Server 2016', 10 => 'Windows Server 2025'][(int) $n] ?? "Nivel $n";
        $nivelTxt = $raiz['domainfunctionality'] === $raiz['forestfunctionality']
            ? $nivel($raiz['domainfunctionality'])
            : 'Dominio: ' . $nivel($raiz['domainfunctionality']) . ' · Bosque: ' . $nivel($raiz['forestfunctionality']);

        $nUsuarios = count($usuarios);
        $obs = array_filter([
            'DC: ' . implode(', ', array_map(fn($d) => $this->uno($d, 'dnshostname') ?: $this->uno($d, 'cn'), $dcs)),
            implode(' · ', $obsSync),
            "$nUsuarios usuarios (" . count(array_filter($usuarios, fn($u) => ((int) $this->uno($u, 'useraccountcontrol')) & self::UAC_DESHABILITADA)) . ' deshabilitados), '
                . count($equipos) . ' equipos, ' . count($grupos) . ' grupos, ' . count($ous) . ' OUs'
                . (count($msa) ? ', ' . count($msa) . ' cuentas de servicio administradas' : ''),
        ]);

        $entorno = [
            $meta['grupo'],
            $bosque,
            $dominio,
            trim(($meta['pais'] ? $meta['pais'] . ' · ' : '') . 'Sitios AD: ' . implode(', ', $sitios)),
            count($dcs),
            $versionesTxt,
            $nivelTxt,
            $sync,
            implode('; ', $trusts) ?: 'Sin relaciones de confianza',
            '',
            implode(' | ', $obs),
        ];

        return ['entorno' => $entorno, 'cuentas' => $filas, 'generado' => now()->toIso8601String()];
    }

    /* ── Filas de la hoja Cuentas AD ──────────────────────────────────────── */

    private function filaUsuario(array $u, array $ctx): array
    {
        $sam   = (string) $this->uno($u, 'samaccountname');
        $uac   = (int) $this->uno($u, 'useraccountcontrol');
        $dn    = (string) $this->uno($u, 'distinguishedname');
        $desc  = (string) $this->uno($u, 'description');
        $nom   = (string) ($this->uno($u, 'displayname') ?: $this->uno($u, 'cn') ?: $sam);
        $spn   = $this->todos($u, 'serviceprincipalname');
        $priv  = $this->privilegiados($u);
        $login = $this->filetime($this->uno($u, 'lastlogontimestamp'));
        $clave = $this->filetime($this->uno($u, 'pwdlastset'));
        $ent   = $ctx['entra'][strtolower($sam)] ?? null;
        $estado = $this->estado($uac, $this->uno($u, 'lockouttime'), $ctx['politica']);

        // Tipo
        $persona = $this->uno($u, 'givenname') || $this->uno($u, 'sn');
        $texto   = "$sam $nom $desc $dn";
        // adminCount=1 no basta: queda marcado para siempre aunque la cuenta ya no
        // sea privilegiada. Administrador = está hoy en un grupo privilegiado.
        if (in_array(strtolower($sam), self::SISTEMA, true) || ($uac & self::UAC_CONFIANZA)) $tipo = 'Otro';
        elseif ($priv) $tipo = 'Administrador';
        elseif ($spn || preg_match(self::RX_SERVICIO, $texto) || preg_match('/^(MSOL_|Sync_|AAD_)/i', $sam)) $tipo = 'Servicio';
        elseif (!$persona || preg_match(self::RX_GENERICA, "$sam $nom")) $tipo = 'Genérica/Compartida';
        else $tipo = 'Usuario';

        $correo = $this->uno($u, 'mail') ?: ($ent['mail'] ?? null) ?: $this->uno($u, 'userprincipalname') ?: '';
        $area   = $this->uno($u, 'department') ?: ($ent['department'] ?? null) ?: $this->uno($u, 'company') ?: '';

        $obs = [];
        if (strtolower($sam) === 'krbtgt') $obs[] = 'Cuenta del sistema (Kerberos)';
        if ($uac & self::UAC_CONFIANZA) $obs[] = 'Cuenta de la relación de confianza con otro dominio';
        if (!$priv && $this->uno($u, 'admincount') == 1) $obs[] = 'adminCount=1: fue privilegiada (o lo es por un grupo anidado)';
        if ($login && $login->isFuture()) { $obs[] = 'Fecha de último inicio inválida (' . $login->format('d-m-Y') . ')'; }
        if ($this->uno($u, 'title')) $obs[] = 'Cargo: ' . $this->uno($u, 'title');
        if (!$this->uno($u, 'mail') && $correo) $obs[] = ($ent['mail'] ?? null) && $correo === $ent['mail'] ? 'Correo tomado de Entra ID' : 'Sin atributo mail: se usa el UPN';
        if ($login === null) $obs[] = 'Nunca ha iniciado sesión';
        elseif (!$login->isFuture() && ($d = (int) $login->diffInDays(now())) > 90) $obs[] = "Sin inicio de sesión hace $d días";
        if ($uac & self::UAC_NO_EXPIRA) $obs[] = 'Contraseña nunca expira';
        if ($uac & self::UAC_SIN_CLAVE) $obs[] = 'Puede no tener contraseña (PASSWD_NOTREQD)';
        if ($uac & self::UAC_DELEGACION) $obs[] = 'Delegación sin restricciones';
        if ($clave && ($d = (int) $clave->diffInDays(now())) > 365) $obs[] = "Contraseña sin cambiar hace $d días";
        if ($spn) $obs[] = 'Tiene SPN (' . count($spn) . ')';
        if ($ent) $obs[] = 'Sincronizada con Entra ID';
        if ($w = $this->fecha($this->uno($u, 'whencreated'))) $obs[] = "Creada {$w}";

        return [
            $ctx['grupo'], $ctx['dominio'], $tipo, $sam, $correo,
            $nom . ($desc && $desc !== $nom ? " — $desc" : ''),
            $estado, $area, $this->ou($dn),
            $priv ? implode(', ', $priv) : $this->resumenGrupos($u),
            $login ? ($login->isFuture() ? 'No disponible' : $login->format('d-m-Y')) : 'Nunca',
            $this->cn($this->uno($u, 'manager')),
            $this->migracion($estado, $login, $tipo),
            implode('; ', $obs),
        ];
    }

    private function filaMsa(array $u, array $ctx): array
    {
        $uac   = (int) $this->uno($u, 'useraccountcontrol');
        $login = $this->filetime($this->uno($u, 'lastlogontimestamp'));
        $gmsa  = in_array('msds-groupmanagedserviceaccount', array_map('strtolower', $this->todos($u, 'objectclass')), true);
        $estado = $uac & self::UAC_DESHABILITADA ? 'Deshabilitada' : 'Habilitada';

        return [
            $ctx['grupo'], $ctx['dominio'], 'Servicio', (string) $this->uno($u, 'samaccountname'), '',
            ($gmsa ? 'Cuenta de servicio administrada de grupo (gMSA)' : 'Cuenta de servicio administrada (MSA)')
                . (($d = $this->uno($u, 'description')) ? " — $d" : ''),
            $estado, '', $this->ou((string) $this->uno($u, 'distinguishedname')),
            $this->privilegiados($u) ? implode(', ', $this->privilegiados($u)) : $this->resumenGrupos($u),
            $login ? $login->format('d-m-Y') : 'Nunca', '',
            'Por validar',
            implode('; ', array_filter([
                'Contraseña administrada por AD',
                ($spn = $this->todos($u, 'serviceprincipalname')) ? 'Tiene SPN (' . count($spn) . ')' : null,
                ($w = $this->fecha($this->uno($u, 'whencreated'))) ? "Creada $w" : null,
            ])),
        ];
    }

    private function filaEquipo(array $e, array $ctx): array
    {
        $uac   = (int) $this->uno($e, 'useraccountcontrol');
        $login = $this->filetime($this->uno($e, 'lastlogontimestamp'));
        $so    = trim($this->uno($e, 'operatingsystem') . ' ' . $this->uno($e, 'operatingsystemversion'));
        $dc    = (bool) ($uac & self::UAC_DC);
        $estado = $uac & self::UAC_DESHABILITADA ? 'Deshabilitada' : 'Habilitada';
        $nombre = $this->uno($e, 'dnshostname') ?: $this->uno($e, 'cn');

        $obs = [];
        if ($so) $obs[] = $so;
        if ($dc) $obs[] = 'Controlador de dominio';
        if ($login === null) $obs[] = 'Nunca se ha conectado';
        elseif (($d = (int) $login->diffInDays(now())) > 90) $obs[] = "Sin conexión hace $d días";
        if ($w = $this->fecha($this->uno($e, 'whencreated'))) $obs[] = "Creado $w";

        return [
            $ctx['grupo'], $ctx['dominio'], 'Equipo', (string) $this->uno($e, 'samaccountname'), '',
            $nombre . (($d = $this->uno($e, 'description')) ? " — $d" : ''),
            $estado, $so, $this->ou((string) $this->uno($e, 'distinguishedname')),
            $dc ? 'Controlador de dominio' : ($this->privilegiados($e) ? implode(', ', $this->privilegiados($e)) : ''),
            $login ? $login->format('d-m-Y') : 'Nunca',
            $this->cn($this->uno($e, 'managedby')),
            $estado === 'Habilitada' && $login && $login->diffInDays(now()) <= 90 ? 'Sí' : 'Por validar',
            implode('; ', $obs),
        ];
    }

    private function filaGrupo(array $g, array $ctx): array
    {
        $tipo    = (int) $this->uno($g, 'grouptype');
        $clase   = $tipo & 0x80000000 ? 'Seguridad' : 'Distribución';
        $ambito  = $tipo & 0x2 ? 'Global' : ($tipo & 0x4 ? 'Dominio local' : ($tipo & 0x8 ? 'Universal' : ($tipo & 0x1 ? 'Integrado' : '')));
        $miembros = count($this->todos($g, 'member'));
        // Con más de 1500 miembros AD entrega la lista por rangos y `member` llega vacío.
        foreach (array_keys($g) as $k) {
            if (is_string($k) && str_starts_with(strtolower($k), 'member;range=')) $miembros = '1500+';
        }
        $cn      = (string) ($this->uno($g, 'cn') ?: $this->uno($g, 'samaccountname'));
        $priv    = in_array(strtolower($cn), self::PRIVILEGIADOS, true);

        return [
            $ctx['grupo'], $ctx['dominio'], 'Grupo', (string) $this->uno($g, 'samaccountname'),
            (string) $this->uno($g, 'mail'),
            $cn . (($d = $this->uno($g, 'description')) ? " — $d" : ''),
            'No aplica', '', $this->ou((string) $this->uno($g, 'distinguishedname')),
            trim("$clase · $ambito · $miembros miembros" . ($priv ? ' · GRUPO PRIVILEGIADO' : '')),
            'No disponible',
            $this->cn($this->uno($g, 'managedby')),
            'Por validar',
            implode('; ', array_filter([
                $miembros === 0 ? 'Sin miembros directos' : null,
                ($w = $this->fecha($this->uno($g, 'whencreated'))) ? "Creado $w" : null,
            ])),
        ];
    }

    /* ── Entra ID ─────────────────────────────────────────────────────────── */

    /**
     * Usuarios sincronizados desde AD, por dominio on-premises y sAMAccountName,
     * para completar correo y área y saber qué dominios sincronizan.
     */
    private function entra(): array
    {
        return Cache::remember(self::CACHE_ENTRA, self::TTL, function () {
            try {
                $g = app(GraphClient::class);
                $tenant = $g->get('https://graph.microsoft.com/v1.0/organization?$select=displayName')->json('value.0.displayName');
                $usuarios = $g->paginar('https://graph.microsoft.com/v1.0/users?$top=999&$select='
                    . 'onPremisesDomainName,onPremisesSamAccountName,onPremisesSyncEnabled,mail,department', 40);

                $porDominio = [];
                foreach ($usuarios as $u) {
                    if (empty($u['onPremisesSyncEnabled']) || empty($u['onPremisesDomainName']) || empty($u['onPremisesSamAccountName'])) continue;
                    $porDominio[strtolower($u['onPremisesDomainName'])][strtolower($u['onPremisesSamAccountName'])] = [
                        'mail' => $u['mail'] ?? null, 'department' => $u['department'] ?? null,
                    ];
                }
                return ['tenant' => $tenant, 'por_dominio' => $porDominio, 'error' => null];
            } catch (\Throwable $e) {
                return ['tenant' => null, 'por_dominio' => [], 'error' => $e->getMessage()];
            }
        });
    }

    /* ── LDAP ─────────────────────────────────────────────────────────────── */

    private function buscar($c, string $base, string $filtro, array $atributos): array
    {
        try {
            return $c->query()->in($base)->rawFilter($filtro)->select($atributos)->paginate(1000);
        } catch (\Throwable $e) {
            // Contenedores que no existen o no se pueden leer (sitios, trusts): sin datos.
            if (str_contains($filtro, 'objectCategory=person')) throw $e;
            return [];
        }
    }

    private function rootDse($c): array
    {
        $r = $c->query()->in('')->read()->whereHas('objectclass')->first([
            'defaultNamingContext', 'rootDomainNamingContext', 'configurationNamingContext',
            'domainFunctionality', 'forestFunctionality',
        ]) ?? [];
        $out = [];
        foreach (['defaultnamingcontext', 'rootdomainnamingcontext', 'configurationnamingcontext', 'domainfunctionality', 'forestfunctionality'] as $k) {
            $out[$k] = (string) $this->uno($r, $k);
        }
        return $out;
    }

    /** Duración del bloqueo del dominio en segundos; 0 = hasta que un admin lo libere. */
    private function politicaBloqueo($c, string $base): int
    {
        try {
            $d = $c->query()->in($base)->read()->whereHas('objectclass')->first(['lockoutDuration']);
            return (int) abs(((int) $this->uno($d ?? [], 'lockoutduration')) / 10000000);
        } catch (\Throwable) {
            return 1800;
        }
    }

    private function estado(int $uac, $lockout, int $duracion): string
    {
        if ($uac & self::UAC_DESHABILITADA) return 'Deshabilitada';
        $desde = $this->filetime($lockout);
        if ($desde && ($duracion === 0 || $desde->copy()->addSeconds($duracion)->isFuture())) return 'Bloqueada';
        return 'Habilitada';
    }

    private function migracion(string $estado, ?Carbon $login, string $tipo): string
    {
        if ($tipo === 'Otro') return 'No';
        $dias = $login && !$login->isFuture() ? (int) $login->diffInDays(now()) : null;
        if ($estado !== 'Deshabilitada' && $dias !== null && $dias <= 90) return 'Sí';
        if ($estado === 'Deshabilitada' && ($dias === null || $dias > 180)) return 'No';
        return 'Por validar';
    }

    private function privilegiados(array $o): array
    {
        $out = [];
        foreach ($this->todos($o, 'memberof') as $dn) {
            $cn = $this->cn($dn);
            if (in_array(strtolower($cn), self::PRIVILEGIADOS, true)) $out[] = $cn;
        }
        return $out;
    }

    private function resumenGrupos(array $o): string
    {
        $n = count($this->todos($o, 'memberof'));
        return $n ? "Sin grupos privilegiados ($n grupos)" : 'Sin grupos';
    }

    /* ── Utilidades ───────────────────────────────────────────────────────── */

    /** Primer valor de un atributo, tolerando mayúsculas y el índice 'count'. */
    private function uno(array $o, string $attr)
    {
        $v = $o[$attr] ?? $o[strtolower($attr)] ?? null;
        if (is_array($v)) return $v[0] ?? null;
        return $v;
    }

    private function todos(array $o, string $attr): array
    {
        $v = $o[$attr] ?? null;
        if (!is_array($v)) return $v === null ? [] : [$v];
        return array_values(array_filter($v, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));
    }

    private function filetime($valor): ?Carbon
    {
        if ($valor === null || $valor === '') return null;
        $ft = (float) $valor;
        if ($ft <= 0 || $ft >= 9.2e18) return null;
        return Carbon::createFromTimestamp((int) ($ft / 10000000 - 11644473600), 'America/Santiago');
    }

    /** whenCreated viene como 20240131120000.0Z. */
    private function fecha($gen): ?string
    {
        if (!$gen || !preg_match('/^(\d{4})(\d{2})(\d{2})/', (string) $gen, $m)) return null;
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    private function cn($dn): string
    {
        if (!$dn) return '';
        return preg_match('/^CN=((?:\\\\,|[^,])+)/i', (string) $dn, $m) ? str_replace('\\,', ',', $m[1]) : (string) $dn;
    }

    /** CN=Juan,OU=Usuarios,OU=Santiago,DC=x,DC=y  →  Santiago/Usuarios */
    private function ou(string $dn): string
    {
        $partes = preg_split('/(?<!\\\\),/', $dn);
        array_shift($partes);  // el propio objeto
        $ruta = [];
        foreach ($partes as $p) {
            if (stripos($p, 'DC=') === 0) break;
            $ruta[] = str_replace('\\,', ',', preg_replace('/^(OU|CN)=/i', '', $p));
        }
        return implode('/', array_reverse($ruta));
    }

    private function dnADns(string $dn): string
    {
        preg_match_all('/DC=([^,]+)/i', $dn, $m);
        return strtolower(implode('.', $m[1]));
    }
}
