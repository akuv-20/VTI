<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['actas_entrega_telefono', 'actas_devolucion_telefono'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                // 'equipo_sim' (equipo físico + SIM) | 'solo_sim'
                $table->string('tipo_acta')->default('equipo_sim')->after('id_linea_telefonica');
            });
        }
    }

    public function down(): void
    {
        foreach (['actas_entrega_telefono', 'actas_devolucion_telefono'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('tipo_acta');
            });
        }
    }
};
