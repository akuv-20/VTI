<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linea_imei_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_linea_telefonica')
                  ->constrained('lineas_telefonicas')
                  ->cascadeOnDelete();
            // 'imei_equipo' o 'imei_sim'
            $table->string('campo', 20);
            $table->string('valor_anterior', 50)->nullable();
            $table->string('valor_nuevo',    50)->nullable();
            $table->timestamps();

            $table->index('id_linea_telefonica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linea_imei_historial');
    }
};
