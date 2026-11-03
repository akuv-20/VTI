<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token personal de la API de GLPI (Unifrutti), para asignar tickets desde
 * Asistencia GLPI a nombre de quien está conectado. Se guarda cifrado con la
 * APP_KEY (cast `encrypted` del modelo), por eso la columna es `text`.
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
