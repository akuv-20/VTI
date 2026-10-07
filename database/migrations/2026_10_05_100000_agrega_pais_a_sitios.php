<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * País del sitio.
 *
 * Hasta ahora todo era Chile y no hacía falta decirlo. Con la incorporación de
 * los campos de Perú sí: cambia a quién se le pide el enlace, con qué operador
 * se mide la cobertura y, sobre todo, qué significan «región» y «comuna».
 *
 * Queda nulo para los sitios nuevos —el formulario obliga a elegir— pero las
 * fichas que ya existen se marcan como Chile: lo son, y dejarlas en blanco
 * obligaría a abrir 50 fichas para decir algo que ya sabemos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitios', function (Blueprint $tabla) {
            $tabla->string('pais', 10)->nullable()->after('tipo')->index();
        });

        DB::table('sitios')->whereNull('pais')->update(['pais' => 'chile']);
    }

    public function down(): void
    {
        Schema::table('sitios', function (Blueprint $tabla) {
            $tabla->dropIndex(['pais']);
            $tabla->dropColumn('pais');
        });
    }
};
