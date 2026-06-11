<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;
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

        $glpiCfg = [
            'host'     => Configuracion::get('glpi_db_host',     env('GLPI_DB_HOST',     '127.0.0.1')),
            'port'     => Configuracion::get('glpi_db_port',     env('GLPI_DB_PORT',     3306)),
            'database' => Configuracion::get('glpi_db_database', env('GLPI_DB_DATABASE', 'glpi')),
            'username' => Configuracion::get('glpi_db_username', env('GLPI_DB_USERNAME', '')),
        ];

        return view('admin.configuracion.index', compact('loginBg', 'appNombre', 'appLogo', 'favicon', 'azureCfg', 'ldapCfg', 'ldap2Cfg', 'glpiCfg'));
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

        return back()->with('success', 'Configuración guardada.');
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
