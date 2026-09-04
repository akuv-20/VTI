<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\FamiliaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\CompaniaController;
use App\Http\Controllers\CuentaContableController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\UsuarioTelefonicoController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\AparatoController;
use App\Http\Controllers\LineaTelefonicaController;
use App\Http\Controllers\CentroCostoController;
use App\Http\Controllers\ImportacionEntelController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\ImportacionMovistarController;
use App\Http\Controllers\ImportacionWomController;
use App\Http\Controllers\EntregaFacturaController;
use App\Http\Controllers\ActaEntregaTelefonoController;
use App\Http\Controllers\ActaDevolucionTelefonoController;
use App\Http\Controllers\RoamingController;
use App\Http\Controllers\Inventario\SelectorController as InvSelectorController;
use App\Http\Controllers\Inventario\EquipoController as InvEquipoController;
use App\Http\Controllers\Inventario\DashboardController as InvDashboardController;
use App\Http\Controllers\Inventario\CruceController as InvCruceController;
use App\Http\Controllers\Inventario\ActaController as InvActaController;
use App\Http\Controllers\Inventario\ExcepcionController as InvExcepcionController;
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\Admin\ConfiguracionController as AdminConfiguracionController;
use App\Http\Controllers\Admin\ActiveDirectoryController as AdminADController;
use App\Http\Controllers\Admin\ActiveDirectory2Controller as AdminAD2Controller;
use App\Http\Controllers\Admin\ActiveDirectory3Controller as AdminAD3Controller;
use App\Http\Controllers\Admin\EntraIDController as AdminEntraIDController;
use App\Http\Controllers\Admin\MfaController;
use App\Http\Controllers\Admin\ActividadBuzonesController;
use App\Http\Controllers\Admin\KpiDisponibilidadController as AdminKpiDisponibilidadController;
use App\Http\Controllers\Admin\MonitoreoMapaController as AdminMonitoreoMapaController;
use App\Http\Controllers\Admin\SitioController as AdminSitioController;
use App\Http\Controllers\Admin\SitioPanelController as AdminSitioPanelController;
use App\Http\Controllers\Admin\InformeController as AdminInformeController;
use App\Http\Controllers\Admin\MapaGeograficoController as AdminMapaGeograficoController;
use App\Http\Controllers\Admin\ZonaController as AdminZonaController;
use App\Http\Controllers\Auth\AzureController;
use App\Http\Controllers\DhcpController;
use App\Http\Controllers\DhcpIngestController;

// Route::get('/', function () {
//     return view('home');
// });

Auth::routes();

// ── Azure AD OAuth ───────────────────────────────────────────────────────────
Route::get('auth/azure/redirect',  [AzureController::class, 'redirect'])->name('azure.redirect');
Route::get('auth/azure/callback',  [AzureController::class, 'callback'])->name('azure.callback');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('facturas/pendientes',        [FacturaController::class, 'pendientes'])->name('facturas.pendientes');
Route::get('facturas/resumen',           [FacturaController::class, 'resumen'])->name('facturas.resumen');
Route::get('facturas/resumen-servicios', [FacturaController::class, 'resumenServicios'])->name('facturas.resumen_servicios');
Route::resource('facturas', FacturaController::class);

// ── Entregas de Facturas ─────────────────────────────────────────────────────
Route::get('entregas_facturas/buscar', [EntregaFacturaController::class, 'buscarFacturas'])->name('entregas_facturas.buscar');
Route::get('entregas_facturas/{entrega}/imprimir', [EntregaFacturaController::class, 'imprimir'])->name('entregas_facturas.imprimir');
Route::resource('entregas_facturas', EntregaFacturaController::class)
    ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
    ->parameters(['entregas_facturas' => 'entrega']);

Route::resource('servicios', ServicioController::class);

Route::resource('familias', FamiliaController::class);

Route::resource('empresas', EmpresaController::class);

Route::resource('companias', CompaniaController::class);

Route::resource('cuentas_contables', CuentaContableController::class); // ¡Añade esta línea!

