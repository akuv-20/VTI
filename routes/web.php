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
use App\Http\Controllers\InventarioTiController;
use App\Http\Controllers\InventarioDashboardController;
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\Admin\ConfiguracionController as AdminConfiguracionController;
use App\Http\Controllers\Admin\ActiveDirectoryController as AdminADController;
use App\Http\Controllers\Admin\ActiveDirectory2Controller as AdminAD2Controller;
use App\Http\Controllers\Auth\AzureController;

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
Route::resource('lineas_telefonicas', LineaTelefonicaController::class);
Route::get('informes/telefonia', [InformeController::class, 'telefonia'])->name('informes.telefonia');

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

// ── Inventario TI ────────────────────────────────────────────────────────────
Route::get('inventario_ti/dashboard',                          [InventarioDashboardController::class, 'index'])->name('inventario_ti.dashboard');
Route::get('inventario_ti',                                    [InventarioTiController::class, 'index'])->name('inventario_ti.index');
Route::get('inventario_ti/actas',                              [InventarioTiController::class, 'actas'])->name('inventario_ti.actas');
Route::get('inventario_ti/actas/{acta}/imprimir',              [InventarioTiController::class, 'imprimirActa'])->name('inventario_ti.actas.imprimir');
Route::get('inventario_ti/actas/{acta}/editar',                [InventarioTiController::class, 'editActa'])->name('inventario_ti.actas.edit');
Route::put('inventario_ti/actas/{acta}',                       [InventarioTiController::class, 'updateActa'])->name('inventario_ti.actas.update');
Route::delete('inventario_ti/actas/{acta}',                    [InventarioTiController::class, 'destroyActa'])->name('inventario_ti.actas.destroy');
Route::get('inventario_ti/{id}',                               [InventarioTiController::class, 'show'])->name('inventario_ti.show');
Route::post('inventario_ti/{id}/acta',                         [InventarioTiController::class, 'storeActa'])->name('inventario_ti.acta.store');

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
        Route::post('/{username}/reset-password', [AdminADController::class, 'resetPassword'])->name('reset-password');
    });

});

// ── Active Directory Grupo Verfrut Perú (admins + usuarios con permiso AD2) ──
Route::middleware(['auth', 'can:acceso_ad2'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('active-directory-2')->name('active_directory2.')->group(function () {
        Route::get('/',                           [AdminAD2Controller::class, 'index'])->name('index');
        Route::get('/importar-correos',           [AdminAD2Controller::class, 'importarCorreos'])->name('importar_correos');
        Route::post('/importar-correos',          [AdminAD2Controller::class, 'procesarImportacion'])->name('procesar_importacion');
        Route::get('/{username}/editar',          [AdminAD2Controller::class, 'edit'])->name('edit');
        Route::put('/{username}',                 [AdminAD2Controller::class, 'update'])->name('update');
        Route::post('/{username}/toggle',         [AdminAD2Controller::class, 'toggleEnabled'])->name('toggle');
        Route::post('/{username}/reset-password', [AdminAD2Controller::class, 'resetPassword'])->name('reset-password');
    });
});

// Test LDAP secundario (también admin)
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('configuracion/test-ldap2', [AdminConfiguracionController::class, 'testLdap2'])->name('configuracion.test-ldap2');
});
