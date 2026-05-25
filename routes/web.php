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
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\Admin\ConfiguracionController as AdminConfiguracionController;

// Route::get('/', function () {
//     return view('home');
// });

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('facturas/pendientes', [FacturaController::class, 'pendientes'])->name('facturas.pendientes');
Route::resource('facturas', FacturaController::class);

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

Route::resource('importaciones_entel', ImportacionEntelController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
Route::post('importaciones_entel/{importaciones_entel}/recruzar', [ImportacionEntelController::class, 'recruzar'])->name('importaciones_entel.recruzar');
Route::resource('importaciones_movistar', ImportacionMovistarController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
Route::post('importaciones_movistar/{importaciones_movistar}/recruzar', [ImportacionMovistarController::class, 'recruzar'])->name('importaciones_movistar.recruzar');

// ── Administración (solo admins) ─────────────────────────────────────────────
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', AdminUsuarioController::class);
    Route::get('configuracion',         [AdminConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::post('configuracion',        [AdminConfiguracionController::class, 'update'])->name('configuracion.update');
});