Route::resource('emisores', EmisorController::class)->parameters(['emisores' => 'emisor']);
Route::resource('usuarios_telefonicos', UsuarioTelefonicoController::class);
Route::resource('ubicaciones', UbicacionController::class)->parameters(['ubicaciones' => 'ubicacion']);
Route::resource('marcas', MarcaController::class);
Route::resource('aparatos', AparatoController::class);
Route::get('centros_costo/buscar', [CentroCostoController::class, 'buscar'])->name('centros_costo.buscar');
Route::resource('centros_costo', CentroCostoController::class);
Route::post('lineas_telefonicas/reprocesar_ccosto', [LineaTelefonicaController::class, 'reprocesarCentroCosto'])->name('lineas_telefonicas.reprocesar_ccosto');
Route::get('lineas_telefonicas/export', [LineaTelefonicaController::class, 'export'])->name('lineas_telefonicas.export');
Route::resource('lineas_telefonicas', LineaTelefonicaController::class);
Route::get('informes/telefonia', [InformeController::class, 'telefonia'])->name('informes.telefonia');

// ── Roamings ─────────────────────────────────────────────────────────────────
Route::get('roamings/buscar-lineas',        [RoamingController::class, 'buscarLineas'])->name('roamings.buscar_lineas');
Route::get('roamings',                      [RoamingController::class, 'index'])->name('roamings.index');
Route::post('roamings/linea/{linea}',       [RoamingController::class, 'store'])->name('roamings.store');
Route::patch('roamings/{roaming}',          [RoamingController::class, 'update'])->name('roamings.update');
Route::patch('roamings/{roaming}/cerrar',   [RoamingController::class, 'cerrar'])->name('roamings.cerrar');
Route::patch('roamings/{roaming}/archivar', [RoamingController::class, 'archivar'])->name('roamings.archivar');
Route::delete('roamings/{roaming}',         [RoamingController::class, 'destroy'])->name('roamings.destroy');

// ── Actas de Entrega Teléfono ────────────────────────────────────────────────
Route::get('actas_entrega_telefono/buscar-lineas', [ActaEntregaTelefonoController::class, 'buscarLineas'])->name('actas_entrega_telefono.buscar_lineas');
Route::get('actas_entrega_telefono/{acta}/imprimir', [ActaEntregaTelefonoController::class, 'imprimir'])->name('actas_entrega_telefono.imprimir');
Route::get('actas_entrega_telefono/{acta}/editar', [ActaEntregaTelefonoController::class, 'edit'])->name('actas_entrega_telefono.edit');
Route::put('actas_entrega_telefono/{acta}', [ActaEntregaTelefonoController::class, 'update'])->name('actas_entrega_telefono.update');
Route::post('actas_entrega_telefono/linea/{linea}', [ActaEntregaTelefonoController::class, 'store'])->name('actas_entrega_telefono.store');
Route::delete('actas_entrega_telefono/{acta}', [ActaEntregaTelefonoController::class, 'destroy'])->name('actas_entrega_telefono.destroy');
Route::get('actas_entrega_telefono', [ActaEntregaTelefonoController::class, 'index'])->name('actas_entrega_telefono.index');

// ── Actas de Devolución Teléfono ─────────────────────────────────────────────
Route::get('actas_devolucion_telefono/buscar-lineas', [ActaDevolucionTelefonoController::class, 'buscarLineas'])->name('actas_devolucion_telefono.buscar_lineas');
Route::get('actas_devolucion_telefono/{acta}/imprimir', [ActaDevolucionTelefonoController::class, 'imprimir'])->name('actas_devolucion_telefono.imprimir');
Route::get('actas_devolucion_telefono/{acta}/editar', [ActaDevolucionTelefonoController::class, 'edit'])->name('actas_devolucion_telefono.edit');
Route::put('actas_devolucion_telefono/{acta}', [ActaDevolucionTelefonoController::class, 'update'])->name('actas_devolucion_telefono.update');
Route::post('actas_devolucion_telefono/linea/{linea}', [ActaDevolucionTelefonoController::class, 'store'])->name('actas_devolucion_telefono.store');
Route::delete('actas_devolucion_telefono/{acta}', [ActaDevolucionTelefonoController::class, 'destroy'])->name('actas_devolucion_telefono.destroy');
Route::get('actas_devolucion_telefono', [ActaDevolucionTelefonoController::class, 'index'])->name('actas_devolucion_telefono.index');

// ── DHCP / Reservas ──────────────────────────────────────────────────────────
// Endpoint de ingesta: token propio, sin sesión (excluido de CSRF en bootstrap/app.php)
Route::post('api/dhcp/importar', [DhcpIngestController::class, 'store'])->name('dhcp.importar');

