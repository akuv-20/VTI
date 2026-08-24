<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LdapRecord\Container as LdapContainer;
use LdapRecord\Connection  as LdapConnection;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $loginBg   = Configuracion::get('login_background');
        $appNombre = Configuracion::get('app_nombre') ?: config('app.name');
        $appLogo   = Configuracion::get('app_logo');
        $favicon   = Configuracion::get('favicon');

        $azureCfg = [
            'enabled'       => (bool) Configuracion::get('azure_enabled', false),
            'client_id'     => Configuracion::get('azure_client_id', ''),
            'client_secret' => Configuracion::get('azure_client_secret', ''),
            'tenant_id'     => Configuracion::get('azure_tenant_id', ''),
        ];

        $ldapCfg = [
            'host'     => Configuracion::get('ldap_host',     env('LDAP_HOST',    'vfrpdc01.verfrut.cl,vfrpdc02.verfrut.cl')),
            'port'     => Configuracion::get('ldap_port',     env('LDAP_PORT',    389)),
            'base_dn'  => Configuracion::get('ldap_base_dn',  env('LDAP_BASE_DN', 'DC=verfrut,DC=cl')),
            'username' => Configuracion::get('ldap_username', env('LDAP_USERNAME', '')),
        ];

        $ldap2Cfg = [
            'host'     => Configuracion::get('ldap2_host',    ''),
            'port'     => Configuracion::get('ldap2_port',    389),
            'base_dn'  => Configuracion::get('ldap2_base_dn', ''),
            'username' => Configuracion::get('ldap2_username',''),
        ];

        $ldap3Cfg = [
            'host'     => Configuracion::get('ldap3_host',    ''),
            'port'     => Configuracion::get('ldap3_port',    389),
            'base_dn'  => Configuracion::get('ldap3_base_dn', ''),
            'username' => Configuracion::get('ldap3_username',''),
        ];

        $glpiCfg = [
            'host'     => Configuracion::get('glpi_db_host',     env('GLPI_DB_HOST',     '127.0.0.1')),
            'port'     => Configuracion::get('glpi_db_port',     env('GLPI_DB_PORT',     3306)),
            'database' => Configuracion::get('glpi_db_database', env('GLPI_DB_DATABASE', 'glpi')),
            'username' => Configuracion::get('glpi_db_username', env('GLPI_DB_USERNAME', '')),
        ];

        $glpiUniCfg = [
            'host'     => Configuracion::get('glpiuni_db_host',     env('GLPIUNI_DB_HOST',     '')),
            'port'     => Configuracion::get('glpiuni_db_port',     env('GLPIUNI_DB_PORT',     3306)),
            'database' => Configuracion::get('glpiuni_db_database', env('GLPIUNI_DB_DATABASE', 'glpi')),
            'username' => Configuracion::get('glpiuni_db_username', env('GLPIUNI_DB_USERNAME', '')),
        ];

        $checkmkCfg = [
            'url'  => Configuracion::get('checkmk_url',  env('CHECKMK_URL',  '')),
            'site' => Configuracion::get('checkmk_site', env('CHECKMK_SITE', '')),
            'user' => Configuracion::get('checkmk_user', env('CHECKMK_USER', '')),
        ];

        $veeamCfg = [
            'url'         => Configuracion::get('veeam_url',  env('VEEAM_URL',  '')),
            'user'        => Configuracion::get('veeam_user', env('VEEAM_USER', '')),
            'api_version' => Configuracion::get('veeam_api_version', ''),
        ];

        return view('admin.configuracion.index', compact('loginBg', 'appNombre', 'appLogo', 'favicon', 'azureCfg', 'ldapCfg', 'ldap2Cfg', 'ldap3Cfg', 'glpiCfg', 'glpiUniCfg', 'checkmkCfg', 'veeamCfg'));
    }

    public function update(Request $request)
    {
        // ── Nombre de la aplicación ──────────────────────────────────────
        if ($request->has('app_nombre')) {
            $request->validate([
                'app_nombre' => 'required|string|max:60',
            ], [
                'app_nombre.required' => 'El nombre de la aplicación es obligatorio.',
                'app_nombre.max'      => 'El nombre no puede superar los 60 caracteres.',
            ]);
            Configuracion::set('app_nombre', trim($request->input('app_nombre')));
            return back()->with('success', 'Nombre de la aplicación actualizado.');
        }

        // ── Logo de la aplicación ────────────────────────────────────────
        if ($request->input('eliminar_logo')) {
            $anterior = Configuracion::get('app_logo');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }
            Configuracion::set('app_logo', null);
            return back()->with('success', 'Logo eliminado.');
        }

        if ($request->hasFile('app_logo')) {
            if (!$request->file('app_logo')->isValid()) {
                return back()->withErrors(['app_logo' => 'No se recibió un archivo válido.']);
            }
            $request->validate([
                'app_logo' => 'image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            ], [
                'app_logo.image' => 'El archivo debe ser una imagen.',
                'app_logo.mimes' => 'Solo se permiten JPG, PNG, WebP o SVG.',
                'app_logo.max'   => 'El logo no puede superar 2 MB.',
            ]);

            $anterior = Configuracion::get('app_logo');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }

            $path = $request->file('app_logo')->store('config', 'public');
            if (!$path) {
                return back()->withErrors(['app_logo' => 'Error al guardar el archivo.']);
            }
            Configuracion::set('app_logo', $path);
            return back()->with('success', 'Logo actualizado correctamente.');
        }

        // ── Favicon ──────────────────────────────────────────────────────
        if ($request->input('eliminar_favicon')) {
            $anterior = Configuracion::get('favicon');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }
            Configuracion::set('favicon', null);
            return back()->with('success', 'Favicon eliminado.');
        }

        if ($request->hasFile('favicon')) {
            if (!$request->file('favicon')->isValid()) {
                return back()->withErrors(['favicon' => 'No se recibió un archivo válido.']);
            }
            $request->validate([
                'favicon' => 'file|mimes:ico,png,svg,webp|max:512',
            ], [
                'favicon.mimes' => 'Solo se permiten ICO, PNG, SVG o WebP.',
                'favicon.max'   => 'El favicon no puede superar 512 KB.',
            ]);

            $anterior = Configuracion::get('favicon');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }

            $path = $request->file('favicon')->store('config', 'public');
            if (!$path) {
                return back()->withErrors(['favicon' => 'Error al guardar el archivo.']);
            }
            Configuracion::set('favicon', $path);
            return back()->with('success', 'Favicon actualizado correctamente.');
        }

        // ── Fondo del login ──────────────────────────────────────────────
        if ($request->input('eliminar_fondo')) {
            $anterior = Configuracion::get('login_background');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }
            Configuracion::set('login_background', null);
            return back()->with('success', 'Imagen de fondo eliminada.');
        }

        if ($request->hasFile('login_background')) {
            if (!$request->file('login_background')->isValid()) {
                return back()->withErrors(['login_background' => 'No se recibió un archivo válido. Verifica que no supere el límite de tamaño del servidor.']);
            }
            $request->validate([
                'login_background' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            ], [
                'login_background.image' => 'El archivo debe ser una imagen.',
                'login_background.mimes' => 'Solo se permiten JPG, PNG o WebP.',
                'login_background.max'   => 'La imagen no puede superar los 10 MB.',
            ]);

            $anterior = Configuracion::get('login_background');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }

            $path = $request->file('login_background')->store('config', 'public');
            if (!$path) {
                return back()->withErrors(['login_background' => 'Error al guardar el archivo.']);
            }
            Configuracion::set('login_background', $path);
            return back()->with('success', 'Imagen de fondo actualizada correctamente.');
        }

        // ── Azure AD ─────────────────────────────────────────────────────
        if ($request->input('seccion') === 'azure') {
            Configuracion::set('azure_enabled',   $request->boolean('azure_enabled') ? '1' : '0');
            Configuracion::set('azure_tenant_id', trim($request->input('azure_tenant_id', '')));

            if ($request->filled('azure_client_id')) {
                Configuracion::set('azure_client_id', trim($request->input('azure_client_id')));
            }
            if ($request->filled('azure_client_secret')) {
                Configuracion::set('azure_client_secret', trim($request->input('azure_client_secret')));
            }

            return back()->with('success', 'Configuración de Azure AD guardada.');
        }

        // ── Active Directory / LDAP ──────────────────────────────────────
        if ($request->input('seccion') === 'ldap') {
            $request->validate([
                'ldap_host'     => 'required|string|max:500',
                'ldap_port'     => 'required|integer|between:1,65535',
                'ldap_base_dn'  => 'required|string|max:200',
                'ldap_username' => 'required|string|max:200',
            ]);

            Configuracion::set('ldap_host',    trim($request->input('ldap_host')));
            Configuracion::set('ldap_port',    $request->input('ldap_port'));
            Configuracion::set('ldap_base_dn', trim($request->input('ldap_base_dn')));
            Configuracion::set('ldap_username', trim($request->input('ldap_username')));

            // Solo actualizar contraseña si se ingresó una nueva
            if ($request->filled('ldap_password')) {
                Configuracion::set('ldap_password', $request->input('ldap_password'));
            }

            return back()->with('success', 'Configuración de Active Directory guardada.');
        }

        // ── GLPI Base de Datos ───────────────────────────────────────────────
        if ($request->input('seccion') === 'glpi') {
            $request->validate([
                'glpi_db_host'     => 'required|string|max:255',
                'glpi_db_port'     => 'required|integer|between:1,65535',
                'glpi_db_database' => 'required|string|max:100',
                'glpi_db_username' => 'required|string|max:100',
            ]);

            Configuracion::set('glpi_db_host',     trim($request->input('glpi_db_host')));
            Configuracion::set('glpi_db_port',     $request->input('glpi_db_port'));
            Configuracion::set('glpi_db_database', trim($request->input('glpi_db_database')));
            Configuracion::set('glpi_db_username', trim($request->input('glpi_db_username')));

            if ($request->filled('glpi_db_password')) {
                Configuracion::set('glpi_db_password', $request->input('glpi_db_password'));
            }

            return back()->with('success', 'Configuración de BD GLPI guardada.');
        }

        // ── GLPI Unifrutti (Helpdesk) Base de Datos ──────────────────────────
        if ($request->input('seccion') === 'glpiuni') {
            $request->validate([
                'glpiuni_db_host'     => 'required|string|max:255',
                'glpiuni_db_port'     => 'required|integer|between:1,65535',
                'glpiuni_db_database' => 'required|string|max:100',
                'glpiuni_db_username' => 'required|string|max:100',
            ]);

            Configuracion::set('glpiuni_db_host',     trim($request->input('glpiuni_db_host')));
            Configuracion::set('glpiuni_db_port',     $request->input('glpiuni_db_port'));
            Configuracion::set('glpiuni_db_database', trim($request->input('glpiuni_db_database')));
            Configuracion::set('glpiuni_db_username', trim($request->input('glpiuni_db_username')));

            if ($request->filled('glpiuni_db_password')) {
                Configuracion::set('glpiuni_db_password', $request->input('glpiuni_db_password'));
            }

            return back()->with('success', 'Configuración de BD GLPI Unifrutti guardada.');
        }

        // ── Active Directory secundario (Grupo Verfrut Perú) ────────────────
        if ($request->input('seccion') === 'ldap2') {
            $request->validate([
                'ldap2_host'     => 'required|string|max:500',
                'ldap2_port'     => 'required|integer|between:1,65535',
                'ldap2_base_dn'  => 'required|string|max:200',
                'ldap2_username' => 'required|string|max:200',
            ]);

            Configuracion::set('ldap2_host',    trim($request->input('ldap2_host')));
            Configuracion::set('ldap2_port',    $request->input('ldap2_port'));
            Configuracion::set('ldap2_base_dn', trim($request->input('ldap2_base_dn')));
            Configuracion::set('ldap2_username', trim($request->input('ldap2_username')));

            if ($request->filled('ldap2_password')) {
                Configuracion::set('ldap2_password', $request->input('ldap2_password'));
            }

            return back()->with('success', 'Configuración de Active Directory (Grupo Verfrut Perú) guardada.');
        }

        // ── Active Directory terciario (Unifrutti) ──────────────────────────
        if ($request->input('seccion') === 'ldap3') {
            $request->validate([
                'ldap3_host'     => 'required|string|max:500',
                'ldap3_port'     => 'required|integer|between:1,65535',
                'ldap3_base_dn'  => 'required|string|max:200',
                'ldap3_username' => 'required|string|max:200',
            ]);

            Configuracion::set('ldap3_host',    trim($request->input('ldap3_host')));
            Configuracion::set('ldap3_port',    $request->input('ldap3_port'));
            Configuracion::set('ldap3_base_dn', trim($request->input('ldap3_base_dn')));
            Configuracion::set('ldap3_username', trim($request->input('ldap3_username')));

            if ($request->filled('ldap3_password')) {
                Configuracion::set('ldap3_password', $request->input('ldap3_password'));
            }

            return back()->with('success', 'Configuración de Active Directory (Unifrutti) guardada.');
        }

        // ── CheckMK (KPI de disponibilidad) ──────────────────────────────────
        if ($request->input('seccion') === 'checkmk') {
            $request->validate([
                'checkmk_url'  => 'required|string|max:255',
                'checkmk_site' => 'required|string|max:100',
                'checkmk_user' => 'required|string|max:100',
            ]);

            Configuracion::set('checkmk_url',  rtrim(trim($request->input('checkmk_url')), '/'));
            Configuracion::set('checkmk_site', trim($request->input('checkmk_site')));
            Configuracion::set('checkmk_user', trim($request->input('checkmk_user')));

            if ($request->filled('checkmk_secret')) {
                Configuracion::set('checkmk_secret', trim($request->input('checkmk_secret')));
            }

            return back()->with('success', 'Configuración de CheckMK guardada.');
        }

        // ── Veeam Backup & Replication (KPI de respaldos) ────────────────────
        if ($request->input('seccion') === 'veeam') {
            $request->validate([
                'veeam_url'  => 'required|string|max:255',
                'veeam_user' => 'required|string|max:150',
            ]);

            Configuracion::set('veeam_url',  rtrim(trim($request->input('veeam_url')), '/'));
            Configuracion::set('veeam_user', trim($request->input('veeam_user')));

            if ($request->filled('veeam_password')) {
                Configuracion::set('veeam_password', $request->input('veeam_password'));
            }

            // Las credenciales cambiaron: el token cacheado ya no sirve.
            \App\Services\VeeamClient::olvidarToken();

            return back()->with('success', 'Configuración de Veeam guardada.');
        }

        return back()->with('success', 'Configuración guardada.');
    }

    /** Test de conexión CheckMK — responde JSON (usa la config guardada) */
    public function testCheckmk(Request $request)
    {
        $resultado = (new \App\Services\CheckMkClient())->probarConexion();

        return response()->json($resultado);
    }

    /**
     * Test de conexión Veeam B&R — responde JSON.
     *
     * Usa los datos del formulario si vienen (para poder probar antes de
     * guardar) y cae en los guardados si no. La contraseña solo viaja si el
     * usuario escribió una nueva.
     */
    public function testVeeam(Request $request)
    {
        $url      = $request->input('url')  ?: Configuracion::get('veeam_url',  env('VEEAM_URL',  ''));
        $user     = $request->input('user') ?: Configuracion::get('veeam_user', env('VEEAM_USER', ''));
        $password = $request->filled('password')
            ? $request->input('password')
            : Configuracion::get('veeam_password', env('VEEAM_PASSWORD', ''));

        // Al probar con datos del formulario, el token cacheado puede ser de
        // otras credenciales: descartarlo para que la prueba sea real. Cuando
        // el test es automático (sin formulario) no se toca, para no invalidar
        // el token en cada carga de la pantalla.
        if ($request->filled('url') || $request->filled('user') || $request->filled('password')) {
            \App\Services\VeeamClient::olvidarToken();
        }

        $resultado = (new \App\Services\VeeamClient($url, $user, $password))->probarConexion();

        return response()->json($resultado);
    }

    /**
     * Test de conexión Microsoft 365 / Entra ID — responde JSON.
     *
     * Pide un token con client credentials y lo usa para leer la organización,
     * que es la forma de comprobar que además de autenticar tiene permisos
     * efectivos sobre Graph.
     */
    public function testAzure(Request $request)
    {
        $tenantId     = Configuracion::get('azure_tenant_id');
        $clientId     = Configuracion::get('azure_client_id');
        $clientSecret = Configuracion::get('azure_client_secret');

        if (!$tenantId || !$clientId || !$clientSecret) {
            return response()->json(['ok' => false, 'message' => 'Completa client ID, secret y tenant, y guarda antes de probar.']);
        }

        try {
            $resp = Http::asForm()->timeout(15)->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                ]
            );

            if (!$resp->successful()) {
                // Los AADSTS vienen con varias líneas y una URL de ayuda; con la
                // primera línea basta para saber qué pasó.
                $detalle = (string) ($resp->json('error_description') ?? $resp->json('error') ?? ('HTTP ' . $resp->status()));
                $detalle = trim(explode("\n", str_replace("\r\n", "\n", $detalle))[0]);
                return response()->json(['ok' => false, 'message' => $detalle]);
            }

            $org = Http::withToken($resp->json('access_token'))->timeout(15)
                    ->get('https://graph.microsoft.com/v1.0/organization');

            if (!$org->successful()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Token obtenido, pero Graph rechazó la consulta: '
                               . ($org->json('error.message') ?? ('HTTP ' . $org->status()))
                               . ' — revisa los permisos de aplicación concedidos.',
                ]);
            }

            $nombre = $org->json('value.0.displayName') ?: 'organización sin nombre';

            return response()->json(['ok' => true, 'message' => "Conexión exitosa — {$nombre}"]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Test de conexión BD GLPI — responde JSON */
    public function testGlpi(Request $request)
    {
        $host     = $request->input('host')     ?: Configuracion::get('glpi_db_host',     env('GLPI_DB_HOST', '127.0.0.1'));
        $port     = $request->input('port')     ?: Configuracion::get('glpi_db_port',     env('GLPI_DB_PORT', 3306));
        $database = $request->input('database') ?: Configuracion::get('glpi_db_database', env('GLPI_DB_DATABASE', 'glpi'));
        $username = $request->input('username') ?: Configuracion::get('glpi_db_username', env('GLPI_DB_USERNAME', ''));
        $password = $request->filled('password')
            ? $request->input('password')
            : Configuracion::get('glpi_db_password', env('GLPI_DB_PASSWORD', ''));

        if (!$host || !$username || !$database) {
            return response()->json(['ok' => false, 'message' => 'Completa los datos antes de probar.']);
        }

        try {
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            return response()->json(['ok' => true, 'message' => "Conexión exitosa a {$database}@{$host} — MySQL {$version}"]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Test de conexión BD GLPI Unifrutti (Helpdesk) — responde JSON */
    public function testGlpiuni(Request $request)
    {
        $host     = $request->input('host')     ?: Configuracion::get('glpiuni_db_host',     env('GLPIUNI_DB_HOST', ''));
        $port     = $request->input('port')     ?: Configuracion::get('glpiuni_db_port',     env('GLPIUNI_DB_PORT', 3306));
        $database = $request->input('database') ?: Configuracion::get('glpiuni_db_database', env('GLPIUNI_DB_DATABASE', 'glpi'));
        $username = $request->input('username') ?: Configuracion::get('glpiuni_db_username', env('GLPIUNI_DB_USERNAME', ''));
        $password = $request->filled('password')
            ? $request->input('password')
            : Configuracion::get('glpiuni_db_password', env('GLPIUNI_DB_PASSWORD', ''));

        if (!$host || !$username || !$database) {
            return response()->json(['ok' => false, 'message' => 'Completa los datos antes de probar.']);
        }

        try {
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            return response()->json(['ok' => true, 'message' => "Conexión exitosa a {$database}@{$host} — MySQL {$version}"]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Test de conexión LDAP secundaria — responde JSON */
    public function testLdap2(Request $request)
    {
        return $this->probarConexionLdap(
            Configuracion::get('ldap2_host'),
            Configuracion::get('ldap2_username'),
            Configuracion::get('ldap2_password'),
            Configuracion::get('ldap2_base_dn', ''),
            (int)(Configuracion::get('ldap2_port') ?: 389)
        );
    }

    /** Test de conexión LDAP terciaria (Unifrutti) — responde JSON */
    public function testLdap3(Request $request)
    {
        return $this->probarConexionLdap(
            Configuracion::get('ldap3_host'),
            Configuracion::get('ldap3_username'),
            Configuracion::get('ldap3_password'),
            Configuracion::get('ldap3_base_dn', ''),
            (int)(Configuracion::get('ldap3_port') ?: 389)
        );
    }

    /** Test de conexión LDAP — responde JSON */
    public function testLdap(Request $request)
    {
        return $this->probarConexionLdap(
            Configuracion::get('ldap_host'),
            Configuracion::get('ldap_username'),
            Configuracion::get('ldap_password'),
            Configuracion::get('ldap_base_dn', 'DC=verfrut,DC=cl'),
            (int)(Configuracion::get('ldap_port') ?: 389)
        );
    }

    private function probarConexionLdap(?string $host, ?string $username, ?string $password, string $baseDn, int $port)
    {
        if (!$host || !$username || !$password) {
            return response()->json(['ok' => false, 'message' => 'Completa los datos y guarda antes de probar.']);
        }

        // Limitar a 10 s para no chocar con el max_execution_time del servidor
        set_time_limit(10);

        try {
            if (extension_loaded('ldap')) {
                ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
                // Timeout de red (TCP connect): evita colgar 30 s si el DC no responde
                ldap_set_option(null, LDAP_OPT_NETWORK_TIMEOUT, 5);
                ldap_set_option(null, LDAP_OPT_TIMELIMIT, 5);
            }

            $hosts = array_values(array_filter(array_map('trim', explode(',', $host))));
            $conn  = new LdapConnection([
                'hosts'    => $hosts,
                'username' => $username,
                'password' => $password,
                'base_dn'  => $baseDn,
                'port'     => $port,
                'timeout'  => 5,
                'use_tls'  => $port === 636,
            ]);
            $conn->connect();
            return response()->json(['ok' => true, 'message' => 'Conexión exitosa con ' . $hosts[0]]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
