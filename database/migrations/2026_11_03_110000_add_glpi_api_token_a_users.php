<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token personal de la API de GLPI (Unifrutti), para asignar tickets desde
 * Asistencia GLPI a nombre de quien está conectado. Nació cifrado con la
 * APP_KEY; desde 2026_11_03_120000 va en claro (ver esa migración).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('glpi_api_token')->nullable()->after('zona_horaria');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('glpi_api_token');
        });
    }
};