Route::middleware('auth')->prefix('dhcp')->name('dhcp.')->group(function () {
    Route::get('/',              [DhcpController::class, 'dashboard'])->name('dashboard');
    Route::get('reservas',       [DhcpController::class, 'reservas'])->name('reservas');
    Route::get('configuracion',  [DhcpController::class, 'configuracion'])->name('configuracion');
    Route::post('configuracion', [DhcpController::class, 'guardarConfig'])->name('configuracion.guardar');
});

// ── Inventario (multidominio) ────────────────────────────────────────────────
//
// Un solo módulo con selector de dominio. Cada dominio de config/inventario.php
// recibe su propio grupo de rutas, con el dominio metido en el NOMBRE
// (`inventario.verfrut.equipos`) y no solo en la URL: así el permiso por
// prefijo de User::tieneAcceso() puede distinguir un dominio de otro.
//
// El dominio viaja al controlador con ->defaults(), que lo convierte en
// parámetro de ruta sin ensuciar la URL.
//
// Ojo: este bucle se resuelve al definir las rutas. Si agregas un dominio con
// las rutas cacheadas, hay que correr `php artisan route:clear`.

// Selector: sin dominio todavía, basta con tener acceso a alguno.
Route::middleware(['auth', 'can:acceso_inventario'])->name('inventario.elegir.')->group(function () {
    Route::get('inventario/dashboard', [InvSelectorController::class, 'dashboard'])->name('dashboard');
    Route::get('inventario/equipos',   [InvSelectorController::class, 'equipos'])->name('equipos');
    Route::get('inventario/cruce',     [InvSelectorController::class, 'cruce'])->name('cruce');
    Route::get('inventario/actas',     [InvSelectorController::class, 'actas'])->name('actas');
});

foreach (array_keys(config('inventario.dominios', [])) as $claveDominio) {
    $gate = config("inventario.dominios.{$claveDominio}.gate");

    Route::middleware(['auth', "can:{$gate}"])
        ->name("inventario.{$claveDominio}.")
        ->group(function () use ($claveDominio) {
            $d = fn($ruta) => $ruta->defaults('dominio', $claveDominio);

            $d(Route::get("inventario/dashboard/{$claveDominio}",  [InvDashboardController::class, 'index']))->name('dashboard');

            $d(Route::get("inventario/equipos/{$claveDominio}",      [InvEquipoController::class, 'index']))->name('equipos');
            $d(Route::get("inventario/equipos/{$claveDominio}/{id}", [InvEquipoController::class, 'show']))->name('equipos.show');

            $d(Route::get("inventario/cruce/{$claveDominio}",            [InvCruceController::class, 'index']))->name('cruce');
            $d(Route::post("inventario/cruce/{$claveDominio}/ajustes",   [InvCruceController::class, 'ajustes']))->name('cruce.ajustes');
            $d(Route::post("inventario/cruce/{$claveDominio}/refrescar", [InvCruceController::class, 'refrescar']))->name('cruce.refrescar');

            $d(Route::get("inventario/excepciones/{$claveDominio}",                  [InvExcepcionController::class, 'index']))->name('excepciones');
            $d(Route::post("inventario/excepciones/{$claveDominio}",                 [InvExcepcionController::class, 'store']))->name('excepciones.store');
            $d(Route::post("inventario/excepciones/{$claveDominio}/previsualizar",   [InvExcepcionController::class, 'previsualizar']))->name('excepciones.preview');
            $d(Route::put("inventario/excepciones/{$claveDominio}/{excepcion}",      [InvExcepcionController::class, 'update']))->name('excepciones.update');
            $d(Route::post("inventario/excepciones/{$claveDominio}/{excepcion}/toggle", [InvExcepcionController::class, 'toggle']))->name('excepciones.toggle');
            $d(Route::delete("inventario/excepciones/{$claveDominio}/{excepcion}",   [InvExcepcionController::class, 'destroy']))->name('excepciones.destroy');

            $d(Route::get("inventario/actas/{$claveDominio}",                    [InvActaController::class, 'index']))->name('actas');
            $d(Route::post("inventario/actas/{$claveDominio}/equipo/{id}",       [InvActaController::class, 'store']))->name('actas.store');
            $d(Route::get("inventario/actas/{$claveDominio}/{acta}/imprimir",    [InvActaController::class, 'imprimir']))->name('actas.imprimir');
            $d(Route::get("inventario/actas/{$claveDominio}/{acta}/editar",      [InvActaController::class, 'edit']))->name('actas.edit');
            $d(Route::put("inventario/actas/{$claveDominio}/{acta}",             [InvActaController::class, 'update']))->name('actas.update');
            $d(Route::delete("inventario/actas/{$claveDominio}/{acta}",          [InvActaController::class, 'destroy']))->name('actas.destroy');
        });
}

