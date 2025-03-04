<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\FamiliaController;
use App\Http\Controllers\EmpresaController;

// Route::get('/', function () {
//     return view('home');
// });

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::resource('facturas', FacturaController::class);

Route::resource('servicios', ServicioController::class);

Route::resource('familias', FamiliaController::class);

Route::resource('empresas', EmpresaController::class);