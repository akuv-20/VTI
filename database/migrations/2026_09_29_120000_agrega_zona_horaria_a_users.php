<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zona horaria por usuario.
 *
 * Queda nula a propósito: null significa «la de la aplicación», así que si
 * mañana cambia la zona por defecto, quien no eligió nada la sigue igual sin
 * tener que tocar 10 filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->string('zona_horaria', 64)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn('zona_horaria');
        });
    }
};