// ── Rutas viejas de Inventario TI ────────────────────────────────────────────
// Se conservan como redirecciones para no romper los favoritos ni los enlaces
// que ya circulan. Todas apuntan al dominio Verfrut, que es lo que servían.
Route::middleware('auth')->group(function () {
    Route::get('inventario_ti/dashboard', fn() => redirect()->route('inventario.verfrut.dashboard'))->name('inventario_ti.dashboard');
    Route::get('inventario_ti/actas',     fn() => redirect()->route('inventario.verfrut.actas'))->name('inventario_ti.actas');
    Route::get('inventario_ti',           fn() => redirect()->route('inventario.verfrut.equipos'))->name('inventario_ti.index');
    Route::get('inventario_ti/{id}',      fn($id) => redirect()->route('inventario.verfrut.equipos.show', $id))
        ->whereNumber('id')->name('inventario_ti.show');
});

Route::resource('importaciones_entel', ImportacionEntelController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
Route::post('importaciones_entel/{importaciones_entel}/recruzar', [ImportacionEntelController::class, 'recruzar'])->name('importaciones_entel.recruzar');
Route::resource('importaciones_movistar', ImportacionMovistarController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
Route::post('importaciones_movistar/{importaciones_movistar}/recruzar', [ImportacionMovistarController::class, 'recruzar'])->name('importaciones_movistar.recruzar');
Route::get('importaciones_wom/buscar-lineas',     [ImportacionWomController::class, 'buscarLineas'])->name('importaciones_wom.buscar_lineas');
Route::get('importaciones_wom/plantilla',          [ImportacionWomController::class, 'plantilla'])->name('importaciones_wom.plantilla');
Route::get('importaciones_wom/plantilla/lineas',   [ImportacionWomController::class, 'plantillaLineas'])->name('importaciones_wom.plantilla_lineas');
Route::post('importaciones_wom/plantilla/agregar', [ImportacionWomController::class, 'plantillaAgregar'])->name('importaciones_wom.plantilla_agregar');
Route::delete('importaciones_wom/plantilla/{plantilla}/quitar',  [ImportacionWomController::class, 'plantillaQuitar'])->name('importaciones_wom.plantilla_quitar');
Route::patch('importaciones_wom/plantilla/{plantilla}/monto',   [ImportacionWomController::class, 'plantillaActualizarMonto'])->name('importaciones_wom.plantilla_monto');
Route::get('importaciones_wom/{importaciones_wom}/imprimir', [ImportacionWomController::class, 'imprimir'])->name('importaciones_wom.imprimir');
Route::resource('importaciones_wom', ImportacionWomController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

// ── Administración (solo admins) ─────────────────────────────────────────────
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('usuarios/buscar-entra',   [AdminUsuarioController::class, 'buscarEntra'])->name('usuarios.buscar_entra');
    Route::post('usuarios/importar-entra', [AdminUsuarioController::class, 'importarEntra'])->name('usuarios.importar_entra');
    Route::post('usuarios/{usuario}/sincronizar-azure', [AdminUsuarioController::class, 'sincronizarAzure'])->name('usuarios.sincronizar_azure');
    Route::resource('usuarios', AdminUsuarioController::class);
    Route::get('configuracion',            [AdminConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::post('configuracion',           [AdminConfiguracionController::class, 'update'])->name('configuracion.update');
    Route::post('configuracion/test-ldap', [AdminConfiguracionController::class, 'testLdap'])->name('configuracion.test-ldap');
    Route::post('configuracion/test-glpi', [AdminConfiguracionController::class, 'testGlpi'])->name('configuracion.test-glpi');
    Route::post('configuracion/test-glpiuni', [AdminConfiguracionController::class, 'testGlpiuni'])->name('configuracion.test-glpiuni');
    Route::post('configuracion/test-checkmk', [AdminConfiguracionController::class, 'testCheckmk'])->name('configuracion.test-checkmk');
    Route::post('configuracion/test-veeam',   [AdminConfiguracionController::class, 'testVeeam'])->name('configuracion.test-veeam');
    Route::post('configuracion/test-azure',   [AdminConfiguracionController::class, 'testAzure'])->name('configuracion.test-azure');
});

// ── Active Directory (admins + usuarios con permiso AD) ───────────────────────
Route::middleware(['auth', 'can:acceso_ad'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('active-directory')->name('active_directory.')->group(function () {
        Route::get('/',                           [AdminADController::class, 'index'])->name('index');
        Route::get('/importar-correos',           [AdminADController::class, 'importarCorreos'])->name('importar_correos');
        Route::post('/importar-correos',          [AdminADController::class, 'procesarImportacion'])->name('procesar_importacion');
        Route::get('/{username}/editar',          [AdminADController::class, 'edit'])->name('edit');
        Route::put('/{username}',                 [AdminADController::class, 'update'])->name('update');
        Route::post('/{username}/toggle',         [AdminADController::class, 'toggleEnabled'])->name('toggle');
        Route::post('/{username}/desbloquear',    [AdminADController::class, 'desbloquear'])->name('desbloquear');
        Route::post('/{username}/reset-password', [AdminADController::class, 'resetPassword'])->name('reset-password');
    });

});

// ── Entra ID / Microsoft 365 ──────────────────────────────────────────────────
Route::middleware(['auth', 'can:acceso_entra'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('entra-id')->name('entra_id.')->group(function () {
        Route::get('/',                          [AdminEntraIDController::class, 'index'])->name('index');
        Route::get('/datos',                     [AdminEntraIDController::class, 'datos'])->name('datos');
        Route::get('/dashboard',                 [AdminEntraIDController::class, 'dashboard'])->name('dashboard');
        Route::get('/hallazgos/{regla}',         [AdminEntraIDController::class, 'hallazgos'])->name('hallazgos');
        Route::get('/reglas',                    [AdminEntraIDController::class, 'reglas'])->name('reglas');
        Route::post('/reglas',                   [AdminEntraIDController::class, 'reglaStore'])->name('reglas.store');
        Route::put('/reglas/{regla}',            [AdminEntraIDController::class, 'reglaUpdate'])->name('reglas.update');
        Route::post('/reglas/{regla}/toggle',    [AdminEntraIDController::class, 'reglaToggle'])->name('reglas.toggle');
        Route::delete('/reglas/{regla}',         [AdminEntraIDController::class, 'reglaDestroy'])->name('reglas.destroy');
        Route::get('/inspector',                 [AdminEntraIDController::class, 'inspector'])->name('inspector');
        Route::get('/inspector/{campo}',         [AdminEntraIDController::class, 'inspectorDetalle'])->name('inspector.detalle');

        Route::prefix('mfa')->name('mfa.')->group(function () {
            Route::get('/',           [MfaController::class, 'dashboard'])->name('dashboard');
            Route::get('/listado',    [MfaController::class, 'index'])->name('index');
            Route::get('/excel',      [MfaController::class, 'excel'])->name('excel');
            Route::post('/refrescar', [MfaController::class, 'refrescar'])->name('refrescar');
        });
    });

    // Actividad de buzones: mismo tenant y mismo permiso que Entra ID.
    Route::prefix('buzones')->name('buzones.')->group(function () {
        Route::get('/',                    [ActividadBuzonesController::class, 'dashboard'])->name('dashboard');
        Route::get('/listado',             [ActividadBuzonesController::class, 'index'])->name('index');
        Route::get('/excel',               [ActividadBuzonesController::class, 'excel'])->name('excel');
        Route::get('/pdf',                 [ActividadBuzonesController::class, 'pdf'])->name('pdf');
        Route::post('/refrescar',          [ActividadBuzonesController::class, 'refrescar'])->name('refrescar');
        Route::post('/excluir',            [ActividadBuzonesController::class, 'excluir'])->name('excluir');
        Route::delete('/excluir/{excluido}', [ActividadBuzonesController::class, 'incluir'])->name('incluir');
        Route::post('/excluir/{id}/restaurar', [ActividadBuzonesController::class, 'restaurar'])->name('restaurar');
    });
});

// ── KPI Disponibilidad (CheckMK) ─────────────────────────────────────────────
Route::middleware(['auth', 'can:acceso_kpi'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('kpi/disponibilidad')->name('kpi.disponibilidad.')->group(function () {
        Route::get('/',                              [AdminKpiDisponibilidadController::class, 'dashboard'])->name('dashboard');
        Route::get('/servicios',                     [AdminKpiDisponibilidadController::class, 'servicios'])->name('servicios');
        Route::post('/servicios',                    [AdminKpiDisponibilidadController::class, 'servicioStore'])->name('servicios.store');
        Route::put('/servicios/{servicio}',          [AdminKpiDisponibilidadController::class, 'servicioUpdate'])->name('servicios.update');
        Route::post('/servicios/{servicio}/toggle',  [AdminKpiDisponibilidadController::class, 'servicioToggle'])->name('servicios.toggle');
        Route::delete('/servicios/{servicio}',       [AdminKpiDisponibilidadController::class, 'servicioDestroy'])->name('servicios.destroy');
        Route::get('/explorar',                      [AdminKpiDisponibilidadController::class, 'explorar'])->name('explorar');
        Route::get('/excepciones',                   [AdminKpiDisponibilidadController::class, 'excepciones'])->name('excepciones');
        Route::post('/excepciones',                  [AdminKpiDisponibilidadController::class, 'excepcionStore'])->name('excepciones.store');
        Route::put('/excepciones/{excepcion}',       [AdminKpiDisponibilidadController::class, 'excepcionUpdate'])->name('excepciones.update');
        Route::post('/excepciones/{excepcion}/toggle', [AdminKpiDisponibilidadController::class, 'excepcionToggle'])->name('excepciones.toggle');
        Route::delete('/excepciones/{excepcion}',    [AdminKpiDisponibilidadController::class, 'excepcionDestroy'])->name('excepciones.destroy');
        Route::post('/capturar',                     [AdminKpiDisponibilidadController::class, 'capturar'])->name('capturar');
        Route::put('/snapshot/{snapshot}',           [AdminKpiDisponibilidadController::class, 'snapshotUpdate'])->name('snapshot.update');
        Route::get('/informe',                       [AdminKpiDisponibilidadController::class, 'informe'])->name('informe');
    });
});

// ── Monitoreo: mapa de red en vivo (CheckMK) ─────────────────────────────────
Route::middleware(['auth', 'can:acceso_monitoreo'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('monitoreo')->name('monitoreo.')->group(function () {
        Route::get('mapas',                     [AdminMonitoreoMapaController::class, 'index'])->name('mapas.index');
        Route::post('mapas',                    [AdminMonitoreoMapaController::class, 'store'])->name('mapas.store');
        Route::get('mapas/hosts',               [AdminMonitoreoMapaController::class, 'hosts'])->name('mapas.hosts');
        Route::post('mapas/tv-token',           [AdminMonitoreoMapaController::class, 'tvTokenRegenerar'])->name('mapas.tv_token');
        Route::get('mapas/{mapa}',              [AdminMonitoreoMapaController::class, 'show'])->name('mapas.show');
        Route::post('mapas/{mapa}/tecnicos',    [AdminMonitoreoMapaController::class, 'tecnicos'])->name('mapas.tecnicos');
        Route::post('mapas/{mapa}/tv-token',    [AdminMonitoreoMapaController::class, 'tvTokenMapa'])->name('mapas.tv_token_mapa');
        Route::put('mapas/{mapa}',              [AdminMonitoreoMapaController::class, 'update'])->name('mapas.update');
        Route::delete('mapas/{mapa}',           [AdminMonitoreoMapaController::class, 'destroy'])->name('mapas.destroy');
        Route::get('mapas/{mapa}/estado',       [AdminMonitoreoMapaController::class, 'estado'])->name('mapas.estado');
        Route::get('mapas/{mapa}/preview',      [AdminMonitoreoMapaController::class, 'preview'])->name('mapas.preview');
        Route::post('mapas/{mapa}/nodos',       [AdminMonitoreoMapaController::class, 'nodoStore'])->name('mapas.nodos.store');
        Route::put('nodos/{nodo}',              [AdminMonitoreoMapaController::class, 'nodoUpdate'])->name('mapas.nodos.update');
        Route::delete('nodos/{nodo}',           [AdminMonitoreoMapaController::class, 'nodoDestroy'])->name('mapas.nodos.destroy');
        Route::post('mapas/{mapa}/enlaces',     [AdminMonitoreoMapaController::class, 'enlaceStore'])->name('mapas.enlaces.store');
        Route::put('enlaces/{enlace}',          [AdminMonitoreoMapaController::class, 'enlaceUpdate'])->name('mapas.enlaces.update');
        Route::delete('enlaces/{enlace}',       [AdminMonitoreoMapaController::class, 'enlaceDestroy'])->name('mapas.enlaces.destroy');
    });
});

// ── Informes ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'can:acceso_informes'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('informes')->name('informes.')->group(function () {
        Route::get('/sitios',       [AdminInformeController::class, 'sitios'])->name('sitios');
        Route::get('/sitios/excel', [AdminInformeController::class, 'sitiosExcel'])->name('sitios.excel');
        Route::get('/sitios/pdf',   [AdminInformeController::class, 'sitiosPdf'])->name('sitios.pdf');
    });
});

