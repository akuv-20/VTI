<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Línea pasa a ser opcional (acta de "solo equipo" no tiene línea)
        Schema::table('actas_entrega_telefono', function (Blueprint $table) {
            $table->dropForeign(['id_linea_telefonica']);
        });
        Schema::table('actas_entrega_telefono', function (Blueprint $table) {
            $table->foreignId('id_linea_telefonica')->nullable()->change();
            $table->foreign('id_linea_telefonica')
                  ->references('id')->on('lineas_telefonicas')
                  ->nullOnDelete();
        });

        // 2) Vínculo al equipo del inventario + datos propios del equipo
        Schema::table('actas_entrega_telefono', function (Blueprint $table) {
            $table->foreignId('id_equipo')->nullable()->after('id_linea_telefonica')
                  ->constrained('equipos')->nullOnDelete();
            $table->string('propiedad')->nullable()->after('modelo'); // Empresa | Personal
            $table->string('mac_wifi', 40)->nullable()->after('imei_equipo');
            // En un acta de "solo equipo" no hay número de teléfono
            $table->string('numero_telefono')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('actas_entrega_telefono', function (Blueprint $table) {
            $table->dropForeign(['id_equipo']);
            $table->dropColumn(['id_equipo', 'propiedad', 'mac_wifi']);
        });

        Schema::table('actas_entrega_telefono', function (Blueprint $table) {
            $table->dropForeign(['id_linea_telefonica']);
        });
        Schema::table('actas_entrega_telefono', function (Blueprint $table) {
            $table->foreignId('id_linea_telefonica')->nullable(false)->change();
            $table->foreign('id_linea_telefonica')
                  ->references('id')->on('lineas_telefonicas')
                  ->cascadeOnDelete();
        });
    }
};