// ── Monitoreo: mapa geográfico de los sitios ─────────────────────────────────
Route::middleware(['auth', 'can:acceso_mapa_geografico'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/mapa-geografico', [AdminMapaGeograficoController::class, 'index'])->name('mapa-geografico');
});

// ── Monitoreo: fichas de sitios (plantas, campos, datacenter, oficinas) ──────
Route::middleware(['auth', 'can:acceso_sitios'])->prefix('admin')->name('admin.')->group(function () {
    // Mantenedor de zonas. Vive fuera de /sitios porque no cuelga de ninguna
    // ficha: se usa desde el listado y desde la edición, siempre por JSON.
    Route::prefix('zonas')->name('zonas.')->group(function () {
        Route::get('/',           [AdminZonaController::class, 'index'])->name('index');
        Route::post('/',          [AdminZonaController::class, 'store'])->name('store');
        Route::put('/{zona}',     [AdminZonaController::class, 'update'])->name('update');
        Route::delete('/{zona}',  [AdminZonaController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('sitios')->name('sitios.')->group(function () {
        // Panel de levantamiento (antes del {sitio} para no chocar con el binding)
        Route::get('/dashboard',            [AdminSitioPanelController::class, 'dashboard'])->name('dashboard');
        Route::get('/descubrimiento',       [AdminSitioPanelController::class, 'descubrimiento'])->name('descubrimiento');
        Route::post('/descubrimiento/sitio', [AdminSitioPanelController::class, 'descubrimientoSitio'])->name('descubrimiento.sitio');
        Route::post('/descubrimiento/equipo', [AdminSitioPanelController::class, 'descubrimientoEquipo'])->name('descubrimiento.equipo');
        Route::post('/descubrimiento/host', [AdminSitioPanelController::class, 'descubrimientoHost'])->name('descubrimiento.host');
        Route::get('/importar',             [AdminSitioPanelController::class, 'importar'])->name('importar');
        Route::get('/importar/plantilla',   [AdminSitioPanelController::class, 'importarPlantilla'])->name('importar.plantilla');
        Route::post('/importar',            [AdminSitioPanelController::class, 'importarProcesar'])->name('importar.procesar');
        Route::get('/enlaces',              [AdminSitioPanelController::class, 'enlaces'])->name('enlaces');
        Route::post('/enlaces/remapear',    [AdminSitioPanelController::class, 'enlacesRemapear'])->name('enlaces.remapear');
        Route::post('/enlaces/desenlazar',  [AdminSitioPanelController::class, 'enlacesDesenlazar'])->name('enlaces.desenlazar');
        Route::get('/dpa',                  [AdminSitioPanelController::class, 'dpa'])->name('dpa');
        Route::get('/geocodificar',         [AdminSitioPanelController::class, 'geocodificar'])->name('geocodificar');
        Route::get('/terreno',              [AdminSitioPanelController::class, 'terreno'])->name('terreno');
        // Antes de /terreno/{sitio}: si no, «ping» se tomaría por un id de sitio.
        Route::get('/terreno/ping',         [AdminSitioPanelController::class, 'terrenoPing'])->name('terreno.ping');
        Route::get('/terreno/{sitio}',      [AdminSitioPanelController::class, 'terrenoFicha'])->name('terreno.ficha');
        Route::post('/terreno/{sitio}',     [AdminSitioPanelController::class, 'terrenoGuardar'])->name('terreno.guardar');

        // Fotos (fuera del scope de {sitio} porque sirven a sitios y equipos)
        Route::post('/fotos',               [AdminSitioController::class, 'fotoStore'])->name('fotos.store');
        Route::put('/fotos/{foto}',         [AdminSitioController::class, 'fotoUpdate'])->name('fotos.update');
        Route::delete('/fotos/{foto}',      [AdminSitioController::class, 'fotoDestroy'])->name('fotos.destroy');

        // Equipos y hosts
        Route::put('/equipos/{equipo}',     [AdminSitioController::class, 'equipoUpdate'])->name('equipos.update');
        Route::delete('/equipos/{equipo}',  [AdminSitioController::class, 'equipoDestroy'])->name('equipos.destroy');
        Route::delete('/hosts/{host}',      [AdminSitioController::class, 'hostDestroy'])->name('hosts.destroy');

        // Fichas
        Route::get('/',                     [AdminSitioController::class, 'index'])->name('index');
        Route::post('/',                    [AdminSitioController::class, 'store'])->name('store');
        Route::get('/{sitio}',              [AdminSitioController::class, 'show'])->name('show');
        Route::put('/{sitio}',              [AdminSitioController::class, 'update'])->name('update');
        Route::delete('/{sitio}',           [AdminSitioController::class, 'destroy'])->name('destroy');
        Route::post('/{sitio}/clonar',      [AdminSitioController::class, 'clonar'])->name('clonar');
        Route::post('/{sitio}/hosts',       [AdminSitioController::class, 'hostStore'])->name('hosts.store');
        Route::post('/{sitio}/equipos',     [AdminSitioController::class, 'equipoStore'])->name('equipos.store');
    });
});

// Plano de fondo de un mapa. Sin login, igual que cuando salía de
// storage/public: el modo TV es público y necesita poder mostrarlo.
Route::get('monitoreo/mapas/{mapa}/fondo', [AdminMonitoreoMapaController::class, 'fondo'])->name('monitoreo.mapas.fondo');

// Modo TV público (sin login; autenticado por token largo regenerable)
Route::get('monitoreo/tv/{token}',               [AdminMonitoreoMapaController::class, 'tv'])->name('monitoreo.tv');
Route::get('monitoreo/tv/{token}/estado/{mapa}', [AdminMonitoreoMapaController::class, 'tvEstado'])->name('monitoreo.tv.estado');

// ── Active Directory Grupo Verfrut Perú (admins + usuarios con permiso AD2) ──
Route::middleware(['auth', 'can:acceso_ad2'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('active-directory-2')->name('active_directory2.')->group(function () {
        Route::get('/',                           [AdminAD2Controller::class, 'index'])->name('index');
        Route::get('/importar-correos',           [AdminAD2Controller::class, 'importarCorreos'])->name('importar_correos');
        Route::post('/importar-correos',          [AdminAD2Controller::class, 'procesarImportacion'])->name('procesar_importacion');
        Route::get('/{username}/editar',          [AdminAD2Controller::class, 'edit'])->name('edit');
        Route::put('/{username}',                 [AdminAD2Controller::class, 'update'])->name('update');
        Route::post('/{username}/toggle',         [AdminAD2Controller::class, 'toggleEnabled'])->name('toggle');
        Route::post('/{username}/desbloquear',    [AdminAD2Controller::class, 'desbloquear'])->name('desbloquear');
        Route::post('/{username}/reset-password', [AdminAD2Controller::class, 'resetPassword'])->name('reset-password');
    });
});

// ── Active Directory Unifrutti (admins + usuarios con permiso AD3) ───────────
Route::middleware(['auth', 'can:acceso_ad3'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('active-directory-3')->name('active_directory3.')->group(function () {
        Route::get('/',                           [AdminAD3Controller::class, 'index'])->name('index');
        Route::get('/importar-correos',           [AdminAD3Controller::class, 'importarCorreos'])->name('importar_correos');
        Route::post('/importar-correos',          [AdminAD3Controller::class, 'procesarImportacion'])->name('procesar_importacion');
        Route::get('/{username}/editar',          [AdminAD3Controller::class, 'edit'])->name('edit');
        Route::put('/{username}',                 [AdminAD3Controller::class, 'update'])->name('update');
        Route::post('/{username}/toggle',         [AdminAD3Controller::class, 'toggleEnabled'])->name('toggle');
        Route::post('/{username}/desbloquear',    [AdminAD3Controller::class, 'desbloquear'])->name('desbloquear');
        Route::post('/{username}/reset-password', [AdminAD3Controller::class, 'resetPassword'])->name('reset-password');
    });
});

// Test LDAP secundario (también admin)
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('configuracion/test-ldap2', [AdminConfiguracionController::class, 'testLdap2'])->name('configuracion.test-ldap2');
    Route::post('configuracion/test-ldap3', [AdminConfiguracionController::class, 'testLdap3'])->name('configuracion.test-ldap3');
});
